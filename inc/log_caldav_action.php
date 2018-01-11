<?php
/**
* Allows logging of CalDAV actions (PUT/DELETE) for possible export or sync
* through some other glue.
*
* @package   davical
* @category Technical
* @subpackage   logging
* @author    Andrew McMillan <andrew@morphoss.com>
* @copyright Morphoss Ltd
* @license   http://gnu.org/copyleft/gpl.html GNU GPL v2
*
* This file is intended to be used as a template, perhaps the user of this service
* will want to log actions in a very different manner and this can be used as an
* example of how to go about doing that.
*/

/**
* Log the action
* @param string $action_type INSERT / UPDATE or DELETE
* @param string $uid The UID of the modified item
* @param integer $user_no The user owning the containing collection.
* @param integer $collection_id The ID of the containing collection.
* @param string $dav_name The DAV path of the item, relative to the DAViCal base path
*/
function log_caldav_action( $action_type, $uid, $user_no, $collection_id, $dav_name ) {

  $summary = '';
  if ($uid === NULL) { $uid = basename($dav_name, '.ics'); } // in case of DELETE

  $sql = 'SELECT summary FROM calendar_item WHERE uid=?';
  $qry = new AwlQuery($sql, $uid);
  if($qry->Exec('log_caldav_action', __LINE__, __FILE__) && $qry->rows() > 0 && $row = $qry->Fetch()) { $summary = $row->summary; }

  $logline = sprintf( '%s, %s, %s, %s, %s, %s', $action_type, $uid, $user_no, $collection_id, $dav_name, $summary );

  openlog('davical', LOG_PID, LOG_LOCAL0);
  syslog(LOG_INFO, $logline);
  closelog();

}
function post_commit_action( $action_type, $uid, $user_no, $collection_id, $dav_name ) {

  $summary = '';
  if ($uid === NULL) { $uid = basename($dav_name, '.ics'); } // in case of DELETE

  $sql = 'SELECT summary FROM calendar_item WHERE uid=?';
  $qry = new AwlQuery($sql, $uid);
  if($qry->Exec('log_caldav_action', __LINE__, __FILE__) && $qry->rows() > 0 && $row = $qry->Fetch()) { $summary = $row->summary; }

  $logline = sprintf( '%s, %s, %s, %s, %s, %s', $action_type, $uid, $user_no, $collection_id, $dav_name, $summary );

  openlog('davical', LOG_PID, LOG_LOCAL0);
  syslog(LOG_INFO, $logline);
  closelog();

}
