<?php
/** todo work out something more than true/false returns for dependency checks */


function i18n($value) {
  return $value;  /* Just pass the value through */
}

$skip_errors = false; // We need to hide a couple of unsightly errors even here...
function log_setup_error($errno , $errstr , $errfile , $errline) {
  global $skip_errors;
  if ( $skip_errors ) return;
  error_log('DAViCal setup.php: Informational: '.$errfile.'('.$errline.'): ['.$errno.'] '.$errstr);
}

function catch_setup_errors($errno , $errstr , $errfile , $errline , $errcontext ) {
  if ( $errno == 2 ) {
    // A working installation will regularly fail to include_once() for several files as it searches for the location
    log_setup_error($errno , $errstr , $errfile , $errline);
    return true;
  }
  if ( $errno == 8 ) {
    // Yeah, OK, so we redundantly call ob_flash() without needing to...
    log_setup_error($errno , $errstr , $errfile , $errline);
    return true;
  }
  else if ( $errno == 256 ) {
    // This will (probably) be a database connection error, which will throw an exception if we return.
    log_setup_error($errno , $errstr , $errfile , $errline);
    return true;
  }
  if ( !headers_sent() ) header("Content-type: text/plain"); else echo "<pre>\n";
  try {
    @ob_flush(); // Seems like it should be better to do the following but is problematic on PHP5.3 at least: while ( ob_get_level() > 0 ) ob_end_flush();
  }
  catch( Exception $ignored ) {}
  echo "Error [".$errno."] ".$errstr."\n";
  echo "At line ", $errline, " of ", $errfile, "\n";

  $e = new Exception();
  $trace = array_reverse($e->getTrace());
  echo "================= Stack Trace ===================\n";
  foreach( $trace AS $k => $v ) {
    printf( "%s[%d] %s%s%s()\n", $v['file'], $v['line'], (isset($v['class'])?$v['class']:''), (isset($v['type'])?$v['type']:''), (isset($v['function'])?$v['function']:'') );
  }
}

set_error_handler('catch_setup_errors', E_ALL);

class CheckResult {
  private $ok;
  private $use_class;
  private $description;

  function __construct( $success, $description=null, $use_class=null ) {
    $this->ok = (boolean) $success;
    $this->description = (isset($description)?$description : ($success===true? i18n('Passed') : i18n('Fail')));
    $this->use_class = (isset($use_class)?$use_class:($success===true?'dep_ok' : 'dep_fail'));
  }

  public function getClass() {
    return $this->use_class;
  }

  public function setClass( $new_class ) {
    $this->use_class = $new_class;
  }

  public function getOK() {
    return $this->ok;
  }

  public function getDescription() {
    return translate($this->description);
  }

  public function setDescription( $new_desc ) {
    $this->description = $new_desc;
  }

}

/**
 * We put many of these checks before we even try to load always.php so that we
 * can try and do some diagnostic work to ensure it will load OK.
 */
function check_pgsql() {
  return new CheckResult(function_exists('pg_connect'));
}

function check_pdo() {
  return new CheckResult(class_exists('PDO'));
}

function check_pdo_pgsql() {
  global $loaded_extensions;

  if ( !check_pdo() ) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['pdo_pgsql']));
}

function check_database_connection() {
  global $c;

  if ( !check_pdo_pgsql() ) return new CheckResult(false);
  return new CheckResult( !( empty($c->schema_major) || $c->schema_major == 0 || empty($c->schema_minor) || $c->schema_minor == 0) );
}

function check_gettext() {
  global $phpinfo, $loaded_extensions;

  if ( !function_exists('gettext') ) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['gettext']));
}

function check_iconv() {
  global $phpinfo, $loaded_extensions;

  if ( !function_exists('iconv') ) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['iconv']));
}

function check_ldap() {
  global $phpinfo, $loaded_extensions;

  if (!function_exists('ldap_connect')) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['ldap']));
}

function check_real_php() {
  global $phpinfo, $loaded_extensions;
  // Looking for "Server API </td><td class="v">Apache 2.0 Filter" in the phpinfo
  if ( preg_match('{Server API.*Apache 2\.. Filter}', $phpinfo) ) return new CheckResult(false);
  return new CheckResult(true);
}

function check_calendar() {
  global $phpinfo, $loaded_extensions;

  if (!function_exists('cal_days_in_month')) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['calendar']));
}

