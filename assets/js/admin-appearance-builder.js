(function($, window) {
	var settings = window.GFEmailApprovalsAppearanceSettings || {};
	var strings = settings.strings || {};

	function dispatchInputEvent(element) {
		if (element) {
			element.dispatchEvent(new Event('input', { bubbles: true }));
		}
	}

	$(document).on('click', '.gf-email-approvals-image-upload', function(e) {
		if ($(e.target).hasClass('gf-email-approvals-image-upload__remove')) {
			return;
		}

		var $wrapper = $(this);
		var $input = $wrapper.find('input[type="hidden"]');
		var $preview = $wrapper.find('.gf-email-approvals-image-upload__preview');

		var frame = wp.media({
			title: strings.selectLogo || 'Select Logo',
			multiple: false,
			library: { type: 'image' },
			button: { text: strings.useThisImage || 'Use this image' }
		});

		frame.on('select', function() {
			var attachment = frame.state().get('selection').first().toJSON();
			$input.val(attachment.url).trigger('change');
			$preview.attr('src', attachment.url);
			$wrapper.addClass('has-image');
			dispatchInputEvent($input[0]);
		});

		frame.open();
	});

	$(document).on('click', '.gf-email-approvals-image-upload__remove', function(e) {
		e.preventDefault();
		e.stopPropagation();

		var $wrapper = $(this).closest('.gf-email-approvals-image-upload');
		var $input = $wrapper.find('input[type="hidden"]');
		var $preview = $wrapper.find('.gf-email-approvals-image-upload__preview');

		$input.val('').trigger('change');
		$preview.attr('src', '');
		$wrapper.removeClass('has-image');
		dispatchInputEvent($input[0]);
	});

	$(document).on('click', '.gf-email-approvals-alignment__option', function() {
		var $option = $(this);
		var $wrapper = $option.closest('.gf-email-approvals-alignment');

		$wrapper.find('.gf-email-approvals-alignment__option').removeClass('active');
		$option.addClass('active');

		var $input = $option.find('input[type="radio"]');
		$input.prop('checked', true).trigger('change');
		dispatchInputEvent($input[0]);
	});

	$(document).on('input change', '.gf-email-approvals-dimension__range', function() {
		var $wrapper = $(this).closest('.gf-email-approvals-dimension');
		var input = $wrapper.find('.gf-email-approvals-dimension__input')[0];

		if (input) {
			input.value = this.value;
			dispatchInputEvent(input);
		}
	});

	$(document).on('input change', '.gf-email-approvals-dimension__input', function() {
		var $wrapper = $(this).closest('.gf-email-approvals-dimension');
		var $range = $wrapper.find('.gf-email-approvals-dimension__range');
		var value = parseFloat(this.value);
		var min = parseFloat($range.attr('min'));
		var max = parseFloat($range.attr('max'));

		if (!isNaN(value)) {
			if (value < min) {
				value = min;
			}

			if (value > max) {
				value = max;
			}

			$range.val(value);
		}
	});

	$(document).on('change', '.gf-email-approvals-dimension__unit', function() {
		var $wrapper = $(this).closest('.gf-email-approvals-dimension');
		var $range = $wrapper.find('.gf-email-approvals-dimension__range');
		var input = $wrapper.find('.gf-email-approvals-dimension__input')[0];
		var $selected = $(this).find('option:selected');
		var newMin = parseFloat($selected.attr('data-min'));
		var newMax = parseFloat($selected.attr('data-max'));
		var newStep = parseFloat($selected.attr('data-step'));
		var newDefault = parseFloat($selected.attr('data-default'));

		$range.attr({ min: newMin, max: newMax, step: newStep });

		if (input) {
			input.min = newMin;
			input.max = newMax;
			input.step = newStep;
			input.value = newDefault;
			$range.val(newDefault);
			dispatchInputEvent(input);
		}
	});

	document.addEventListener('click', function(event) {
		var header = event.target.closest('.gf-email-approvals-accordion__header');

		if (!header) {
			return;
		}

		event.preventDefault();

		var accordion = header.closest('.gf-email-approvals-accordion');

		if (!accordion) {
			return;
		}

		if (accordion.classList.contains('open')) {
			accordion.classList.remove('open', 'fully-open');
			return;
		}

		accordion.classList.add('open');

		setTimeout(function() {
			if (accordion.classList.contains('open')) {
				accordion.classList.add('fully-open');
			}
		}, 300);
	});

	function parseConfig($builder) {
		var raw = $builder.attr('data-config');

		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		} catch (error) {
			return null;
		}
	}

	function getScope($builder) {
		return $(document);
	}

	function findInput($builder, name) {
		var $scope = getScope($builder);
		var selectors = [
			'#' + name,
			'#_gaddon_setting_' + name,
			'[name="_gaddon_setting_' + name + '"]',
			'[id$="' + name + '"]',
			'[name$="' + name + '"]'
		];
		var $input = $();

		$.each(selectors, function(index, selector) {
			$input = $scope.find(selector).first();

			if ($input.length) {
				return false;
			}
		});

		return $input;
	}

	function getInputValue($builder, name) {
		var $input = findInput($builder, name);

		if (!$input.length) {
			return '';
		}

		if ($input.is(':radio')) {
			var radioName = $input.attr('name');
			var $checked = getScope($builder).find('input[name="' + radioName + '"]:checked');
			return $checked.length ? $checked.val() : '';
		}

		if ($input.is(':checkbox')) {
			return $input.is(':checked') ? $input.val() : '';
		}

		return $input.val();
	}

	function getFieldRow($builder, name) {
		return findInput($builder, name).closest('.gform-settings-field');
	}

	function getColorSettingNames(config) {
		return [
			config.settings.pageBackground,
			config.settings.cardBackground,
			config.settings.textColor,
			config.settings.titleColor,
			config.settings.approveButton,
			config.settings.rejectButton,
			config.settings.buttonText
		];
	}

	function refreshAllBuilders() {
		$('.gf-email-approvals-appearance-builder').each(function() {
			refresh($(this));
		});
	}

	window.refreshAllBuilders = refreshAllBuilders;

	function initColorPickers($builder, config) {
		if (!$.fn.wpColorPicker) {
			return;
		}

		$.each(getColorSettingNames(config), function(index, name) {
			var $input = findInput($builder, name);

			if (!$input.length || $input.data('gfEmailApprovalsColorPickerReady')) {
				return;
			}

			$input.wpColorPicker({
				width: 300,
				change: function() {
					setTimeout(refreshAllBuilders, 0);
				},
				clear: function() {
					setTimeout(refreshAllBuilders, 0);
				}
			});

			var $container = $input.parents('.wp-picker-container');

			$container.find('.wp-color-result').addClass('ed_button');

			if (!$container.find('.gf-email-approvals-color-edit-btn').length) {
				var $editButton = $('<button type="button" class="gf-email-approvals-color-edit-btn" aria-label="' + (strings.editColor || 'Edit color') + '"><span class="dashicons dashicons-edit"></span></button>');
				$container.append($editButton);
				$editButton.on('click', function(e) {
					e.preventDefault();
					$container.find('.wp-color-result').trigger('click');
				});
			}

			$input.data('gfEmailApprovalsColorPickerReady', true);
		});
	}

	function applySettingsLayout($builder, config) {
		var names = [
			config.settings.pageBackground,
			config.settings.cardBackground,
			config.settings.textColor,
			config.settings.titleColor,
			config.settings.approveButton,
			config.settings.rejectButton,
			config.settings.buttonText
		];
		var colorSettingNames = getColorSettingNames(config);
		var baseColorSettingNames = [
			config.settings.pageBackground,
			config.settings.cardBackground,
			config.settings.textColor,
			config.settings.titleColor
		];
		var buttonColorSettingNames = [
			config.settings.approveButton,
			config.settings.rejectButton,
			config.settings.buttonText
		];
		var $rows = $();

		$.each(names, function(index, name) {
			var $row = getFieldRow($builder, name);

			if (!$row.length) {
				return;
			}

			$row
				.addClass('gf-email-approvals-appearance-setting gf-email-approvals-settings-field-wrapper gf-email-approvals-settings-field-wrapper-' + name)
				.attr('data-setting-name', name);

			if ($.inArray(name, colorSettingNames) !== -1) {
				$row.addClass('gf-email-approvals-appearance-setting--color');
			}

			if ($.inArray(name, baseColorSettingNames) !== -1) {
				$row.addClass('gf-email-approvals-appearance-setting--base-color');
			}

			if ($.inArray(name, buttonColorSettingNames) !== -1) {
				$row.addClass('gf-email-approvals-appearance-setting--button-color');
			}

			$rows = $rows.add($row);
		});

		var $previewRow = $builder.closest('.gform-settings-field');

		if ($previewRow.length) {
			$previewRow.addClass('gf-email-approvals-appearance-preview-row');
		}

		if ($rows.length) {
			var $grid = $rows.first().parent();

			$grid.addClass('gf-email-approvals-appearance-grid');
			$grid.children('.gf-email-approvals-appearance-row-break').remove();

			var $firstButtonColorRow = getFieldRow($builder, buttonColorSettingNames[0]);

			if ($firstButtonColorRow.length) {
				$('<div class="gf-email-approvals-appearance-row-break" aria-hidden="true"></div>').insertBefore($firstButtonColorRow);
			}
		}
	}

	function sanitizeColor(value, fallback) {
		value = (value || '').toString().trim();

		return /^#(?:[0-9a-fA-F]{3}){1,2}$/.test(value) ? value : fallback;
	}

	function sanitizeNumber(value, fallback) {
		var number = parseFloat(value);

		if (isNaN(number)) {
			number = fallback;
		}

		return number;
	}

	function hexToRgba(hex, alpha, fallback) {
		hex = sanitizeColor(hex, '');

		if (!hex) {
			return fallback;
		}

		var normalized = hex.replace('#', '');

		if (normalized.length === 3) {
			normalized = normalized.replace(/(.)/g, '$1$1');
		}

		var red = parseInt(normalized.substring(0, 2), 16);
		var green = parseInt(normalized.substring(2, 4), 16);
		var blue = parseInt(normalized.substring(4, 6), 16);

		if ([red, green, blue].some(isNaN)) {
			return fallback;
		}

		return 'rgba(' + red + ',' + green + ',' + blue + ',' + alpha + ')';
	}

	function applyTheme($builder, config) {
		var defaults = config.defaults || {};
		var configSettings = config.settings || {};
		var pageBackground = sanitizeColor(getInputValue($builder, configSettings.pageBackground), defaults.pageBackground);
		var cardBackground = sanitizeColor(getInputValue($builder, configSettings.cardBackground), defaults.cardBackground);
		var textColor = sanitizeColor(getInputValue($builder, configSettings.textColor), defaults.textColor);
		var titleColor = sanitizeColor(getInputValue($builder, configSettings.titleColor), defaults.titleColor);
		var titleAlignment = getInputValue($builder, configSettings.titleAlignment) || defaults.titleAlignment || 'left';
		var titleFontSize = sanitizeNumber(getInputValue($builder, configSettings.titleFontSize), defaults.titleFontSize);
		var titleFontSizeUnit = getInputValue($builder, configSettings.titleFontSizeUnit) || defaults.titleFontSizeUnit || 'px';
		var messageAlignment = getInputValue($builder, configSettings.messageAlignment) || defaults.messageAlignment || 'left';
		var messageFontSize = sanitizeNumber(getInputValue($builder, configSettings.messageFontSize), defaults.messageFontSize);
		var messageFontUnit = getInputValue($builder, configSettings.messageFontUnit) || defaults.messageFontUnit || 'px';
		var approveButton = sanitizeColor(getInputValue($builder, configSettings.approveButton), defaults.approveButton);
		var rejectButton = sanitizeColor(getInputValue($builder, configSettings.rejectButton), defaults.rejectButton);
		var buttonText = sanitizeColor(getInputValue($builder, configSettings.buttonText), defaults.buttonText);
		var cardWidth = sanitizeNumber(getInputValue($builder, configSettings.cardWidth), defaults.cardWidth);
		var cardWidthUnit = getInputValue($builder, configSettings.cardWidthUnit) || defaults.cardWidthUnit || 'px';
		var cardPadding = sanitizeNumber(getInputValue($builder, configSettings.cardPadding), defaults.cardPadding);
		var cardPaddingUnit = getInputValue($builder, configSettings.cardPaddingUnit) || defaults.cardPaddingUnit || 'px';
		var cardRadius = sanitizeNumber(getInputValue($builder, configSettings.cardRadius), defaults.cardRadius);
		var cardRadiusUnit = getInputValue($builder, configSettings.cardRadiusUnit) || defaults.cardRadiusUnit || 'px';

		var logoImage = getInputValue($builder, configSettings.logoImage);
		var logoAlignment = getInputValue($builder, configSettings.logoAlignment) || defaults.logoAlignment || 'center';
		var logoMaxHeight = sanitizeNumber(getInputValue($builder, configSettings.logoMaxHeight), defaults.logoMaxHeight);
		var logoMaxHeightUnit = getInputValue($builder, configSettings.logoMaxHeightUnit) || defaults.logoMaxHeightUnit || 'px';

		$builder[0].style.setProperty('--gf-email-approvals-page-bg', pageBackground);
		$builder[0].style.setProperty('--gf-email-approvals-card-bg', cardBackground);
		$builder[0].style.setProperty('--gf-email-approvals-text', textColor);
		$builder[0].style.setProperty('--gf-email-approvals-title', titleColor);
		$builder[0].style.setProperty('--gf-email-approvals-title-align', titleAlignment);
		$builder[0].style.setProperty('--gf-email-approvals-title-size', titleFontSize + titleFontSizeUnit);
		$builder[0].style.setProperty('--gf-email-approvals-message-align', messageAlignment);
		$builder[0].style.setProperty('--gf-email-approvals-message-size', messageFontSize + messageFontUnit);
		$builder[0].style.setProperty('--gf-email-approvals-approve', approveButton);
		$builder[0].style.setProperty('--gf-email-approvals-reject', rejectButton);
		$builder[0].style.setProperty('--gf-email-approvals-button-text', buttonText);
		$builder[0].style.setProperty('--gf-email-approvals-card-width', cardWidth + cardWidthUnit);
		$builder[0].style.setProperty('--gf-email-approvals-card-padding', cardPadding + cardPaddingUnit);
		$builder[0].style.setProperty('--gf-email-approvals-card-radius', cardRadius + cardRadiusUnit);
		$builder[0].style.setProperty('--gf-email-approvals-shadow', hexToRgba(textColor, 0.12, 'rgba(29,35,39,0.12)'));
		$builder[0].style.setProperty('--gf-email-approvals-logo-align', logoAlignment);
		$builder[0].style.setProperty('--gf-email-approvals-logo-height', logoMaxHeight + logoMaxHeightUnit);

		var $logoImg = $builder.find('.gf-email-approvals-appearance-builder__logo-img');
		var $logoWrapper = $builder.find('.gf-email-approvals-appearance-builder__logo');

		if (logoImage) {
			$logoImg.attr('src', logoImage);
			$logoWrapper.show();
		} else {
			$logoWrapper.hide();
		}
	}

	function applyState($builder, stateName) {
		var config = parseConfig($builder);

		if (!config || !config.states || !config.states[stateName]) {
			return;
		}

		var state = config.states[stateName];
		var variantColor = state.variant === 'reject'
			? getComputedStyle($builder[0]).getPropertyValue('--gf-email-approvals-reject')
			: getComputedStyle($builder[0]).getPropertyValue('--gf-email-approvals-approve');

		$builder.attr('data-preview-state', stateName);
		$builder[0].style.setProperty('--gf-email-approvals-active-button', variantColor);
		$builder.find('[data-preview-title]').text(state.title);
		$builder.find('[data-preview-message]').text(state.message);
		$builder.find('[data-preview-button]').text(state.button).prop('hidden', !state.showButton);
		$builder.find('[data-preview-field]').prop('hidden', !state.showField);
		$builder.find('[data-preview-state]').removeClass('is-active');
		$builder.find('[data-preview-state="' + stateName + '"]').addClass('is-active');
	}

	function refresh($builder) {
		var config = parseConfig($builder);

		if (!config) {
			return;
		}

		applySettingsLayout($builder, config);
		initColorPickers($builder, config);
		applyTheme($builder, config);
		applyState($builder, $builder.attr('data-preview-state') || 'approve');
	}

	$(document).on('click', '.gf-email-approvals-appearance-builder [data-preview-state]', function() {
		var $button = $(this);
		var $builder = $button.closest('.gf-email-approvals-appearance-builder');

		applyState($builder, $button.attr('data-preview-state'));
	});

	$(document).on('input change', '.gform-settings-panel input, .gform-settings-panel textarea, .gform-settings-panel select, form input, form textarea, form select', function() {
		refreshAllBuilders();
	});

	$(function() {
		refreshAllBuilders();
	});
})(jQuery, window);