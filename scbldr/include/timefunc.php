<?php

function parsetime($t) {
	list ($hrs, $min) = sscanf($t, "%02d:%02d");
	return ($min + $hrs * 60) * 60 * 1000;
}

function timetostr($t, $hr24fmt = true, $hronly = false) {
	$t /= 60 * 1000;
	$hrs = floor($t / 60);
	$min = $t % 60;
	if ($hr24fmt) {
		return sprintf("%02d:%02d:00", $hrs, $min);
	} else {
		$ampm = $hrs < 12 ? "am":"pm";
		$hrs %= 12;
		if ($hrs == 0)
			$hrs = 12;
		if ($hronly) {
			return sprintf("%d %s", $hrs, $ampm);
		} else {
			return sprintf("%d:%02d %s", $hrs, $min, $ampm);
		}
	}
}

?>
