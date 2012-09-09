<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

function set_status_and_exit($sc, $msg = null) {
	$str = is_string($sc) ? $sc : get_status_string($sc);
	header("HTTP/1.1 $str");
	header("Status: $str");
	
	if ($msg != null)
		echo $msg;
	exit;
}

function get_status_string($sc) {
	switch($sc) {
	case 200: return "200 OK";
	case 304: return "304 Not Modified";
	case 400: return "400 Bad Request";
	case 401: return "401 Unauthorized";
	case 403: return "403 Forbidden";
	case 404: return "404 Not Found";
	case 405: return "405 Method Not Allowed";
	case 409: return "409 Conflict";
	case 500: return "500 Internal Server Error";
	case 501: return "501 Not Implemented";
	case 502: return "502 Bad Gateway";
	default: return "500 Internal Server Error";
	}
}

function allow_get_only() {
	if ($_SERVER['REQUEST_METHOD'] != "GET")
		set_status_and_exit(405, "Only GET allowed");
}
function allow_post_only() {
	if ($_SERVER['REQUEST_METHOD'] != "POST")
		set_status_and_exit(405, "Only POST allowed");
}
function allow_get_or_post_only() {
	if ($_SERVER['REQUEST_METHOD'] != "POST" && $_SERVER['REQUEST_METHOD'] != "GET")
		set_status_and_exit(405, "Only GET/POST allowed");
}

function parseTime($s) {
	list($hh, $mm, $ss) = sscanf($s, "%02d:%02d:%02d");
	return ($ss + ($mm + ($hh) * 60) * 60) * 1000;
}

function get_rqvar(&$env, $name, $type = false) {
	if (!isset($env[$name])) {
		set_status_and_exit(400, "$name is a required variable");
	}
	if ($type !== false) {
		if ($type != "array" && is_array($env[$name]))
			set_status_and_exit(400, "$name is formatted improperly.");
		if ($type == "array" && !is_array($env[$name]))
			return array($env[$name]);
		
		if ($type == "integer")
			return intval($env[$name]);
		if ($type == "float")
			return floatval($env[$name]);
		if ($type == "time")
			return parseTime($env[$name]);
	}
	return $env[$name];
}

?>
