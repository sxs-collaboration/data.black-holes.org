<?php
// Display the metadata file from the Waveform database for this ID
if (!array_key_exists('id', $_GET)) {
  die("Must specify an 'id' URL parameter.");
}
$path = GetFieldValueFromID($config['table'], $_GET['id'], "Metapath");
header('Content-type: text/plain');
header('Content-Disposition: filename="metadata.txt"');
echo file_get_contents($path);
?>
