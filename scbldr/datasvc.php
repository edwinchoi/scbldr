<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

//ini_set("display_errors", 1);
//ini_set("display_startup_errors", 1);

date_default_timezone_set("GMT");
$timezone = date_default_timezone_get();

if ( !isset($_GET['p'])) {
	header("HTTP/1.1 400 Bad Request");
	header("Status: Bad Request");
	exit;
}

$t_start = microtime(true);

$data = array();

require_once "./dbconnect.php";
require_once "./terminfo.php";

if ( !$conn ) {
	header("HTTP/1.1 400 Bad Request");
	header("Status: Bad Request");
	echo "Failed to connect to DB: $conn->connect_error\n";
	exit;
}

if ( strpos($_GET['p'], '/') !== 0 ) {
	header("HTTP/1.1 400 Bad Request");
	header("Status: Bad Request");
	echo "Invalid path spec";
	exit;
}

$paths = explode(';', $_GET['p']);
if (count($paths) == 1) {
	$path = explode('/', $paths[0]);
	array_shift($path);
	if (count($path) > 1 && $path[0] != "") {
		$cond = "course = '" . implode("", $path) . "'";
	} else {
		$cond = "TRUE";
	}
} else {
	$names = array();
	$cond = "";
	foreach ($paths as $p) {
		$path = explode('/', $p);
		
		array_shift($path);
		if (count($path) == 1 && $path[0] == "") {
			continue;
		}
		
		if ($cond !== "")
			$cond .= " OR\n";
		$cond .= "course = '" . implode("", $path) . "'";
	}
}

$result_array = array();



if (true) {
	define("CF_SUBJECT"		,0);
	define("CF_COURSE"		,0);
	define("CF_TITLE"		,1);
	define("CF_CREDITS"		,2);
	//define("CF_SECTIONS"	,3);
	//define("CF_CRSID"		,0);

/*
struct {
	const char* course;
	const char* title;
	float credits;
	struct {
		const char* course;
		const char* section;
		short callnr;
		const char* seats;
		const char* instructor;
		bool online;
		bool honors;
		const char* comments;
		const char* alt_title;
		const void* slots;
	} sections[1];
};
 */

	define("SF_COURSE"		,0);
	define("SF_SECTION"		,1);
	define("SF_CALLNR"		,2);
	//define("SF_ENROLLED"	,3);
	//define("SF_CAPACITY"	,4);
	define("SF_SEATS"		,3);
	define("SF_INSTRUCTOR"	,4);
	define("SF_ONLINE"		,5);
	define("SF_HONORS"		,6);
	//define("SF_FLAGS"		,7);
	define("SF_COMMENTS"	,7);
	define("SF_ALT_TITLE"	,8);
	define("SF_SLOTS"		,9);
	define("SF_SUBJECT"		,10);
} else {
	define("CF_CRSID", "_id");
	define("CF_COURSE", "course");
	define("CF_TITLE","title");
	define("CF_CREDITS","credits");
	define("CF_SECTIONS","sections");
	
	define("SF_SUBJECT","subject");
	define("SF_COURSE","course");
	define("SF_SECTION","section");
	define("SF_CALLNR","callnr");
	define("SF_ENROLLED", "enrolled");
	define("SF_CAPACITY", "capacity");
	define("SF_SEATS","seats");
	define("SF_INSTRUCTOR","instructor");
	define("SF_ONLINE","online");
	define("SF_HONORS","honors");
	define("SF_FLAGS","flags");
	define("SF_COMMENTS","comments");
	define("SF_ALT_TITLE","alt_title");
	define("SF_SLOTS", "slots");
}

$query = <<<_
	SELECT
		course, title, credits,
		callnr, flags, section, enrolled, capacity, instructor, comments
	  FROM
		NX_COURSE
	 WHERE
		($cond)
_;
$res = $conn->query($query);
if ( !$res ) {
	header("HTTP/1.1 400 Bad Request");
	header("Status: Bad Request");
	echo "MySQL Query Failed: $conn->error";
	exit;
}
$num_sects = $res->num_rows;

class Flags {
	const ONLINE = 1;
	const HONORS = 2;
	const ST = 4;
	const CANCELLED = 8;
};

$map = array();
$ctable = array();
$result = array();

$secFields = array(SF_INSTRUCTOR, SF_COMMENTS, SF_ALT_TITLE);

$callnrToSlots = array();

while ($row = $res->fetch_row()) {
	if (!isset($ctable[$row[0]])) {
		$ctable[$row[0]] = array();
		$arr = &$ctable[$row[0]];
		$arr[CF_COURSE] = $row[0];
		$arr[CF_TITLE] = $row[1];
		$arr[CF_CREDITS] = floatval($row[2]);
		if (defined("CF_SECTIONS"))
			$arr[CF_SECTIONS] = array();
	} else {
		$arr = &$ctable[$row[0]]; 
	}
	if (defined("CF_SECTIONS")) {
		$map[$row[3]] = array($row[0], count($arr[CF_SECTIONS]));
	} else {
		$map[$row[3]] = array($row[0], count($arr) - 3);
	}
	$sec = array();
	$sec[SF_COURSE] = $row[0];
	$sec[SF_SECTION] = $row[5];
	$sec[SF_CALLNR] = intval($row[3]);
	if (defined("SF_ENROLLED")) {
		$sec[SF_ENROLLED] = intval($row[6]);
		$sec[SF_CAPACITY] = intval($row[7]);
	} else {
		$sec[SF_SEATS] = "$row[6] / $row[7]";
	}
	$sec[SF_INSTRUCTOR] = $row[8];
	if (defined("SF_FLAGS")) {
		$sec[SF_FLAGS] = intval($row[4]);
	} else {
		$sec[SF_ONLINE] = ($row[4] & Flags::ONLINE) != 0;
		$sec[SF_HONORS] = ($row[4] & Flags::HONORS) != 0;
	}
	$sec[SF_COMMENTS] = $row[9];
	
	$sec[SF_ALT_TITLE] = $row[1];
	$sec[SF_SLOTS] = array();
	$callnrToSlots[$sec[SF_CALLNR]] = &$sec[SF_SLOTS];
	
	if (defined("CF_SECTIONS"))
		$arr[CF_SECTIONS][] = $sec;
	else
		$arr[] = $sec;
	//$data[] = &$arr;
	
}

if (count($map) > 0) {
	//$in_cond = 'callnr=' . implode(' OR callnr=', array_keys($map));
	$in_cond = implode(',', array_keys($map));
	$query = <<<_
		SELECT	DISTINCT callnr, day, TIME_TO_SEC(start), TIME_TO_SEC(end), room
		FROM	TIMESLOT
		WHERE	callnr IN ($in_cond)
_;
	$res = $conn->query($query) or die("abc: $conn->error\nQuery: $query");
	while ($row = $res->fetch_row()) {
		$callnrToSlots[$row[0]][] = array(
			/*"day" => */intval($row[1]),
			/*"start" => */intval($row[2]),
			/*"end" => */intval($row[3]),
			/*"location" => */trim($row[4])
		);
	}
}

$data = array_values($ctable);
unset($ctable);
unset($map);

if (!defined("INTERNAL")) {
	header("Content-Type: application/json");
	header("Cache-Control: private, must-revalidate");
	header("Last-Modified: " . gmdate("D, d M Y H:i:s", $last_run_timestamp). " GMT");
	
	if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo "define({data:";
		echo json_encode($data);
		echo "});";
    } else {
		echo json_encode($data);
	}
}

?>
