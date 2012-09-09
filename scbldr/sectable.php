<table class='sec-table' id='tbl_<?=$course?>' data-course='<?=$course=?>' credits='<?=$credits?>'>
<?php
foreach ($sections as $sec) {
	?><tr id='<?=$sec->callnr?>' data-title='<?=$sec->alt_title or $title?>'>
	<td class='sec-input'><input id='<?=$course?>_<?=$sec->section?>' type='radio' name='<?=$course?>' value='<?=$sec->section?>'/></td>
	<?php
}
?>
