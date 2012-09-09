<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

require_once "./dbconnect.php";

$current_term_label = "";
$current_term_value = "";
$last_update_timestamp = 0;
$last_run_timestamp = 0;
$incomplete_data = 0;
$has_active_term = false;

if ( $conn ) {
	$res = $conn->query(<<<_
		SELECT semester, disp_name, UNIX_TIMESTAMP(last_updated), UNIX_TIMESTAMP(last_run), updating, incomplete
		  FROM TERMINFO
		 WHERE active = 1
_
	);
	if ( $row = $res->fetch_row() ) {
		$current_term_value = $row[0];
		$current_term_label = $row[1];
		$last_update_timestamp = $row[2];
		$last_run_timestamp = $row[3];
		$updating_data = $row[4];
		$incomplete_data = $row[5];
		$has_active_term = true;
	}
}

?>
