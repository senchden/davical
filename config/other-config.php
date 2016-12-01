<?php
/**
* For the curious, there are a number of other references to $c->something
* which are (or might appear to be) configuration items.  This file contains
* some documentation for them, but it is strongly recommended that you should
* not touch any of these.  Either you will break the application or they will
* have no effect because they are simply calculated internally.
*/

/**
* Set automatically according to $_SERVER['SCRIPT_NAME']
* It will be used to set the address of each tab of the web interface,
* to set the relative address of images and so forth.  You probably should
* not change it unless you know why you want to.
*/
// $c->base_url = 'http://example.com/davical';

/**
* Automatically set according to $_SERVER['DOCUMENT_ROOT'], but could be overridden
* if that gets it wrong for some reason.
*/
// $c->base_directory = "/not/here";

/**
* Used to set the timeouts applying to the LOCK method.
*/
// $c->default_lock_timeout = 900;
// $c->maximum_lock_timeout = 8640000;

/**
* default = true;
* If set, DAViCal will store each unique time zone used in any calendar to speed
* future timezone interpretation.
*/
// $c->save_time_zone_defs = true;


/**
* Internal variable used to contain arrays of stylesheets or javascripts
* which are needed by the page being displayed.
*/
// Usually internally assigned, but you may want to set it to something meaningful
// if you are writing your own pages within the admin interface.
// $c->scripts = array();
// $c->stylesheets = array();


/**
* Internal variable to display page's title
* in the web interface
*/
// Usually internally assigned, but you may want to set it to something meaningful
// if you are writing your own pages within the admin interface.
// $c->page_title = 'DAViCal CalDAV Server';


/**
* Internal array variable to hold error messages to be displayed on top of page
* in the web interface
*/
// Usually internally assigned, but you may want to append meaningful messages
// to this array if you are writing your own pages within the admin interface.
//$c->messages[] = 'Hello World!';


/**
* This property is used to enforce regular ordering of query results so
* that the regression test output is deterministically ordered. In
* real life this is not important, and it is a performance hit, so it
* should not usually be enabled anywhere else.
*/
// $c->strict_result_ordering = false;
