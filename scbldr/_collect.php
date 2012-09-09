<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

/*
 * This script is buggy at best. Though you are allowed to execute the script from the
 * web, the likelihood of reaching the max execution time increases. Perhaps it'd be best
 * to use a transaction when modifying the TERMINFO (which contains information on when
 * the data was last updated, and whether or not that update was a success). However, the
 * problem is that a user may access the page during an update, in which case no promises
 * can be made about the validity of the data.
 * Ideally, this would never be run from the web and would be run using a cronjob; the fact
 * that it isn't is a limitation of what a student is entitled to do on the servers.
 */

include_once "./model/objects.php";
include "./dbconnect.php";
date_default_timezone_set("EST");

libxml_use_internal_errors(true);

class Flags {
	const ONLINE = 1;
	const HONORS = 2;
	const ST = 4;
	const CANCELLED = 8;
};

function isCli() {
     if(php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
          return true;
     } else {
          return false;
     }
}

define("IS_CLI", isCli());

if (IS_CLI) {
	if (!isset($argv)) {
		echo "Usage: <script> [fall|spring]";
		return;
	}
	$term = $argv[1];

	ini_set("display_errors", 1);
	ini_set("display_startup_errors", 1);

} else {
	$term = $_GET["term"];
}

if (!isset($term)) {
	$term = date("m") < "11" ? "fall" : "spring";
}

define("TERM", $term);
define("SOURCE_URL", "http://www.njit.edu/registrar/schedules/courses/$term/");
define("SCRAPE_COURSES", true);

define("CLEAR_COURSES", false);

if (!defined("DISABLE_CATALOG_SCRAPING")) {
	define("DISABLE_CATALOG_SCRAPING", 0);
}

define("DENORM_TABLES", true);

// no courses would be scheduled for Sunday, so just arbitrarily picking @ to represent it
define("SINGLE_CHAR_DAY_ENCODING", "@MTWRFS");

if (!IS_CLI) {
?>
<html>
<head>
<title>CS490 Project - Data Extractor<?php if (DISABLE_CATALOG_SCRAPING) echo " (Sections only)"; ?></title>
<style type="text/css">
body, * { font-size: 10pt; font-family: sans-serif; }
table tr > *  { padding-left: 4px; padding-right: 4px; }
table tr > td { text-align: right; }
table tr > td:first-child { text-align: left; }
</style>
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript">
function showExpandErrors(el) {
	var panel = $(el).next();
	if (panel.is(":visible")) {
		panel.slideUp();
	} else {
		panel.slideDown();
	}
}
</script>
</head>
<body>
<?php
}
/**
 * Utilizes PHP DOM to parse the HTML data.
 * 
 * Total time taken to update is around 60 seconds. If we take away searching
 * the catalog for course titles (titles given through the schedule site are
 * shortened and capitalized) and descriptions, execution time is reduced by
 * about one-half, to roughly 30 seconds.
 */

//ini_set("display_errors", true);

function readPage($url, $inc_hash = false, $params = array(), $ch = null) {
	$q = '';
	foreach ($params as $name => $value) {
		if ($q != '')
		   $q .= '&';

		if (!is_array($value))
			$q .= urlencode($name) . '=' . urlencode($value);
		else {
			$str = '';
			for ($i = 0; $i < count($value); $i++) {
				if ($i != 0)
					$str .= '&';
				$str .= urlencode($name . '[]') . "=" . urlencode($data[$i]);
			}
			$q .= $str;
		}
	}

	if (!$ch) {
		$ch = curl_init();
		if (!$ch)
			throw new ErrorException('Failed to initialize cURL');
	}

	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_COOKIE, session_name() . "=" . session_id());
	curl_setopt($ch, CURLOPT_COOKIESESSION, true);
	//curl_setopt($this->ch, CURLOPT_FILE, '/dev/null');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_HTTPGET, true);

	if ($q != '') {
		if (!strchr($url, '?'))
			$url .= '?' . $q;
  		else
  			$url .= '&' . $q;
	}
	curl_setopt($ch, CURLOPT_URL, $url);

	$result = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	if ($status != 200)
		throw new Exception(
			"An error occured accessing '$url'.",
			$status);
	
	if ($inc_hash) {
		$hash = md5($result);
	}
	$doc = new DOMDocument();
	$doc->preserveWhiteSpace = false;
	if (!$doc->loadHTML(
			// attributes are not allowed on closing tags.. but NJIT's
			// course schedule data doesn't obey
			preg_replace("/<\\/(\\w+)[^>]*>/", "</\\1>",
				str_replace("&nbsp;", " ", $result)))) {
		throw new ErrorException();
	}

	if ($inc_hash) {
		return array($doc, $hash);
	} else {
		return $doc;
	}
}

