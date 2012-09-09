<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

require_once "./helpers.php";

function scheduleToImage($data, $ids, $range, $width, $height, $n, $lines = false) {
	$xfact = floatval($width) / count($data);
	$yfact = floatval($height) / $range;
	
	$image = imagecreatetruecolor($width, $height);
	imagealphablending($image, true);
	imagesavealpha($image, true);
	
	$bgcolors = array();

	/*
	// tetris colors
	$bgcolors[] = imagecolorallocate($image, 0, 255, 255); // cyan
	$bgcolors[] = imagecolorallocate($image, 0, 0, 255); // blue
	$bgcolors[] = imagecolorallocate($image, 255, 102, 0); //orange
	$bgcolors[] = imagecolorallocate($image, 255, 255, 0); // yellow
	$bgcolors[] = imagecolorallocate($image, 0, 255, 0); // green
	$bgcolors[] = imagecolorallocate($image, 102, 0, 255); // purple
	$bgcolors[] = imagecolorallocate($image, 255, 0, 0); // red
	*/

	$bgcolors[] = imagecolorallocate($image, 255, 170, 170); // red
	$bgcolors[] = imagecolorallocate($image, 181, 225, 152); // green
	$bgcolors[] = imagecolorallocate($image, 180, 205, 235); // blue
	$bgcolors[] = imagecolorallocate($image, 255, 237, 160); // yellow
	$bgcolors[] = imagecolorallocate($image, 195, 172, 218); // purple
	$bgcolors[] = imagecolorallocate($image, 245, 198, 95); // orange
	$bgcolors[] = imagecolorallocate($image, 225, 181, 165); // another red
	$bgcolors[] = imagecolorallocate($image, 190, 235, 176); // another green
	$bgcolors[] = imagecolorallocate($image, 176, 191, 235); // another blue

	$shapebgcolor = imagecolorallocate($image, 255, 170, 170);
	$shapelncolor = imagecolorallocate($image, 151, 151, 151);
	$bgcolor = imagecolorallocatealpha($image, 255, 255, 255, 127);
	$dashcolor = imagecolorallocate($image, 170, 170, 170);
	
	imagefill($image, 0, 0, $bgcolor);
	
	/*if ($lines !== false && is_array($lines))*/ {
		imagesetstyle($image, array($bgcolor, $bgcolor, $dashcolor, $dashcolor));
		
		imageline($image, 0, $yfact * 5, $width, $yfact * 5, IMG_COLOR_STYLED);
		imageline($image, 0, $yfact * 9, $width, $yfact * 9, IMG_COLOR_STYLED);
	}
	
	foreach ($data as $day => &$times) {
		foreach ($times as $off => $t) {
			if (count($t) == 0) continue;
			$x0 = $day * $xfact;
			$y0 = $t[0] * $yfact;
			$wd = $xfact;
			$ht = ($t[1] - $t[0]) * $yfact;
			
			$fillColor = false;
			if (count($ids[$day]) > 0) {
				$index = $ids[$day][$off] % count($bgcolors);
				$fillColor = $bgcolors[$n - $index];
			}
			if (!$fillColor)
				$fillColor = $shapebgcolor;

			imagefilledrectangle($image, $x0 + 1, $y0 + 1, $x0 + $wd - 2, $y0 + $ht - 2, $fillColor);
			imagerectangle($image, $x0, $y0, $x0 + $wd - 1, $y0 + $ht - 1, $shapelncolor);
			$fillColor = false;
		}
	}
	
	return $image;
}

$height = get_rqvar($_GET, "h", "integer");
$width = get_rqvar($_GET, "w", "integer");
$data = get_rqvar($_GET, "data", "string");

if ($height < 1 || $width < 1) {
	set_status_and_exit(400, "height and width must be positive");
}

$allowed_img_types = array("png", "jpg"/*, "svg", "vml"*/);

$imgtype = "png";
if (isset($_GET['img'])) {
	$imgtype = strtolower($_GET['img']);
	if (!in_array($imgtype, $allowed_img_types))
		set_status_and_exit(400, "unsupported image format: $imgtype");
}

$parts = explode("/", $data);
if (isset($_GET['N'])) {
	$upper = $_GET['N'];
} else {
	$upper = -1;
}
if (count($parts) > 1) {
	$idents = explode(";", $parts[1]);
	foreach ($idents as &$ids) {
		$ids = explode(",", $ids);
		for ($i = 0; $i < count($ids); $i++) {
			$ids[$i] = intval($ids[$i]);
			if ($ids[$i] > $upper)
				$upper = $ids[$i];
		}
	}
}
$data = explode(";", $parts[0]);

$lines = false;
if (isset($_GET['lns'])) {
	$lines = array();
	foreach ($_GET['lns'] as $t) {
		$lines[] = parseTime($t);
	}
}

$rlow = parseTime("08:00:00") / (1000 * 60 * 60);
$rhigh = parseTime("22:00:00") / (1000 * 60 * 60);

$pts = array_fill(0, count($data), array());

foreach ($data as $day => $times) {
	$times = trim($times);
	if (count($times) == 0) continue;
	$evts = explode(",", $times);
	foreach ($evts as $str) {
		if (strlen($str) != 8) continue;
		list($low, $high) = sscanf($str, '%04x%04x');
		//echo "$low $high\n";
		$pts[$day][] = array(floatval($low)/60 - $rlow, floatval($high)/60 - $rlow);
	}
}

$range = ($rhigh - $rlow);

if ($imgtype == "png" || $imgtype == "jpg") {
	$img = scheduleToImage($pts, $idents, $range, $width, $height, $upper, $lines);
	header("Cache-Control: public, maxage=86400");
	header("Expires: Fri, 30 Sep 23:59:00 GMT");
	header("Last-Modified: Thu, 01 Jan 00:00:00 GMT");
	header("Content-Type: image/$imgtype");
	imagepng($img);
	imagedestroy($img);
}

?>
