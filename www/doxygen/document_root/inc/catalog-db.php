<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
   "http://www.w3.org/TR/html4/loose.dtd">

<html>
<head>
<meta http-equiv='Content-type' content='text/html;charset=UTF-8'>
<link rel='stylesheet' type='text/css' href='styles/reset.css'>
<link rel='stylesheet' type='text/css' href='styles/cuscosky.css'>
<title>Finished Runs</title>
</head>

<h1>SXS Gravitational Waveform Database</h1>
  <h2>Completed Simulations</h2>
<hr>

<body>

<?php
include "email.php";
$sub_email   = email::subscribe();
$unsub_email = email::unsubscribe();
$q_email     = email::questions();
print "
<div align='left' style='font-size:18px'><a href='index.html'>Important Information</a></div><br>
<div align='left' style='font-size:18px'><a href='news.html'>Latest News and Updates</a></div><br>
<div align='left' style='font-size:18px'><a href='docs.html'>Help and Documentation</a></div><br>
<h4>
Click to <a href='mailto:$sub_email'>subscribe</a>
(or <a href='mailto:$unsub_email'>unsubscribe</a>) to the
waveform announcement mailing list.<br>
For general questions about the Catalog, please send an email to
<a href='mailto:$q_email'>$q_email</a>.<br>
For questions about a specific simulation, please click the email
icon in the table below.
</h4>
<hr>
";

include "inc/DatabaseAccess.php";
$config['table'] = tables::catalog();
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
$config['defaultoptionalfields'] = array('Metapath', 'MassRatio',
    'Chi1', 'Chi2', 'Chi1X', 'Chi1Y', 'Chi1Z', 'Chi2X', 'Chi2Y', 'Chi2Z',
    'Eccentricity', 'Freq', 'Orbits', 'Email'); // Initially displayed.  Can be 'all'.

// Get database handle
include "inc/sqlite.php";
$dbh = sqliteDB($config['table']);

include "inc/DisplayPage.php";
?>
</body>
</html>
