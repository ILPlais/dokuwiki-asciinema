/**
 * DokuWiki Plugin asciinema
 *
 * Boots the bundled asciinema player on the placeholders emitted by syntax.php.
 * The player bundle weighs about 180 kB, so it is fetched on demand and only on
 * pages that actually contain a recording.
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Lionel PLAIS <lionel.plais@ilp-web.net>
 */
var plugin_asciinema = {

	loaded: false,
	loading: false,
	pending: [],

	base: function () {
		var base = (typeof DOKU_BASE === 'undefined') ? '/' : DOKU_BASE;
		return base + 'lib/plugins/asciinema/';
	},

	addStylesheet: function (href) {
		if (document.querySelector('link[href="' + href + '"]')) return;

		var link = document.createElement('link');
		link.rel = 'stylesheet';
		link.type = 'text/css';
		link.href = href;
		document.getElementsByTagName('head')[0].appendChild(link);
	},

	/**
	 * Fetch the player bundle once, queueing callbacks until it is ready
	 */
	load: function (callback) {
		if (this.loaded) {
			callback();
			return;
		}

		this.pending.push(callback);
		if (this.loading) return;
		this.loading = true;

		this.addStylesheet(this.base() + 'player/asciinema-player.css');

		var self = this;
		var script = document.createElement('script');
		script.src = this.base() + 'player/asciinema-player.min.js';
		script.onload = function () {
			self.loaded = true;
			self.loading = false;
			var queue = self.pending;
			self.pending = [];
			for (var i = 0; i < queue.length; i++) queue[i]();
		};
		script.onerror = function () {
			self.loading = false;
			self.pending = [];
		};
		document.getElementsByTagName('head')[0].appendChild(script);
	},

	/**
	 * The player measures the terminal font on creation, so a web font that is
	 * still loading would give it wrong dimensions
	 */
	whenFontReady: function (font, callback) {
		if (!font || !document.fonts || !document.fonts.load) {
			callback();
			return;
		}
		try {
			document.fonts.load(font).then(callback, callback);
		} catch (e) {
			callback();
		}
	},

	create: function (node) {
		var opts = {};
		try {
			opts = JSON.parse(node.getAttribute('data-asciinema-opts') || '{}');
		} catch (e) {
			opts = {};
		}

		try {
			AsciinemaPlayer.create(node.getAttribute('data-asciinema-src'), node, opts);
		} catch (e) {
			node.appendChild(document.createTextNode('asciinema: ' + e));
		}
	},

	init: function () {
		var nodes = document.querySelectorAll('.plugin_asciinema__player[data-asciinema-src]');
		var todo = [];
		var i;

		for (i = 0; i < nodes.length; i++) {
			if (nodes[i].getAttribute('data-asciinema-done')) continue;
			nodes[i].setAttribute('data-asciinema-done', '1');
			todo.push(nodes[i]);
		}
		if (!todo.length) return;

		var fontCss = todo[0].getAttribute('data-asciinema-fontcss');
		if (fontCss) this.addStylesheet(fontCss);

		var self = this;
		this.load(function () {
			self.whenFontReady(todo[0].getAttribute('data-asciinema-fontcheck'), function () {
				for (var j = 0; j < todo.length; j++) self.create(todo[j]);
			});
		});
	}
};

jQuery(function () {
	plugin_asciinema.init();

	// catches previews and section edits, which replace parts of the page
	jQuery(document).on('ajaxComplete', function () {
		plugin_asciinema.init();
	});
});
