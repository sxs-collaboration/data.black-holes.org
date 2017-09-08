<?php

function csv_download($header, $field_names, $array2d, $filename, $delim) {

  // First write the whole file to memory
  $f = fopen('php://memory', 'w');

  // write the header
  fwrite($f, $header);

  // write the labels
  for ($i=0; $i<count($field_names); $i=$i+1){
    $field=$field_names[$i];
    fwrite($f, "#[$i] = $field\n");
  }

  // write the data
  foreach ($array2d as $line) {
    fputcsv($f, $line, $delim);
  }

  // rewind to the start of the file
  fseek($f, 0);

  // tell the browser to download the file
  header('Content-Type: application/csv');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  fpassthru($f);
}


//Get the data
if (isset($_POST['data']) and isset($_POST['fields'])) {
  $data = json_decode($_POST['data']);
  $field_names = json_decode($_POST['fields'], true);
  $header = "# SXS public gravitational waveform database parameters\n";
  $header .= "# www.black-holes.org/waveforms\n";
  csv_download($header, $field_names, $data, "SXS_catalog_params.txt", " ");
}
else echo 'Error: Download not accessed from a valid table';

?>
