<?php
define('DOKU_INC', $_SERVER['DOCUMENT_ROOT'].'/wiki/');
require_once(DOKU_INC.'inc/init.php');
require_once(DOKU_INC.'inc/auth.php');

// Includes a file after first authenticating with DokuWiki
function IncludeAfterAuthentication($FileToInclude,$ID){
  // Check if requesting user is authenticated
  if ($_SERVER['REMOTE_USER']) {
    $INFO['perm'] = auth_quickaclcheck($ID);
  } else {
    $INFO['perm'] = auth_aclcheck($ID,'',null);
  }

  // If authenticated, show page
  if ($INFO['perm'] >= 1) {
    include $FileToInclude;
  } else {
    print "You can't view this page without authenticating first. Please log ".
    "into the <a href=\"/wiki/\">wiki</a>.<br>\n".
    "If this message persists after logging in, be sure you are connecting ".
    "to this page via https.\n";
  }
}
?>
