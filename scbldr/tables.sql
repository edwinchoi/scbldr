/*
 * File: $Id$
 * Author: $Author$
 * 
 * The database model.
 */

/*
used to specify the current term and indicate some basic info about the
most recent data collection.
*/
CREATE TABLE IF NOT EXISTS TERMINFO(
	semester CHAR(5) PRIMARY KEY,
	disp_name VARCHAR(15),
	last_updated TIMESTAMP DEFAULT 0,
	last_run TIMESTAMP DEFAULT 0,
	updating BOOL,
	active BOOL,
	incomplete BOOL,
	schedule_hash CHAR(32),
	failed_courses INT DEFAULT 0,
	failed_sections INT DEFAULT 0
) engine=MyISAM;

/*
the course name (i.e., CS490) is <courseid><coursenr><coursevar>
*/
CREATE TABLE IF NOT EXISTS COURSE(
	crs_id INT PRIMARY KEY auto_increment,
	subject CHAR(4) NOT NULL, /*REFERENCES SUBJECT(abbr),*/
	number CHAR(3) NOT NULL,
	suffix CHAR(1) DEFAULT '',
	title VARCHAR(127),
	description TEXT,
	credits FLOAT,

	INDEX(subject, number, suffix)
) engine=MyISAM;

/*
first 4 letters of term is the year, the last two is one of WN, SP, SU, FL

ideally, instructor would reference a userid in the User table
*/
CREATE TABLE IF NOT EXISTS SECTION(
	callnr MEDIUMINT UNSIGNED PRIMARY KEY,
	crs_id SMALLINT UNSIGNED REFERENCES N_COURSE(crs_id),
	section CHAR(3),
	alt_title VARCHAR(127),
	enrolled smallint,
	capacity smallint,
	instructor VARCHAR(31),
	cancelled DATE,
	flags INT,
	sdate DATE,
	edate DATE,
	comments VARCHAR(255)
) engine=MyISAM;

CREATE TABLE IF NOT EXISTS TIMESLOT(
	callnr MEDIUMINT UNSIGNED REFERENCES SECTION(callnr),
	day TINYINT,
	start TIME,
	end TIME,
	room VARCHAR(15),
	
	INDEX(callnr)
) engine=MyISAM;

/*
denormalized table... eliminating the join (COURSE |x| SECTION) to speed up queries
also combines the attributes: subject, number, suffix
*/
CREATE TABLE IF NOT EXISTS NX_COURSE(
	callnr MEDIUMINT UNSIGNED PRIMARY KEY,

	crs_id SMALLINT UNSIGNED REFERENCES COURSE(crs_id),

	course CHAR(8) NOT NULL,
	title VARCHAR(127),
	credits FLOAT,

	section CHAR(3),
	enrolled SMALLINT,
	capacity SMALLINT,
	instructor VARCHAR(31),
	cancelled DATE,
	flags INT,
	sdate DATE,
	edate DATE,
	comments VARCHAR(255),

	INDEX(course),
	INDEX(title)
) engine=MyISAM;
