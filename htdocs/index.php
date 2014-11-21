<?php
if ( $_SERVER['REQUEST_METHOD'] != "GET" && $_SERVER['REQUEST_METHOD'] != "POST" && $_SERVER['REQUEST_METHOD'] != "HEAD" ) {
  /**
  * If the request is not a GET or POST then they must really want caldav.php!
  */
  include("./caldav.php");
  @ob_flush(); exit(0);  // Not that it should return from that!
}

include("./always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");
include("page-header.php");

$heading_admin = translate('Administration');
$content_admin = sprintf(translate('You are logged on as %s (%s)'), $session->username, $session->fullname);

$heading_functions = translate('Administration Functions');
$content_func1 = translate('The administration of this application should be fairly simple. You can administer:');
$content_func2 = translate('Users (or Resources or Groups) and the relationships between them');
$content_func3 = translate('The types of relationships that are available');
$content_func4 = translate('There is no ability to view and / or maintain calendars or events from within this administrative interface.');
$content_func5 = translate('To do that you will need to use a CalDAV capable calendaring application such as Evolution, Sunbird, Thunderbird (with the Lightning extension) or Mulberry.');

$heading_principals = translate('Principals: Users, Resources and Groups');
$content_princ1 = translate('These are the things which may have collections of calendar resources (i.e. calendars).');
$content_princ2 = sprintf('<a href="%s/admin.php?action=browse&t=principal&type=1">%s</a>. %s',
$c->base_url,
translate('Here is a list of users (maybe :-)'),
translate("You can click on any user to see the full detail for that person (or group or resource - but from now we'll just call them users).")
);
$content_princ3 = translate('The primary differences between them are as follows:');
$content_princ4 = translate('Users will probably have calendars, and are likely to also log on to the system.');
$content_princ5 = translate('Resources do have calendars, but they will not usually log on.');
$content_princ6 = translate('Groups provide an intermediate linking to minimise administration overhead.  They might not have calendars, and they will not usually log on.');
$content_princ7 = translate('Users will probably have calendars, and are likely to also log on to the system.');

$heading_groups = translate('Groups &amp; Grants');
$content_grp1 = translate('Grants specify the access rights to a collection or a principal');
$content_grp2 = translate('Groups allow those granted rights to be assigned to a set of many principals in one action');
$content_grp3 = translate('Groups may be members of other groups, but complex nesting will hurt system performance');

// Translations shared with setup.php
$heading_clients = translate('Configuring Calendar Clients for DAViCal');
$content_cli1 = translate('The <a href="http://www.davical.org/clients.php">client setup page on the DAViCal website</a> has information on how to configure Evolution, Sunbird, Lightning and Mulberry to use remotely hosted calendars.');
$content_cli2 = translate('The administrative interface has no facility for viewing or modifying calendar data.');

// Translations shared with setup.php
$heading_configure = translate('Configuring DAViCal');
$content_config1 = translate('If you can read this then things must be mostly working already.');
$content_config3 = translate('The <a href="http://www.davical.org/installation.php">DAViCal installation page</a> on the DAViCal website has some further information on how to install and configure this application.');


  echo <<<EOBODY
<h1>$heading_admin</h1>
<p>$content_admin</p>

<h2>$heading_functions</h2>
<p>$content_func1</p>
<ul>
<li>$content_func2</li>
<li>$content_func3</li>
</ul>
<p><i>$content_func4</i></p>
<p>$content_func5</p>

<h3>$heading_principals</h3>
<p>$content_princ1</p>
<p>$content_princ2</p>
<p>$content_princ3</p>
<ul>
<li>$content_princ4</li>
<li>$content_princ5</li>
<li>$content_princ6</li>
</ul>

<h3>$heading_groups</h3>
<ul>
<li>$content_grp1</li>
<li>$content_grp2</li>
<li>$content_grp3</li>
</ul>

<h2>$heading_clients</h2>
<p>$content_cli1</p>
<p>$content_cli2</p>

<h2>$heading_configure</h2>
<p>$content_config1</p>
<p>$content_config3</p>

EOBODY;

include("page-footer.php");
