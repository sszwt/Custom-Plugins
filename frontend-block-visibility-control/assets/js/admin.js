(function ($) {
	'use strict';

	$(document).ready(function () {
		var $nav = $('.fbv-nav__btn');
		var $panes = $('[data-fbv-pane]');
		var $search = $('#fbv-block-search');
		var $grid = $('#fbv-block-grid');
		var $meta = $('#fbv-block-count');

		$nav.on('click', function () {
			var tab = $(this).data('fbv-tab');
			$nav.removeClass('is-active');
			$(this).addClass('is-active');
			$panes.removeClass('is-active');
			$panes.filter('[data-fbv-pane="' + tab + '"]').addClass('is-active');
		});

		if (!$grid.length) {
			return;
		}

		function updateSelectedCount() {
			var n = $grid.find('input[type="checkbox"]:checked').length;
			if ($meta.length) {
				$meta.text(n + ' selected');
			}
		}

		$search.on('input', function () {
			var q = String($(this).val() || '').toLowerCase().trim();
			$grid.find('.fbv-chip').each(function () {
				var hay = String($(this).attr('data-search') || '');
				$(this).toggleClass('is-hidden', q !== '' && hay.indexOf(q) === -1);
			});
		});

		$grid.on('change', 'input[type="checkbox"]', function () {
			$(this).closest('.fbv-chip').toggleClass('is-on', this.checked);
			updateSelectedCount();
		});
	});
})(jQuery);