function findByAttribute($doc, $elem, $attrName, $attrValue) {
	$elems = $doc->getElementsByTagName($elem);
	$list = array();
	$len = strlen($attrValue);
	foreach ($elems as $e) {
		$cmpValue = $e->getAttribute($attrName);
		if (strlen($cmpValue) >= $len && strncasecmp($attrValue, $e->getAttribute($attrName), $len) == 0)
		//if ($e->getAttribute($attrName) == $attrValue)
			$list[] = $e;
	}
	return $list;
}

function findFirstByAttribute($root, $elem, $attrName, $attrValue) {
	if ( $root->tagName == $elem ) {
		$attr = $root->getAttribute( $attrName );
		if ( strncasecmp( $attrValue, $attr, strlen( $attrValue ) ) == 0 ) {
			return $root;
		}
	}
	foreach ( $root->childNodes as $c ) {
		if ( $c->nodetype == XML_ELEMENT_NODE ) {
			$result = findFirstByAttribute( $c, $elem, $attrName, $attrValue );
			if ( $result !== false ) {
				return $result;
			}
		}
	}
	return false;
}

/*
FOR THE NEW COURSE SCHEDULE DATA SITE (incomplete due to ASPX state management scheisse)..

Development was ditched due to difficulties gathering scheduling data for
subjects that span multiple pages.
 */

function getCourseLinks($doc) {
	$clsList = findByAttribute($doc, "div", "class", "courseList_section");
	$links = array();
	foreach ($clsList as $elem) {
		$linkSubset = $elem->getElementsByTagName("a");
		foreach ($linkSubset as $link) {
			if ($link->hasAttribute("href"))
			   $links[] = $link;
		}
	}
	return $links;
}

function extractCourseInfo($doc, $a) {
	$list = $a->getElementsByTagName("strong");
	if ($list->length != 1) return false;
}

function getCourse($doc) {
	$table = findByAttribute($doc, "table", "class", "subject_tablewrapper_table");
	if ($table->length != 1) return false;
	$table = $table->item(0);

	$rowNodeList = $table->getElementsByTagName("tr");
	$cells = array();
	foreach ($rowNodeList as $rowNode) {
		$cellNodeList = $rowNode->getElementsByTagName("td");
		if ($cellNodeList->length == 1)
		   $cells[] = $cellNodeList->item(0);
	}
	return $cells;
}

/**
 * Current site uses state information passed through hidden inputs to handle
 * pagination, making it extremely difficult to get course information for
 * subjects that span multiple pages.
 */
function dumpCourseInfo_new() {
	list($doc, $hash) = readPage("http://courseschedules.njit.edu/index.aspx", array("semester" => date("Y") . strtoupper(substr(TERM, 0, 1))));
	$elemList = getCourseLinks($doc);
	$res = array();
	if (count($elemList) > 0) {
		foreach ($elemList as $elem) {
			$href = htmlspecialchars_decode($elem->getAttribute("href"));
			list($doc2, $hash2) = readPage("http://courseschedules.njit.edu/" . $href);
			if (!$doc2) {
				echo "failed to read $href";
				continue;
			}

			$nameList = findByAttribute($doc2, "div", "class", "courseName");
			foreach ($nameList as $elem2) {
				$listOfLinks = $elem2->getElementsByTagName("a");
				foreach ($listOfLinks as $link) {
					echo $link->textContent . "<br/>";
				}
				if ($elem2->parentNode) {
					$descInfo = findByAttribute($elem2->parentNode, "div", "class", "coursedescription_info");
					if (count($descInfo) == 1) {
						echo $descInfo[0]->textContent . "<br/><br/>";
					}
				}
			}
			echo "Processed " . count($nameList) . "<br/>";
			flush();
			unset($doc2);
		}
	}
	return $res;
}

