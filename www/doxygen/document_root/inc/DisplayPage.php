<?php
include "inc/PagesMaker.php";


// Set up the variables needed for the functions
list($all_fields,$display_fields,$orderby,$sort,
     $startrow,$limit,$Pagination,$page) =
  SetUp($config,$dbh);

// Get the data from the database
list($data,$prev_link,$next_link,$pagination_links,$Stats) =
  QueryDatabase(
    $config,$dbh,$all_fields,$display_fields,$orderby,$sort,
    $startrow,$limit,$Pagination,$page);

// Add Filters and FilterBox
CreateFilters($all_fields,$sort,$orderby,$limit,$config);

// Add Column adder
if ($config['usecolumnadder']) {
  CreateColumnAdder($all_fields,$display_fields,$config);
}

// Add a button to download the visible table, but all rows, as a csv file
if ($config['downloadbutton']) {
  CreateDownloadButton($display_fields,$data,$config);
}

// Adds a last updated timestamp
CreateLastUpdated($dbh, $config);

// Adds Number of Pages Displayed Box
CreatePageBox($limit,$page);

// Create Table Header and Entries
CreateTableHeader($config,$display_fields,$orderby,$sort);
CreateTableEntries($config,$startrow,$limit,$data,$Stats);

// Add Page Links to Bottom of the Page
CreatePageLinks($prev_link,$next_link,$pagination_links);



//-------------------------------------------------------------------------

//                             MAIN ROUTINES

//-------------------------------------------------------------------------

// -------------------------------------------------------------
// Database-independent method to get the table column names
function GetTableFields($dbh,$table,$nice_names) {
  $result = $dbh->query("SELECT * FROM $table LIMIT 1");
  $table_fields = array_keys($result->fetch(PDO::FETCH_ASSOC));
  if($nice_names) {
    $table_fields = array_map('ucwords',$table_fields);
  }
  return $table_fields;
}

// -------------------------------------------------------------
// Sets up all the required parameters for all of the functions,
function SetUp($config,$dbh){

  // Delete Entry if Requested
  if($config['delete'] && isset($_POST['deleteid'])){
    $stmt = $dbh -> prepare("DELETE FROM $config[table] WHERE id = ?");
    $result = $stmt -> execute(array($_POST['deleteid']));
    if (!$result) {
      $err_msg = $stmt -> errorInfo()[2];
      print_r("WARNING: failed to delete '$_POST[deleteid]': $err_msg");
    }
  }

  // Set the timezone appropriate the to user
  if(isset($_COOKIE['Timezone']) 
    && in_array($_COOKIE['Timezone'], DateTimeZone::listIdentifiers())) {
    date_default_timezone_set($_COOKIE['Timezone']);
  }

  // Get all allowed fields for displaying/filtering
  $all_fields = GetTableFields($dbh,$config['table'],$config['nicefields']);

  // Get the displayed fields
  if ($config['usecolumnadder']) {
    if (isset($_POST['extrafield'])) {
      $extrafield = $_POST['extrafield'];
      if (in_array($extrafield, $all_fields)) {
        if (isset($_GET['rmfields']) and
            in_array($extrafield, $_GET['rmfields'])) {
          $_GET['rmfields'] = array_diff($_GET['rmfields'], array($extrafield));
        } else {
          if (!isset($_GET['addfields'])) {
            $_GET['addfields'] = array();
          }
          array_push($_GET['addfields'], $extrafield);
        }
      }
    }
    $display_fields = $config['requiredfields'];
    if (isset($_GET['addfields'])) {
      $additional_fields = $_GET['addfields'];
    } else {
      $additional_fields = array();
    }
    if (isset($_GET['rmfields'])) {
      $removed_fields = $_GET['rmfields'];
    } else {
      $removed_fields = array();
    }
    if ($config['defaultoptionalfields'] == 'all') {
      $config['defaultoptionalfields'] = $all_fields;
    }
    foreach (array_merge($config['defaultoptionalfields'],
                         $additional_fields) as $field)
    {
      if (in_array($field, $all_fields) and
          !in_array($field, $removed_fields) and
          !in_array($field, $display_fields))
        array_push($display_fields, $field);
    }
  } else {
    $display_fields = $all_fields;
  }

  // Set limit per page, what is current page, define first record for page
  $Pagination = new Pagination();
  $limit = $config['perpage'];
  if(isset($_POST['disp']) && (is_numeric(trim($_POST['disp'])))) {
    $_GET['disp']=(int)$_POST['disp'];
    $limit = (int)$_POST['disp'];
  } elseif(isset($_GET['disp']) && is_numeric(trim($_GET['disp']))) {
    $limit = (int)$_GET['disp'];
  }

  if(isset($_POST['page']) && (is_numeric(trim($_POST['page'])))) {
    $page = (int)($_POST['page']);
  } elseif(isset($_GET['page']) && is_numeric(trim($_GET['page']))) {
    $page = (int)($_GET['page']);
  } else {
    $page = 1;
  }
  $startrow = $Pagination->getStartRow($page,$limit);

  // Set default sort direction
  if(isset($_GET['sort']) && $_GET['sort'] == "DESC") {
    $sort = "DESC";
  } else {
    $sort = 'ASC';
  }

  // Get and/or set which field to order by
  if(!isset($_GET['orderby']) OR trim($_GET['orderby']) == ""
    OR !in_array($_GET['orderby'],$all_fields))
  {
    if (in_array("LastUpdated",$all_fields)) {
      $sort = "DESC";
      $orderby = "LastUpdated";
    } else {
      $orderby = $all_fields[0];
    }
  } elseif(in_array($_GET['orderby'],$all_fields)) {
    $orderby = $_GET['orderby'];
  }

  return array($all_fields,$display_fields,$orderby,$sort,
    $startrow,$limit,$Pagination,$page);
}