function check_suhosin_server_strip() {
  global $loaded_extensions;

  if ( !isset($loaded_extensions['suhosin']) ) return new CheckResult(true);
  return new CheckResult( ini_get('suhosin.server.strip') == "0"
       || strtolower(ini_get('suhosin.server.strip')) == "off"
       || ini_get('suhosin.server.strip') == "" );
}

function check_magic_quotes_gpc() {
  return new CheckResult( (get_magic_quotes_gpc() == 0) );
}

function check_magic_quotes_runtime() {
  return new CheckResult( (get_magic_quotes_runtime() == 0) );
}

function check_curl() {
  global $phpinfo, $loaded_extensions;

  if (!function_exists('curl_init')) return new CheckResult(false);
  return new CheckResult(isset($loaded_extensions['curl']));
}

$loaded_extensions = array_flip(get_loaded_extensions());


function do_error( $errormessage ) {
  // We can't translate this because we're testing these things even before
  // the translation interface is available...
  printf("<p class='error'><br/>%s</p>", $errormessage );
}

if ( !check_gettext()->getOK() )   do_error("The GNU 'gettext' extension for PHP is not available.");
if ( !check_pgsql()->getOK() )     do_error("PHP 'pgsql' functions are not available");
if ( !check_pdo()->getOK() )       do_error("PHP 'PDO' module is not available");
if ( !check_pdo_pgsql()->getOK() ) do_error("The PDO drivers for PostgreSQL are not available");
if ( !check_iconv()->getOK() )     do_error("The 'iconv' extension for PHP is not available");

function get_phpinfo() {
  ob_start( );
  phpinfo();
  $phpinfo = ob_get_contents( );
  ob_end_clean( );

  $phpinfo = preg_replace( '{^.*?<body>}s', '', $phpinfo);
  $phpinfo = preg_replace( '{</body>.*?$}s', '', $phpinfo);
  return $phpinfo;
}
$phpinfo = get_phpinfo();

try {
  include("./always.php");
  set_error_handler('log_setup_error', E_ALL);
  include("DAViCalSession.php");
  if ( check_pgsql()->GetOK() ) {
    $session->LoginRequired( (isset($c->restrict_setup_to_admin) && $c->restrict_setup_to_admin ? 'Admin' : null ) );
  }
}
catch( Exception $e ) {
  class setupFakeSession {
    function AllowedTo() {
      return true;
    }
  }
  $session = new setupFakeSession(1);
}


include("interactive-page.php");
include("page-header.php");

require_once("AwlQuery.php");


function check_datetime() {
  if ( class_exists('DateTime') ) return new CheckResult(true);
  $result = new CheckResult(false);
  $result->setClass('dep_warning');
  $result->setDescription(i18n('Most of DAViCal will work but upgrading to PHP 5.2 or later is strongly recommended.'));
  return $result;
}

function check_xml() {
  return new CheckResult(function_exists('xml_parser_create_ns'));
}

function check_schema_version() {
  global $c;
  if ( $c->want_dbversion[0] == $c->schema_major
    && $c->want_dbversion[1] == $c->schema_minor
    && $c->want_dbversion[2] == $c->schema_patch ) {
    return new CheckResult( true );
  }
  $result = new CheckResult(false);
  if ( $c->want_dbversion[0] < $c->schema_major
       || ($c->want_dbversion[0] == $c->schema_major && $c->want_dbversion[1] < $c->schema_minor)
       || ($c->want_dbversion[0] == $c->schema_major
              && $c->want_dbversion[1] == $c->schema_minor
              && $c->want_dbversion[2] < $c->schema_patch)
      )
    {
      $result->setClass('dep_warning');
    }
    $result->setDescription( sprintf(i18n('Want: %s, Currently: %s'), implode('.',$c->want_dbversion),
            $c->schema_major.'.'.$c->schema_minor.'.'.$c->schema_patch));
    return $result;
}

function check_davical_version() {
  global $c;
  if ( ! ini_get('allow_url_fopen') )
    return new CheckResult( false, translate("Cannot determine upstream version, because PHP has set “<a href=\"https://secure.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen\"><code>allow_url_fopen</code></a>” to “<code>FALSE</code>”."), 'dep_warning' );
  $url = 'https://www.davical.org/current_davical_version?v='.$c->version_string;
  $version_file = @fopen($url, 'r');
  if ( ! $version_file ) return new CheckResult( false, translate("Could not retrieve") . " '$url'", 'dep_warning' );
  $current_version = htmlentities( trim(fread( $version_file,12)) );
  fclose($version_file);
  $result = new CheckResult($c->version_string == $current_version);
  if ( ! $result->getOK() ) {
    if ( $c->version_string > $current_version ) {
      $result->setClass('dep_ok');
      $result->setDescription( sprintf(i18n('Stable: %s, We have: %s !'), $current_version, $c->version_string) );
    }
    else {
      $result->setDescription( sprintf(i18n('Want: %s, Currently: %s'), $current_version, $c->version_string) );
    }
  } else {
      $result->setDescription( sprintf(i18n('Passed: %s'), $c->version_string) );
  }
  return $result;
}


