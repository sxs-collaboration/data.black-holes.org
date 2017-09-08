<?php
# Name of table associated with each database
class tables {
  static public function catalog() {
    return "Waveform";
  }
  static public function completed() {
    return "Completed";
  }
  static public function ongoing() {
    return "Ongoing";
  }
}

#-----------------------------------------------------------------------------
# Convenience function for the Waveform database
function GetPathFromID($id) {
  $MetadataPath = GetFieldValueFromID(tables::catalog(),$id,"Metapath");
  return realpath(dirname($MetadataPath)."/..");
}

#-----------------------------------------------------------------------------
function GetFieldValueFromID($table,$id,$field,$dbh=False) {
  # Get the database handle if it was not passed
  if (!$dbh) {
    include "sqlite.php";
    $dbh = sqliteDB($table);
  }

  # Create a prepared statement
  $stmt = $dbh -> prepare("SELECT * FROM `$table` WHERE id = ?");
  if (!$stmt) return False;
  $stmt -> execute(array($id)); // Execute it
  $fetch = $stmt -> fetch(); // Fetch the value
  if (empty($fetch)) die ("Invalid id in '$table' database: $id");
  return $fetch[$field];
}
?>