// --------------------------------------------------------------------
// Uses all of the parameters to get the actual data from the database,
// in the desired format.  Only returns data for displayed fields.
function QueryDatabase($config,$dbh,$all_fields,$display_fields,
  $orderby,$sort,$startrow,$limit,$Pagination,$page){

  // Define the start of the query string, selecting all fields
  $DatabaseQuery = "SELECT ";
  foreach ($display_fields as $field) {
    $DatabaseQuery .= $field.", ";
  }
  $DatabaseQuery = substr($DatabaseQuery, 0, strlen($DatabaseQuery)-2);
  $DatabaseQuery .= " FROM `".$config['table']."` WHERE 1 = 1 ";

  $params = array();
  // Check and set min/max values for filters
  for($i=0;$i<=200;$i++) {
    if (!isset($_GET["Filter$i"])) break;
    $filter = $_GET["Filter$i"];
    if(in_array($filter,$all_fields) && (strcmp($filter,"Select")!=0)) {
      if(isset($_GET["min$i"]) && $_GET["min$i"]!=""){
        $DatabaseQuery .= " AND $filter >= ? ";
        $params[] = $_GET["min$i"];
      }
      if(isset($_GET["max$i"]) && $_GET["max$i"]!=""){
        $DatabaseQuery .= " AND $filter <= ? ";
        $params[] = $_GET["max$i"];
      }
      if(isset($_GET["regex$i"]) && $_GET["regex$i"]!=""){
        $DatabaseQuery .= " AND $filter REGEXP ? ";
        // escape forward slashes before submitting the regexp
        $regex = str_replace("/", "\/", $_GET["regex$i"]);
        $params[] = $regex;
      }
    }
  }

  // Add the required sorting to the string
  $DatabaseQuery .= "ORDER BY $orderby $sort";

  // Query the database
  $stmt = $dbh->prepare($DatabaseQuery);
  $stmt->execute($params);
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $Stats = array();
  foreach ($data as $row){
    foreach ($row as $field=>$value) {
      if(is_numeric($value) && $value!="") {
        $Stats[$field][] = $value;
      }
    }
  }
  $Stats = array_map('sd',$Stats);

  // Get total rows
  $totalrows = count($data);

  // Create page links
  if($config['showpagenumbers'] == true) {
    $pagination_links = $Pagination->showPageNumbers($totalrows,$page,$limit);
  } else {
    $pagination_links=null;
  }

  if($config['showprevnext'] == true){
    $prev_link = $Pagination->showPrev($totalrows,$page,$limit);
    $next_link = $Pagination->showNext($totalrows,$page,$limit);
  } else {
    $prev_link=null;
    $next_link=null;
  }

  // Return all of the values needed for the other functions
  return array($data,$prev_link,$next_link,$pagination_links,$Stats);
}

