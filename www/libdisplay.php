<?php

include_once("Html.php");
include_once("libdb.php");

date_default_timezone_set('America/New_York');

######################################
# true/false color
# color text green if true, else red 
######################################
function tfColor($string, $tf) {
    if ( $tf ) {
        $colorout = "<font color=\"#00aa00\">$string</font>";
    } else {
        $colorout = "<font color=\"#880000\">$string</font>";
    }
    return $colorout;
}

function hiLoColor($value, $lo, $hi) {
    $fvalue = floatval($value);
    if ($fvalue >= $lo and $fvalue <=$hi) {
	$colorout = "<font color=\"#00aa00\">$value</font>";
    } elseif ($fvalue < $lo) {
	$colorout = "<font color=\"#000088\">$value</font>";
    } elseif ($fvalue > $hi) {
	$colorout = "<font color=\"#880000\">$value</font>";
    }
    return $colorout;
}


#######################################
#
#
#######################################
function getCurrentUriDir() {
    $uri = $_SERVER['SCRIPT_NAME'];
    $dirs = preg_split("/\//", $uri);
    $dir = $dirs[count($dirs)-3];
    return $dir;
}

function getDefaultTitle() {
    return getCurrentUriDir()." pipeQA";
}

function getDefaultH1() {
    return "Q.A. Test Summary";
}

function verifyTest($value, $lo, $hi) {
    $pass = true;  # default true (ie. no limits were set)
    if ($lo and $hi) {
	$pass = ($value >= $lo and $value <=$hi);
    }
    if ($lo and !$hi) {
	$pass = ($value >= $lo);
    }
    if (!$lo and $hi) {
	$pass = ($value <= $hi);
    }
    return $pass;
}


function getDefaultTest() {

    $testDir = "";
    if (array_key_exists('test', $_GET)) {
	$testDir = $_GET['test'];
	setcookie('displayQA_test', $testDir);
    } elseif (array_key_exists('displayQA_test', $_COOKIE)) {
	$testDir = $_COOKIE['displayQA_test'];
    }

    # if it didn't get set, or if it doesn't exists (got deleted since cookie was set.
    # ... use the first available test directory
    if (strlen($testDir) == 0 or !file_exists($testDir)) {
	$d = @dir(".");
	while(false !== ($f = $d->read())) {
	    if (preg_match("/test_/", $f) and is_dir($f)) {
		$testDir = $f;
		break;
	    }
	}
    }
    return $testDir;
}


function getActive() {

    $testDir = getDefaultTest();


    # see if there are maps associated with this
    $d = @dir("$testDir");
    $haveMaps = false;
    $navmapFile = "";
    while(false !== ($f = $d->read())) {
	if (preg_match("/\.navmap/", $f)) {$navmapFile =  "$testDir/$f";}
	if (preg_match("/\.(map|navmap)$/", $f)) { $haveMaps = true; break; }
    }

    # validation list from the navmap file
    # ... a bit excessive to read the whole file, but it should only contain max ~100 entries.
    $validActive = array("all", ".*");
    if (strlen($navmapFile) > 0) {
	$lines = file($navmapFile);
	foreach ($lines as $line) {
	    $arr = preg_split("/\s+/", $line);
	    $validActive[] = $arr[0];
	}
    }
    
    # if there are .map files, the default is a *_all.png file
    $active = $haveMaps ? "all" : ".*";
    if (array_key_exists('active', $_GET) and in_array($_GET['active'], $validActive)) {
	$active = $_GET['active'];
	setcookie('displayQA_active', $active);

    # get a value stored as a cookie, but not if the test changed (then use the default)
    } elseif (array_key_exists('displayQA_active', $_COOKIE) and
	      (in_array($_COOKIE['displayQA_active'], $validActive)) and 
	      (!array_key_exists('test', $_GET))) {
	$active = $_COOKIE['displayQA_active'];
	if ($haveMaps and preg_match("/\.\*/", $active)) {
	    $active = "all";
	}
    }

    
    return $active;    
}


