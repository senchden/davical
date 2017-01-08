<?php
include("./always.php");
include("DAViCalSession.php");
$session->LoginRequired();

include("interactive-page.php");

$c->page_title = translate('DAViCal CalDAV Server - Configuration Help');
include("page-header.php");

$wiki_help = '';
if ( isset($_SERVER['HTTP_REFERER']) ) {
  $wiki_help = preg_replace('#^.*/#', '', $_SERVER['HTTP_REFERER']);
  $wiki_help = preg_replace('#\.php.*$#', '', $wiki_help);
  $wiki_help = 'w/Help/'.$wiki_help;
}

$content = sprintf(translate('<h1>Help</h1>
<p>For initial help you should visit the <a href="https://www.davical.org/" target="_blank">DAViCal Home Page</a> or take
a look at the <a href="https://wiki.davical.org/%s" target="_blank">DAViCal Wiki</a>.</p>
<p>If you can\'t find the answers there, visit us on <a href="https://wikipedia.org/wiki/Internet_Relay_Chat" target="_blank">IRC</a> in
the <b>#davical</b> channel on <a href="https://www.oftc.net/" target="_blank">irc.oftc.net</a>,
or send a question to the <a href="https://lists.sourceforge.net/mailman/listinfo/davical-general" target="_blank">DAViCal Users mailing list</a>.</p>
<p>The <a href="https://sourceforge.net/p/davical/mailman/davical-general/" title="DAViCal Users Mailing List" target="_blank">mailing list
archives can be helpful too.</p>'), $wiki_help);

echo $content;

include("page-footer.php");

