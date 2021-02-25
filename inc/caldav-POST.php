<?php
/**
* CalDAV Server - handle PUT method
*
* @package   davical
* @subpackage   caldav
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd - http://www.morphoss.com/
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2 or later
*/
dbg_error_log("POST", "method handler");

require_once("XMLDocument.php");
include_once('caldav-PUT-functions.php');
include_once('freebusy-functions.php');
include_once('iSchedule.php');

if ( ! ini_get('open_basedir') && (isset($c->dbg['ALL']) || isset($c->dbg['post'])) ) {
  $fh = fopen('/var/log/davical/POST.debug','w');
  if ( $fh ) {
    fwrite($fh,$request->raw_post);
    fclose($fh);
  }
}


function handle_freebusy_request( $ic ) {
  global $c, $session, $request, $ical;

  $request->NeedPrivilege('urn:ietf:params:xml:ns:caldav:schedule-send-freebusy');
  $reply = new XMLDocument( array("DAV:" => "", "urn:ietf:params:xml:ns:caldav" => "C" ) );
  $responses = array();

  $fbq_start = $ic->GetPValue('DTSTART');
  $fbq_end   = $ic->GetPValue('DTEND');
  if ( ! ( isset($fbq_start) || isset($fbq_end) ) ) {
    $request->DoResponse( 400, 'All valid freebusy requests MUST contain a DTSTART and a DTEND' );
  }

  $range_start = new RepeatRuleDateTime($fbq_start);
  $range_end   = new RepeatRuleDateTime($fbq_end);

  $attendees = $ic->GetProperties('ATTENDEE');
  if ( preg_match( '# iCal/\d#', $_SERVER['HTTP_USER_AGENT']) ) {
    dbg_error_log( "POST", "Non-compliant iCal request.  Using X-WR-ATTENDEE property" );
    $wr_attendees = $ic->GetProperties('X-WR-ATTENDEE');
    foreach( $wr_attendees AS $k => $v ) {
      $attendees[] = $v;
    }
  }
  dbg_error_log( "POST", "Responding with free/busy for %d attendees", count($attendees) );

  if (isset($c->enable_attendee_group_resolution) && $c->enable_attendee_group_resolution) {
    $new_attendees = array();
    foreach( $attendees AS $attendee ) {
      $v = $attendee->Value();
      unset($localname);
      if ($v == "invalid:nomail") {
        $localname = $attendee->GetParameterValue("CN");
      } else if ((preg_match('/^@/', $v) == 1) || (preg_match('/mailto:@/',$v) == 1)) {
        $localname = preg_replace('/^.*@/', '', $v);
      } else if (preg_match('/@/', $v) != 1) {
        $localname = $v;
      }
      if ($localname) {
        dbg_error_log( 'POST', 'try to resolve local attendee %s', $localname);
        $qry = new AwlQuery('    SELECT fullname, email
                                 FROM usr
                                 WHERE user_no = (
                                     SELECT user_no
                                     FROM principal
                                     WHERE type_id = 1
                                       AND user_no = (
                                           SELECT user_no
                                           FROM usr
                                       WHERE lower(username) = (text(:username))
                                   )
                                 )
                             UNION
                                 SELECT fullname, email
                                 FROM usr
                                 WHERE user_no IN (
                                     SELECT user_no
                                     FROM principal
                                     WHERE principal_id IN (
                                         SELECT member_id
                                         FROM group_member
                                         WHERE group_id = (
                                             SELECT principal_id
                                             FROM principal
                                             WHERE type_id = 3
                                               AND user_no = (
                                                   SELECT user_no
                                                   FROM usr
                                                   WHERE lower(username) = (text(:username))
                                               )
                                         )
                                     )
                                 )', array(':username' => strtolower($localname)));
        if ( $qry->Exec('POST',__LINE__,__FILE__) && $qry->rows() >= 1 ) {
          dbg_error_log( 'POST', 'resolved local name %s to %d individual attendees', $localname, $qry->rows());
          while ($row = $qry->Fetch()) {
            dbg_error_log( 'POST', 'adding individual attendee %s <%s>', $row->fullname, $row->email);
            $new_attendees[] = new vProperty("ATTENDEE:mailto:" . $row->email);
          }
        }
      } else {
        $new_attendees[] = clone($attendee);
      }
    }
    $attendees = $new_attendees;
  }

  foreach( $attendees AS $k => $attendee ) {
    $attendee_email = preg_replace( '/^mailto:/', '', $attendee->Value() );
    dbg_error_log( "POST", "Calculating free/busy for %s", $attendee_email );

    /** @todo Refactor this so we only do one query here and loop through the results */
    $params = array( ':session_principal' => $session->principal_id, ':scan_depth' => $c->permission_scan_depth, ':email' => $attendee_email );
    $qry = new AwlQuery('
      SELECT
        pprivs(:session_principal::int8,principal_id,:scan_depth::int) AS p,
        username
      FROM usr
        JOIN principal USING (user_no)
        JOIN usr_emails USING (user_no)
      WHERE lower(usr_emails.email) = lower(:email)
      ', $params
    );
    if ( !$qry->Exec('POST',__LINE__,__FILE__) ) $request->DoResponse( 501, 'Database error');
    if ( $qry->rows() > 1 ) {
      // Unlikely, but if we get more than one result we'll do an exact match instead.
      if ( !$qry->QDo('
        SELECT
          pprivs(:session_principal::int8,principal_id,:scan_depth::int) AS p,
          username
        FROM usr
          JOIN principal USING (user_no)
          JOIN usr_emails USING (user_no)
        WHERE usr_emails.email = :email ', $params ) )
        $request->DoResponse( 501, 'Database error');
      if ( $qry->rows() == 0 ) {
        /** Sigh... Go back to the original case-insensitive match */
        $qry->QDo('
          SELECT
            pprivs(:session_principal::int8,principal_id,:scan_depth::int) AS p,
            username
          FROM usr
            JOIN principal USING (user_no)
            JOIN usr_emails USING (user_no)
          WHERE lower(usr_emails.email) = lower(:email)
          ', $params
        );
      }
    }

    $response = $reply->NewXMLElement("response", false, false, 'urn:ietf:params:xml:ns:caldav');
    $reply->CalDAVElement($response, "recipient", $reply->href($attendee->Value()) );

    if ( $qry->rows() == 0 ) {
      $remote = new iSchedule ();
      $answer = $remote->sendRequest ( $attendee->Value(), 'VFREEBUSY/REQUEST', $ical->Render() );
      if ( $answer === false ) {
        $reply->CalDAVElement($response, "request-status", "3.7;Invalid Calendar User" );
        $reply->CalDAVElement($response, "calendar-data" );
        $responses[] = $response;
        continue;
      }

      foreach ( $answer as $a )
      {
        if ( $a === false ) {
          $reply->CalDAVElement($response, "request-status", "3.7;Invalid Calendar User" );
          $reply->CalDAVElement($response, "calendar-data" );
        }
        elseif ( substr( $a, 0, 1 ) >= 1 ) {
          $reply->CalDAVElement($response, "request-status", $a );
          $reply->CalDAVElement($response, "calendar-data" );
        }
        else {
          $reply->CalDAVElement($response, "request-status", "2.0;Success" );
          $reply->CalDAVElement($response, "calendar-data", $a );
        }
        $responses[] = $response;
      }
      continue;
    }
    if ( ! $attendee_usr = $qry->Fetch() ) $request->DoResponse( 501, 'Database error');
    if ( (privilege_to_bits('schedule-query-freebusy') & bindec($attendee_usr->p)) == 0 ) {
      $reply->CalDAVElement($response, "request-status", "3.8;No authority" );
      $reply->CalDAVElement($response, "calendar-data" );
      $responses[] = $response;
      continue;
    }
    $attendee_path_match = '^/'.$attendee_usr->username.'/';
    $fb = get_freebusy( $attendee_path_match, $range_start, $range_end, bindec($attendee_usr->p) );

    $fb->AddProperty( 'UID',       $ic->GetPValue('UID') );
    $fb->SetProperties( $ic->GetProperties('ORGANIZER'), 'ORGANIZER');
    $fb->AddProperty( $attendee );

    $vcal = new vCalendar( array('METHOD' => 'REPLY') );
    $vcal->AddComponent( $fb );

    $response = $reply->NewXMLElement( "response", false, false, 'urn:ietf:params:xml:ns:caldav' );
    $reply->CalDAVElement($response, "recipient", $reply->href($attendee->Value()) );
    $reply->CalDAVElement($response, "request-status", "2.0;Success" );  // Cargo-cult setting
    $reply->CalDAVElement($response, "calendar-data", $vcal->Render() );
    $responses[] = $response;
  }

  $response = $reply->NewXMLElement( "schedule-response", $responses, $reply->GetXmlNsArray(), 'urn:ietf:params:xml:ns:caldav' );
  $request->XMLResponse( 200, $response );
}


function handle_cancel_request( $ic ) {
  global $c, $session, $request;

  $request->NeedPrivilege('urn:ietf:params:xml:ns:caldav:schedule-send-reply');

  $reply = new XMLDocument( array("DAV:" => "", "urn:ietf:params:xml:ns:caldav" => "C" ) );

  $response = $reply->NewXMLElement( "response", false, false, 'urn:ietf:params:xml:ns:caldav' );
  $reply->CalDAVElement($response, "request-status", "2.0;Success" );  // Cargo-cult setting
  $response = $reply->NewXMLElement( "schedule-response", $response, $reply->GetXmlNsArray() );
  $request->XMLResponse( 200, $response );
}


$ical = new vCalendar( $request->raw_post );
$method =  $ical->GetPValue('METHOD');

$resources = $ical->GetComponents('VTIMEZONE',false);
$first = $resources[0];
switch ( $method ) {
  case 'REQUEST':
    dbg_error_log('POST', 'Handling iTIP "REQUEST" method with "%s" component.', $method, $first->GetType() );
    if ( $first->GetType() == 'VFREEBUSY' )
      handle_freebusy_request( $first );
    elseif ( $first->GetType() == 'VEVENT' ) {
      $request->NeedPrivilege('urn:ietf:params:xml:ns:caldav:schedule-send-invite');
      handle_schedule_request( $ical );
    }
    else {
      dbg_error_log('POST', 'Ignoring iTIP "REQUEST" with "%s" component.', $first->GetType() );
    }
    break;
  case 'REPLY':
    dbg_error_log('POST', 'Handling iTIP "REPLY" with "%s" component.', $first->GetType() );
    $request->NeedPrivilege('urn:ietf:params:xml:ns:caldav:schedule-send-reply');
    handle_schedule_reply ( $ical );
    break;

  case 'CANCEL':
    dbg_error_log("POST", "Handling iTIP 'CANCEL'  method.", $method );
    handle_cancel_request( $first );
    break;

  default:
    dbg_error_log("POST", "Unhandled '%s' method in request.", $method );
}