####################################################
# groups
####################################################
function getGroupList() {
    $dir = "./";
    $groups = array();
    $d = @dir($dir) or dir("");
    while(false !== ($testDir = $d->read())) {
	$parts = preg_split("/_/", $testDir);

	if (count($parts) > 2) {
	    $group = $parts[1];
	} else {
	    $group = "";
	}
	
	if (array_key_exists($group, $groups)) {
	    $groups[$group] += 1;
	} else {
	    $groups[$group] = 1;
	}
    }
    ksort($groups);
    return $groups;
}
function getGroup() {
   if (array_key_exists('group', $_GET)) {
	$group = $_GET['group'];
	setcookie('displayQA_group', $group);
   } elseif (array_key_exists('displayQA_group', $_COOKIE)) {
       $group = $_COOKIE['displayQA_group'];
   } else {
       $group = "";
   }

   $allGroups = array_keys(getGroupList());
   # if we don't have the group, default to ""
   if (strlen($group) > 0 and ! in_array($group, $allGroups)) {
       $group = "";
   }
   
   return $group;
}





####################################################
#
####################################################
function writeTable_timestamps($group=".*") {

    $i = 0;
    $min = 0;
    $max = 0;
    $d = @dir(".");
    while(false !== ($f = $d->read())) {
	if (! is_dir($f) or preg_match("/^\./", $f)) { continue; }

	if (! preg_match("/^test_$group/", $f)) { continue; }
	
	$db = connect($f);
	$cmd = "select min(entrytime),max(entrytime) from summary";
	$prep = $db->prepare($cmd);
	$prep->execute();
	$results = $prep->fetchAll();
	$result = $results[0];
	if ($i == 0) {
	    list($min,$max) = $result;
	}

	if ($result[0] < $min) { $min = $result[0]; }
	if ($result[1] > $max) { $max = $result[1]; }

	$i += 1;
    }

    $table = new Table("width=\"80%\"");
    $table->addHeader(array("Oldest Entry", "Most Recent Entry"));
    $now = time();
    $oldest = date("Y-m-d H:i:s", $min);
    $latest = date("Y-m-d H:i:s", $max);

    if ($now - $max < 120) {
	$latest .= "<br/><font color=\"#880000\">(< 2m ago, testing in progress)</font>";
    }
    $table->addRow(array($oldest, $latest));

    return "<h2>Timestamps</h2>\n".$table->write();
}



####################################################
#
####################################################
function writeTable_ListOfTestResults() {

    $testDir = getDefaultTest();
    $active = getActive();
    
    $table = new Table("width=\"90%\"");

    $headAttribs = array("align=\"center\"");
    $table->addHeader(
	array("Label", "Timestamp", "Value", "Limits", "Comment"),
	$headAttribs
	);

    global $dbFile;

    $db = connect($testDir);
    if (! $db) { return "Unable to query database for $testDir."; }
    $cmd = "select * from summary order by label";
    $prep = $db->prepare($cmd);
    $prep->execute();
    $result = $prep->fetchAll();
	
    $tdAttribs = array("align=\"left\"", "align=\"center\"",
		       "align=\"right\"", "align=\"center\"",
		       "align=\"left\" width=\"200\"");
    foreach ($result as $r) {
	list($test, $lo, $value, $hi, $comment) =
	    array($r['label'], $r['lowerlimit'], $r['value'], $r['upperlimit'], $r['comment']);

	if (! preg_match("/$active/", $test) and ! preg_match("/all/", $active)) { continue; }
	
	$pass = verifyTest($value, $lo, $hi);
	if (!$lo) { $lo = "None"; }
	if (!$hi) { $hi = "None"; }
	
	if (!$pass) {
	    $test .= " <a href=\"backtrace.php?label=$test\">Backtrace</a>";
	}
	$mtime = date("Y-m_d H:i:s", $r['entrytime']);

	$loStr = sprintf("%.3f", $lo);
	$hiStr = sprintf("%.3f", $hi);
	$valueStr = sprintf("%.3f", $value);

	$table->addRow(array($test, $mtime,
			     hiLoColor($valueStr, $lo, $hi), "[$loStr, $hiStr]", $comment), $tdAttribs);
    }
    $db = NULL;
    return $table->write();
    
}
function displayTable_ListOfTestResults($testDir) {
    echo writeTable_ListOfTestResults($testDir);
}



