<?php
/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

require_once "./include/timefunc.php";
require_once "./terminfo.php";

function getvar($name, $default) {
	return isset($_REQUEST[$name]) && strlen($_REQUEST[$name]) ? $_REQUEST[$name] : $default;
}
$daynames = array("", "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday");

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

header("Cache-Control: no-cache");


if (isset($_GET['debug']) && $_GET['debug'] == '1') {
	define("DEBUG", true);
} else {
	define("DEBUG", true);
}

?>
<!DOCTYPE html>
<html xmlns:svg="http://www.w3.org/2000/svg" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
	<title>Print Schedule</title>

	<link type="text/css" rel="stylesheet" href="css/scheduleGrid.css" />
	<link type="text/css" rel="stylesheet" media="print" href="css/print.css" />
	
	<style type="text/css">
body { background-color: #fff; font-family: arial, sans-serif; font-size: 10px; }
	</style>

	<!--[if !mso]>
	<style>
v\:* {behavior:url(#default#VML);}
.shape {behavior:url(#default#VML);}
	</style>
	<![endif]--> 

	<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
	<script type="text/javascript" src="js/compat.js"></script>
	<script type="text/javascript" src="lib/raphael.js"></script>
	<script>
	<?php if (DEBUG) { ?>
function timeToStr(t) {
	t /= 1000;
	var h = Math.floor(t / (60 * 60));
	var m = Math.floor((t / 60) % 60);
	return ((h % 12) == 0 ? "12" : (h % 12)) + ":" + (m < 10 ? "0" : "") + m + " " + (h < 12 ? "am" : "pm");
}

if ($.browser.msie && false) {
	document.namespaces.add("v", "urn:schemas-microsoft-com:vml");
	
	Elem = function(tag, attrs) {
		attrs = attrs || {};
		var tagmap = {
			svg: "div",
			g: "div",
			rect: "roundrect",
			line: "line"
		};
		if (tag in tagmap)
			tag = tagmap[tag];
		var el = ($.type(tag) == "string" && document.createElementNS("urn:schemas-microsoft-com:vml", tag)) || tag;
		this.el = el;
		this.attr(attrs);
	};

	Elem.prototype = {
		append: function(el) {
			this.el.appendChild(el.el || el);
			return this;
		},
		attr: function(k,v) {
			if (arguments.length == 1) {
				attrs = {};
				attrs[k] = v;
			} else {
				if ($.type(k) == "string")
					return this.el.getAttribute(k);
				attrs = k;
			}
			var style = "";
			var atr = {style:""};
			if ("y" in attrs) atr.style += "top:" + attrs.y + ";";
			if ("x" in attrs) atr.style += "left:" + attrs.x + ";";
			if ("width" in attrs) atr.style += "width:" +  attrs.width + ";";
			if ("height" in attrs) atr.style += "height:" + attrs.height + ";";
			if ("x1" in attrs) {
				atrs.from = attrs.x1 + "," + attrs.y1;
				atrs.to = attrs.x2 + "," + attrs.y2;
			}
			if ("stroke" in attrs)
				atrs.strokecolor = attrs.stroke;
			if ("fill" in attrs)
				atrs.fillcolor = attrs.stroke;
			if ("stroke-width" in attrs)
				atrs.strokewidth = attrs["stroke-width"];

			var self = this;
			$.each(atr, function(objKey, objVal) {
				self.el.setAttribute(objKey, objVal);
			});
			return this;
		}
	};
	
	function SVG(base, attr) {
		if (arguments.length == 1 && $.type(base) !== "string")
			attr = base, base = "svg";
		var obj = new Elem(base, attr || {});
		this.el = obj.el;
		this.rect = function(attr) {
			var n = new Elem("v:roundrect", attr);
			this.el.appendChild(n.el);
			return n;
		};
		this.text = function(text, attr) {
			var n = new Elem("text", attr);
			var c = new Elem("tspan");
			c.append(document.createTextNode(text));
			n.append(c);
			this.el.appendChild(n.el);
			return n;
		};
		this.line = function(attr) {
			var n = new Elem("v:line", attr);
			this.el.appendChild(n.el);
			return n;
		};
	}
} else {
	Elem = function(tag, attrs) {
		attrs = attrs || {};
		var el = ($.type(tag) == "string" && document.createElement(tag)) || tag;
		this.el = el;
		this.attr(attrs);
	};
	Elem.prototype = {
		append: function(el) {
			this.el.appendChild(el.el || el);
			return this;
		},
		attr: function(k,v) {
			if (arguments.length == 1) {
				if ($.isPlainObject(k)) {
					var self = this;
					$.each(k, function(objKey, objVal) {
						self.el.setAttribute(objKey, objVal);
					});
					return this;
				}
				return this.el.getAttribute(k);
			}
			this.el.setAttribute(k, v);
		}
	};
	
	function SVG(base, attr) {
		if (arguments.length == 1 && $.type(base) !== "string")
			attr = base, base = "svg";
		var obj = new Elem(base, attr || {});
		this.el = obj.el;
		this.rect = function(attr) {
			var n = new Elem("rect", attr);
			this.el.appendChild(n.el);
			return n;
		};
		this.text = function(text, attr) {
			var n = new Elem("text", attr);
			n.append(document.createTextNode(text));
			this.el.appendChild(n.el);
			return n;
		};
		this._text = function(attr) {
			var n = new Elem("text", attr);
			this.el.appendChild(n.el);
			return n;
		};
		this.line = function(attr) {
			var n = new Elem("line", attr);
			this.el.appendChild(n.el);
			return n;
		};
		this.create = function(tag,attr) {
			var n = new Elem(tag, attr);
			n.tspan = this.tspan;
			this.el.appendChild(n.el);
			return n;
		};
		this.tspan = function(text, attr) {
			var n = new Elem("tspan", attr);
			n.append(document.createTextNode(text));
			this.el.appendChild(n.el);
			return n;
		};
	}
	SVG.svgns = "http://www.w3.org/2000/svg";
}
$.extend(SVG.prototype, Elem.prototype);

var COLORS=["ffaaaa","b5e198","b4cdeb","ffeda0","c3acda","f5c65f","e1b5a5","d7fac6","b0bfeb"];
var days = ["","Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];


function renderEvent(obj, o) {
	var ev = $("<div id='evt_" + obj.callnr + "' class='sv-event'></div>");
	ev.addClass(obj.cssClass || "");
	var content = $("<div class='sv-event-content'>");
	var title = $("<div class='sv-event-title'><strong>" + (obj.course) + "</strong></div>");
	if (o.location)
		title.append(" @ <span class='sv-event-location'>" + o.location + "</span>");
	content.append(title);
	if (obj.title) {
		content.append("<span class='sv-event-notes'>" + obj.title + "</span>");
	}
	ev.append(content);
	return ev;
}

$.extend(Raphael.fn, {
	line: function(x0,y0,x1,y1) { return this.path("M"+x0+","+y0+"L"+x1+","+y1); }
});

function renderGrid_Raphael(cfg, schedule) {
	var RH_W = cfg.timeColWidth;
	var CH_H = cfg.colHeaderHeight;
	var LOWER = cfg.startTime;
	var UPPER = cfg.endTime;
	var STEP = cfg.timeStep;
	var DAYS = cfg.visibleDays;
	var LINE_H = 12;

	var nRows = (UPPER - LOWER) / (STEP * 2);
	var mode = cfg.mode;
	
	var wd = (cfg.mode=='L'&&9.5||6.5) * 96, ht=(cfg.mode=='P'&&9.5||6.5) * 96;
	var CE_W = Math.floor((wd - RH_W) / DAYS.length);
	var CE_H = Math.floor((ht - CH_H) / ((UPPER - LOWER) / (STEP*2)));
	
	
	$("#svgcont").prev().width(wd-RH_W-4);
	rG = Raphael("svgcont", wd,ht);
	if (Raphael.type == "SVG")
		$.each({"color-interpolation":"linearRGB","stroke-width":1,"shape-rendering":"crispEdges"}, $.proxy(rG.canvas, "setAttribute"));

	
	function loadSchedule(s) {
		var doff = DAYS[0];
		var daymap = {};
		for (var i = 0; i < DAYS.length; i++)
			daymap[DAYS[i]] = true;

		var oldcnv = null;
		if (Raphael.type == "SVG") {
			oldcnv = rG.canvas;
			var g = document.createElementNS("http://www.w3.org/2000/svg", "g");
			g.setAttribute("shape-rendering", "geometricPrecision");
			g.setAttribute("width", "100%");
			g.setAttribute("height", "100%");
			g.setAttribute("x", 0);
			g.setAttribute("y", 0);
			rG.canvas.appendChild(g);
			rG.canvas = g;
		}
		for (var i = 0; i < s.length; i++) {
			var obj = s[i];
			var slots = s[i].slots;
			if (!slots || slots.length == 0)
				continue;
			for (var j = 0; j < slots.length; j++) {
				var o = slots[j];
				if (!daymap[o.day])
					continue;
				
				var y0 = (o.start - LOWER) / STEP * CE_H / 2;
				var y1 = (o.end - LOWER) / STEP * CE_H / 2;

				var set = rG.set();
				var rect = rG.rect(0,0,CE_W-5,y1-y0-1,4).attr({fill:obj.bgColor,stroke:"#444","stroke-width":1});
				var conf = {x:((o.day-doff)*CE_W+RH_W+2),y:(y0+CH_H+1),width:CE_W-5,height:(y1-y0-1)};
				
				set.push(rect);
				set.push(rG.text(CE_W-9,LINE_H/2+2,"#"+obj.callnr).attr("text-anchor","end"));
				
				if (Raphael.type == "VML") {
					rect.node.appendChild(renderEvent(obj, o)[0]);
				} else {
					set.push(rG.text(4,LINE_H/2+2,obj.course).attr({"font-weight":"bold","text-anchor":"start"}));
					
					var tit = rG.text(4,LINE_H*2,obj.title).attr("text-anchor","start");
					var buf = [];
					var lim = 5;

					set.push(tit);
					var curr = tit;
					var n = 2;
					while(true) {
						var ratio = (conf.width - 10) / curr.node.getComputedTextLength();
						if (ratio > 1) {
							curr = rG.text(4,LINE_H*++n, buf.join("")).attr("text-anchor","start");
							set.push(curr);
							ratio = (conf.width - 10) / curr.node.getComputedTextLength();
							if (ratio > 1)
								break;
						}
						if (--lim < 1) {
							console.error(ratio, curr.node.getComputedTextLength());
							break;
						}
						var lntext = curr.attr("text");
						var estidx = Math.floor(lntext.length * ratio);
						while (estidx >= 0 && " \t,.-".indexOf(lntext.charAt(estidx)) == -1) {
							estidx--;
						}
						if (estidx == -1)
							break;
						curr.attr("text", lntext.substr(0, estidx+1));
						buf.unshift(lntext.substr(estidx+1));
					}
				}
				set.translate(conf.x, conf.y);
				set.show();
			}
		}
		if (Raphael.type == "SVG") {
			rG.canvas = oldcnv;
		}
		//svg.appendChild(g.el);
	}

	rG.rect(RH_W, CH_H, DAYS.length*CE_W,nRows*CE_H).attr({fill:"#fff",stroke:"#bbb"});
	
	for (var i = 0;; i++) {
		var xo = RH_W + CE_W * i;
		rG.line(xo,0,xo,nRows*CE_H+CH_H).attr({stroke:"#bbb"});
		if (i == DAYS.length)
			break;
		rG.rect(xo,1,CE_W,CH_H-1).attr({fill:"#dddfee",stroke:"#aaa"});
		xo += CE_W / 2;
		rG.text(xo,CH_H/2,days[DAYS[i]]).attr({"text-anchor":"middle","font-weight":"bold","font-family":"arial","font-size":"10px"});
	}
	
	var off = 0;
	for (var t = LOWER; ; t += STEP * 2) {
		var yo = off * CE_H + CH_H;
		rG.line(0,yo,(RH_W+DAYS.length*CE_W),yo).attr("stroke","#bbb");
		if (t >= UPPER)
			break;
		rG.line(RH_W,yo+CE_H/2,RH_W+DAYS.length*CE_W,yo+CE_H/2).attr({stroke:"#ddd","stroke-width":1,"stroke-dasharray":"- "});
		yo += CE_H / 4;
		rG.text(RH_W-4,yo-2,timeToStr(t)).attr({"text-anchor":"end","font-weight":"bold","font-family":"arial","font-size":"10px"});
		off++;
	}
	loadSchedule(schedule);
	rG.show && rG.show();
}

function renderGrid_SVG(cfg, schedule) {
	var RH_W = cfg.timeColWidth;
	var CH_H = cfg.colHeaderHeight;
	var LOWER = cfg.startTime;
	var UPPER = cfg.endTime;
	var STEP = cfg.timeStep;
	var CE_W = cfg.cellWidth;
	var CE_H = cfg.cellHeight;
	var DAYS = cfg.visibleDays;
	var LINE_H = 12;

	var nRows = (UPPER - LOWER) / (STEP * 2);
	
	function loadSchedule(s) {
		var svg = $("#svgcont").children()[0];
		var doff = DAYS[0];
		var daymap = {};
		for (var i = 0; i < DAYS.length; i++)
			daymap[DAYS[i]] = true;

		var g = new SVG("g", {"shape-rendering": "geometricPrecision"});
		svg.appendChild(g.el);
		for (var i = 0; i < s.length; i++) {
			var obj = s[i];
			var slots = s[i].slots;
			if (!slots || slots.length == 0)
				continue;
			for (var j = 0; j < slots.length; j++) {
				var o = slots[j];
				if (!daymap[o.day])
					continue;
				
				var y0 = (o.start - LOWER) / STEP * CE_H / 2;
				var y1 = (o.end - LOWER) / STEP * CE_H / 2;
				
				var rect = new Elem("rect", {rx:4,ry:4});
				
				var conf = {x:((o.day-doff)*CE_W+RH_W+2),y:(y0+CH_H+1),width:CE_W-5,height:(y1-y0-1)};
				rect.attr(conf);

				rect.attr("stroke", "#444");
				rect.attr("stroke-width",1);
				rect.attr("fill", obj.bgColor);
				g.append(rect.el);

				
				g.text("#"+obj.callnr,{x:conf.x+CE_W-9,y:conf.y+LINE_H,width:CE_W-8,"text-anchor":"end"});
				g.text(obj.course, {x:conf.x+4,y:conf.y+LINE_H,"font-weight": "bold"});
				var text = g.create("text", {x:conf.x+4,y:conf.y+LINE_H,height:conf.height,width:conf.width});

				var tit = g.text(obj.title, {x:conf.x+4,y:conf.y+LINE_H,dy:LINE_H*1.3});
				var buf = [];
				var lim = 5;
				if (tit.el.getComputedTextLength) {
					while (true) {
						var ratio = (conf.width - 10) / tit.el.getComputedTextLength();
						if (ratio > 1) {
							g.text(buf.join(""), {x:conf.x+4,y:conf.y+LINE_H*2,dy:LINE_H*1.3});
							break;
						}
						if (--lim < 1) {
							console.error(ratio, tit.el.getComputedTextLength());
							break;
						}
						if (ratio > 2)
							throw new Error("handle more than 1 split");
						var estidx = Math.floor(obj.title.length * ratio);
						while (estidx >= 0 && " \t,.-".indexOf(obj.title.charAt(estidx)) == -1) {
							estidx--;
						}
						tit.el.firstChild.nodeValue = obj.title.substr(0, estidx+1);
						buf.unshift(obj.title.substr(estidx+1));
					}
				}
			}
		}
		//svg.appendChild(g.el);
	}

	var mode = cfg.mode;
	var svg = new SVG({ xmlns: SVG.svgns,version:"1.1", width: mode == 'L' ? 912:624, height: mode == 'P' ? 912:624, "color-interpolation": "linearRGB","stroke-width":1,"shape-rendering":"crispEdges" });
	svg.rect({x:RH_W,y:CH_H,width:(DAYS.length*CE_W),height:(nRows*CE_H),fill:"#fff","stroke":"#bbb","stroke-width":1});
	
	for (var i = 0;; i++) {
		var xo = RH_W + CE_W * i;
		svg.line({x1:xo,y1:0,x2:xo,y2:(nRows * CE_H + CH_H),stroke:"#bbb","stroke-width":1});
		if (i == DAYS.length)
			break;
		svg.rect({x:xo,y:1,width:CE_W,height:CH_H-1,fill:"#dddfee",stroke:"#aaa","stroke-width":1});
		xo += CE_W / 2;
		svg.text(days[DAYS[i]], {x:xo,y:(CH_H * 0.7),"text-anchor":"middle","font-weight":"bold","font-family":"arial","font-size":"10px"});
	}
	
	var off = 0;
	for (var t = LOWER; ; t += STEP * 2) {
		var yo = off * CE_H + CH_H;
		svg.line({x1:0,y1:yo,x2:(RH_W+DAYS.length*CE_W),y2:yo,stroke:"#bbb","stroke-width":1});
		if (t >= UPPER)
			break;
		svg.line({x1:RH_W,y1:yo+CE_H/2,x2:(RH_W+DAYS.length*CE_W),y2:yo+CE_H/2,stroke:"#ddd","stroke-width":1,"stroke-dasharray":"4 2"});
		yo += CE_H / 4;
		svg.text(timeToStr(t), {x:RH_W-4,y:yo+2,"text-anchor":"end","font-weight":"bold","font-family":"arial","font-size":"10px"});
		off++;
	}
	
	var cont = $("#svgcont");
	cont.empty();
	cont[0].appendChild(svg.el);
	loadSchedule(schedule);
};
<?php } else { ?>
var SVG;function timeToStr(a){a/=1E3;var d=Math.floor(a/3600),a=Math.floor(a/60%60);return(d%12==0?"12":d%12)+":"+(a<10?"0":"")+a+" "+(d<12?"am":"pm")}Elem=function(a,d){d=d||{};this.el=$.type(a)=="string"&&document.createElement(a)||a;this.attr(d)}; Elem.prototype={append:function(a){this.el.appendChild(a.el||a);return this},attr:function(a,d){if(arguments.length==1){if($.isPlainObject(a)){var h=this;$.each(a,function(a,e){h.el.setAttribute(a,e)});return this}return this.el.getAttribute(a)}this.el.setAttribute(a,d)}}; SVG=function(a,d){arguments.length==1&&$.type(a)!=="string"&&(d=a,a="svg");this.el=(new Elem(a,d||{})).el;this.rect=function(a){a=new Elem("rect",a);this.el.appendChild(a.el);return a};this.text=function(a,g){var e=new Elem("text",g);e.append(document.createTextNode(a));this.el.appendChild(e.el);return e};this._text=function(a){a=new Elem("text",a);this.el.appendChild(a.el);return a};this.line=function(a){a=new Elem("line",a);this.el.appendChild(a.el);return a};this.create=function(a,g){var e=new Elem(a, g);e.tspan=this.tspan;this.el.appendChild(e.el);return e};this.tspan=function(a,g){var e=new Elem("tspan",g);e.append(document.createTextNode(a));this.el.appendChild(e.el);return e}};SVG.svgns="http://www.w3.org/2000/svg";$.extend(SVG.prototype,Elem.prototype);var COLORS=["ffaaaa","b5e198","b4cdeb","ffeda0","c3acda","f5c65f","e1b5a5","d7fac6","b0bfeb"],days=["","Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]; function renderEvent(a,d){var h=$("<div id='evt_"+a.callnr+"' class='sv-event'></div>");h.addClass(a.cssClass||"");var g=$("<div class='sv-event-content'>"),e=$("<div class='sv-event-title'><strong>"+a.course+"</strong></div>");d.location&&e.append(" @ <span class='sv-event-location'>"+d.location+"</span>");g.append(e);a.title&&g.append("<span class='sv-event-notes'>"+a.title+"</span>");h.append(g);return h}$.extend(Raphael.fn,{line:function(a,d,h,g){return this.path("M"+a+","+d+"L"+h+","+g)}}); function renderGrid_Raphael(a,d){var h=a.timeColWidth,g=a.colHeaderHeight,e=a.startTime,k=a.endTime,u=a.timeStep,j=a.cellWidth,l=a.cellHeight,m=a.visibleDays,i=(k-e)/(u*2);rG=Raphael("svgcont",912,624);Raphael.type=="SVG"&&$.each({"color-interpolation":"linearRGB","stroke-width":1,"shape-rendering":"crispEdges"},$.proxy(rG.canvas,"setAttribute"));rG.rect(h,g,m.length*j,i*l).attr({fill:"#fff",stroke:"#bbb"});for(var b=0;;b++){var f=h+j*b;rG.line(f,0,f,i*l+g).attr({stroke:"#bbb"});if(b==m.length)break; rG.rect(f,1,j,g-1).attr({fill:"#dddfee",stroke:"#aaa"});f+=j/2;rG.text(f,g/2,days[m[b]]).attr({"text-anchor":"middle","font-weight":"bold","font-family":"arial","font-size":"10px"})}i=0;for(b=e;;b+=u*2){f=i*l+g;rG.line(0,f,h+m.length*j,f).attr("stroke","#bbb");if(b>=k)break;rG.line(h,f+l/2,h+m.length*j,f+l/2).attr({stroke:"#ddd","stroke-width":1,"stroke-dasharray":"- "});f+=l/4;rG.text(h-4,f-2,timeToStr(b)).attr({"text-anchor":"end","font-weight":"bold","font-family":"arial","font-size":"10px"}); i++}(function(a){for(var d=m[0],f={},b=0;b<m.length;b++)f[m[b]]=!0;var i=null;if(Raphael.type=="SVG")i=rG.canvas,b=document.createElementNS("http://www.w3.org/2000/svg","g"),b.setAttribute("shape-rendering","geometricPrecision"),b.setAttribute("width","100%"),b.setAttribute("height","100%"),b.setAttribute("x",0),b.setAttribute("y",0),rG.canvas.appendChild(b),rG.canvas=b;for(b=0;b<a.length;b++){var v=a[b],w=a[b].slots;if(w&&w.length!=0)for(var k=0;k<w.length;k++){var q=w[k];if(f[q.day]){var c=(q.start- e)/u*l/2,o=(q.end-e)/u*l/2,t=rG.set(),p=rG.rect(0,0,j-5,o-c-1,4).attr({fill:v.bgColor,stroke:"#444","stroke-width":1}),c={x:(q.day-d)*j+h+2,y:c+g+1,width:j-5,height:o-c-1};t.push(p);t.push(rG.text(j-9,8,"#"+v.callnr).attr("text-anchor","end"));if(Raphael.type=="VML")p.node.appendChild(renderEvent(v,q)[0]);else{t.push(rG.text(4,8,v.course).attr({"font-weight":"bold","text-anchor":"start"}));o=rG.text(4,24,v.title).attr("text-anchor","start");q=[];p=5;t.push(o);for(var r=2;;){var s=(c.width-10)/o.node.getComputedTextLength(); if(s>1&&(o=rG.text(4,12*++r,q.join("")).attr("text-anchor","start"),t.push(o),s=(c.width-10)/o.node.getComputedTextLength(),s>1))break;if(--p<1){console.error(s,o.node.getComputedTextLength());break}for(var x=o.attr("text"),s=Math.floor(x.length*s);s>=0&&" \t,.-".indexOf(x.charAt(s))==-1;)s--;if(s==-1)break;o.attr("text",x.substr(0,s+1));q.unshift(x.substr(s+1))}}t.translate(c.x,c.y);t.show()}}}if(Raphael.type=="SVG")rG.canvas=i})(d);rG.show&&rG.show()} function renderGrid_SVG(a,d){var h=a.timeColWidth,g=a.colHeaderHeight,e=a.startTime,k=a.endTime,u=a.timeStep,j=a.cellWidth,l=a.cellHeight,m=a.visibleDays,i=(k-e)/(u*2),b=a.mode,b=new SVG({xmlns:SVG.svgns,version:"1.1",width:b=="L"?912:624,height:b=="P"?912:624,"color-interpolation":"linearRGB","stroke-width":1,"shape-rendering":"crispEdges"});b.rect({x:h,y:g,width:m.length*j,height:i*l,fill:"#fff",stroke:"#bbb","stroke-width":1});for(var f=0;;f++){var n=h+j*f;b.line({x1:n,y1:0,x2:n,y2:i*l+g,stroke:"#bbb", "stroke-width":1});if(f==m.length)break;b.rect({x:n,y:1,width:j,height:g-1,fill:"#dddfee",stroke:"#aaa","stroke-width":1});n+=j/2;b.text(days[m[f]],{x:n,y:g*0.7,"text-anchor":"middle","font-weight":"bold","font-family":"arial","font-size":"10px"})}i=0;for(f=e;;f+=u*2){n=i*l+g;b.line({x1:0,y1:n,x2:h+m.length*j,y2:n,stroke:"#bbb","stroke-width":1});if(f>=k)break;b.line({x1:h,y1:n+l/2,x2:h+m.length*j,y2:n+l/2,stroke:"#ddd","stroke-width":1,"stroke-dasharray":"4 2"});n+=l/4;b.text(timeToStr(f),{x:h-4, y:n+2,"text-anchor":"end","font-weight":"bold","font-family":"arial","font-size":"10px"});i++}k=$("#svgcont");k.empty();k[0].appendChild(b.el);(function(a){for(var b=$("#svgcont").children()[0],f=m[0],d={},i=0;i<m.length;i++)d[m[i]]=!0;var k=new SVG("g",{"shape-rendering":"geometricPrecision"});b.appendChild(k.el);for(i=0;i<a.length;i++){var b=a[i],n=a[i].slots;if(n&&n.length!=0)for(var q=0;q<n.length;q++){var c=n[q];if(d[c.day]){var o=(c.start-e)/u*l/2,t=(c.end-e)/u*l/2,p=new Elem("rect",{rx:4,ry:4}), c={x:(c.day-f)*j+h+2,y:o+g+1,width:j-5,height:t-o-1};p.attr(c);p.attr("stroke","#444");p.attr("stroke-width",1);p.attr("fill",b.bgColor);k.append(p.el);k.text("#"+b.callnr,{x:c.x+j-9,y:c.y+12,width:j-8,"text-anchor":"end"});k.text(b.course,{x:c.x+4,y:c.y+12,"font-weight":"bold"});k.create("text",{x:c.x+4,y:c.y+12,height:c.height,width:c.width});p=k.text(b.title,{x:c.x+4,y:c.y+12,dy:12*1.3});o=[];t=5;if(p.el.getComputedTextLength)for(;;){var r=(c.width-10)/p.el.getComputedTextLength();if(r>1){k.text(o.join(""), {x:c.x+4,y:c.y+24,dy:12*1.3});break}if(--t<1){console.error(r,p.el.getComputedTextLength());break}if(r>2)throw Error("handle more than 1 split");for(r=Math.floor(b.title.length*r);r>=0&&" \t,.-".indexOf(b.title.charAt(r))==-1;)r--;p.el.firstChild.nodeValue=b.title.substr(0,r+1);o.unshift(b.title.substr(r+1))}}}}})(d)};
<?php } ?>
</script>
<script>


(function() {
	var DEFAULT_LANDSCAPE = {
		timeColWidth:52,
		colHeaderHeight:20,
		startTime:<?=$start?>,
		endTime:<?=$end?>,
		timeStep:<?=$step?>,
		cellWidth:142,
		cellHeight:42,
		visibleDays:<?=json_encode($days)?>,
		mode: 'L'
	};

	var DEFAULT_PORTRAIT = {
		timeColWidth:52,
		colHeaderHeight:20,
		startTime:<?=$start?>,
		endTime:<?=$end?>,
		timeStep:<?=$step?>,
		cellWidth:94,
		cellHeight:42,
		visibleDays:<?=json_encode($days)?>,
		mode: 'P'
	};

	$(document).ready(function() {
		if (window.opener) {
			var schedule = window.opener.getActiveSchedule();
			renderGrid_Raphael(DEFAULT_LANDSCAPE, schedule);
			setTimeout(function() {
				//window.print();
			}, 500);
		}
	});
})();
</script>
<script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-15834370-1']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
</head>
<body>
<h1 style="padding:0 0 4px 58px;margin:0;font-size:22px;">
<button type="button" class="noprint" style="float:right;" onclick="javascript:window.print();">Print</button>
Schedule - <?=$current_term_label?>
</h1>
<div id="svgcont" class="sv-showrange sv-shownotes"></div>
<em class="noprint">
Use Landscape layout when printing.
<br/>
NOTE: This does not include non-meeting courses. Print the
main page to include these.
</em>
</body>
</html>
