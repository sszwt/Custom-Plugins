(function ($) {
	'use strict';

	function setStatus($el, message, type) {
		$el
			.text(message)
			.removeClass('is-success is-error')
			.addClass(type ? 'is-' + type : '');
	}

	$(document).on('click', '.aiio-optimize-btn', function () {
		var $btn = $(this);
		var attachmentId = $btn.data('attachment-id');
		var force = $btn.data('force') ? 1 : 0;
		var $status = $('#aiio-status-' + attachmentId);

		if (!$status.length) {
			$status = $btn.siblings('.aiio-optimize-status');
		}

		if (!$status.length) {
			$status = $('<span class="aiio-optimize-status"></span>').insertAfter($btn);
		}

		if (!attachmentId) {
			return;
		}

		$btn.addClass('is-loading').prop('disabled', true);
		setStatus($status, aiioAdmin.i18n.optimizing, '');

		$.post(aiioAdmin.ajaxUrl, {
			action: 'aiio_optimize',
			nonce: aiioAdmin.nonce,
			attachment_id: attachmentId,
			force: force
		})
			.done(function (response) {
				if (response.success && response.data) {
					setStatus($status, response.data.message || aiioAdmin.i18n.success, 'success');

					var $badge = $btn.closest('td').find('.aiio-badge');
					if ($badge.length && response.data.stats) {
						$badge
							.removeClass('aiio-badge--pending')
							.addClass('aiio-badge--ok')
							.text('-' + (response.data.stats.saved_percent || 0) + '%');
						$btn.remove();
					}
				} else {
					var message = (response.data && response.data.message) || aiioAdmin.i18n.error;
					setStatus($status, message, 'error');
				}
			})
			.fail(function () {
				setStatus($status, aiioAdmin.i18n.error, 'error');
			})
			.always(function () {
				$btn.removeClass('is-loading').prop('disabled', false);
			});
	});

	$('#aiio-bulk-optimize').on('click', function (e) {
		e.preventDefault();
		e.stopPropagation();

		var $btn = $(this);
		var $status = $('#aiio-bulk-status');
		var totalUpdated = 0;
		var totalSaved = 0;
		var lastRemaining = null;
		var stalledBatches = 0;
		var maxBatches = 200;

		$btn.prop('disabled', true).addClass('is-loading');
		setStatus($status, aiioAdmin.i18n.optimizing, '');

		function finish(message, type) {
			setStatus($status, message, type || 'success');
			$btn.prop('disabled', false).removeClass('is-loading');
			// Refresh dashboard counts after bulk run.
			window.setTimeout(function () {
				window.location.reload();
			}, 900);
		}

		function runBatch(batchIndex) {
			if (batchIndex >= maxBatches) {
				finish(
					aiioAdmin.i18n.bulkDone +
						' (' +
						totalUpdated +
						' images, ' +
						(lastRemaining || 0) +
						' remaining)',
					'success'
				);
				return;
			}

			$.post(aiioAdmin.ajaxUrl, {
				action: 'aiio_bulk_optimize',
				nonce: aiioAdmin.nonce,
				batch: 5
			})
				.done(function (response) {
					if (!response.success || !response.data) {
						setStatus($status, aiioAdmin.i18n.error, 'error');
						$btn.prop('disabled', false).removeClass('is-loading');
						return;
					}

					var remaining = response.data.remaining || 0;
					totalUpdated += response.data.updated || 0;
					totalSaved += response.data.saved || 0;

					if (lastRemaining !== null && remaining >= lastRemaining && (response.data.updated || 0) === 0) {
						stalledBatches += 1;
					} else {
						stalledBatches = 0;
					}
					lastRemaining = remaining;

					if (response.data.done || response.data.processed === 0 || remaining === 0 || stalledBatches >= 2) {
						finish(
							aiioAdmin.i18n.bulkDone +
								' (' +
								totalUpdated +
								' images, ~' +
								(response.data.savedHuman || totalSaved + ' B') +
								' this run, ' +
								remaining +
								' remaining)',
							'success'
						);
						return;
					}

					setStatus(
						$status,
						aiioAdmin.i18n.optimizing +
							' ' +
							totalUpdated +
							' done... (' +
							remaining +
							' remaining)',
						''
					);

					runBatch(batchIndex + 1);
				})
				.fail(function () {
					setStatus($status, aiioAdmin.i18n.error, 'error');
					$btn.prop('disabled', false).removeClass('is-loading');
				});
		}

		runBatch(0);
	});

	$('#aiio_api_provider').on('change', function () {
		var provider = $(this).val();
		$('#aiio-openai-panel').toggle(provider === 'openai');
		$('#aiio-gemini-panel').toggle(provider === 'gemini');
		$('#aiio-test-result').empty();
	});

	$('#aiio-test-api').on('click', function () {
		var $btn = $(this);
		var $result = $('#aiio-test-result');

		$btn.prop('disabled', true);
		$result.text(aiioAdmin.i18n.testing);

		$.post(aiioAdmin.ajaxUrl, {
			action: 'aiio_test_api',
			nonce: aiioAdmin.nonce
		})
			.done(function (response) {
				if (response.success) {
					$result.html('<span class="aiio-badge aiio-badge--ok">' + response.data.message + '</span>');
				} else {
					$result.html(
						'<span class="aiio-badge aiio-badge--pending">' +
							((response.data && response.data.message) || aiioAdmin.i18n.error) +
							'</span>'
					);
				}
			})
			.fail(function () {
				$result.html('<span class="aiio-badge aiio-badge--pending">' + aiioAdmin.i18n.error + '</span>');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$('#aiio-clear-cache').on('click', function () {
		var $btn = $(this);
		var $status = $('#aiio-cache-status');

		$btn.prop('disabled', true);
		setStatus($status, aiioAdmin.i18n.clearing, '');

		$.post(aiioAdmin.ajaxUrl, {
			action: 'aiio_clear_cache',
			nonce: aiioAdmin.nonce
		})
			.done(function (response) {
				if (response.success) {
					setStatus($status, response.data.message || aiioAdmin.i18n.cacheCleared, 'success');
				} else {
					setStatus($status, (response.data && response.data.message) || aiioAdmin.i18n.error, 'error');
				}
			})
			.fail(function () {
				setStatus($status, aiioAdmin.i18n.error, 'error');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});

	$('#aiio-bulk-alt').on('click', function () {
		var $btn = $(this);
		var $status = $('#aiio-alt-status');
		var totalUpdated = 0;

		$btn.prop('disabled', true);
		setStatus($status, aiioAdmin.i18n.altRunning, '');

		function runBatch() {
			$.post(aiioAdmin.ajaxUrl, {
				action: 'aiio_bulk_alt',
				nonce: aiioAdmin.nonce,
				batch: 5
			})
				.done(function (response) {
					if (!response.success || !response.data) {
						setStatus($status, aiioAdmin.i18n.error, 'error');
						$btn.prop('disabled', false);
						return;
					}

					totalUpdated += response.data.updated || 0;

					if (response.data.done || response.data.processed === 0) {
						setStatus(
							$status,
							aiioAdmin.i18n.altDone +
								' (' +
								totalUpdated +
								' updated, ' +
								(response.data.remaining || 0) +
								' remaining)',
							'success'
						);
						$btn.prop('disabled', false);
						return;
					}

					setStatus(
						$status,
						aiioAdmin.i18n.altRunning +
							' ' +
							totalUpdated +
							' done... (' +
							(response.data.remaining || 0) +
							' remaining)',
						''
					);
					runBatch();
				})
				.fail(function () {
					setStatus($status, aiioAdmin.i18n.error, 'error');
					$btn.prop('disabled', false);
				});
		}

		runBatch();
	});
})(jQuery);