function writeTable_OneTestResult($label) {

    $testDir = getDefaultTest();
    
    if (empty($label)) {
	return "<h2>No test label specified. Cannot display test result.</h2><br/>\n";
    }
    
    $table = new Table("width=\"90%\"");
    
    $headAttribs = array("align=\"center\"");
    $table->addHeader(
	array("Label", "Timestamp", "Value", "Limits", "Comment"),
	$headAttribs
	);
    #$table->addHeader(array("Label", "Timestamp", "LowerLimit", "Value", "UpperLimit", "Comment"));

    global $dbFile;
    #$mtime = date("Y-m_d H:i:s", filemtime("$testDir/$dbFile"));
    $db = connect($testDir);
    $cmd = "select * from summary where label = ?";
    $prep = $db->prepare($cmd);
    $prep->execute(array($label));
    $result = $prep->fetchAll();
    
    $tdAttribs = array("align=\"left\"", "align=\"center\"",
		       "align=\"right\"", "align=\"center\"",
		       "align=\"left\" width=\"200\"");
    foreach ($result as $r) {
	list($test, $timestamp, $lo, $value, $hi, $comment, $backtrace) =
	    array($r['label'], $r['entrytime'], $r['lowerlimit'], $r['value'], $r['upperlimit'],
		  $r['comment'], $r['backtrace']);
	
	$pass = verifyTest($value, $lo, $hi);
	if (!$lo) { $lo = "None"; }
	if (!$hi) { $hi = "None"; }

	$mtime = date("Y-m_d H:i:s", $r['entrytime']);

	$loStr = sprintf("%.3f", $lo);
	$hiStr = sprintf("%.3f", $hi);
	$valueStr = sprintf("%.3f", $value);

	$table->addRow(array($test, $mtime,
			     hiLoColor($valueStr, $lo, $hi), "[$loStr, $hiStr]", $comment), $tdAttribs);
	#$table->addRow(array($test, date("Y-m-d H:i:s", $timestamp),
	#		     $lo, hiLoColor($value, $pass), $hi, $comment));
    }
    $db = NULL;

    return $table->write();
}
function write_OneBacktrace($label) {

    $testDir = getDefaultTest();
    
    $out = "<h2>Backtrace</h2><br/>\n";
    if (empty($label)) {
	return "<b>No test label specified. Cannot display backtrace.</b><br/>\n";
    }
    
    global $dbFile;
    $db = connect($testDir);
    $cmd = "select * from summary where label = ?";
    $prep = $db->prepare($cmd);
    $prep->execute(array($label));
    $result = $prep->fetchAll();

    $backtrace = "";
    foreach ($result as $r) {
	$backtrace .= $r['backtrace'];
    }
    $db = NULL;

    $out .= preg_replace("/\n/", "<br/>\n", $backtrace);
    $out = preg_replace("/(\t|\s{4})/", "&nbsp;&nbsp;", $out);
    
    return $out;
    
}
function displayTable_OneTestResult($testDir, $label) {
    echo writeTable_OneTestResult($testDir);
}




function writeTable_metadata() {

    $testDir = getDefaultTest();
    $active = getActive();
	
    $meta = new Table();
    
    $db = connect($testDir);
    $cmd = "select key, value from metadata";
    $prep = $db->prepare($cmd);
    $prep->execute();
    $results = $prep->fetchAll();

    foreach ($results as $r) {
	$meta->addRow(array($r['key'].":", $r['value']));
    }
    $meta->addRow(array("Active:", $active));
    return $meta->write();
}




####################################################
#
# Figures
#
####################################################
 
