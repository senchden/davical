<?php

include("./always.php");
include("DAViCalSession.php");
$session->LoginRequired('Admin');

include("interactive-page.php");

 $heading_pagelem1 = translate('Upgrade Database');
 $content_pagelem11 = translate('Currently this page does nothing. Suggestions or patches to make it do something useful will be gratefully received.');

 $heading_pagelem2 = translate('Upgrading DAViCal Versions');
 $content_pagelem21 = translate('The <a href="http://wiki.davical.org/w/Update-davical-database">update-davical-database</a> should be run manually after upgrading the software to a new version of DAViCal.');
 $content_pagelem22 = translate("In due course this program will implement the functionality which is currently contained in that script, but until then I'm afraid you do need to run it.");

 $page_elements = array();
 $page_elements[] = <<<EOBODY
<h1>$heading_pagelem1</h1>
<p>$content_pagelem11
<br>&nbsp;
</p>
<h2>$heading_pagelem2</h2>
<p>$content_pagelem21</p>

<p>$content_pagelem22</p>
EOBODY;



include("classEditor.php");
include("AwlUpgrader.php");

$editor = new Editor(translate('Upgrade Database'));
$editor->AddField('dbhost', "''");
$editor->AddField('dbport', "''");
$editor->AddField('dbname', "'davical'");
$editor->AddField('dbuser', "'davical_dba'");
$editor->AddField('dbpass', "''");
$editor->AddField('app_user', "'davical_app'");
$editor->AddField('apply_patches', "'t'");
$editor->AddField('owner', "davical_dba");

$prompt_dbname = translate('Database Name');
$prompt_dbuser = translate('Database Username');
$prompt_dbpass = translate('Database Password');
$prompt_dbport = translate('Database Port');
$prompt_dbhost = translate('Database Host');

$prompt_app_user = translate('Application DB User');
$prompt_do_patch = translate('Apply DB Patches');
$prompt_owner = translate('Database Owner');

$content_template1 = translate('Connection Parameters');
$content_template2 = translate('Operation Parameters');

$template = <<<EOTEMPLATE
##form##
<table>
 <tr> <th class="h2" colspan="2">$content_template1</th> </tr>
 <tr> <th class="right">$prompt_dbhost:</th>           <td class="left">##dbhost.input.20##</td> </tr>
 <tr> <th class="right">$prompt_dbport:</th>      <td class="left">##dbport.input.5##</td> </tr>
 <tr> <th class="right">$prompt_dbname:</th>    <td class="left">##dbname.input.20##</td> </tr>
 <tr> <th class="right">$prompt_dbuser:</th>         <td class="left">##dbuser.input.20##</td> </tr>
 <tr> <th class="right">$prompt_dbpass:</th>        <td class="left">##dbpass.password.20##</td> </tr>
 <tr> <th class="h2" colspan="2">$content_template2</th> </tr>
 <tr> <th class="right">$prompt_app_user:</th>         <td class="left">##app_user.input.20##</td> </tr>
 <tr> <th class="right">$prompt_do_patch:</th>      <td class="left">##apply_patches.checkbox##</td> </tr>
 <tr> <th class="right">$prompt_owner:</th>         <td class="left">##owner.input.20##</td> </tr>
 <tr> <th class="right"></th>                   <td class="left" colspan="2">##submit##</td> </tr>
</table>
</form>

EOTEMPLATE;


$editor->SetTemplate( $template );
$page_elements[] = $editor;

$c->stylesheets[] = 'css/edit.css';

include("page-header.php");

/**
* Page elements could be an array of viewers, browsers or something else
* that supports the Render() method... or a non-object which we assume is
* just a string of text that we echo.
*/
$heading_level = null;
foreach( $page_elements AS $k => $page_element ) {
  if ( is_object($page_element) ) {
    echo $page_element->Render($heading_level);
    $heading_level = 'h2';
  }
  else {
    echo $page_element;
  }
}

if (function_exists("post_render_function")) {
  post_render_function();
}

include("page-footer.php");