function check_awl_version() {
  global $c;

  if ( !function_exists('awl_version') ) return new CheckResult(false);

  $result = new CheckResult($c->want_awl_version == awl_version());
  if ( ! $result->getOK() ) {
    $result->setDescription( sprintf(i18n('Want: %s, Currently: %s'), $c->want_awl_version, awl_version()) );
    if ( $c->want_awl_version < awl_version() ) $result->setClass('dep_warning');
  } else {
    $result->setDescription( sprintf(i18n('Passed: %s'), $c->want_awl_version) );
  }
  return $result;

}


function build_site_statistics() {
  $principals  = translate('No. of Principals');
  $collections = translate('No. of Collections');
  $resources   = translate('No. of Resources');
  $table = <<<EOTABLE
<table class="statistics">
<tr><th>$principals</th><th>$collections</th><th>$resources</th></tr>
<tr>%s</tr>
</table>
EOTABLE;

  if ( !check_database_connection() ) {
    return sprintf( $table, '<td colspan="3">'.translate('Site Statistics require the database to be available!').'</td>');
  }
  $sql = 'SELECT
(SELECT count(1) FROM principal) AS principals,
(SELECT count(1) FROM collection) AS collections,
(SELECT count(1) FROM caldav_data) AS resources';
  $qry = new AwlQuery($sql);
  if ( $qry->Exec('setup',__LINE__,__FILE__) && $s = $qry->Fetch() ) {
    $row = sprintf('<td align="center">%s</td><td align="center">%s</td><td align="center">%s</td>',
                                       $s->principals, $s->collections, $s->resources );
    return sprintf( $table, $row );
  }
  return sprintf( $table, '<td colspan="3">'.translate('Site Statistics require the database to be available!').'</td>');
}


function build_dependencies_table( ) {
  global $c;

  $dependencies = array(
    translate('Current DAViCal version ')         => 'check_davical_version',
    translate('AWL Library version ')             => 'check_awl_version',
    translate('PHP not using Apache Filter mode') => 'check_real_php',
    translate('PHP PDO module available')         => 'check_pdo',
    translate('PDO PostgreSQL drivers')           => 'check_pdo_pgsql',
    translate('Database is Connected')            => 'check_database_connection',
    translate('DAViCal DB Schema version ')       => 'check_schema_version',
    translate('GNU gettext support')              => 'check_gettext',
    translate('PHP iconv support')                => 'check_iconv',
    translate('PHP DateTime class')               => 'check_datetime',
    translate('PHP XML support')                  => 'check_xml',
    translate('Suhosin "server.strip" disabled')  => 'check_suhosin_server_strip',
    translate('PHP Magic Quotes GPC off')         => 'check_magic_quotes_gpc',
    translate('PHP Magic Quotes runtime off')     => 'check_magic_quotes_runtime',
    translate('PHP calendar extension available') => 'check_calendar',
    translate('PHP curl support')                 => 'check_curl'
    );

  if ( isset($c->authenticate_hook) && isset($c->authenticate_hook['call']) && $c->authenticate_hook['call'] == 'LDAP_check') {
    $dependencies[translate('PHP LDAP module available')] = 'check_ldap';
  }

  $translated_failure_code = translate('<a href="https://wiki.davical.org/w/Setup_Failure_Codes/%s">Explanation on DAViCal Wiki</a>');

  $dependencies_table = '';
  $dep_tpl = '<tr class="%s">
  <td>%s</td>
  <td>%s</td>
  <td>'.$translated_failure_code.'</td>
</tr>
';
  foreach( $dependencies AS $k => $v ) {
    $check_result = $v();
    $dependencies_table .= sprintf( $dep_tpl, $check_result->getClass(),
                             $k,
                             $check_result->getDescription(),
                             rawurlencode($k)
                           );
  }

  return $dependencies_table;
}


$heading_setup = translate('Setup');
$paragraph_setup = translate('This page primarily checks the environment needed for DAViCal to work correctly.  Suggestions or patches to make it do more useful stuff will be gratefully received.');