function writeMappedFigures($suffix="map") {

    $testDir = getDefaultTest();

    $active = getActive();

    $figNum = ($suffix=="map") ? 2 : 1;
    $j = 0;
    $out = "";
    $d = @dir("$testDir");
    while(false !== ($f = $d->read())) {
    	if (! preg_match("/.(png|PNG|jpg|JPG)/", $f)) { continue; }

	$base = preg_replace("/\.(png|PNG|jpg|JPG)/", "", $f);
	$mapfile = $base . "." . $suffix;

	if (! preg_match("/$active/", $f) and $suffix != 'navmap') { continue; }

	
	# get the image path
    	$path = "$testDir/$f";
    	$mtime = date("Y-m_d H:i:s", filemtime($path));
	$mapPath = "$testDir/$mapfile";

	if (! file_exists($mapPath)) { continue; }
	
	# get the caption
	$db = connect($testDir);
	$cmd = "select caption from figure where filename = ?";
	$prep = $db->prepare($cmd);
	$prep->execute(array($f));
	$result = $prep->fetchColumn();

	# load the map
	$mapString = "<map id=\"$base\" name=\"$base\">\n";
	$mapList = file($mapPath);
	foreach($mapList as $line) {
	    list($label, $x0, $y0, $x1, $y1, $info) = preg_split("/\s+/" ,$line);
	    if (!preg_match("/^nolink:/", $info)) {
		$href = "summary.php?active=$label";
		$mapString .= sprintf("<area shape=\"rect\" coords=\"%d,%d,%d,%d\" href=\"%s\" title=\"%s\">\n",
				      $x0, $y0, $x1, $y1, $href, $label." ".$info);
	    } else {
		$info = preg_replace("/^nolink:/", "", $info);
		$mapString .= sprintf("<area shape=\"rect\" coords=\"%d,%d,%d,%d\" title=\"%s\">\n",
				      $x0, $y0, $x1, $y1, $info);
	    }
	}
	$mapString .= "</map>\n";

	if (preg_match("/.tiff$/", $path)) {
	    $imgTag = "<object data=\"$path\" type=\"image/tiff\" usemap=\"#$base\"><param name=\"negative\" value=\"yes\"></object>";
	} else {
	    $imgTag = "<img src=\"$path\" usemap=\"#$base\">";
	}
	
	$img = new Table();
	if ($suffix == 'navmap') {
	    $img->addRow(array("Show <a href=\"summary.php?active=all\">all</a>"));
	}
	$img->addRow(array("<center>$imgTag</center>"));
	$img->addRow(array("<b>Figure $figNum.$j</b>: ".$result));
	$img->addRow(array("<b>$f</b>: timestamp=$mtime"));
	$out .= $img->write();
	$out .= $mapString;
	$out .= "<br/>";
	
	$j += 1;
    }
    return $out;
}


function writeFigures() {

    $testDir = getDefaultTest();
    $active = getActive();
    $d = @dir($testDir);

    $j = 0;
    $out = "";
    while( false !== ($f = $d->read())) {
    	if (! preg_match("/.(png|PNG|jpg|JPG)/", $f)) { continue; }

	if (! preg_match("/$active/", $f)) { continue; }

	
	# get the image path
    	$path = "$testDir/$f";

	# skip mapped files (they're obtained with writeMappedFigures() )
	$base = preg_replace("/.(png|PNG|jpg|JPG)/", "", $path);
	$map = $base . ".map";
	$navmap = $base . ".navmap";
	if (file_exists($map) or file_exists($navmap)) {
	    continue;
	}

    	$mtime = date("Y-m_d H:i:s", filemtime($path));

	# get the caption
	$db = connect($testDir);
	$cmd = "select caption from figure where filename = ?";
	$prep = $db->prepare($cmd);
	$prep->execute(array($f));
	$result = $prep->fetchColumn();

	# tiff must be handled specially
	 
	if (preg_match("/.tiff$/", $path)) {
	    # this doesn't work.  tiffs disabled for now.
	    $imgTag = "<object data=\"$path\" type=\"image/tiff\"><param name=\"negative\" value=\"yes\"></object>";
	} else {
	    $imgTag = "<img src=\"$path\">";
	}
	
	$img = new Table();
	$img->addRow(array("<center>$imgTag</center>"));
	$img->addRow(array("<b>Figure 2.$j</b>:".$result));
	$img->addRow(array("<b>$f</b>: timestamp=$mtime"));
	$out .= $img->write();
	$out .= "<br/>";
	$j += 1;
    }
    
    return $out;
}
function displayFigures($testDir) {
    echo writeFigures($testDir);
}







