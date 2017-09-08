<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">

<html>
<head>
<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
<link rel="stylesheet" type="text/css" href="styles/reset.css" >
<link rel="stylesheet" type="text/css" href="styles/cuscosky.css" >
<title>Completed Runs</title>
</head>

<h1>SXS Gravitational Waveform Database</h1>
  <h2>Completed Simulations</h2>
<hr>

<?php
include "inc/DatabaseAccess.php";
$config['table'] = tables::completed();
$config['nicefields'] = true; //true or false | "Field Name" or "field_name"
$config['perpage'] = 25; //Number of Entries per page
$config['showpagenumbers'] = true; //true or false
$config['showprevnext'] = false; //true or false
$config['delete'] = false; //Adds a delete button to delete entries
$config['showlastupdated'] = true; //Adds a "Last Updated" timestamp
$config['downloadbutton'] = true; // Add a button to download table
$config['usecolumnadder'] = true; //Allow adding/removing columns. 
//The following two fields are only used if $config['usecolumnadder'] is true:
$config['requiredfields'] = array('id'); // These are always displayed
$config['defaultoptionalfields'] = array('Metapath', 'Type', 'MassRatio',
    'Chi1', 'Chi2', 'Chi1X', 'Chi1Y', 'Chi1Z', 'Chi2X', 'Chi2Y', 'Chi2Z',
    'Eccentricity', 'Freq', 'Orbits', 'Email'); // Initially displayed.  Can be 'all'.

// Get database handle
include "inc/sqlite.php";
$dbh = sqliteDB($config['table']);

include "inc/DisplayPage.php";
?>
</html>
