<?php

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);

session_start();

require_once "./terminfo.php";


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

function getvar($name, $default) {
	return isset($_REQUEST[$name]) && strlen($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}
$daynames = array("", "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

$naked = getvar("naked", "0") == "1";
$is_mobile = strpos($_SERVER['HTTP_USER_AGENT'],"iPhone") !== false;

if ( $is_mobile ) {
	$days = date("w") + 1;
} else {
	$days = "2,3,4,5,6,7";
}
$days = explode(",", getvar("days", $days));
for ( $i = 0; $i < count( $days ); $i++ ) {
	$days[$i] = intval($days[$i]);
}
$start = parsetime(getvar("start", "08:00"));
$end = parsetime(getvar("end", "22:00"));
$step = parsetime(getvar("step", "00:30"));

$wpct = floor(100.0 / count($days));

if (!isset($owner_info) || count($owner_info) == 0) {
	$owner_info = array("owner_name" => "Nobody", "term" => "Never");
}

if (!$naked) { ?>
<!DOCTYPE html>
<html>
<head>
<title>Your Schedule</title>
<meta name="title" content="Schedule Builder - <?= $owner_info["owner_name"] ?>'s Schedule" /> 
<meta name="viewport" content="width=320; initial-scale=1.0; user-scalable=no;"/>

<link rel="stylesheet" href="css/scheduleGrid.css" type="text/css" />
<link type="text/css" rel="stylesheet" href="../css/smoothness/jquery-ui-1.8.4.custom.css" />

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript">
function parseTime2(str) {
	var match = /^(\d\d?):(\d\d)(?::(\d\d)(?:\.(\d\d\d))?)?(?:\s*(am|pm))?$/i.exec(str);
	if (match == null)
		throw new Error("Time format is invalid. Expected: hh:mm[:ss[.mmm]] [am/pm]. Received: " + str);
	var h = parseInt(match[1], 10);
	var m = parseInt(match[2], 10);
	var s = parseInt(match[3] || 0, 10);
	var ms = parseInt(match[4] || 0, 10);
	var ampm = match[5];
	
	if (h > 23 || (ampm && (h == 0 || h > 12)) || (m > 59) || (s > 59))
		throw new RangeError("Not a valid time");
	
	if (ampm) {
		ampm = ampm.toLowerCase();
		if (ampm == "pm" && h != 12) {
			h += 12;
		}
	}
	return ms + 1000 * (s + 60 *(m + 60 * h));
}

function timeToStr(t) {
	t /= 1000;
	var h = Math.floor(t / (60 * 60));
	var m = Math.floor((t / 60) % 60);
	return ((h % 12) == 0 ? "12" : (h % 12)) + ":" + (m < 10 ? "0" : "") + m + (h < 12 ? "am" : "pm");
}

var PRV_root;
var PRV_cont;
var PRV_gtbl;
var PRV_eCont;
var PRV_vCont;
var PRV_events = [];
var PRV_stackLayout = false;

(function(undefined) {
var COLORS = [
	"ffaaaa",
	"b5e198",
	"b4cdeb",
	"ffeda0",
	"c3acda",
	"f5c65f",
	"e1b5a5",
	"d7fac6",
	"b0bfeb"
];

var conf = {
	start: <?= $start ?>,
	end: <?= $end ?>,
	step: <?= $step ?>,
	days: <?= json_encode($days) ?>
};

/**
 * The priority queue used by A* search. Underlying data structure is a
 * min-order binary heap.
 * 
 * @param n the max size of the queue.
 * @return
 */
function Heap(n, compare) {
	this._heap = new Array(n + 1);
	this._heapSize = 0;
	this.compare = compare;
};
Heap.prototype = {
	add: function(item) {
		if ((this._heapSize + 1) == this._heap.length)
			return false;
		this._setAt(++this._heapSize, item);
		this._pullUp(this._heapSize);
		return true;
	},
	peek: function() {
		if (this.isEmpty())
			return null;
		return this._getAt(1);
	},
	poll: function() {
		if (this.isEmpty())
			throw RangeError("underflow");
		var min = this._getAt(1);
		this._setAt(1, this._getAt(this._heapSize--));
		this._pushDown(1);
		return min.value;
	},
	isEmpty: function() {
		return this._heapSize == 0;
	},
	contains: function(value) {
		for (var i = 0; i < this._heapSize; i++)
			if (this.compare(this._heap[i], value) === 0)
				return true;
		return false;
	},
	remove: function(value) {
		for (var i = 0; i < this._heapSize; i++) {
			if (this.compare(this._heap[i].value, value) === 0) {
				this._setAt(i + 1, this._getAt(this._heapSize--));
				this._pushDown(i + 1);
				return true;
			}
		}
		return false;
	},
	toArray: function() {
		return this._heap.slice(0, this._heapSize);
	},
	_pullUp: function(hole) {
		var parent = hole;
		while ((parent >>= 1) != 0 && this.compare(this._getAt(hole), this._getAt(parent)) < 0) {
			this._swap(parent, hole);
			hole = parent;
		}
	},
	_pushDown: function(hole) {
		while ((hole << 1) <= this._heapSize) {
			var child = hole << 1;
			if (child < this._heapSize && this.compare(this._getAt(child + 1), this._getAt(child)))
				child++;
			if (compare(this._getAt(child), this._getAt(hole)) >= 0)
				break;
			this._swap(hole, child);
			hole = child;
		}
	},
	_setAt: function(idx, obj) { this._heap[idx - 1] = obj; },
	_getAt: function(idx) { return this._heap[idx - 1]; },
	_swap: function(first, second) {
		var obj = this._getAt(first);
		this._setAt(first, this._getAt(second));
		this._setAt(second, obj);
	}
};

function Range(start, end) {
	this.start = start;
	this.end = end;
}
Range.prototype = {
	overlaps: function(that) {
		return this.start >= that.start && this.start < that.end || that.start >= this.start && that.start < this.end;
	},
	contains: function(time) {
		return time >= this.start && time < this.end;
	}
};

function PRV_onWindowResize() {
	if (!PRV_cont)
		return;
	var gt = $(PRV_cont);
	var wd =
		$(PRV_root).width() -
		$(".sv-lcol").outerWidth(true) - (gt.outerWidth(true) - gt.width());
	//console.info(wd, TGrid.getDayCount());
	gt.width(wd - TGrid.getDayCount() - 1);

	var ec = $(PRV_eCont);
	var tb = $(PRV_gtbl.tBodies[0]);
	
	ec.css("top", tb.position().top)
	ec.css("left", tb.position().left);
	ec.height(tb.outerHeight()).width(tb.outerWidth() - 1);
	var ecw = ec.width() / TGrid.getDayCount();
	ec.find(".sv-events-day").width(ecw - 1);
}

function PRV_onMouseEnter(e) {
	var evt = $(this);
	evt.parent().find(".sv-event[name=" + evt.attr("name") + "]").addClass("sv-event-highlight");
}
function PRV_onMouseLeave(e) {
	var evt = $(this);
	evt.parent().find(".sv-event[name=" + evt.attr("name") + "]").removeClass("sv-event-highlight");
}

function PRV_renderEvent(o) {
	var ev = $("<div class='sv-event'>");
	ev.attr("key", o.key).attr("type", o.type);
	//ev.draggable({ grid: [this._dim.width, this._dim.height] });
	ev.addClass("ui-corner-all " + (o.cssClass || ""));
	if (o.bgColor)
		ev.css("background-color", o.bgColor);
	var content = $("<div class='sv-event-content'>");
	content.append(o.title || o.name);
	if (o.loc)
		content.append("<span style='display:block' class='sv-event-location'>" + o.loc + "</span>");
	ev.append(content);
	if (o.start && o.end) {
		ev.data("range", new Range(o.start, o.end));
	}

	return ev;
}

function _bits() { return bits_per_integer }
function _contains(a, i) { return (a & (1 << i)) != 0; }
function _get(a, i) { return (a >> i) & 1; }
function _set(a, i) { return a | (1 << i); }
function _clr(a, i) { return a & ~(1 << i); }
function _and(a, b) { return a & b; }
function _or(a, b) { return a | b; }
function _iszero(a) { return a == 0; }
function _mkset(n) { return ((1 << n) - 1); } // overflows when n is _bits
function _notin(a, b) { return a & ~b; }
function _count(x) {
    var v = x - ((x >>> 1) & 0x55555555);
    v = (v & 0x33333333) + ((v >>> 2) & 0x33333333);
    return ((v + (v >>> 4) & 0x0F0F0F0F) * 0x01010101) >>> 24;
}
function _each(a, callback, ctx) {
	for (var i = 0; a != 0 && i < 32; i++) {
		if (_contains(a, i)) {
			_clr(a, i);
			if (callback.call(ctx, i) === false)
				break;
		}
	}
}
function _list(a) {
	var ret = [];
	_each(a, ret.push, ret);
	return ret;
}
function _toset(list) {
	var a = 0;
	$.each(list, function() { a = _set(a, this); });
	return a;
}

function PRV_findConflicts(ev, inList) {
	var res = [];
	var r = $(ev).data("range");
	$.each(inList, function(i) {
		if (ev !== this && r.overlaps($(this).data("range")))
			res.push(i);
	});
	return res;
}
function PRV_getXRange(day) {
	var th = $(PRV_gtbl.tHead.rows[0].cells).filter(".sv-day-" + day);
	return { pos: th.position().left - $(PRV_gtbl.tHead.rows[0].cells[0]).position().left + 2,
			 len: th.width() - 3 };
}
function PRV_getYRange(range) {
	var upper = TGrid.getEndTime();
	var lower = TGrid.getStartTime();
	var step = <?= $step ?> / (1000 * 60);

	var td = $(PRV_gtbl.tBodies[0].rows[0].cells[0]);
	return { pos: (range.start - lower) * PRV_eCont.height() / (upper - lower),
			 len: (range.end - range.start) * td.innerHeight() * step / (upper - lower) - 1 };
}

function PRV_doLayout(day) {
	var evts = PRV_events[day];
	var dset = [], desc = [], conf = [];
	for (var i = 0; i < evts.length; i++) {
		dset[i] = i; desc[i] = 0; conf[i] = 0;
		conf[i] = _toset(PRV_findConflicts(evts[i], evts));
	}
	
	var visited = 0;
	var xr = PRV_getXRange(day);
	for (var i = 0; i < evts.length; i++) {
		var ev = evts[i];
		var yr = PRV_getYRange(ev.data("range"));
		ev.css("top", yr.pos).height(yr.len - (ev.outerHeight() - ev.height()));
		if (ev.hasClass("sv-event-locked") || !conf[i]) {
			ev.css("left", xr.pos);
			ev.width(xr.len - (ev.outerWidth() - ev.width()));
			ev.show();
			visited = _set(visited, i);
		}
	}
	var stk = [];
	for (var x = 0; x < evts.length; x++) {
		if (_contains(visited, x))
			continue;
		visited = _set(visited, x);
		
		stk.push(x);
		var gconf = 0;
		while (stk.length > 0) {
			var i = stk.pop();
			gconf |= (1 << i);
			stk = stk.concat(_list(_notin(conf[i], _or(gconf, visited))));
		}
		visited = _or(visited, gconf);
		
		var groups = _list(gconf);
		groups.sort(function(a, b) {
			return -(_count(conf[a]) - _count(conf[b]));
		});
		//console.info(groups);
		
		var cols = [];
		for (var i = 0; i < groups.length; i++) {
			var u = groups[i];
			var j;
			for (j = 0; j < cols.length; j++) {
				if ((conf[u] & cols[j]) == 0) {
					cols[j] = _set(cols[j], u);
					cols.sort(function(a, b) {
						return -(_count(a) - _count(b));
					});
					break;
				}
			}
			if (j == cols.length) {
				cols.push(1 << u);
			}
		}
		// smaller cols have wider spans
		cols.sort(function(a, b) {
			return _count(a) - _count(b);
		});
		
		if (PRV_stackLayout) {
			var xlen = xr.len / (2 * Math.max(4, cols.length));

			for (var i = 0; i < cols.length; i++) {
				var x = xr.len - xlen * i;
				_each(cols[i], function(j) {
					var ev = evts[j];
					ev.css("left", xr.pos);
					ev.width(x - (ev.outerWidth() - ev.width()));
					ev.show();
				}, evts);
			}
		} else {
			var xlen = xr.len / cols.length;
			for (var i = 0; i < cols.length; i++) {
				_each(cols[i], function(j) {
					var ev = this[j];
					ev.css("left", xr.pos + xlen * i);
					ev.width(xr - (ev.outerWidth() - ev.width()));
					ev.show();
				}, evts);
			}
		}
		//console.info(cols);
	}
}

TGrid = (function() {
	$.each(conf.days, function() {
		PRV_events[this] = [];
	});
	var map = {};
	var cidx = 0;
	return {
		getTimeRange: function(evt) {
			return evt.data("range");
		},
		getStartTime: function() {
			return conf.start;
		},
		getEndTime: function() {
			return conf.end;
		},
		getTimeStep: function() {
			return conf.step;
		},
		getValidDays: function() {
			return conf.days.slice(0);
		},
		getDayCount: function() {
			return conf.days.length;
		},
		addEvent: function(o) {
			if (map[o.key])
				throw new Error("duplicate key '" + o.key + "'");
			var obj = $.extend({}, o);
			var self = this;
			var res = [];
			var bgcolor = "333333";
			var fgcolor = "f2f2f2";
			if (o.type == "s") {
				bgcolor = COLORS[(cidx++) % COLORS.length];
				fgcolor = "111111";
			}

			if (!o.slots || !o.slots.length) {
				var ev = PRV_renderEvent(o);
				PRV_vCont.append(ev);
				ev.css("width", PRV_getXRange(conf.days[0]).len - (ev.outerWidth() - ev.width()));
				ev.css("background-color", "#" + bgcolor);
				ev.css("color", "#" + fgcolor);
				return ev;
			} else {
				$.each(o.slots, function() {
					if (!PRV_events[this.day])
						return;//throw new RangeError("Not defined for day=" + this.day);
					var dayevs = PRV_events[this.day];
					var data = $.extend({}, obj, this);
					var ins_pos = -1;
					$.each(dayevs, function(i) {
						var r = self.getTimeRange(this);
						if (r.start >= data && ins_pos == -1)
							ins_pos = i;
						if (r.overlaps(data)) {
							if (o.locked && !$(this).hasClass("sv-event-locked"))
								;//throw new Error();
						}
					});
					var ev = PRV_renderEvent(data);
					dayevs.splice(ins_pos, 0, ev);
					PRV_eCont.append(ev);
					ev.bind("mouseenter", PRV_onMouseEnter).bind("mouseleave", PRV_onMouseLeave);
					ev.css("background-color", "#" + bgcolor);
					ev.css("color", "#" + fgcolor);
					res.push(ev);
				});
			}

			map[o.key] = true;
			return res;
		},
		invalidate: function() {
			$.each(conf.days, function() {
				if (PRV_events[this].length)
					PRV_doLayout(this);
			});
			PRV_vCont.children().each(function() {
				var ev = $(this);
				ev.css("width", PRV_getXRange(conf.days[0]).len - (ev.outerWidth() - ev.width()));
			});
		}
	};
})();

$(document).ready(function() {
	PRV_root = $("#timeGrid");
	PRV_cont = PRV_root.find(".sv-grid");
	PRV_gtbl = PRV_cont.find(".sv-grid-table")[0];
	if (!PRV_gtbl)
		return setTimeout(arguments.callee, 100);
	PRV_eCont = PRV_cont.find(".sv-events-container");
	PRV_vCont = PRV_cont.find(".sv-virtual-events");
	PRV_root.width(Math.max($(window).width(), 640));
	$(".sv-col").width(<?= $wpct ?> * $(PRV_gtbl).outerWidth());
	window.onload = function() {
		$(window).resize(function() {
			PRV_root.width(Math.max($(document.body).width() - 16, 640));
			PRV_onWindowResize();
			TGrid.invalidate();
		});
		PRV_root = $(PRV_root[0]);
		PRV_cont = $(PRV_cont[0]);
		PRV_gtbl = $(PRV_gtbl)[0];
		$(window).resize();
		TGrid.invalidate();
	};
});

<?php if ($result && count($result) > 0) { ?>
$(document).ready(function() {
	var data = <?= json_encode($result) ?>;
	$.each(data, function() {
		this.key = this.name;
		$.each(this.slots, function() {
			this.start = parseTime2(this.start);
			this.end = parseTime2(this.end);
		});
		this.locked = this.type == "c";
		TGrid.addEvent(this);
	});
	TGrid.invalidate();
});
<?php } ?>

})();

</script>
<?php } ?>
<style type="text/css">
body { font-size: .668em; font-family:helvetica, arial; margin:0;padding:0; }
.sv-events-day { float:left;margin:0;padding:0 0 0 1px; height:100%;width: <?= $wpct ?>%; }
.sv-events-container { margin-left:0;padding:0; }
<?php if ( $is_mobile ) { ?>
.sv-view { width: inherit !important; }
td.even span.time-label { font-size: 0.7em; }
<?php } ?>
</style>
<style type="text/css" media="print">
#altlink { display: none; }
</style>
<?php if (!$naked) { ?>
</head>
<body>
<?php } ?>
<div id="#gridHeader" style="padding:4px;">
<span style="padding-left:52px;font-weight:700;font-size:1.5em;">
	<?= $owner_info["owner_name"] ?>'s Schedule - <?= $owner_info["term"] ?>
</span>
<?php if (!(isset($_REQUEST['fbuid']) || isset($_SESSION['fbuid']))) { ?>
<fb:login-button></fb:login-button>
<div id="fb-root"></div>
<script src="http://connect.facebook.net/en_US/all.js"></script>
<script>
FB.init({appId: '<?= FACEBOOK_APP_ID ?>', status: true, cookie: true, xfbml: true});
FB.Event.subscribe('auth.sessionChange', function(response) {
    if (response.session) {
    	window.location.reload();
		// A user has logged in, and a new cookie has been saved
    }
});
</script>
<?php } ?>
</div>
<div id="timeGrid" class='sv-view'>
	<div class='sv-lcol'>
		<table class='sv-lcol-table'>
			<colgroup>
				<col class='sv-row-hdr' />
			</colgroup>
			<thead>
				<tr><th class='sv-col-hdr'></th></tr>
			</thead>
			<tbody>
			<?php
			for ( $t = $start; $t < $end; $t += $step * 2 ) {
				?>
				<tr class='even'><td><span class="time-label"><?= timetostr($t, false, $is_mobile) ?></span></td></tr>
				<tr class='odd'><td></td></tr>
				<?php
			}
			?>
			</tbody>
		</table>
	</div>
	<div class='sv-grid'>
		<table class='sv-grid-table'>
			<colgroup>
				<?php
				foreach ( $days as $day ) {
					echo "<col day='$day' class='sv-body-tbl-col sv-col' />";
				}
				?>
			</colgroup>
			<thead>
				<tr class='sv-grid-hdr-row'>
				<?php
				foreach ( $days as $day ) {
				?>
					<th class='sv-col-hdr sv-grid-hdr sv-col sv-day-<?= $day ?>'><?php echo $daynames[$day] ?></th>
				<?php
				}
				?>
				</tr>
			</thead>
			<caption></caption>
			<tbody tabindex="0">
				<?php
				$rowtypes = array("even", "odd");
				for ( $t = $start; $t < $end; $t += $step ) {
					$rowtype = array_shift($rowtypes);
					?>
					<tr class='sv-grid-row <?= $rowtype ?>'>
						<?php
						foreach ( $days as $day ) {
							?>
							<td class='sv-grid-col sv-grid-cell'></td>
							<?php
						}
						?>
					</tr>
					<?php
					$rowtypes[] = $rowtype;
				}
				?>
			</tbody>
		</table>
		<div class="sv-events-container">
		</div>
		<div class="sv-virtual-events">
		<strong>Online and other non-meeting courses</strong>
		</div>
	</div>
</div>
<?php if (!$naked) { ?>
</body>
</html>
<?php } ?>