// -----------------------------------------------------------------
// Creates the Filters section used to filter the data
function CreateFilters($all_fields,$sort,$orderby,$limit,$config){

  $Filters=array();
  for($i=0;$i<=50;$i++) {
    if(isset($_GET["Filter$i"]) && in_array($_GET["Filter$i"],$all_fields) &&
      (strcmp($_GET["Filter$i"],"Select Filter")!=0) && 
      (strcmp($_GET["Filter$i"],"Clear Filter")!=0)) {
        $Filters[$i]=$_GET["Filter$i"];
    }
  }

  // Set up the headers of the filter box
  echo "<form method='GET' action=''>
    <input type=hidden name=sort value=$sort>
    <input type=hidden name=orderby value=$orderby>
    <input type=hidden name=disp value=$limit>";
  if (isset($_GET['addfields'])) {
    foreach ($_GET['addfields'] as $field) {
      echo "<input type=hidden name=addfields[] value=$field>";
    }
  }
  if (isset($_GET['rmfields'])) {
    foreach ($_GET['rmfields'] as $field) {
      echo "<input type=hidden name=rmfields[] value=$field>";
    }
  }
  echo "<table class='reset'> <tr>
    <th class='reset'>Filters</th>
    <th class='reset'>Filter Value</th>
    <th class='reset'>Delete</th>";

  array_push($Filters,"Select Filter");
  AddFilterBoxes($Filters,$all_fields,$config);

  echo "</table>\n";

  // Add a filter button that calls the filters and a reset button
  echo "<table class='invis'><tr><td class='invis'>
    <INPUT TYPE='Submit' VALUE='Filter'>
    </td>
    <td class='invis'>
    <input type='reset' onClick='window.location.replace(\"?\");'>
    </td></tr></table>
    </form>
    <br>";  
}

//--------------------------------------------------------------------------
// Takes a list of filters in a numerical array and creates table entries
function AddFilterBoxes($FilterList,$table_fields,$config){
  // Note that these array elements are the *displayed* field names
  $StringFilters = array('Id','Metapath','Email','Status','User',
    'Machine','TrackingStarted','LastUpdated','Type','Ev_commit',
    'ID_commit','Keywords');

  foreach($FilterList as $Index=>$Filter) {
    // Set up the scrolldown button
    $filterstr="<tr> <td> <select name = Filter$Index";
    $filterstr .= " onchange='this.form.submit();'> <option>"; 
    $filterstr .= in_array($Filter,$table_fields) 
      ? "Clear Filter" : "Select Filter";
    $filterstr .= "</option>";
    echo $filterstr;

    // Select the option currently used, if set
    foreach($table_fields as $listname) {
      $text = $listname;
      $text = DisplayedFieldName($listname,$config);
      if ($Filter==$listname) {
        echo "<option value=$listname selected> $text </option>";
      } else {
        echo "<option value=$listname> $text </option>";
      }
    }
    echo "</select></td>";

    // Create input boxes for min and max values, fill with current value
    if(!stristr($Filter,"Filter")) {
      echo "<td style='padding:0px 0px 0px 0px'><table class='divider'><tr>";
      if(in_array($Filter,$StringFilters)) {
        echo GetBoundValue("regex",$Filter,$Index);
      } else {
        echo GetBoundValue("min",$Filter,$Index);
        echo GetBoundValue("max",$Filter,$Index);
      }
      $queries = http_build_query(array("Filter$Index"=>"Clear Filter")+$_GET);
      echo "</tr></table><td class='reset'>
        <a href='?".htmlentities($queries, ENT_QUOTES, 'UTF-8',false)."'>
        <img src='images/redx.png' width=20 height=20 border=0 alt=''>
        </a> </td>";
    } else {
      echo "<td></td><td></td>";
    }
    echo "</tr>";
  }
}

// -----------------------------------------------------------------
// Creates a dropdown menu.  Selecting a new field adds the column.
function CreateColumnAdder($all_fields,$display_fields,$config){
  $queries = http_build_query($_GET);

  echo "<form method='POST' action='?".htmlentities($queries,
                            ENT_QUOTES, 'UTF-8',false)."'>";
  echo "<select name=extrafield>";
  echo "<option value=''>--Choose a field--</option>";
  foreach ($all_fields as $field) {
    if (!in_array($field, $display_fields)) {
      if ($config['nicefields']) {
        $nicefield = ucwords(str_replace('_', ' ', $field));
      } else {
        $nicefield = $field;
      }
      echo "<option value=$field>".DisplayedFieldName($nicefield,$config)."</option>";
    }
  }
  echo "</select>";
  echo "<input type='submit' value='Add column'></form>";
}