/*

EVERYTHING FROM HERE ON DOWN IS FOR THE OLD SCHEDULE SITE

It works reasonably well with the Fall 2010 data but will not properly parse
the Spring 2010 data.

Older data uses merged table cells, which this script does not check for.

 */

function getFileNames() {
	$doc = readPage(SOURCE_URL . "index_list.html");
	$listOfLinks = $doc->getElementsByTagName("a");
	$fileNames = array();
	foreach ($listOfLinks as $link) {
		if ($link->hasAttribute("href")) {
			$fileNames[$link->textContent] = $link->getAttribute("href");
		}
	}
	return $fileNames;
}

function myToArray($node) {
	$arr = array();
	foreach ($node->childNodes as $c) {
		if ($c->nodeType == XML_TEXT_NODE) {
			$arr[] = $c->wholeText;
		}
	}
	return $arr;
}

function parseTime($s) {
	for ($i = 0; $i < strlen($s); $i++) {
		if (!ctype_digit($s[$i]))
			break;
	}
	if ($i != 3 && $i != 4) return -1;

	$time = substr($s, 0, $i);
	$ampm = substr($s, $i);
	
	if ($i == 3) $hh = intval(substr($time, 0, 1), 10) - 1;
	else $hh = intval(substr($time, 0, 2), 10);
	
	$mm = intval(substr($time, $i - 2, 2), 10);
	if ($ampm == "pm" && $hh != 12) $hh += 12;

	$tms = (($hh * 60) + $mm) * 60 * 1000;
	return $tms;
}

// parses a time in the format ###[#](am|pm)?, where the time may start with a 0.
function fixTime($s) {
	for ($i = 0; $i < strlen($s); $i++) {
		if (!ctype_digit($s[$i]))
			break;
	}
	if ($i != 3 && $i != 4) return false;

	$time = substr($s, 0, $i);
	$ampm = substr($s, $i);
	
	if ($i == 3) $hh = intval(substr($time, 0, 1), 10);
	else $hh = intval(substr($time, 0, 2), 10);
	
	$mm = intval(substr($time, $i - 2, 2), 10);
	if ($ampm == "pm" && $hh != 12) $hh += 12;

	return sprintf("%02d:%02d:00", $hh, $mm);
}

function parseRange($r) {
	list($start, $end) = sscanf($r, "%s - %s");
	$tStart = fixTime(trim($start));
	$tEnd = fixTime(trim($end));
	return array($tStart, $tEnd);
}

/*
 * Parses the time slots
 */
function parseSlots($daysNode, $timesNode, $roomsNode) {
	$days = myToArray($daysNode);
	$times = myToArray($timesNode);
	$rooms = myToArray($roomsNode);

	$slots = array();
	for ($i = 0; $i < count($days); $i++) {
		$d = trim($days[$i]);
		$t = trim($times[$i]);
		if (isset($rooms[$i])) {
			$l = preg_replace('/\s\s+/', ' ', $rooms[$i]);
			if (strlen( $l ) === 0)
				$l = null;
		} else {
			$l = null;
		}

		$r = parseRange($t);

		if (strlen($d) != 1 && $d != "TBA") {
			for ($j = 0; $j < strlen($d); $j++) {
				// NOTE:
				$dayOfWeek = strpos(SINGLE_CHAR_DAY_ENCODING, $d[$j]);
				$slots[] = new TimeSlot($dayOfWeek + 1, $r[0], $r[1], $l); 
			}
		} else {
			$slots[] = new TimeSlot(
				$d == "TBA" ? 0 : (strpos(SINGLE_CHAR_DAY_ENCODING, $d) + 1),
				$r[0],
				$r[1],
				$l
			);
		}
	}
	return $slots;
}

