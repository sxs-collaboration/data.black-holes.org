<?php

# sanitize file request by preventing the use of relative paths
$file = str_replace('..', '', $_REQUEST['spec']);
$root = '/var/www/html/doxygen/data';

if (empty($file)) {
  include "$root/index.xhtml";
  exit;
}

if (!file_exists("$root/$file")) {
  print "Sorry, '$file' was not found in the documentation.";
  exit;
}

if(substr_compare($file, '.php', -4)===0) {
  include "$root/$file";
} else {
  // Send proper MIME types for Doxygen resources
  if(substr_compare($file, '.css', -4)===0) {
    header('Content-Type: text/css');
  } elseif(substr_compare($file, '.js', -3)===0) {
    header('Content-Type: text/javascript');
  } elseif(substr_compare($file, '.png', -4)===0) {
    header('Content-Type: image/png');
  }
  readfile("$root/$file");
}

?>
