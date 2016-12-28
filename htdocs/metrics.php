<?php
/**
 * DAViCal - metrics page for Prometheus
 *
 * @package   davical
 * @subpackage   metrics
 * @author    Andrew McMillan <andrew@mcmillan.net.nz>
 * @copyright Andrew McMillan <andrew@mcmillan.net.nz>
 * @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
 */

header("Content-type: text/plain; version=0.4.0");

require_once('./always.php');

// If necessary, validate they are coming from an allowed address
if ( isset($c->metrics_collectors) && !in_array($_SERVER['REMOTE_ADDR'], $c->metrics_collectors) ) {
  echo "Nope.";
  exit(0);
}

// If necessary, validate that they are coming in as an authorized user
if ( isset($c->metrics_require_user) ) {
  require_once('HTTPAuthSession.php');
  $session = new HTTPAuthSession();
  if ( $session->username != $c->metrics_require_user ) {
    $session->AuthFailedResponse();
    echo "Nope.";
    exit(0);
  }
}

// Validate that the metrics are actually turned on!
if ( !isset($c->metrics_style) || $c->metrics_style === false ) {
  echo "Metrics are not enabled.";
  exit(0);
}

// Helper function to ensure we get the metric format consistent
function print_metric( $name, $qualifiers, $value ) {
  print $name;
  if ( !empty($qualifiers) ) {
    print '{';
    $continuation = '';
    foreach( $qualifiers AS $k => $v ) {
      if ( $continuation == '' ) {
        $continuation = ',';
      } else {
        print $continuation;
      }
      printf( '%s="%s"', $k, $v);
    }
    print '}';
  }
  echo ' ', $value, "\n";
}


// If they want 'both' or 'all' or something then that's what they will get
// If they don't want counters, they must want to use memcache!
if ( $c->metrics_style != 'counters' ) {
  // These are the preferred metrics, which include some internal details
  // of the request processing.
  include_once('AwlCache.php');
  $cache = getCacheInstance();

  $index = unserialize($cache->get('metrics', 'index'));
  print "# HELP caldav_request_status The DAViCal requests broken down by HTTP method and response status\n";
  print "# TYPE caldav_request_status counter\n";
  foreach( $index['methods'] AS $method => $ignored ) {
    foreach( $index['statuses'] AS $status => $ignored ) {
      $count = $cache->get('metrics', $method.':'.$status );
      if ( $count !== false ) {
        print_metric("caldav_request_status", array('method'=>$method, 'status'=>$status), $count);
      }
    }
  }

  print "\n";
  print "# HELP caldav_response_bytes The DAViCal response size by HTTP method\n";
  print "# TYPE caldav_response_bytes counter\n";
  foreach( $index['methods'] AS $method => $ignored ) {
    $count = $cache->get('metrics', $method.':size');
    print_metric("caldav_request_bytes", array('method'=>$method), $count);
  }

  $timings = array('script', 'query', 'flush');
  print "\n";
  print "# HELP caldav_request_microseconds The DAViCal response time taken in general, in queries, and in flushing buffers\n";
  print "# TYPE caldav_request_microseconds counter\n";
  foreach( $index['methods'] AS $method => $ignored ) {
    foreach( $timings AS $timing ) {
      $count = $cache->get('metrics', $method.':'.$timing.'_time');
      print_metric("caldav_request_microseconds", array('method'=>$method, 'timing'=>$timing), $count);
    }
  }
}

// If they don't want memcache, they must want to use counters!
if ( $c->metrics_style != 'memcache' ) {
  // These are more basic metrics.  Just counts of requests, by type.
  $sql = <<<QUERY
SELECT
  (SELECT last_value FROM metrics_count_get) AS get_count,
  (SELECT last_value FROM metrics_count_put) AS put_count,
  (SELECT last_value FROM metrics_count_propfind) AS propfind_count,
  (SELECT last_value FROM metrics_count_proppatch) AS proppatch_count,
  (SELECT last_value FROM metrics_count_report) AS report_count,
  (SELECT last_value FROM metrics_count_head) AS head_count,
  (SELECT last_value FROM metrics_count_options) AS options_count,
  (SELECT last_value FROM metrics_count_post) AS post_count,
  (SELECT last_value FROM metrics_count_mkcalendar) AS mkcalendar_count,
  (SELECT last_value FROM metrics_count_mkcol) AS mkcol_count,
  (SELECT last_value FROM metrics_count_delete) AS delete_count,
  (SELECT last_value FROM metrics_count_move) AS move_count,
  (SELECT last_value FROM metrics_count_acl) AS acl_count,
  (SELECT last_value FROM metrics_count_lock) AS lock_count,
  (SELECT last_value FROM metrics_count_unlock) AS unlock_count,
  (SELECT last_value FROM metrics_count_mkticket) AS mkticket_count,
  (SELECT last_value FROM metrics_count_delticket) AS delticket_count,
  (SELECT last_value FROM metrics_count_bind) AS bind_count,
  (SELECT last_value FROM metrics_count_unknown) AS unknown_count
QUERY;

  $qry = new AwlQuery($sql);
  $result = $qry->Exec("metrics", __LINE__ , __FILE__);
  $row = (array) $qry->Fetch();
  print "\n";
  print "# HELP caldav_request_count The DAViCal requests broken down by HTTP method (get, put, propfind, etc.).\n";
  print "# TYPE caldav_request_count counter\n";
  foreach ($row as $k => $v) {
    print_metric("caldav_request_count", array( "method" => str_replace("_count", "", $k)), $v);
  }
}

print "\n";
print "# HELP davical_up Are the servers up.\n";
print "# TYPE davical_up gauge\n";
print_metric("davical_up", array('server'=>$c->sysabbr), 1);

if ( function_exists('memory_get_usage') ) {
  print "\n";
  print "# HELP davical_process_memory How much memory is this process using.\n";
  print "# TYPE davical_process_memory gauge\n";
  print_metric("davical_process_memory", array("pid" => getmypid(), 'type' => 'curr'), memory_get_usage());
  print_metric("davical_process_memory", array("pid" => getmypid(), 'type' => 'peak'), memory_get_peak_usage());
}
