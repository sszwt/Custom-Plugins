(function (wp, $) {
	'use strict';

	if (!wp || !wp.hooks || !wp.element || !wp.components || !wp.blockEditor) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;
	var BlockControls = wp.blockEditor.BlockControls;
	var ToolbarButton = wp.components.ToolbarButton;

	var SKIP_ACF_TYPES = {
		group: true,
		tab: true,
		accordion: true,
		message: true,
		separator: true,
		clone: true,
		flexible_content: true,
		repeater: true
	};

	function defaultVisibility() {
		return {
			hidden: false,
			hiddenFields: []
		};
	}

	function mergeVisibility(attrs) {
		return Object.assign({}, defaultVisibility(), attrs || {});
	}

	function addAttributes(blockSettings) {
		if (!blockSettings.attributes) {
			blockSettings.attributes = {};
		}

		blockSettings.attributes.blockVisibility = {
			type: 'object',
			default: defaultVisibility()
		};

		return blockSettings;
	}

	wp.hooks.addFilter('blocks.registerBlockType', 'fbv-control/add-attributes', addAttributes);

	var withToolbarVisibilityButton = createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			var blockVisibility = mergeVisibility(props.attributes.blockVisibility);
			var isHidden = !!blockVisibility.hidden;

			return el(
				Fragment,
				null,
				el(BlockEdit, props),
				el(
					BlockControls,
					{ group: 'other' },
					el(ToolbarButton, {
						icon: isHidden ? 'hidden' : 'visibility',
						label: isHidden ? 'Show on live site' : 'Hide on live site',
						title: isHidden ? 'Hidden on live site — click to show' : 'Visible on live site — click to hide',
						isActive: isHidden,
						className: isHidden ? 'fbv-toolbar-btn-hidden' : 'fbv-toolbar-btn-visible',
						onClick: function () {
							props.setAttributes({
								blockVisibility: Object.assign({}, blockVisibility, { hidden: !isHidden })
							});
						}
					})
				)
			);
		};
	}, 'withToolbarVisibilityButton');

	wp.hooks.addFilter('editor.BlockEdit', 'fbv-control/toolbar-eye-button', withToolbarVisibilityButton);

	var withHiddenBlockClass = createHigherOrderComponent(function (BlockListBlock) {
		return function (props) {
			var blockVisibility = mergeVisibility((props.attributes || {}).blockVisibility);
			var isHidden = !!blockVisibility.hidden;
			var wrapperProps = props.wrapperProps || {};

			if (isHidden) {
				var existingClass = wrapperProps.className || '';
				wrapperProps = Object.assign({}, wrapperProps, {
					className: (existingClass + ' fbv-editor-hidden-block').trim()
				});
			}

			return el(BlockListBlock, Object.assign({}, props, { wrapperProps: wrapperProps }));
		};
	}, 'withHiddenBlockClass');

	wp.hooks.addFilter('editor.BlockListBlock', 'fbv-control/hidden-block-wrapper', withHiddenBlockClass);

	if ($) {
		function getFieldType($field) {
			var type = $field.data('type');
			if (type) {
				return String(type);
			}
			var className = $field.attr('class') || '';
			var match = className.match(/acf-field-([a-z0-9_-]+)/i);
			return match ? match[1].replace(/-/g, '_') : '';
		}

		function shouldSkipField($field) {
			var type = getFieldType($field);
			if (SKIP_ACF_TYPES[type]) {
				return true;
			}
			if (
				$field.hasClass('acf-field-group') ||
				$field.hasClass('acf-field-tab') ||
				$field.hasClass('acf-field-accordion') ||
				$field.hasClass('acf-field-message') ||
				$field.hasClass('acf-field-separator') ||
				$field.hasClass('acf-field-clone') ||
				$field.hasClass('acf-field-flexible-content') ||
				$field.hasClass('acf-field-repeater')
			) {
				return true;
			}
			var name = $field.data('name');
			if (!name || name === 'group' || name === '') {
				return true;
			}
			return false;
		}

		function syncFieldEyeState($field, $btn) {
			var selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
			var fieldName = $field.data('name');
			var hiddenFields = [];

			if (selectedBlock && selectedBlock.attributes && selectedBlock.attributes.blockVisibility) {
				hiddenFields = selectedBlock.attributes.blockVisibility.hiddenFields || [];
			}

			var isHidden = fieldName && hiddenFields.indexOf(fieldName) !== -1;
			$field.toggleClass('fbv-field-hidden', !!isHidden);
			$btn.toggleClass('is-hidden', !!isHidden);
			$btn.attr('aria-pressed', isHidden ? 'true' : 'false');
			$btn.attr('title', isHidden ? 'Show this field on the live site' : 'Hide this field on the live site');
		}

		function attachAcfFieldEyes() {
			$('.acf-field').each(function () {
				var $field = $(this);
				if (shouldSkipField($field)) {
					$field.find('> .acf-label .fbv-field-eye-btn, > .acf-label label .fbv-field-eye-btn').remove();
					$field.removeClass('fbv-field-hidden');
				}
			});

			$('.acf-field').each(function () {
				var $field = $(this);
				if (shouldSkipField($field)) {
					return;
				}

				var $label = $field.find('> .acf-label label').first();
				if (!$label.length) {
					$label = $field.find('> .acf-label').first();
				}
				if (!$label.length) {
					return;
				}

				var fieldName = $field.data('name');
				if (!fieldName) {
					return;
				}

				var $btn = $label.find('.fbv-field-eye-btn').first();
				if (!$btn.length) {
					$btn = $(
						'<button type="button" class="fbv-field-eye-btn" aria-label="Toggle field visibility on live site">' +
							'<span class="fbv-field-eye-btn__icon" aria-hidden="true"></span>' +
						'</button>'
					);
					$label.append($btn);

					$btn.on('click', function (e) {
						e.preventDefault();
						e.stopPropagation();

						var selectedBlock = wp.data.select('core/block-editor').getSelectedBlock();
						if (!selectedBlock) {
							return;
						}

						var attrs = selectedBlock.attributes || {};
						var blockVisibility = mergeVisibility(attrs.blockVisibility);
						var hiddenFields = Array.isArray(blockVisibility.hiddenFields)
							? blockVisibility.hiddenFields.slice()
							: [];
						var idx = hiddenFields.indexOf(fieldName);
						var willHide = idx === -1;

						if (willHide) {
							hiddenFields.push(fieldName);
						} else {
							hiddenFields.splice(idx, 1);
						}

						wp.data.dispatch('core/block-editor').updateBlockAttributes(selectedBlock.clientId, {
							blockVisibility: Object.assign({}, blockVisibility, { hiddenFields: hiddenFields })
						});

						syncFieldEyeState($field, $btn);
					});
				}

				syncFieldEyeState($field, $btn);
			});
		}

		setInterval(attachAcfFieldEyes, 800);
	}
})(window.wp, window.jQuery);
