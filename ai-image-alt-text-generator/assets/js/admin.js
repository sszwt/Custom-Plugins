(function ($) {
	'use strict';

	function setStatus($el, message, type) {
		$el
			.text(message)
			.removeClass('is-success is-error')
			.addClass(type ? 'is-' + type : '');
	}

	$(document).on('click', '.aiatg-generate-btn', function () {
		var $btn = $(this);
		var attachmentId = $btn.data('attachment-id');
		var $status = $('#aiatg-status-' + attachmentId);
		var $altField = $('#attachments-' + attachmentId + '-alt');

		if (!attachmentId) {
			return;
		}

		$btn.addClass('is-loading').prop('disabled', true);
		setStatus($status, aiatgAdmin.i18n.generating, '');

		$.post(aiatgAdmin.ajaxUrl, {
			action: 'aiatg_generate_alt',
			nonce: aiatgAdmin.nonce,
			attachment_id: attachmentId,
			force: 1
		})
			.done(function (response) {
				if (response.success && response.data) {
					if ($altField.length) {
						$altField.val(response.data.alt_text);
					}

					setStatus($status, response.data.message || aiatgAdmin.i18n.success, 'success');
				} else {
					var message = (response.data && response.data.message) || aiatgAdmin.i18n.error;
					setStatus($status, message, 'error');
				}
			})
			.fail(function () {
				setStatus($status, aiatgAdmin.i18n.error, 'error');
			})
			.always(function () {
				$btn.removeClass('is-loading').prop('disabled', false);
			});
	});

	$('#aiatg-bulk-generate').on('click', function () {
		var $btn = $(this);
		var $status = $('#aiatg-bulk-status');
		var totalUpdated = 0;

		$btn.prop('disabled', true);
		setStatus($status, aiatgAdmin.i18n.generating, '');

		function runBatch() {
			$.post(aiatgAdmin.ajaxUrl, {
				action: 'aiatg_bulk_generate',
				nonce: aiatgAdmin.nonce,
				batch: 5
			})
				.done(function (response) {
					if (!response.success || !response.data) {
						setStatus($status, aiatgAdmin.i18n.error, 'error');
						$btn.prop('disabled', false);
						return;
					}

					totalUpdated += response.data.updated || 0;

					if (response.data.done || response.data.processed === 0) {
						setStatus(
							$status,
							aiatgAdmin.i18n.bulkDone + ' (' + totalUpdated + ' updated, ' + (response.data.remaining || 0) + ' remaining)',
							'success'
						);
						$btn.prop('disabled', false);
						return;
					}

					setStatus(
						$status,
						aiatgAdmin.i18n.generating + ' ' + totalUpdated + ' updated... (' + (response.data.remaining || 0) + ' remaining)',
						''
					);

					runBatch();
				})
				.fail(function () {
					setStatus($status, aiatgAdmin.i18n.error, 'error');
					$btn.prop('disabled', false);
				});
		}

		runBatch();
	});

	$('#aiatg_api_provider').on('change', function () {
		var provider = $(this).val();
		$('#aiatg-openai-panel').toggle(provider === 'openai');
		$('#aiatg-gemini-panel').toggle(provider === 'gemini');
		$('#aiatg-test-result').empty();
	});

	$('#aiatg-test-api').on('click', function () {
		var $btn = $(this);
		var $result = $('#aiatg-test-result');

		$btn.prop('disabled', true);
		$result.text(aiatgAdmin.i18n.testing);

		$.post(aiatgAdmin.ajaxUrl, {
			action: 'aiatg_test_api',
			nonce: aiatgAdmin.nonce
		})
			.done(function (response) {
				if (response.success) {
					$result.html('<span class="aiatg-badge aiatg-badge--ok">' + response.data.message + '</span>');
				} else {
					$result.html('<span class="aiatg-badge aiatg-badge--missing">' + (response.data?.message || aiatgAdmin.i18n.error) + '</span>');
				}
			})
			.fail(function () {
				$result.html('<span class="aiatg-badge aiatg-badge--missing">' + aiatgAdmin.i18n.error + '</span>');
			})
			.always(function () {
				$btn.prop('disabled', false);
			});
	});
})(jQuery);
