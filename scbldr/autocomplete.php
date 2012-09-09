<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

include "./helpers.php";

allow_get_only();
require_once "./dbconnect.php";

if ( !$conn ) { set_status_and_exit(400, "Failed to connect to DB: " . $conn->connect_error); }

if (!isset($_GET['q']))
	set_status_and_exit(400);

$seq = -1;
if (isset($_GET['seq']))
	$seq = $_GET['seq'];

$q = $_GET['q']; // query
//$csv = isset($_GET['csv']) && $_GET['csv'] == 1; // comma-separated values?

$q = trim($q);

if (strncmp($q, '@', 1) === 0) {
	$q = trim(substr($q, 1));
	if (strlen($q) == 0) {
		echo "{}";
		return;
	}
	$cond = "title LIKE '%$q%'";
} else {
	$cond = "course LIKE '$q%'";
}

$query = <<<END
	SELECT	DISTINCT c.subject, CONCAT(c.number, c.suffix) AS name, c.title
	FROM	N_COURSE c, NX_COURSE x
	WHERE   c.crs_id = x.crs_id AND x.$cond
	LIMIT	0, 20
END;
$res = $conn->query($query);
if (!$res)
	set_status_and_exit(400, "MySQL Error: " . $conn->error);

$data = array();
$q = strtoupper($q);
while ($row = $res->fetch_row()) {
	$data[] = array(
		"title" => "$row[2]",
		"value" => "$row[0]$row[1]",
		"path" => "$row[0]/$row[1]"
	);
}

ob_start("ob_gzhandler");
header("Content-Type: application/json");
echo json_encode(array("seq" => $seq, "data" => &$data));

?>
