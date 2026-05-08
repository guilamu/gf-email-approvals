(function($, window) {
	var settings = window.GFEmailApprovalsNotificationSettings || null;

	if (!settings || !settings.fieldNames) {
		return;
	}

	var fieldConfig = settings.fieldConfig || {};
	var targetFieldChoices = settings.targetFieldChoices || {};
	var fieldNames = settings.fieldNames || {};
	var strings = settings.strings || {};

	function getFieldInput(name) {
		if (!name) {
			return $();
		}

		return $('#' + name);
	}

	function getFieldRow(name) {
		return getFieldInput(name).closest('.gform-settings-field');
	}

	function escapeHtml(value) {
		return $('<div />').text(value || '').html();
	}

	function getChoiceLabel(choice) {
		return choice && typeof choice.label !== 'undefined' ? choice.label : '';
	}

	function getChoiceValue(choice) {
		return choice && typeof choice.value !== 'undefined' ? choice.value : '';
	}

	function setSelectChoices(name, choices, multiple, emptyLabel) {
		var $input = getFieldInput(name);

		if (!$input.length) {
			return;
		}

		var selected = $input.val();

		if (multiple) {
			selected = Array.isArray(selected) ? selected : (selected ? [selected] : []);
		} else {
			selected = selected || '';
		}

		var html = '';

		if (!multiple) {
			html += '<option value="">' + escapeHtml(emptyLabel || strings.leaveUnchanged || '') + '</option>';
		}

		(choices || []).forEach(function(choice) {
			html += '<option value="' + escapeHtml(getChoiceValue(choice)) + '">' + escapeHtml(getChoiceLabel(choice)) + '</option>';
		});

		$input.html(html);

		if (multiple) {
			var validValues = selected.filter(function(value) {
				return (choices || []).some(function(choice) {
					return getChoiceValue(choice) === value;
				});
			});

			$input.val(validValues);
			return;
		}

		var isValid = (choices || []).some(function(choice) {
			return getChoiceValue(choice) === selected;
		});

		$input.val(isValid ? selected : '');
	}

	function toggleDecisionUpdateFields() {
		var updateMode = getFieldInput(fieldNames.mode).val() || '';
		var isAutomatic = updateMode === settings.automaticMode;
		var hasUpdateMode = updateMode !== '';
		var targetChoices = hasUpdateMode && targetFieldChoices[updateMode] ? targetFieldChoices[updateMode] : [];

		setSelectChoices(fieldNames.target, targetChoices, false, strings.doNotUpdateField || '');

		var targetFieldId = hasUpdateMode ? (getFieldInput(fieldNames.target).val() || '') : '';
		var config = fieldConfig[targetFieldId] || null;
		var kind = config ? config.kind : '';
		var choices = config ? config.choices : [];

		setSelectChoices(fieldNames.approvedChoice, choices, false, strings.leaveUnchanged || '');
		setSelectChoices(fieldNames.rejectedChoice, choices, false, strings.leaveUnchanged || '');
		setSelectChoices(fieldNames.approvedChoices, choices, true);
		setSelectChoices(fieldNames.rejectedChoices, choices, true);

		getFieldRow(fieldNames.target).toggle(hasUpdateMode);
		getFieldRow(fieldNames.approvedText).toggle(isAutomatic && kind === 'text');
		getFieldRow(fieldNames.rejectedText).toggle(isAutomatic && kind === 'text');
		getFieldRow(fieldNames.approvedChoice).toggle(isAutomatic && kind === 'single');
		getFieldRow(fieldNames.rejectedChoice).toggle(isAutomatic && kind === 'single');
		getFieldRow(fieldNames.approvedChoices).toggle(isAutomatic && kind === 'multi');
		getFieldRow(fieldNames.rejectedChoices).toggle(isAutomatic && kind === 'multi');
	}

	$(function() {
		toggleDecisionUpdateFields();
	});

	$(document).on('change', '#' + fieldNames.mode, toggleDecisionUpdateFields);
	$(document).on('change', '#' + fieldNames.target, toggleDecisionUpdateFields);
})(jQuery, window);