function getHTML($node) {
     $str = "";
     foreach ($node->childNodes as $child) {
     	$doc = new DOMDocument();
     	$doc->appendChild($doc->importNode($child, true));
     	$str .= $doc->saveHTML();
     }
     // fix the content so that the string doesn't start or end with a <br>
     // and adjacent breaks are combined into one
     $parts = explode("<br>", $str);
     $arr = array();
     foreach ($parts as $s) {
     	$s = trim($s);
     	if (strlen($s) > 0)
     		$arr[] = $s;
     }
     return implode("<br/>", $arr);
}

function parseTableData($table, $course, $term, $alt_title) {
	$tRows = $table->getElementsByTagName("tr");

	$sections = array();
	foreach ($tRows as $tRow) {
		$tCells = $tRow->getElementsByTagName("td");
		if ($tCells->length != 11) {
			if ($tCells->length != 0) {
				echo "bad table format<br/>\n";
			}
			continue;
		}

		$sect		= trim($tCells->item(0)->textContent);
		$callnr		= trim($tCells->item(1)->textContent);
		$days		= trim($tCells->item(2)->textContent);
		$times		= trim($tCells->item(3)->textContent);
		$room		= trim($tCells->item(4)->textContent);
		$status		= trim($tCells->item(5)->textContent);
		$max		= trim($tCells->item(6)->textContent);
		$now		= trim($tCells->item(7)->textContent);
		$instructor	= trim($tCells->item(8)->textContent);
		$comments	= trim(getHTML($tCells->item(9)));
		//$comments	= trim($tCells->item(9)->nodeValue);
		$credits	= trim($tCells->item(10)->textContent);
		
		if (strlen($comments) == 0)
			$comments = null;
		if ($instructor == "See Department")
			$instructor = null;

		$cancelled	= $status == "Canceled" ? 1 : 0;
		$online		= strstr($comments, "elearning") ? 1 : 0;
		if ($online) $comments = '';
		$slots = parseSlots($tCells->item(2), $tCells->item(3), $tCells->item(4));

		$section = new Section();
		if (isset($alt_title))
			$section->alt_title = $alt_title;
		else
			$section->alt_title = null;
		$section->callnr		= $callnr;
		$section->instructor	= $instructor;
		$section->section		= $sect;
		$section->enrolled		= $now;
		$section->capacity		= $max;
		$section->course		= $course;
		$section->online		= $online;
		$section->comments		= $comments;
		$section->cancelled		= $cancelled;
		$section->term			= $term;

		if (count($slots) != 0)
			$section->slots = $slots;
		$sections[] = $section;

		$course->credits = $credits;

		//mysql_query($str, $db->link);
	}
	$course->sections = array_merge($course->sections, $sections);
	return $sections;
}

function subjectTaughtAt($subject) {
	global $conn;
	$query = "SELECT campus FROM Subject WHERE prefix = '$subject'";
	$res = $conn->query($query);
	if (!$res) return "";
	$row = $res->fetch_row();
	return $row[0];
}

function courseExists($subject, $nr, $var) {
	global $conn;
	$query = "SELECT courseid FROM COURSE WHERE subject = '$subject' AND coursenr = '$nr' AND coursevar = '$var'";
	$res = $conn->query($query);
	return $res && $res->num_rows == 1;
}
function getCourseID($subject, $nr, $var, $conn) {
	$query = "SELECT crs_id FROM COURSE WHERE subject='$subject' AND number='$nr' AND suffix='$var'";
	$res = $conn->query($query);
	if (!$res)
		return false;
	$row = $res->fetch_row();
	if (!$row)
		return false;
	return $row[0];
}

