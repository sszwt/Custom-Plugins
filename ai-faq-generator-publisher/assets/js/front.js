/**
 * Frontend accordion — smooth slide up / down.
 */
(function () {
	'use strict';

	function closeItem(item) {
		var trigger = item.querySelector('[data-aifaq-trigger]');
		var panel = item.querySelector('[data-aifaq-panel]');
		if (!trigger || !panel) return;
		item.classList.remove('is-open');
		trigger.setAttribute('aria-expanded', 'false');
		panel.setAttribute('hidden', '');
	}

	function openItem(item) {
		var trigger = item.querySelector('[data-aifaq-trigger]');
		var panel = item.querySelector('[data-aifaq-panel]');
		if (!trigger || !panel) return;
		item.classList.add('is-open');
		trigger.setAttribute('aria-expanded', 'true');
		panel.removeAttribute('hidden');
	}

	function initFaq(root) {
		if (root.getAttribute('data-aifaq-ready')) return;
		root.setAttribute('data-aifaq-ready', '1');

		var allowMultiple = root.getAttribute('data-allow-multiple') === '1';
		var items = Array.prototype.slice.call(root.querySelectorAll('[data-aifaq-item]'));

		items.forEach(function (item) {
			var trigger = item.querySelector('[data-aifaq-trigger]');
			if (!trigger) return;

			trigger.addEventListener('click', function () {
				var isOpen = item.classList.contains('is-open');

				if (!allowMultiple) {
					items.forEach(function (other) {
						if (other !== item) closeItem(other);
					});
				}

				if (isOpen) {
					closeItem(item);
				} else {
					openItem(item);
				}
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-aifaq]').forEach(initFaq);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
