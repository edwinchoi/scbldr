/*!
 * Schedule builder
 *
 * Copyright (c) 2011, Edwin Choi
 *
 * Licensed under LGPL 3.0
 * http://www.gnu.org/licenses/lgpl-3.0.txt
 */

if (typeof window === "undefined") {
	importScripts("simplerpc.js");
}

(function() {
	"use strict";
	
	/*
	 * Uses a bitset to represent the adjacency matrix.
	 * 
	 * Much more memory efficient than the alternative.
	 * Each section is assigned a particular bit-index and position within the
	 * adjacency matrix. The logical AND of bit-0 and bit-1, for example, will
	 * yield all vertices that are adjacent to both section[0] and section[1].
	 * 
	 * This algorithm finds all valid permutations of a set of courses by
	 * reducing the constraint satisfaction problem (CSP) into the clique problem,
	 * using the Bron-Kerbosch algorhtm.
	 */
	
	// For browsers that don't support WebWorkers, use a timer based approach
	// This allows the algorithm to execute without having the browser complain
	// about execution taking too long.
	/** @private */
	var USE_TIMER = (typeof window !== "undefined"),
	
	/* reference to the EMessageExchange object */
		xchg,
	
	/** @protected */
		bits_per_integer = (function() {
			var x = -1, bcnt = 1;
			while ((x >>>= 1) != 0) {
				bcnt++;
			}
			return bcnt;
		}()),
	/** @protected */
	// http://graphics.stanford.edu/~seander/bithacks.html#CountBitsSetParallel
		count_bits2 = function(x) {
		    var v = x - ((x >>> 1) & 0x55555555);
		    v = (v & 0x33333333) + ((v >>> 2) & 0x33333333);
		    return ((v + (v >>> 4) & 0x0F0F0F0F) * 0x01010101) >>> 24;
		},
		count_bits = function(x) {
			var c;
			for (c = 0; x; c++) {
				x &= x - 1;
			}
			return c;
		},
	
	/** @protected */
		setup = (function() {
			var _bits = function() { return bits_per_integer; },
				_contains = function(a, i) { return (a & (1 << i)) != 0; },
				_set = function(a, i) { return a | (1 << i); },
				_clr = function(a, i) { return a & ~(1 << i); },
				_and = function(a, b) { return a & b; },
				_or = function(a, b) { return a | b; },
				_iszero = function(a) { return a == 0; },
				_mkset = function(n) { return ((-1) >>> (31 - (n & 31))); }, // overflows when n is _bits
				_notin = function(a, b) { return a & ~b; };
			
			return function(N) {
				if (N <= bits_per_integer) {
					return [
						/*_N: */function() { return bits_per_integer; },
						/*isAdjacent: */function(E, u) { return _contains(E[0], u); },
						/*addChild: */function(E, u) { E[0] = _set(E[0], u); return E; },
						/*removeChild: */function(E, u) { E[0] = _clr(E[0], u); return E; },
						/*intersect: */function(Eu, Ev) { return [ _and(Eu[0], Ev[0]) ]; },
						/*count: */function(E) { return count_bits(E[0]); },
						/*isEmpty: */function(E) { return E[0] == 0; },
						/*zeroes: */function() { return [0]; },
						/*makeRange: */function(s, t) { return [_notin(_mkset(t - 1), s!=0 && _mkset(s - 1) || 0)]; }
					];
				} else if (N <= (bits_per_integer * 2)) {
					return [
						/*_N: */function() { return bits_per_integer * 2; },
						/*isAdjacent: */function(E, u) { return _contains(E[u >>> 5], u & 31); },
						/*addChild: */function(E, u) { E[u >>> 5] = _set(E[u >>> 5], u & 31); return E; },
						/*removeChild: */function(E, u) { E[u >>> 5] = _clr(E[u >>> 5], u & 31); return E; },
						/*intersect: */function(Eu, Ev) { return [ _and(Eu[0], Ev[0]), _and(Eu[1], Ev[1]) ]; },
						/*count: */function(E) { return count_bits(E[0]) + count_bits(E[1]); },
						/*isEmpty: */function(E) { return !(E[0] || E[1]); },
						/*zeroes: */function() { return [0, 0]; },
						/*makeRange: */function(s, t) {
							var r = [-1,-1];
							if (s != 0) {
								r[--s >>> 5] &= ~_mkset(s & 31);
							}
							if (!(--t >>> 5)) {
								r[1] = 0;
							}
							r[t >>> 5] &= _mkset(t & 31);
							return r;
						}
					];
				} else {
					var map = function(array, callback, obj) {
						var result = [];
						var i;
						for (i = 0; i < array.length; i++) {
							result[i] = callback.call(array[i], array[i], i, obj);
						}
						return result;
					};
					if (bits_per_integer < 32) { throw new Error(); }
					var ints = Math.ceil(N / bits_per_integer);
					var Z = [];
					for (var i = 0; i < ints; i++) Z[i] = 0;
					//console.info("N",N,"ints",ints);
					return [
						/*_N: */function() { return ints; },
						/*isAdjacent: */function(E, u) { return _contains(E[u>>>5], 31&u); },
						/*addChild: */function(E, u) { E[u>>>5] = _set(E[u>>>5], 31&u); return E; },
						/*removeChild: */function(E, u) { E[u>>>5] = _clr(E[u>>>5], u&31); return E; },
						/*intersect: */function(Eu, Ev) {
							var R = [];
							for (var i = 0; i < ints; i++) { R[i] = _and(Eu[i], Ev[i]); }
							return R;
						},
						/*count: */function(E) { var sum = 0; for (var i = 0; i < E.length; i++) { sum += count_bits(E[i]); } return sum; },
						/*isEmpty: */function(E) { for (var i = 0; i < E.length; i++) { if (E[i]) return false; } return true; },
						/*zeroes: */function() { return new Array(ints); },
						/*makeRange: */function(s, t) {
							var r = new Array(ints);
							t--;
							while (s < t) {
								r[s >>> 5] |= (1 << (s & 31));
								s++;
							}
							//console.info("range", r);
							return r;
						}
					];
				}
			};
		}());
	
	/** @constructor
	 *  @protected
	 */
	var Solver = function(p) {
		var V = p[0], E = p[1];
		var k = p[2];
		var solver = this;
		var G = p[3];
	
		/** @private */
		function $adj(E, u) { return G[1](E, u); }
		/** @private */
		function $has(E, u) { return G[1](E, u); }
		/** @private */
		function $set(E, u) { return G[2](E, u); }
		/** @private */
		function $clr(E, u) { return G[3](E, u); }
		/** @private */
		function $int(R, S) { return G[4](R, S); }
		/** @private */
		function $cnt(E) { return G[5](E); }
		/** @private */
		function $empty(E) { return G[6](E); }
		/** @private */
		function $zeroes() { return G[7](); }
		/** @private */
		function $mkr(s,t) { return G[8](s, t); }
		/** @private */
		function $list(e) {
			var res = [];
			for (var i = 0, N = E.length; i < N; i++) {
				if ($has(e, i))
					res.push(i);
			}
			return res;
		}
		
		var R = $zeroes();
		var P = $mkr(0, V.length);
		var X = $zeroes();
		var i0 = Math.floor(Math.random() * V.length);
	
		function testExists(P, X) {
			var i;
			for (i = 0; i < E.length; i++) {
				if (!$has(X, i)) continue;
				var j;
				for (j = 0; j < E.length; j++) {
					if (!$has(P, j)) continue;
					if (!$has(E[i], j)) break;
					//if (!$has(E[i], j)) break;
				}
				
				if (j == E.length)
					break;
			}
			return i == E.length;
		}
	
		function find(R, P, X, callback, ctx) {
			if (!testExists(P, X))
				return;
			// p => candidates
			// x => nots
			// r => clique
			for (var zz = 0; zz < E.length; zz++) {
				var i = (zz + i0) % E.length;
				if (!$has(P, i)) continue;
				
				$set(R, i);
				$clr(P, i);
	
				var Pi = $int(E[i], P);
				var Xi = $int(E[i], X);
				
				if ($empty(Pi) && $empty(Xi)) {
					if ($cnt(R) >= k)
						if (callback.call(ctx, $list(R)) === false)
							return false;
				} else {
					if (find(R, Pi, Xi, callback, ctx) === false)
						return false;
				}
				
				$clr(R, i);
				$set(X, i);
			}
		}
		/** @param {Object=} ctx */
		function run(callback, ctx) {
			find(R, P, X, callback, ctx);
		}
	
		this.setsplice = function(val) {
			this.splice = val;
		};
	
		/** @protected */
		this.nth = function(n) {
			var res = [];
			run(function(c) {
				if ((--n) == 0) {
					res[0] = c;
					return false;
				}
			}, this);
			return res;
		};
		/** @protected */
		this.first = function() {
			return this.nth(1);
		};
		this.all = (function() {
			var findAll;
			if (!USE_TIMER) {
				/** @protected */
				findAll = function(reportInterval) {
					var C = [];
					reportInterval = reportInterval || 80;
	
					if (reportInterval && typeof window === "undefined") {
						var t0 = new Date().getTime();
						//var last = 0;
						var total = 0;
						var self = this;
						run(function(a) {
							this.push(a);
							var dt = new Date().getTime() - t0;
							if (dt >= reportInterval) {
								//total += this.length;
								//last = this.length;
								//_postMessage({id: msgid, found: total, current: this.splice(0, this.length)});
								if (self.splice) {
									total += this.length;
									xchg.publish("solver.progress", [total, this.splice(0, this.length)]);
								} else {
									xchg.publish("solver.progress", [this.length]);
								}
								t0 += dt;
							}
						}, C);
						if (this.splice) {
							xchg.publish("solver.progress", [total+C.length, C]);
							C = [];
						} else {
							xchg.publish("solver.progress", [C.length]);
						}
					} else {
						run(C.push, C);
					}
					return C.slice(0);
				};
			} else {
				var stack;
				var C = [];
				
				/**
				 * Mimicks the recursive behavior of Solver using a stack.
				 * This is needed so the operation doesn't consume all of the
				 * block the browser's event thread.
				 */
				function next() {
					if (stack.length == 0)
						return false;
					var cfg = stack.pop();
					var idx = cfg[0];
					var P = cfg[1];
					var X = cfg[2];
					if (idx == -1) {
						if (!testExists(P, X))
							return next();
					} else {
						$clr(R, idx);
						$set(X, idx);
					}
					stack.push(cfg);
					// p => candidates
					// x => nots
					// r => clique
					for (var i = idx + 1; i < E.length; i++) {
						if (!$has(P, i)) continue;
						
						$set(R, i);
						$clr(P, i);
						
						var Pi = $int(E[i], P);
						var Xi = $int(E[i], X);
						
						if ($empty(Pi) && $empty(Xi)) {
							if ($cnt(R) >= k) {
								C.push($list(R));
								this.found++;
							}
						} else {
							cfg[0] = i;
							stack.push([-1, Pi, Xi]);
							return true;
						}
						
						$clr(R, i);
						$set(X, i);
					}
					stack.pop();
					return next();
				};
				// using a timer based solution so the browser doesn't freeze
				findAll = function(reportInterval) {
					var self = this;
					var d = new $.Deferred();
					var t0 = new Date().getTime();
					
					reportInterval = reportInterval || 80;
					stack = [];
					stack.push([-1, $mkr(0, V.length), $zeroes()]);
					
					var timeout = setTimeout(function() {
						var done = false;
						while (new Date().getTime() < (t0 + reportInterval)) {
							if (!next()) {
								done = true;
								break;
							}
						}
						t0 = new Date().getTime();
						xchg.publish("solver.progress", [C.length]);
						if (!done) {
							timeout = setTimeout(arguments.callee, 17);
						} else {
							d.resolve(C.splice(0));
						}
					}, 0);
					// result will not be posted immediately when returning deferreds
					return d;
				};
			}
			return findAll;
		})();
	}
	
	function slotsAreEqual(s1, s2) {
		if (!s1.slots || !s2.slots)
			return !s1.slots && !s2.slots;
		var slotcps = 0;
		for (var i = 0; i < s1.slots.length; i++) {
			var a = s1.slots[i];
			var j;
			for (j = 0; j < s2.slots.length; j++) {
				var b = s2.slots[j];
				if (a.day == b.day &&
					a.start == b.start &&
					a.end == b.end)
					break;
			}
			if (j == s2.slots.length)
				return false;
		}
		return true;
	}
	
	function timeConflicts(s1, s2) {
		if (!s1.slots || !s2.slots) return false;
		for (var i = 0; i < s1.slots.length; i++) {
			var a = s1.slots[i];
			for (var j = 0; j < s2.slots.length; j++) {
				var b = s2.slots[j];
				
				if (a.day == b.day) {
					if (a.start < b.start) {
						if (a.end >= b.start)
							return true;
					} else {
						if (a.start <= b.end)
							return true;
					}
				}
			}
		}
		return false;
	}
	
	function removeDups(cands) {
		var merged = {};
		for (var i = 0; i < cands.length - 1; i++) {
			for (var j = i + 1; j < cands.length; j++) {
				if (cands[i].name !== cands[j].name)
					continue;
				if (slotsAreEqual(cands[i], cands[j]))
					cands.splice(j--, 1);
			}
		}
		return merged;
	}
	
	function createGraph(cands, G) {
		var V = cands;
		var E = new Array(cands.length);
		
		var $set = G[2];
		var $zeroes = G[7];
		var $clr = G[3];
		
		for (var i = 0; i < E.length; i++) {
			E[i] = $zeroes();
			//$set(E[i], i);
		}
		
		for (var i = 0; i < V.length - 1; i++) {
			var u = V[i];
			
			for (var j = i + 1; j < V.length; j++) {
				var v = V[j];
				if (u.name === v.name) {
					continue;
				}
				
				if (!timeConflicts(u, v)) {
					$set(E[i], j);
					$set(E[j], i);
				}
			}
		}
		
		return [V, E];
	}
	
	function countDistinct(V, p) {
		var cnt = 0, set = {};
		for (var i = 0; i < V.length; i++) {
			if ($set[V[i][p]] === undefined)
				$set[V[i][p]] = ++cnt;
		}
		return cnt;
	}
	
	function setupProblem(data) {
		// reduce the satisfiability problem to the clique problem
		if (data.length < 2) throw new Error("at least 2 required");
	
		var merged = [];
		var maxSects = -1;
		for (var i = 0; i < data.length; i++) {
			removeDups(data[i]);
		}
		//_info("merged", merged);
		var cnt = data.length;
		var cands = [];
	
		for (var i = 0; i < data.length; i++) {
			for (var j = 0; j < data[i].length; j++)
				cands.push(data[i][j]);
		}
	
		//merged = removeDups(cands);
		var G = setup(cands.length);
		var graph = createGraph(cands, G);
		
		graph.push(cnt);
		graph.push(G);
		
		return graph;
	}
	
	function unmapResults(V, C) {
		for (var i = 0; i < C.length; i++) {
			var ci = C[i];
			for (var j = 0; j < ci.length; j++) {
				ci[j] = V[ci[j]];
			}
		}
	}
	
	// not worth using... reduces size of result set by <10%... and doubles the execution time
	function removeTimeDups(V, C) {
		var m = {};
		var cm = [];
		var cnt = 0;
		for (var i = 0; i < V.length; i++) {
			var ss = V[i].slots;
			if (!ss)
				continue;
			for (var j = 0; j < ss.length; j++) {
				var s = ss[j];
				var key = s.day + "/" + s.start + "-" + s.end;
				if (!(key in m)) {
					cm[cnt] = 0;
					m[key] = cnt++;
				}
				cm[m[key]]++;
			}
		}
		
		var dups = {};
		var xdu = [];
		for (var i = 0; i < C.length; i++) {
			var cfg = [];
			var idm = [];
			for (var j = 0; j < C[i].length; j++) {
				cfg.push(V[C[i][j]]);
				var ss = V[C[i][j]].slots;
				if (!ss)
					continue;
				for (var k = 0; k < ss.length; k++) {
					var s = ss[k];
					var key = s.day + "/" + s.start + "-" + s.end;
					idm.push(m[key]);
				}
			}
			idm.sort();
			var str = idm.join(":");
			if (str in dups) {
				C.splice(i--, 1);
				xdu.push(str);
			} else {
				dups[str] = true;
			}
		}
		return C;
	}
	
	/*
	 * connects the message exchange object and binds the externally accessible objects.
	 * this MUST be called
	 */
	function connectObject(port) {
		xchg = new EMessageExchange(port);
		xchg.bind("redux", {
			init: function(data) {
				var p = setupProblem(data);
				solver = new Solver(p);
				xchg.bind("solver", solver);
				return p.slice(0, p.length - 1);
			}
		});
		//console = xchg.connect("console", {info:false,log:false,error:false,dir:false});
	}
	
	if (typeof window === "undefined") {
		connectObject(this);
	} else {
		generator_connectObject = connectObject;
	}

}());