function storeDataFrom($subject, $url) {
	global $conn;
	list($doc, $hash) = readPage($url, true);
	$term = $doc->getElementsByTagName("i")->item(0)->textContent;
	$tables = $doc->getElementsByTagName("table");
	$count = array();
	$count[0] = 0;
	$count[1] = 0;
	$courses = array();
	foreach ($tables as $table) {
		$title = $table->previousSibling->previousSibling;
		// happens on the first element... HTML output is not well-formed
		if ($title->nodeType != XML_ELEMENT_NODE)
			$title = $title->previousSibling;
		$titleStr = trim($title->textContent);
		for ($i = 0; $i < strlen($titleStr); $i++)
			if (ctype_space($titleStr[$i])) break;
		// each course number consists of 3 characters that represent the course
		// number followed by a single optional variant character
		$nr = substr($titleStr, 0, $i);
		$var = '';
		if (strlen($nr) > 3) {
			$var = substr($nr, 3);
			$nr = substr($nr, 0, 3);
		}
		// check for an invalid course#.. if the length is less than 3, we
		// assume the data is invalid. ACCT11, for example, is invalid
		if (strlen($nr) < 3) {
			continue;
		}
		$str = trim(substr($titleStr, $i));
		$desc = "";
		
		// lazy load the catalog info..
		if (!isset($catDoc)) {
			if (!DISABLE_CATALOG_SCRAPING && !courseExists($subject, $nr, $var)) {
				$campus = strtolower(subjectTaughtAt($subject));
				$pageName = false;
				$query = "SELECT Rprefix FROM SubjectMap WHERE Lprefix='$subject'";
				if ($campus != "njit") {
					$res = $conn->query($query);
					if ($res) {
						$row = $res->fetch_row();
						if ($row) {
							$pageName = $row[0];
						}
					}
				}
				if ($campus == "njit" || $pageName !== false) {
					try {
						// the script would run faster if it did not check the course
						// catalog for course information.
						$pagestr = $campus == "njit" ? $subject : $pageName;
						$catDoc = readPage("http://catalog.njit.edu/courses/" . strtolower($pagestr) . ".php");
					} catch(Exception $e) {
						$catDoc = false;
					}
				}
			}
		}

		if (isset($catDoc) && $catDoc !== false) {
			// search for the information excluding the variant
			//	example: BIOL100
			// then reduce the returned list if it is greater than
			$searchPrefix = $campus != "rutg" ? $subject . $nr : $subject . ":" . $nr;
			$searchPrefix = strtolower($searchPrefix);
			$list = findByAttribute($catDoc, "a", "name", $searchPrefix);
			if (count($list) > 1 && strlen($var) > 0) {
				$searchPrefix .= $var;
				while (count($list) > 0 && strcasecmp($list[0]->getAttribute("name"), $searchPrefix) != 0)
					array_shift($list);
			}
			if (count($list) > 0) {
				$text = $list[0]->textContent;

				$titleStart = strpos($text, '-') + 1;
				$titleEnd = strpos($text, '(', $titleStart + 1);
				
				$numEnd = $titleEnd;
				while (ctype_digit($text[++$numEnd]))
					;
				if ($text[$numEnd] == ')' || $text[$numEnd] == '-' ||
					strpos(substr($text, $titleEnd + 1, strpos($text, ')', $titleEnd) - $titleEnd), "credits") !== false
					)
					$credits = floatval(substr($text, $titleEnd + 1, $numEnd - $titleEnd - 1));

				$text = trim(substr($text, $titleStart, $titleEnd - $titleStart));

				$par = $list[0]->parentNode;
				$par->removeChild($list[0]);
				$desc = trim(str_replace("\\n", "", getHTML($par)));

				$prereq = strncasecmp("prerequisite", $desc, 12) == 0;
			}
		}

		if (array_key_exists($subject . $nr . strtoupper($var), $courses)) {
			$course = $courses[$subject . $nr . strtoupper($var)];
			$alt_title = $str;
		} else {
			$course = new Course();
			$course->title = $str;
			$course->name = $subject . $nr . strtoupper($var);
			$course->subject = $subject;
			$course->coursenr = $nr;
			$course->coursevar = strtoupper($var);
			$course->description = $desc;
		}

//		echo "&gt; <b>$nr</b>$var - $str<br/>";

		if (!isset($alt_title))
			$alt_title = $course->title;
		$sections = parseTableData($table, $course, $term, $alt_title);
		unset($alt_title);
		
		if (isset($credits) && $credits > 0) {
			$course->credits = $credits;
			$credits = 0;
		}

		if (!array_key_exists($course->name, $courses)) {
			$count[0]++;
			$courses[$course->name] = $course;
		}
		$count[1] += count($sections);

		//persist($course);
	}

	return array(array_values($courses), $count, $hash);
}