function summarizeTest($testDir) {
    $summary = array();

    global $dbFile;
    #$mtime = date("Y-m_d H:i:s", filemtime("$testDir/$dbFile"));

    $db = connect($testDir);
    $testCmd = "select count(*) from summary";
    $prep = $db->prepare($testCmd);
    $prep->execute();
    $nTest = $prep->fetchColumn();
    
    $passCmd = "select * from summary order by label";
    $prep = $db->prepare($passCmd);
    $prep->execute();
    $results = $prep->fetchAll();
    
    $nPass = 0;
    $timestamp = 0;
    foreach($results as $result) {
	if (verifyTest($result['value'], $result['lowerlimit'], $result['upperlimit'])){
	    $nPass += 1;
	}
	$timestamp = $result['entrytime'];
    }

    $ret = array();
    $ret['name'] = $testDir;
    $ret['entrytime'] = $timestamp;
    $ret['ntest'] = $nTest;
    $ret['npass'] = $nPass;
    return $ret;
}






function writeTable_SummarizeAllTests() {
    $dir = "./";

    $group = getGroup();
    

    ## go through all directories and look for .summary files
    $d = @dir($dir) or dir("");
    $dirs = array();
    while(false !== ($testDir = $d->read())) {
	$dirs[] = $testDir;
    }
    sort($dirs);
    
    $d = @dir($dir) or dir("");
    $table = new Table("width=\"90%\"");
    $table->addHeader(array("Test", "mtime", "No. Tests", "No. Passed", "Fail Rate"));
    #while(false !== ($testDir = $d->read())) {
    foreach ($dirs as $testDir) {
	# only interested in directories, but not . or ..
	if ( preg_match("/^\./", $testDir) or ! is_dir("$testDir")) {
	    continue;
	}
	# only interested in the group requested
	if (! preg_match("/test_".$group."/", $testDir)) {
	    continue;
	}

	# if our group is "" ... ignore other groups
	$parts = preg_split("/_/", $testDir);
	if ( $group == "" and (count($parts) > 2)) {
	    continue;
	}

	$summ = summarizeTest($testDir);
	$testDirStr = preg_replace("/^test_${group}_/", "", $testDir);
	$testLink = "<a href=\"summary.php?test=$testDir\">$testDirStr</a>";

	$passLink = tfColor($summ['npass'], ($summ['npass']==$summ['ntest']));
	$failRate = "n/a";
	if ($summ['ntest'] > 0) {
	    $failRate = 1.0 - 1.0*$summ['npass']/$summ['ntest'];
	    $failRate = tfColor(sprintf("%.3f", $failRate), ($failRate == 0.0));
	}
	if ($summ['entrytime'] > 0) {
	    $timestampStr = date("Y-m-d H:i:s", $summ['entrytime']);
	} else {
	    $timestampStr = "n/a";
	}
	
	$table->addRow(array($testLink, $timestampStr,
			     $summ['ntest'], $passLink, $failRate));
    }
    return $table->write();
    
}

function writeTable_SummarizeAllGroups() {
    $dir = "./";

    $groups = getGroupList();
    
    ## go through all directories and look for .summary files
    $table = new Table("width=\"90%\"");
    $table->addHeader(array("Test", "mtime", "TestSets", "TestSets Passed", "Tests", "Tests Passed", "Fail Rate"));
    foreach ($groups as $group=>$n) {

	$nTestSets = 0;
	$nTestSetsPass = 0;
	$nTest = 0;
	$nPass = 0;
	
	$d = @dir($dir) or dir("");
	$dirs = array();
	while(false !== ($testDir = $d->read())) {
	    $dirs[] = $testDir;
	}
	asort($dirs);
	
	$lastUpdate = 0;
	$d = @dir($dir) or dir("");
	#while(false !== ($testDir = $d->read())) {
	foreach ($dirs as $testDir) {
	    if (!preg_match("/test_".$group."/", $testDir)) {
		continue;
	    }
	    # must deal with default group "" specially
	    $parts = preg_split("/_/", $testDir);
	    if (count($parts) > 2 and $group == "") {
		continue;
	    }
	    
	    $summ = summarizeTest($testDir);
	    $nTestSets += 1;
	    $nTest += $summ['ntest'];
	    $nPass += $summ['npass'];
	    if ($summ['ntest'] == $summ['npass']) {
		$nTestSetsPass += 1;
	    }
	    if ($summ['entrytime'] > $lastUpdate) {
		$lastUpdate = $summ['entrytime'];
	    }
	}

	if ($group == "") {
	    $testLink = "<a href=\"group.php?group=\">Top level</a>";
	} else {
	    $testLink = "<a href=\"group.php?group=$group\">$group</a>";
	}

	$passLink = tfColor($nPass, ($nPass==$nTest));
	$failRate = "n/a";
	if ($nTest > 0) {
	    $failRate = 1.0 - 1.0*$nPass/$nTest;
	    $failRate = tfColor(sprintf("%.3f", $failRate), ($failRate == 0.0));
	}
	if ($lastUpdate > 0) {
	    $timestampStr = date("Y-m-d H:i:s", $lastUpdate);
	} else {
	    $timestampStr = "n/a";
	}
	
	$table->addRow(array($testLink, $timestampStr,
			     $nTestSets, $nTestSetsPass, $nTest, $passLink, $failRate));
    }
    return $table->write();
    
}

