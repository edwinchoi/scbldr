<?php

class PagedResultSet {
	static $PAGE_SIZE = 30;
	
	/**
	 * The current page being viewed.
	 * 
	 * @var int
	 */
	var $viewPage;
	
	/**
	 * The total number of pages in the result set.
	 * 
	 * @var int
	 */
	var $totalPages;
	
	/**
	 * The total number of records in the result set.
	 * 
	 * @var int
	 */
	var $totalRecords;
	
	/**
	 * The actual results of the query (for the given page only).
	 * 
	 * @var unknown_type
	 */
	var $results;
};

?>