/*
 * This will only work with the 2010 Fall schedule data.
 * 
 * Use the old file (d2.html) to parse the 2010 Spring schedule data.
 */
function dumpCourseInfo() {
	$files = getFileNames();
	$stored = false;
	$t = 0;
	$totalSects = 0;
	$totalCourses = 0;
	$totalTime = 0;
	$totals = array(0, 0, 0);

	global $conn;

	if (!IS_CLI) {
		echo "<table border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse; border: 0;'>\n";
		echo "<tr><th>Subject</th><th>Courses</th><th>Sections</th><th>Elapsed Time</th><th>Failed</th></tr>\n";
	}
	
	$ins_crs_stmt = $conn->prepare(
		"INSERT INTO COURSE(subject, number, suffix, title, credits) " .
		"VALUES(?, ?, ?, ?, ?)") or die ("ps course " . $conn->error);
	if (DENORM_TABLES) {
		$ins_sect_stmt = $conn->prepare(
			"INSERT INTO NX_COURSE(callnr, crs_id, course, title, credits, section, enrolled, capacity, instructor, flags, comments) " .
			"VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)") or die ("ps section $conn->error");
	} else {
		$ins_sect_stmt = $conn->prepare(
			"INSERT INTO SECTION(callnr, alt_title, crs_id,section,enrolled,capacity,instructor,flags,comments) " .
			"VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?)") or die ("ps section " . $conn->error);
	}
	$ins_slot_stmt = $conn->prepare(
		"INSERT INTO TIMESLOT(callnr, day, start, end, room) " .
		"VALUES(?, ?, TIME(?), TIME(?), ?)") or die ("ps timeslot " . $conn->error);

	$conn->autocommit(FALSE);
	$term;

	if (DENORM_TABLES) {
		$conn->query("ALTER TABLE NX_COURSE DISABLE KEYS");
	}
	
	if (IS_CLI) {
		$new_line = "\n";
	} else {
		$new_line = "<br/>\n";
	}

	$hashes = array();
	foreach ($files as $key => $name) {
		if ( !isset($term) ) {
			list($term, $subj) = sscanf($name, "%s.%s.html");
		}
		$t0 = microtime(true);
		$result = storeDataFrom($key, SOURCE_URL . $name);
		
		
		$actuals = array(0, 0);
		$skipped = array();
		foreach ($result[0] as $crs) {
			$crs->id = getCourseID($crs->subject, $crs->coursenr, $crs->coursevar, $conn);
			if (!$crs->id) {
				if (SCRAPE_COURSES) {
					$ins_crs_stmt->bind_param(
						"ssssd",
						$crs->subject, $crs->coursenr, $crs->coursevar, $crs->title, $crs->credits);
					if (!$ins_crs_stmt->execute()) {
						echo "$crs->subject $crs->coursenr$crs->coursevar could not be inserted ($conn->error)";
						continue;
					}
					$crs->id = $conn->insert_id;
					$actuals[0]++;
				} else {
					$skipped[] = array("number" => "$crs->subject.$crs->coursenr$crs->coursevar", "reason" => "NotFound");
					continue;
				}
			}
			$baseMask = 0;
			if (strpos($crs->title, "ST:") === 0) {
				$baseMask = Flags::ST;
			}
			foreach( $crs->sections as $sec ) {
				$flags = $baseMask;
				if ($sec->cancelled) $flags |= Flags::CANCELLED;
				if ($sec->online) $flags |= Flags::ONLINE;
				if (substr($sec->section, 0, 1) == 'H') $flags |= Flags::HONORS;
				
				/*
				*/
				if (DENORM_TABLES) {
					$course = "$crs->subject$crs->coursenr$crs->coursevar";
					$ins_sect_stmt->bind_param(
						"iissdsiisis",
						$sec->callnr, $crs->id, $course, $sec->alt_title, $crs->credits, $sec->section,
						$sec->enrolled, $sec->capacity,
						$sec->instructor, $flags, $sec->comments);
				} else {
					$ins_sect_stmt->bind_param(
						"isisiisis",
						$sec->callnr, $sec->alt_title, $crs->id, $sec->section,
						$sec->enrolled, $sec->capacity,
						$sec->instructor, $flags, $sec->comments);
				}
				if (!$ins_sect_stmt->execute()) {
					echo "$crs->subject.$crs->coursenr$crs->coursevar.$sec->section could not be inserted: $conn->error$new_line";
					continue;
				}
				
				$failed = false;
				if ($sec->slots && count($sec->slots) > 0) {
					foreach ( $sec->slots as $slot ) {
						$ins_slot_stmt->bind_param(
							"iisss",
							$sec->callnr, $slot->dayOfWeek,
							$slot->startTime, $slot->endTime,
							$slot->location);
						if (!$ins_slot_stmt->execute()) {
							$skipped[] = array("number" => "$crs->coursenr$crs->coursevar-$sec->section", "reason" => "$conn->error");
							echo "Failed to execute insert timeslot statement(course=$crs->coursenr$crs->coursevar-$sec->section; start=$slot->startTime; end=$slot->endTime):$new_line\t$conn->error$new_line";
							$failed = true;
							break;
						}
					}
				}
				if ($failed) $conn->rollback();
				else {
					$conn->commit();
					$actuals[1]++;
				}
			}
		}
		
		list($count_courses, $count_sections) = $actuals;
		$tf = microtime(true);
		$dt = $tf - $t0;
		if (IS_CLI) {
			echo "$key\t$count_courses\t$count_sections\t" . sprintf("%.3lfs", $dt) . "\n";
		} else {
			echo "<tr><td>$key</td><td>$count_courses</td><td>$count_sections</td><td>" . sprintf("%.3lfs", $dt) . "</td>";
			echo "<td><span onclick='showExpandErrors(this);'>" . count($skipped) . "</span>";
			if (count($skipped) > 0) {
				echo "<div style='display:none;'>";
				foreach ( $skipped as $ent ) {
					echo $ent['number'] . ": " . $ent['reason'] . "<br/>";
				}
				echo "</div>";
			}
			echo "</td></tr>\n";
		}
		
		$hashes[] = $result[2] . "$count_courses$count_sections";
		
		while (ob_get_level() > 0)
			ob_end_flush();
		flush();
		$totalTime += $dt;
		$totalSects += $count_sections;
		$totalCourses += $count_courses;
	}

	if (DENORM_TABLES) {
		$conn->query("ALTER TABLE NX_COURSE ENABLE KEYS");
	}

	if (IS_CLI) {
		echo "\n";
		echo "Total Courses:\t$totalCourses\n";
		echo "Total Sections:\t$totalSects\n";
		echo "Total Time:\t" . sprintf("%.3lfs", $totalTime) . "\n";
	} else {
		echo "</table><br/>";
		
		echo "Total Courses: $totalCourses<br/>\n";
		echo "Total Sections: $totalSects<br/>\n";
		echo "Total Time: " . sprintf("%.3lfs", $totalTime) . "<br/>\n";
	}

	$ins_crs_stmt->reset();
	$ins_sect_stmt->reset();
	$ins_slot_stmt->reset();

	$conn->close();

	return $hashes;
}