// -----------------------------------------------------------------
// Creates the download button and the associated data
function CreateDownloadButton($display_fields,$data,$config){

  //Don't allow 'metapath' to be in display_fields
  for ($i=0; $i<count($display_fields); $i += 1) {
    if (ucwords($display_fields[$i]) == 'Metapath') unset($display_fields[$i]);
  }
  $display_fields = array_values($display_fields);

  //Create an array with only the data in $display_fields
  $table_data = array();
  foreach ($data as $row) {
    $newRow = array();
    foreach ($row as $field=>$value) {
      if (in_array(ucwords($field), $display_fields)) {
        array_push($newRow, $value);
      }
    }
    array_push($table_data, $newRow);
  }

  //Serialize as json so it can be set as 1 POST variable
  $serial_fields = json_encode($display_fields);
  $serial_data = json_encode($table_data);

  // Include all needed data in the form
  echo "<p><form method='POST' action=DownloadTable.php>";
  echo "<input type=hidden name=fields value='".$serial_fields."'>";
  echo "<input type=hidden name=data value='".$serial_data."'>";
  echo "<input type='submit' value='Download Table' class='buttons'>
        </form></p>";

}

// ----------------------------------------------------------------------
// Adds last updated time to page
function CreateLastUpdated($dbh, $config) {
  if (!$config['showlastupdated']) { return; }
  $result = $dbh->query("SELECT * FROM Date")->fetch(PDO::FETCH_ASSOC);
  echo "<div class='floatleft'>Last updated: $result[datetime]</div>";
}

