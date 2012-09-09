/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

function _sprintf() {
	if (arguments.length < 1)
		return "";
	var args = $.makeArray(arguments);
	var fmt = args.shift();
	var toks = fmt.split(/((?:%%)|(?:%-?[+ ]?0?(?:[1-9]\d*)?(?:\.[1-9]\d*)?[idfsxX]))/);
	var s = "";
	while (toks.length) {
		var tok = toks.shift();
		if (tok == "")
			continue;
		if (tok.charAt(0) != "%") {
			s += tok;
		} else if (tok.charAt(1) == '%') {
			s += "%";
		} else {
			if (args.length == 0) {
				console.info("no more arguments left: " + tok);
				s += toks.join("");
				break;
			}
			var arg = args.shift();
			var ljust = false, sign = 1, padch = ' ';
			var minw = 0, prec = 0;
			var type;
			var j = 1;
			if (tok.charAt(j) == '-') {
				ljust = true;
				j++;
			}
			if (tok.charAt(j) == '+' || tok.charAt(j) == ' ') {
				sign = tok.charAt(j) == '+' ? -1 : 0;
				j++;
			}
			if (tok.charAt(j) == '0') {
				padch = '0';
				j++;
			}
			var r = j;
			while (tok.charAt(r) >= '0' && tok.charAt(r) <= '9')
				r++;
			if (r != j) {
				minw = parseInt(tok.slice(j, r), 10);
				j = r;
			}
			if (tok.charAt(j) == '.') {
				r = ++j;
				while (tok.charAt(r) >= '0' && tok.charAt(r) <= '9')
					r++;
				prec = parseInt(tok.slice(j, r), 10);
				j = r;
			}
			type = tok.charAt(j);
			if ("idf".indexOf(type) != -1) {
				if (sign == -1 && arg > 0)
					s += "+";
				else if (sign == 0 && arg < 0)
					arg = -arg;
				s += arg;
			} else if ("xX".indexOf(type) != -1) {
				var len = 8 - minw;
				var mask = arg;
				var dcnt = 0;
				do {
					mask = (mask >>> 4);
					dcnt++;
				} while(mask != 0);
				for (var i = dcnt + 1; i < minw; i++)
					s += padch;
				var str = "";
				for (var i = dcnt; i >= 0; i--) {
					str += "0123456789abcdef".charAt((arg >>> (i * 4)) & 0x0f);
				}
				if (type == "X")
					str = str.toUpperCase();
				s += str;
			} else {
				s += arg;
			}
		}
	}
	return s;
}
if (Array.prototype.indexOf === undefined)
	Array.prototype.indexOf = function (a) {
		for (var i = 0, N = this.length; i < N; i++)
			if (this[i] == a)
				return i;
		return -1;
	};

if (Array.prototype.map === undefined)
	Array.prototype.map = function(fun /*, thisp */) {
		if (this === void 0 || this === null)
			throw new TypeError();
	
		var t = Object(this);
		var len = t.length >>> 0;
		if (typeof fun !== "function")
			throw new TypeError();
	
		var res = new Array(len);
		var thisp = arguments[1];
		for (var i = 0; i < len; i++) {
			if (i in t)
				res[i] = fun.call(thisp, t[i], i, t);
		}
	
		return res;
	};
if (Array.prototype.forEach === undefined)
	Array.prototype.forEach = function(fun /*, thisp */) {
		if (this === void 0 || this === null)
			throw new TypeError();
		
		var t = Object(this);
		var len = t.length >>> 0;
		if (typeof fun !== "function")
			throw new TypeError();
		
		var thisp = arguments[1];
		for (var i = 0; i < len; i++)
			if (i in t) fun.call(thisp, t[i], i, t);
	};

if (String.prototype.trim === undefined) {
	String.prototype.trim = function() {
		return this.replace(/^\s+|\s+$/g, ''); 
	};
}

if (Function.prototype.bind === undefined) {
	Function.prototype.bind = function(thisArg) {
		var args = Array.prototype.slice.call(arguments, 1);
		var self = this;
		
		function noop() {}
		noop.prototype = this.prototype;
		
		function bound() {
			var newargs = Array.prototype.slice.call(arguments, 0);
			return self.apply(this instanceof noop ? this : (obj || {}), args.concat(newargs));
		}
		bound.prototype = new noop();
		return bound;
	}
}