function displayTable_SummarizeAllTests() {
    echo writeTable_SummarizeAllTests($group);
}




####################################################
# Logs
####################################################

function writeTable_Logs() {

    $testDir = getDefaultTest();
    $db = connect($testDir);

    # first get the tables ... one for each ccd run
    $cmd = "select name from sqlite_sequence where name like 'log%'";
    $prep = $db->prepare($cmd);
    $prep->execute();
    $dbtables = $prep->fetchAll();
    
    # make links at the top of the page
    $tables = "";
    $ul = new UnorderedList();
    foreach ($dbtables as $dbtable) {

	$name = $dbtable['name'];
	$ul->addItem("<a href=\"#$name\">$name</a>");
	
	$cmd = "select * from ?";
	$prep = $db->prepare($cmd);
	$prep->execute(array($name));
	$logs = $prep->fetchAll();

	$tables .= "<h2 id=\"$name\">$name</h2><br/>";
	
	$table = new Table("width=\"80%\"");
	$table->addHeader(array("Module", "Message", "Date", "Level"));
	foreach ($logs as $log) {

	    # check for tracebacks from TestData
	    $module = $log['module'];
	    $msg = $log['message'];
	    if (preg_match("/testQA.TestData$/", $module)) {
		# get the idString from the message
		$idString = preg_replace("/:.*/", "", $msg);
		$module .= " <a href=\"backtrace.php?label=$idString\">Backtrace</a>";
	    }
	    $table->addRow(array($module, $msg, $log['date'], $log['level']));
	}
	$tables .= $table->write();
    }
    $contents = "<h2>Data Used in This Test</h2><br/>" . $ul->write() . "<br/><br/>";
    return $contents . $tables;
}

function displayTable_Logs() {
    echo writeTable_Logs();
}




####################################################
# EUPS
####################################################
 
function writeTable_EupsSetups() {

    $testDir = getDefaultTest();
    $db = connect($testDir);

    # first get the tables ... one for each ccd run
    $cmd = "select name from sqlite_sequence where name like 'eups%'";
    $prep = $db->prepare($cmd);
    $prep->execute();
    $dbtables = $prep->fetchAll();

    # make links at the top of the page
    $tables = "";
    $ul = new UnorderedList();
    foreach ($dbtables as $dbtable) {

	$name = $dbtable['name'];
	$ul->addItem("<a href=\"#$name\">$name</a>");
	
	$cmd = "select * from ?";
	$prep = $db->prepare($cmd);
	$prep->execute(array($name));
	$logs = $prep->fetchAll();

	$tables .= "<h2 id=\"$name\">$name</h2><br/>";
	
	$table = new Table("width=\"80%\"");
	$table->addHeader(array("Product", "Version", "Timestamp"));
	foreach ($logs as $log) {
	    $table->addRow(array($log['product'],$log['version'],date("Y-m-d H:i:s", $log['entrytime'])));
	}
	$tables .= $table->write();
    }
    $contents = "<h2>Data Sets Used in This Test</h2><br/>" . $ul->write() . "<br/><br/>";
    return $contents . $tables;

}

function displayTable_EupsSetups() {
    echo writeTable_EupsSetups();
}



?>