/*
$want_dbversion = implode('.',$c->want_dbversion);
$heading_versions = translate('Current Versions');
if ( check_schema_version() != true )
{
  $paragraph_versions = translate('You are currently running DAViCal version %s. The database schema should be at version %s and it is at version %d.%d.%d.');
  $paragraph_versions = sprintf( $paragraph_versions, $c->version_string, $want_dbversion, $c->schema_major, $c->schema_minor, $c->schema_patch);
} else {
  $paragraph_versions = translate('You are currently running DAViCal version %s. The database schema is at version %d.%d.%d.');
  $paragraph_versions = sprintf( $paragraph_versions, $c->version_string, $c->schema_major, $c->schema_minor, $c->schema_patch);
}
*/

$heading_dependencies = translate('Dependencies');
$th_dependency = translate('Dependency');
$th_status     = translate('Status');
$dependencies_table = build_dependencies_table();

$heading_site_statistics = translate('Site Statistics');
if ( check_database_connection()->GetOK() ) {
  try {
    $site_statistics_table = build_site_statistics();
  }
  catch( Exception $e ) {
    $site_statistics_table = translate('Statistics unavailable');
  }
}
else {
  $site_statistics_table = translate('Statistics unavailable');
}

$heading_php_info = translate('PHP Information');

// Translations shared with index.php
$heading_clients = translate('Configuring Calendar Clients for DAViCal');
$content_cli1 = translate('The <a href="https://www.davical.org/clients.php">client setup page on the DAViCal website</a> has information on how to configure Evolution, Sunbird, Lightning and Mulberry to use remotely hosted calendars.');
$content_cli2 = translate('The administrative interface has no facility for viewing or modifying calendar data.');

// Translations shared with index.php
$heading_configure = translate('Configuring DAViCal');
$content_config1 = translate('If you can read this then things must be mostly working already.');
$content_config2 = ( $config_warnings == '' ? '' : '<div class="error"><h3 class="error">'
             . translate('Your configuration produced PHP errors which should be corrected') . '</h3><pre>'
             . $config_warnings.'</pre></div>'
          );
$content_config3 = translate('The <a href="https://www.davical.org/installation.php">DAViCal installation page</a> on the DAViCal website has some further information on how to install and configure this application.');


  echo <<<EOBODY
<style>
tr.dep_ok {
  background-color:#80ff80;
}
tr.dep_fail {
  background-color:#ff8080;
}
tr.dep_warning {
  background-color:#ffb040;
}
table, table.dependencies {
  border: 1px grey solid;
  border-collapse: collapse;
  padding: 0.1em;
  margin: 0 1em 1.5em;
}
table tr td, table tr th, table.dependencies tr td, table.dependencies tr th {
  border: 1px grey solid;
  padding: 0.1em 0.2em;
}
p {
  padding: 0.3em 0.2em 0.7em;
}
</style>

<h1>$heading_setup</h1>
<p>$paragraph_setup

<h2>$heading_dependencies</h2>
<p>
<table class="dependencies">
<tr>
<th>$th_dependency</th>
<th>$th_status</th>
</tr>
$dependencies_table
</table>
</p>
<h2>$heading_configure</h2>
<p>$content_config1</p>
$content_config2
<p>$content_config3</p>

<h2>$heading_clients</h2>
<p>$content_cli1</p>
<p>$content_cli2</p>

<h2>$heading_site_statistics</h2>
<p>$site_statistics_table</p>

<h2>$heading_php_info</h2>
<script language="javascript">
function toggle_visible() {
  var argv = toggle_visible.arguments;
  var argc = argv.length;

  var fld_checkbox =  document.getElementById(argv[0]);

  if ( argc < 2 ) {
    return;
  }

  for (var i = 1; i < argc; i++) {
    var block_id = argv[i].substr(1);
    var block_logical = argv[i].substr(0,1);
    var b = document.getElementById(block_id);
    if ( block_logical == '!' )
      b.style.display = (fld_checkbox.checked ? 'none' : '');
    else
      b.style.display = (!fld_checkbox.checked ? 'none' : '');
  }
}
</script><p><label>Show phpinfo() output:<input type="checkbox" value="1" id="fld_show_phpinfo" onclick="toggle_visible('fld_show_phpinfo','=phpinfo')"></label></p>
<div style="display:none" id="phpinfo">$phpinfo</div>

EOBODY;

include("page-footer.php");
