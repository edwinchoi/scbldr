<?php

/**
 * 
 */
class Course {
	var $id;
	var $name;
	var $title;
	var $subject;
	var $coursenr;
	var $coursevar;
	var $school;
	var $credits;
	var $sections = array();
	
	public function compare($that) {
		if (!($that instanceof Course)) throw new InvalidArgumentException();
		return strcmp($this->name, $that->name);
	}
	public function equals($that) {
		if (!($that instanceof Course)) throw new InvalidArgumentException();
		return $this->subject == $that->subject && $this->coursenr == $that->coursenr && $this->coursevar == $that->coursevar;
	}
};

/**
 * 
 */
class Section {
	var $alt_title;
	var $callnr;
	var $course;
	var $section;
	var $slots;
	var $enrolled;
	var $capacity;
	var $online;
	var $cancelled;
	var $instructor;
	var $comments;
	var $term;
};

/**
 * Represents an instance of a time slot.
 */
class TimeSlot {
	/**
	 * Constant representing any day.
	 * 
	 * @var int
	 */
	const UNDEFINED = 0;
	
	/**
	 * Constant representing Sunday.
	 * 
	 * @var int
	 */
	const SUNDAY = 1;

	/**
	 * Constant representing Monday.
	 * 
	 * @var int
	 */
	const MONDAY = 2;

	/**
	 * Constant representing Tuesday.
	 * 
	 * @var int
	 */
	const TUESDAY = 3;

	/**
	 * Constant representing Wednesday.
	 * 
	 * @var int
	 */
	const WEDNESDAY = 4;

	/**
	 * Constant representing Thursday.
	 * 
	 * @var int
	 */
	const THURSDAY = 5;

	/**
	 * Constant representing Friday.
	 * 
	 * @var int
	 */
	const FRIDAY = 6;

	/**
	 * Constant representing Saturday.
	 * 
	 * @var int
	 */
	const SATURDAY = 7;
	
	/**
	 * The day of the week (range is 1-7, not 0-6 like strptime).
	 * 
	 * @var int
	 */
	var $dayOfWeek;

	/**
	 * 
	 * @var string
	 */
	var $startTime;

	/**
	 * 
	 * @var string
	 */
	var $endTime;

	/**
	 * 
	 * @var string
	 */
	var $location;
	
	/**
	 * 
	 * @param $dayOfWeek
	 * @param $startTimeStr
	 * @param $endTimeStr
	 * 
	 * @throws InvalidArgumentException if $startTimeStr or $endTimeStr
	 *  cannot be parsed.
	 * @throws OutOfRangeException if $dayOfWeek is not in the range 1-7 or
	 *  the end time is less than the start time.
	 */
	function __construct($dayOfWeek, $startTime, $endTime, $location = false) {
		if ($dayOfWeek < self::UNDEFINED || $dayOfWeek > self::SATURDAY)
			throw new OutOfRangeException();
		$this->dayOfWeek = $dayOfWeek;
		$this->startTime = $startTime;
		$this->endTime = $endTime;
		$this->location = $location;
	}
	
	function __toString() {
		return json_encode(array(
			"dayOfWeek" => $this->dayOfWeek,
			"startTime" => $this->startTime,
			"endTime" => $this->endTime,
			"location" => $this->location));
	}
	
	function compare($that) {
		if (!($that instanceof TimeSlot)) return false;
		$cmp = $this->dayOfWeek - $that->dayOfWeek;
		if ($cmp == 0) {
			$cmp = strcmp($this->startTime, $that->startTime);
			if ($cmp == 0)
				$cmp = strcmp($this->endTime, $that->endTime);
		}
		return $cmp;
	}
};

function comparable_cmp($a, $b) {
	return $a->compare($b);
}

function sectionsConflict($sec1, $sec2) {
	foreach ($sec1->slots as $slot1) {
		foreach ($sec2->slots as $slot2) {
			// time format must be HH:MM[:SS] for lexicographical comparison to work
			if ($slot1->dayOfWeek == $slot2->dayOfWeek) {
				if ($slot1->startTime < $slot2->startTime) {
					if ($slot1->endTime >= $slot2->startTime) {
						return true;
					}
				} else { // slot1->startTime >= slot2->startTime
					if ($slot1->startTime <= $slot2->endTime) {
						return true;
					}
				}
			}
		}
	}
	return false;
}

/**
 * Defines the data held by every schedule and the basic operations
 * to be implemented by the concrete implementation.
 */
class Schedule {
	/**
	 * Name of the schedule.
	 *
	 * @var string
	 */
	var $name;

	/**
	 * The term for the schedule.
	 *
	 * @var string
	 */
	var $term;

	/**
	 *
	 * @var array of Section
	 */
	var $sections = array();

	/**
	 * Indicates the name of the conflicting course from the last addSection
	 * call.
	 * 
	 * @var string
	 */
	var $conflict;

	/**
	 * Indicates the type of conflict that occured ('time' or 'duplicate').
	 * 
	 * @var string
	 */
	var $conflictType;

	/**
	 * The conflicting time slot on time conflicts.
	 * 
	 * @var TimeSlot
	 */
	var $when;

	/**
	 * Attempts to add a section to the schedule. If adding the section
	 * would not result in a conflict, the course is added and the function
	 * returns true. Otherwise, it returns false.
	 *
	 * @param Section $section
	 * @return true if the section is added successfully.
	 */
	function addSection($section) {
		$cnfl = $this->testConflict($section);
		$this->conflict = $cnfl == null ? null : $cnfl->course->name;
		if ($cnfl != null) {
			return false;
		}
		$this->sections[] = $section;
		return true;
	}

	/**
	 *
	 * @param $callnr
	 * @return true on success
	 */
	function removeSection($callnr) {
		foreach($this->sections as $idx => $sect) {
			if ($sect->callnr == $callnr) {
				array_splice($this->sections, $idx, 1);
				return true;
			}
		}
		return false;
	}

	/**
	 * Determines whether or not adding the section to the schedule would
	 * result in a scheduling conflict.
	 *
	 * A conflict occurs if a time slot in $section overlaps any other
	 * time slot.
	 *
	 * @param Section $section
	 */
	protected function testConflict($section) {
		// check for duplicates first
		foreach ($this->sections as $sect) {
			if ($sect->callnr == $section->callnr || $sect->course->name == $section->course->name) {
				$this->conflictType = "duplicate";
				return $sect;
			}
		}
		foreach ($this->sections as $sect) {
			foreach ($sect->slots as $slot1) {
				foreach ($section->slots as $slot2) {
					// time format must be HH:MM[:SS] for lexicographical comparison to work
					if ($slot1->dayOfWeek == $slot2->dayOfWeek) {
						if ($slot1->startTime < $slot2->startTime) {
							if ($slot1->endTime >= $slot2->startTime) {
								$this->conflictType = "time";
								$this->when = $slot1;
								return $sect;
							}
						} else { // slot1->startTime >= slot2->startTime
							if ($slot1->startTime <= $slot2->endTime) {
								$this->conflictType = "time";
								$this->when = $slot1;
								return $sect;
							}
						}
					}
				}
			}
		}
		$this->conflictType = "";
		return null;
	}
	
	function getTotalCourses() {
		return count($this->sections);
	}
	
	function getTotalCredits() {
		$cnt = 0;
		foreach ($this->sections as $sect)
			$cnt += $sect->course->credits;
		return $cnt;
	}
};

?>