// ----------------------------------------------------------------------
// Creates box to choose how many pages to display
function CreatePageBox($limit,$page){
  $htmlstr = "<form method=POST style='text-align:right' action=''>Per Page:";
  $htmlstr .= "<input type='hidden' name='page' value=$page>";
  $htmlstr .= "<input type='hidden' id='disp' name='disp' value=$limit>";
  $htmlstr .= "<select name='' onchange='
    document.getElementById(\"disp\").value=this.value;
  this.value=$limit;
  this.form.submit();'>";
  $pages = range(25,200,25);
  array_push($pages,100000);
  foreach($pages as $pagenumber) {
    $htmlstr .= "<option value=$pagenumber";
    $htmlstr .= ($limit==$pagenumber) ? " selected": "";
    $htmlstr .= ">";
    $htmlstr .= ($pagenumber==100000) ? "All" : "$pagenumber";
    $htmlstr .= "</option>";
  }
  $htmlstr .= "</select></form>";
  echo $htmlstr;
}

// ----------------------------------------------------------------------
// Initializes the table and sets up the headers with appropriate sorting
function CreateTableHeader($config,$table_fields,$orderby,$sort){

  // Make sure table takes up whole patch
  echo "<table><tr>";

  // Make a delete header if desired
  if ($config['delete']) {
    echo "<th>Del</th>\n";
  }

  // For each field, capitalized if desired and add sorting link and arrow
  foreach ($table_fields as $column) {
    if ($column == "Metapath" && $config['table'] != tables::catalog()) continue;
    if ($config['nicefields']) {
      $text = DisplayedFieldName(ucwords(str_replace('_', ' ', $column)),$config);
    } else {
      $text = DisplayedFieldName($column,$config);
    }
    $field = columnHeader($column,$text,$config,$orderby,$sort);
    echo "<th>$field</th>\n";
  }
  echo "</tr>\n";
}

// ----------------------------------------------------------------------
// Fills out the table
function CreateTableEntries($config,$startrow,$limit,$data,$Stats){
  include_once "email.php";
  $q_email = email::questions();

  // Start first row style
  $tr_class = "class='odd'";

  // Go through all of the returned data
  if ($limit > count($data)-$startrow) {
    $limit = count($data)-$startrow;
  }

  for ($i=$startrow; $i<($startrow+$limit); $i++) {
    // Start row
    echo "<tr $tr_class>\n";

    $row = $data[$i];
    $id = $row['id'];

    // Initialize the html string
    $htmlstring = '';

    // If desired, set up a delete button next to each entry
    if ($config['delete'] && $id!='') {
      $confirm_msg = "Are you sure you want to delete the following entry? $id";
      echo "<td style='text-align:center'>
      <form onsubmit='return confirm(\"$confirm_msg\")' method='POST'>
      <input type=hidden name='deleteid' value=$id>
      <input type=image src='images/redx.png'
      width=10 height=10 alt=submit>
      </form></td>";
    }

    // Split the rows in fields and values
    foreach ($row as $field=>$value) {
      if ($field == "Metapath" && $config['table'] != tables::catalog()) continue;

      $htmlstring = "<td> ";
      if($value!='') {
        // Create link to view metadata file
        if(strcasecmp($field,"metapath")==0) {
          $htmlstring .=
            "<a href='data/DisplayMetadataFile.php/?id=$id'>
            <img src='images/metadata.png' width=40 height=40 border=0 alt=''>
            </a>";
          $htmlstring .=
            "<a href='data/DisplayDownloadPage.php/?id=$id'>
            <img src='images/data.png' width=40 height=40 border=0 alt=''>
            </a>";
        }

        // Format time appropriately
        elseif((strcasecmp($field, "LastUpdated")==0) ||
               (strcasecmp($field, "TrackingStarted")==0)) {
          // These fields are stored in the database as UTC, but we want to
          // display them with the user's local timezone.
          $date = new DateTime($value, new DateTimeZone("UTC"));
          $TZ = @date_default_timezone_get();
          $date->setTimeZone(new DateTimeZone($TZ));
          $htmlstring .= $date->format("H:i:s")."<br>".$date->format("Y-m-d");
        }

        // Round spin values to 4 decimals
        elseif(preg_match("/(\whi||\wpin)(\d\w?)/",$field)) {
          $value = round($value,4);
          if($value!=0) {
            $htmlstring .= ($value>0) ? "<span class='hidden'>-</span>" : "";
            $htmlstring .= sprintf("%.4f",0+$value);
          } else {
            $htmlstring .= "0";
          }
        }

        // Don't round Time
        elseif(strcasecmp($field,"Time") == 0) {
          $format = ($value > 1) ? "%.1f" : "%.1e";
          $htmlstring .= ($value!=0) ? " ".sprintf($format, $value) : "0";
        }

        // Round other values based on median and significant figures
        elseif(is_numeric($value)) {
          $sigs = $Stats[$field]["sigs"];
          $value = round($value,$sigs);
          $htmlstring .= ($value!=0) ? " ".sprintf($Stats[$field]["formatstr"], $value) : "0";
        }

        // Format for the id, which may be a full path or a short ID string
        elseif(strcasecmp($field,'id') == 0) {
          if ($config['table'] == tables::ongoing()) {
            // for ongoing (id is a full path)
            preg_match('|[^/]+(/.*)|',$value,$matches);
            $value = $matches[1];
            $htmlstring = "<td><div style='white-space:normal; text-align:left; "
                          ."word-wrap:break-word; width:20em'>$value</div>";
          } else {
            // for completed and catalog
            $htmlstring = "<td style='white-space:normal; text-align:center;'>";
            if ($config['table'] == tables::completed()) {
              $htmlstring .= "<a href='data/DisplayMetadataFile.php/?id=$id'>$value</a>";
            } else {
              $htmlstring .= $value;
            }
          }
        }

        elseif(stristr($field,'Email')) {
          $htmlstring .=
            "<a href='mailto:$q_email?cc=$value &subject=$id'>
            <img src='images/email.png' width=40 height=40 border=0 alt=''>
            </a>";
        }

        // Otherwise, display the value without formatting
        else {
          $htmlstring .= $value;
        }
      }
      $htmlstring .= "</td>\n";
      echo $htmlstring;
    }
    echo "</tr>\n";

    // Switch row style
    if($tr_class == "class='odd'") {
      $tr_class = "class='even'";
    } else {
      $tr_class = "class='odd'";
    }
  }
  echo "</table>\n";
}

//-------------------------------------------------------------------------

//                             SUBROUTINES

//-------------------------------------------------------------------------


//----------------------------------------------------------------------
// Create min/max boxes, reads in values if appropriate
function GetBoundValue($Bound,$Filter,$Index){
  $valuestring = ($Bound=='regex') ? "string or " : "";
  $valuestring .= "$Bound<br><INPUT TYPE=Text NAME=$Bound$Index size=";
  $valuestring .= ($Bound=='regex') ? '23' : '10';
  if (isset($_GET["$Bound$Index"])) {
    $valuestring .= " Value='".htmlentities($_GET["$Bound$Index"])."'";
  }
  return "<td class='reset'>$valuestring></td>";
}

// --------------------------------------------------------------------------
// Returns the html needed for column sorting with the arrows as well as
// deleting the column.
// Defaults all field links to SORT ASC,
// unless currently being sorted by that field, then link to SORT DSC
function columnHeader($field,$text,$config,$currentfield=null,$currentsort=null){
  $sort = "ASC";

  if($currentsort == "ASC") {
    $sort = "DESC";
    $sortarrow = "<img src='images/arrow_up.png' alt='Ascending'>";
  } elseif($currentsort == "DESC") {
    $sort = "ASC";
    $sortarrow = "<img src='images/arrow_down.png' alt='Descending'>";
  }

  if ($currentfield != $field) {
    $sortarrow = null;
  }

  $queriesSort = http_build_query(array('sort' => $sort,'orderby' => $field)
    + $_GET);

  $htmlstr = "<a href='?".htmlentities($queriesSort, ENT_QUOTES, 'UTF-8',false).
      "'>$text</a>$sortarrow";

  // Create a delete button for the column if needed
  if ($config['usecolumnadder'] and
      !in_array($field, $config['requiredfields'])) {
    if (isset($_GET['addfields']) and
        in_array($field, $_GET['addfields'])) {
      $newAddFields = array_diff($_GET['addfields'], array($field));
      $queriesDel = http_build_query(array('addfields' => $newAddFields) + $_GET);
    } else {
      if (isset($_GET['rmfields'])) {
        $newRmFields = $_GET['rmfields'];
        array_push($newRmFields, $field);
      } else {
        $newRmFields = array($field);
      }
      $queriesDel = http_build_query(array('rmfields' => $newRmFields) + $_GET);
    }
    $deletex = "<img src='images/redx.png', width=12, height=12, alt='Delete'>";
    $htmlstr .= " <a href='?".htmlentities($queriesDel, ENT_QUOTES,'UTF-8',
                  false)."'>$deletex<a>";
  }

  return $htmlstr;
}

//-----------------------------------------------------------------------
// Create the links at the bottom of the page as necessary
function CreatePageLinks($prev_link,$next_link,$pagination_links){
  if(!($prev_link==null && $next_link==null && $pagination_links==null)) {
    echo '<div class="pagination">'."\n";
    echo $prev_link;
    echo $pagination_links;
    echo $next_link;
    echo '<div style="clear:both;"></div>'."\n";
    echo "</div>\n";
  }
}

// Function to calculate statistics on data 
function sd($array) {
  asort($array);
  $median = $array[count($array)/2];
  $max = end($array);
  reset($array);
  $sf = array();
  foreach ($array as $value) {
    $sf[] = strlen(preg_replace("/\./","",preg_replace("/(?!\d+(\.\d*)?)/","",$value)));
  }
  $decs = ceil(log10(abs($median)));
  $sigs = round(array_sum($sf)/count($sf));
  // note: negative numbers break format specifiers
  $sigs = max(0, min($sigs,4)-$decs);
  if ($sigs<6) {
    $formatstr = "%.${sigs}f";
  } else {
    $formatstr = "%.2e";
  }
  return array("sigs"=>$sigs,"formatstr"=>$formatstr,"max"=>ceil(log10(abs($max))));
}

// Function to return the displayed name associated with a mysql field name
function DisplayedFieldName($mysql_name,$config) {
  $text = $mysql_name;
  if(stristr($text,"chi")) {
    $text = preg_replace("/\whi(\d\w?)/","&chi;<sub>$1</sub>",$text);
  } elseif(stristr($text,"spin")) {
    $text = preg_replace("/\wpin(\w+)/","S<sub>$1</sub>",$text);
  } elseif(strcmp($text,"Metapath")==0) {
    $text = "Data";
  } elseif(strcmp($text,"id")==0) {
    if ($config['table'] == tables::ongoing()) {
      $text = "Path";
    } else {
      $text = "ID";
    }
  } elseif(strcasecmp($text,"Freq")==0) {
    $text = "M&omega;<sub>orb</sub>";
  } elseif(strcasecmp($text,"Eccentricity")==0) {
    $text = "Ecc";
  } elseif(strcasecmp($text,"massratio")==0) {
    $text = "m<sub>1</sub>/m<sub>2</sub>";
  } elseif((strcasecmp($text,"LastUpdated")==0) ||
           (strcasecmp($text,"TrackingStarted")==0)) {
    $text = preg_replace("/\wastUpdated/","Updated",$text);
    $text = preg_replace("/\wrackingStarted/","Inserted",$text);
    $TZ = date('T');
    $text = "$text ($TZ)";
  }
  $text = implode(" ",preg_split('/(?<=([a-z]))(?=[A-Z])/',$text,-1,PREG_SPLIT_NO_EMPTY));
  return $text;
}

?>
