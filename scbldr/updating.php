<?php
require_once "./terminfo.php";
?>
<!DOCTYPE html>
<html>
<head>
<title>Database updating...</title>
<script type="text/javascript">
setTimeout(function() {
	window.location.href = window.location.href;
}, 10 * 1000);
</script>
</head>
<body>
The database is currently being updated.
<br/>
Started at: <?= date("D, d M Y H:i", $last_run_timestamp) ?>
<br/>
Takes roughly a minute on average.
</body>
</html>
