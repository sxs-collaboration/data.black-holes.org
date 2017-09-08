<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">

<html>
<head>
<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
</head>


<!-- Gets the viewer's timezone and saves it to a cookie -->
<script src="js/jstz.min.js" type="text/javascript"> </script>
<script>
if (!(document.cookie.indexOf("Timezone") >= 0)) {
  document.cookie = "Timezone=" + jstz.determine().name();
  location.reload();
}
</script>

<link rel="stylesheet" type="text/css" href="styles/reset.css" >
<link rel="stylesheet" type="text/css" href="styles/cuscosky.css" >
<title>Current Runs</title>
<h1>SXS Gravitational Waveform Database</h1>
  <h2>Ongoing Simulations</h2>
<hr>

<?php
include "inc/DatabaseAccess.php";
$config['table'] = tables::ongoing();
$config['nicefields'] = true; //true or false | "Field Name" or "field_name"
$config['perpage'] = 25; //Number of Entries per page
$config['showpagenumbers'] = true; //true or false
$config['showprevnext'] = false; //true or false
$config['delete'] = true; //Adds a delete button to delete entries
$config['downloadbutton'] = false; // Add a button to download table
$config['usecolumnadder'] = false; //Allow adding/removing columns. 
//The following two fields are only used if $config['usecolumnadder'] is true:
//$config['requiredfields'] = array('id'); // These are always displayed
//$config['defaultoptionalfields'] = 'all';

// Get database handle
include "inc/sqlite.php";
$dbh = sqliteDB($config['table']);

include "inc/DisplayPage.php";
?>
</html>