$last_update;
$schedule_hash;
function onshutdown() {
	global $last_update, $schedule_hash;
	
	$failed = 'FALSE';
	if (!isset($schedule_hash) || strlen($schedule_hash) != 32) {
		$schedule_hash = "0123456789abcxyz0123456789abcxyz";
		$failed = 'TRUE';
	}
	
	include "./dbconnect.php";
	$conn->query("UPDATE TERMINFO SET	last_run = NOW(), last_updated = '$last_update', updating = FALSE, incomplete = $failed, schedule_hash = '$schedule_hash' WHERE active = TRUE");
}

try {
	$doc = readPage(SOURCE_URL . "index_foot.html");
	$updStr = trim($doc->getElementsByTagName("center")->item(0)->textContent);
	if (!preg_match("/^Last updated from registrar's system ([^\\.]+)\\./", $updStr, $matches)) {
		die("Couldn't determine age of data");
	}
	$date = new DateTime($matches[1]);
	$last_update = date_format($date, "Y-m-d H:i:s");
	$year = date_format($date, "Y");
	
	$semester = $year . strtoupper(substr(TERM, 0, 1));
	$res = $conn->query("SELECT semester, disp_name, updating, last_updated, TIMESTAMPDIFF(MINUTE, last_run, NOW()) as diff FROM TERMINFO WHERE semester = '$semester'");
	if (!$res) {
		die("No active term found");
	}
	
	$row = $res->fetch_row();
	if ($row[2] == "1" && $row[4] < "120") {
		die("Update has already been initiated elsewhere...");
	}
	$lineEnd = IS_CLI ? "\n" : "<br/>";
	
	if ($row[2] == "1") {
		echo "Resetting... last update likely timed out.$lineEnd$lineEnd";
	}

	$conn->query("UPDATE TERMINFO SET active = FALSE WHERE active = TRUE");
	if ($row[1] != $semester) {
		echo "Not currently the active semester (" . $row[0] . "), setting to $semester...$lineEnd$lineEnd";
		$disp_semester = strtoupper(substr(TERM, 0, 1)) . strtolower(substr(TERM, 1)) . " $year";
		$conn->query("INSERT INTO TERMINFO(semester, disp_name, updating, active, incomplete) VALUES('$semester', '$disp_semester', TRUE, TRUE, TRUE)");
	} else {
		$disp_semester = $row[1];
	}
	register_shutdown_function("onshutdown");
	
	/*if ($row[3] == $last_update && $row[4] == "0") {
		echo "Data has not been changed since ($last_update).\n";
		return;
	}
	else*/ {
		echo "Deleting old data";
		if (DISABLE_CATALOG_SCRAPING)
			echo " (section information only)";
		echo "..$lineEnd$lineEnd";
		
		$conn->query("UPDATE TERMINFO SET updating = TRUE, incomplete = TRUE, active = TRUE WHERE semester = '$semester'");
		if (SCRAPE_COURSES) {
			$conn->query("TRUNCATE TABLE COURSE");
		}
		if (DENORM_TABLES) {
			$conn->query("TRUNCATE TABLE NX_COURSE");
		} else {
			$conn->query("TRUNCATE TABLE SECTION");
		}
		$conn->query("TRUNCATE TABLE TIMESLOT");
		
		echo "Extracting new data for $disp_semester..$lineEnd";
		if (!IS_CLI)
			echo "<a href='" . SOURCE_URL . "'>";
		echo SOURCE_URL;
		if (!IS_CLI)
			echo "</a>";
		echo "..$lineEnd";
		echo "last update: $last_update$lineEnd$lineEnd";
		$hashes = dumpCourseInfo();
		$schedule_hash = md5(implode("/", $hashes));
	}
} catch(Exception $e) {
	echo "Exception: $e";
}

//$doc = readPage("http://www.njit.edu/registrar/schedules/courses/fall/index_list.html");
//echo $doc->saveHTML();

//dumpCourseInfo_new();

if (!IS_CLI) {
?>
</body>
</html>
<?php
}
?>
