/**
 * Palette switcher.
 *
 * Applies a palette by rewriting the :root custom properties on
 * documentElement, so the change is instant and does not reload the page. The
 * server renders the same block for the request (via wp_add_inline_style), so a
 * visitor who never touches this still gets a correct first paint -- this file
 * only takes over afterwards.
 *
 * The cookie is what survives navigation. Without it, choosing Night and then
 * opening a product page would silently revert, because the server has no other
 * record of the choice and there is no login involved.
 */
(function () {
	'use strict';

	var cfg = window.lylyrosePalette;
	if (!cfg || !cfg.enabled || !cfg.choices || cfg.choices.length < 2) {
		return;
	}

	var root = document.documentElement;
	var current = cfg.active;

	// The server already rendered the right palette for this request, so do not
	// write a second identical block on load.
	function apply(choice, persist) {
		if (!choice || !choice.css) {
			return;
		}
		var style = document.getElementById('lylyrose-palette-live');
		if (!style) {
			style = document.createElement('style');
			style.id = 'lylyrose-palette-live';
			document.head.appendChild(style);
		}
		style.textContent = choice.css;
		root.setAttribute('data-palette', choice.slug);
		current = choice.slug;

		if (persist) {
			// SameSite=Lax so the cookie rides normal navigation but is not sent
			// on cross-site requests, and one year so a visitor is not asked again.
			document.cookie = cfg.cookie + '=' + encodeURIComponent(choice.slug) +
				';path=/;max-age=31536000;samesite=lax';
		}

		var buttons = document.querySelectorAll('[data-dk-palette]');
		for (var i = 0; i < buttons.length; i++) {
			var on = buttons[i].getAttribute('data-dk-palette') === choice.slug;
			buttons[i].setAttribute('aria-pressed', on ? 'true' : 'false');
		}
	}

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	ready(function () {
		var buttons = document.querySelectorAll('[data-dk-palette]');
		if (!buttons.length) {
			return;
		}

		for (var i = 0; i < buttons.length; i++) {
			buttons[i].addEventListener('click', function (ev) {
				var slug = ev.currentTarget.getAttribute('data-dk-palette');
				for (var j = 0; j < cfg.choices.length; j++) {
					if (cfg.choices[j].slug === slug) {
						apply(cfg.choices[j], true);
						break;
					}
				}
			});
		}

		// Mark the active swatch without writing a redundant cookie.
		for (var k = 0; k < buttons.length; k++) {
			buttons[k].setAttribute(
				'aria-pressed',
				buttons[k].getAttribute('data-dk-palette') === current ? 'true' : 'false'
			);
		}
	});
})();