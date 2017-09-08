<?php
# Hard-coded file/directory paths
class paths {
  static public function catalog() {
    # Must be absolute path due to use of 'realpath' in GetPathFromID
    return "/sxs-annex/SimulationAnnex.git/Catalog/";
  }
  static public function access_log() {
    return "/tmp/catalogaccess.log";
  }
}
?>
