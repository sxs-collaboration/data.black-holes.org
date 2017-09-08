<?php
# This function sets up a PDO for an sqlite database.
# It is assumed that the db file is named after the table.
function sqliteDB($table) {
  $db = realpath(dirname(__FILE__)."/../../$table.db");
  $dbh = new PDO("sqlite:$db");
  if (!$dbh) {
    die("Could not open database file with PDO: $db");
  }

  # sqlite declares but does not define the REGEXP mysql function.
  # Here we define it as php's preg_match and bind it to the PDO.
  function _sqliteRegexp($pattern, $string) {
    return preg_match("/$pattern/i", $string);
  }
  $dbh->sqliteCreateFunction('regexp', '_sqliteRegexp', 2);
  return $dbh;
}
?>
