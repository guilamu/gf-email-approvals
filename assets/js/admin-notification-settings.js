(function($, window) {
	var settings = window.GFEmailApprovalsNotificationSettings || null;

	if (!settings || !settings.fieldNames) {
		return;
	}

	var fieldConfig = settings.fieldConfig || {};
	var targetFieldChoices = settings.targetFieldChoices || {};
	var fieldNames = settings.fieldNames || {};
	var strings = settings.strings || {};
	var automaticMode = settings.automaticMode || 'automatic';
	var manualMode = settings.manualMode || 'manual';
	var templateIds = settings.templateIds || {};
	var virtualIdBases = settings.virtualIdBases || {};

	function getFieldInput(name) {
		if (!name) {
			return $();
		}

		return $('#' + name);
	}

	function getFieldRowId(name) {
		return 'gform_setting_' + String(name || '').replace(/\[/g, '_').replace(/\]/g, '');
	}

	function getFieldRow(name) {
		var $input = getFieldInput(name);

		if ($input.length) {
			return $input.closest('.gform-settings-field');
		}

		return $('#' + getFieldRowId(name));
	}

	function getAutomaticMappingsBuilder() {
		return getFieldRow(fieldNames.mappings).find('[data-gf-email-approvals-mappings-builder]').first();
	}

	function getManualFieldsBuilder() {
		return getFieldRow(fieldNames.manualFields).find('[data-gf-email-approvals-manual-fields-builder]').first();
	}

	function getAutomaticMappingsRowsContainer() {
		return getAutomaticMappingsBuilder().find('[data-gf-email-approvals-mappings-rows]').first();
	}

	function getManualFieldsRowsContainer() {
		return getManualFieldsBuilder().find('[data-gf-email-approvals-manual-fields-rows]').first();
	}

	function getAutomaticMappingRows() {
		return getAutomaticMappingsRowsContainer().children('[data-gf-email-approvals-mapping-row]');
	}

	function getManualFieldRows() {
		return getManualFieldsRowsContainer().children('[data-gf-email-approvals-manual-field-row]');
	}

	function syncMappingsBuilderState() {
		var $builder = getAutomaticMappingsBuilder();

		if (!$builder.length) {
			return;
		}

		$builder.toggleClass('gf-email-approvals-mappings--has-rows', getAutomaticMappingRows().length > 0);
	}

	function syncManualFieldsBuilderState() {
		var $builder = getManualFieldsBuilder();

		if (!$builder.length) {
			return;
		}

		$builder.toggleClass('gf-email-approvals-mappings--has-rows', getManualFieldRows().length > 0);
	}

	function getApprovalPagesPanel() {
		return getFieldInput(fieldNames.confirmationTitle).closest('.gform-settings-panel.gform-settings-panel--with-title');
	}

	function getApprovalPagesPanelHeader($panel) {
		var $header = $panel.find('.gform-settings-panel__header').first();

		if ($header.length) {
			return $header;
		}

		$header = $panel.find('.gform-settings-panel__title-wrapper').first();

		if ($header.length) {
			return $header;
		}

		$header = $panel.find('.gform-settings-panel__title').first();

		return $header.length ? $header : $panel;
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

	function getAutomaticFieldChoices() {
		return targetFieldChoices[automaticMode] || [];
	}

	function getManualFieldChoices() {
		return targetFieldChoices[manualMode] || [];
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

	function buildMappingFieldSelectHtml(rowKey, selectedFieldId) {
		var inputName = '_gform_setting_' + fieldNames.mappingsSetting + '[' + rowKey + '][field]';
		var inputId = 'gf-email-approvals-mapping-field-' + rowKey;
		var html = '<select name="' + escapeHtml(inputName) + '" id="' + escapeHtml(inputId) + '" data-gf-email-approvals-mapping-field>';

		html += '<option value="">' + escapeHtml(strings.selectField || '') + '</option>';

		getAutomaticFieldChoices().forEach(function(choice) {
			var value = getChoiceValue(choice);
			html += '<option value="' + escapeHtml(value) + '"' + (value === selectedFieldId ? ' selected="selected"' : '') + '>' + escapeHtml(getChoiceLabel(choice)) + '</option>';
		});

		html += '</select>';

		return html;
	}

	function buildManualFieldSelectHtml(rowKey, selectedFieldId) {
		var inputName = '_gform_setting_' + fieldNames.manualFieldsSetting + '[' + rowKey + ']';
		var inputId = 'gf-email-approvals-manual-field-' + rowKey;
		var html = '<select name="' + escapeHtml(inputName) + '" id="' + escapeHtml(inputId) + '" data-gf-email-approvals-manual-field>';

		html += '<option value="">' + escapeHtml(strings.selectField || '') + '</option>';

		getManualFieldChoices().forEach(function(choice) {
			var value = getChoiceValue(choice);
			html += '<option value="' + escapeHtml(value) + '"' + (value === selectedFieldId ? ' selected="selected"' : '') + '>' + escapeHtml(getChoiceLabel(choice)) + '</option>';
		});

		html += '</select>';

		return html;
	}

	function buildMappingRowHtml(rowKey, selectedFieldId) {
		return '' +
			'<div class="gf-email-approvals-mappings__row" data-gf-email-approvals-mapping-row data-row-key="' + escapeHtml(String(rowKey)) + '">' +
				'<div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--field">' +
					'<label class="gf-email-approvals-mappings__mobile-label" for="gf-email-approvals-mapping-field-' + escapeHtml(String(rowKey)) + '">' + escapeHtml(strings.field || '') + '</label>' +
					buildMappingFieldSelectHtml(rowKey, selectedFieldId) +
				'</div>' +
				'<div class="gf-email-approvals-mappings__cell">' +
					'<label class="gf-email-approvals-mappings__mobile-label">' + escapeHtml(strings.approvedValue || '') + '</label>' +
					'<div class="gf-email-approvals-mappings__slot" data-gf-email-approvals-mapping-slot="approved"></div>' +
				'</div>' +
				'<div class="gf-email-approvals-mappings__cell">' +
					'<label class="gf-email-approvals-mappings__mobile-label">' + escapeHtml(strings.rejectedValue || '') + '</label>' +
					'<div class="gf-email-approvals-mappings__slot" data-gf-email-approvals-mapping-slot="rejected"></div>' +
				'</div>' +
				'<div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--actions">' +
					'<button type="button" class="gf-email-approvals-mappings__remove" data-gf-email-approvals-mapping-remove aria-label="' + escapeHtml(strings.removeField || '') + '">' +
						'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
						'<span class="screen-reader-text">' + escapeHtml(strings.removeField || '') + '</span>' +
					'</button>' +
				'</div>' +
			'</div>';
	}

	function buildManualFieldRowHtml(rowKey, selectedFieldId) {
		return '' +
			'<div class="gf-email-approvals-mappings__row" data-gf-email-approvals-manual-field-row data-row-key="' + escapeHtml(String(rowKey)) + '">' +
				'<div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--field">' +
					'<label class="gf-email-approvals-mappings__mobile-label" for="gf-email-approvals-manual-field-' + escapeHtml(String(rowKey)) + '">' + escapeHtml(strings.field || '') + '</label>' +
					buildManualFieldSelectHtml(rowKey, selectedFieldId) +
				'</div>' +
				'<div class="gf-email-approvals-mappings__cell gf-email-approvals-mappings__cell--actions">' +
					'<button type="button" class="gf-email-approvals-mappings__remove" data-gf-email-approvals-manual-field-remove aria-label="' + escapeHtml(strings.removeField || '') + '">' +
						'<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
						'<span class="screen-reader-text">' + escapeHtml(strings.removeField || '') + '</span>' +
					'</button>' +
				'</div>' +
			'</div>';
	}

	function initializeDecisionValueInputs($scope) {
		if (!$scope || !$scope.length) {
			return;
		}

		if (typeof window.gformInitDatepicker === 'function' && $scope.find('.gform-datepicker').length) {
			window.gformInitDatepicker();
		}
	}

	function getTemplateId(status) {
		return String(status === 'approved' ? (templateIds.approved || '') : (templateIds.rejected || ''));
	}

	function getActualVirtualId(status, rowKey) {
		var baseId = Number(status === 'approved' ? (virtualIdBases.approved || 0) : (virtualIdBases.rejected || 0));

		return String(baseId + Number(rowKey || 0));
	}

	function instantiateDecisionTemplate(markup, status, rowKey) {
		var templateId = getTemplateId(status);

		if (!templateId) {
			return String(markup || '');
		}

		return String(markup || '').split(templateId).join(getActualVirtualId(status, rowKey));
	}

	function renderMappingRow($row) {
		var rowKey = Number($row.data('row-key')) || 0;
		var targetFieldId = $row.find('[data-gf-email-approvals-mapping-field]').val() || '';
		var config = targetFieldId ? (fieldConfig[targetFieldId] || null) : null;
		var $approvedSlot = $row.find('[data-gf-email-approvals-mapping-slot="approved"]');
		var $rejectedSlot = $row.find('[data-gf-email-approvals-mapping-slot="rejected"]');

		if (!$approvedSlot.length || !$rejectedSlot.length) {
			return;
		}

		if (!config) {
			$approvedSlot.empty();
			$rejectedSlot.empty();
			return;
		}

		$approvedSlot.html(instantiateDecisionTemplate(config.approvedTemplate || '', 'approved', rowKey));
		$rejectedSlot.html(instantiateDecisionTemplate(config.rejectedTemplate || '', 'rejected', rowKey));
		initializeDecisionValueInputs($row);
	}

	function syncMappingFieldOptions() {
		var selectedFieldIds = getAutomaticMappingRows().map(function() {
			return $(this).find('[data-gf-email-approvals-mapping-field]').val() || '';
		}).get().filter(Boolean);

		getAutomaticMappingRows().each(function() {
			var $row = $(this);
			var $select = $row.find('[data-gf-email-approvals-mapping-field]');
			var currentValue = $select.val() || '';
			var html = '<option value="">' + escapeHtml(strings.selectField || '') + '</option>';

			getAutomaticFieldChoices().forEach(function(choice) {
				var value = getChoiceValue(choice);
				var disabled = value !== currentValue && selectedFieldIds.indexOf(value) !== -1;

				html += '<option value="' + escapeHtml(value) + '"' + (value === currentValue ? ' selected="selected"' : '') + (disabled ? ' disabled="disabled"' : '') + '>' + escapeHtml(getChoiceLabel(choice)) + '</option>';
			});

			$select.html(html);
		});
	}

	function syncManualFieldOptions() {
		var selectedFieldIds = getManualFieldRows().map(function() {
			return $(this).find('[data-gf-email-approvals-manual-field]').val() || '';
		}).get().filter(Boolean);

		getManualFieldRows().each(function() {
			var $row = $(this);
			var $select = $row.find('[data-gf-email-approvals-manual-field]');
			var currentValue = $select.val() || '';
			var html = '<option value="">' + escapeHtml(strings.selectField || '') + '</option>';

			getManualFieldChoices().forEach(function(choice) {
				var value = getChoiceValue(choice);
				var disabled = value !== currentValue && selectedFieldIds.indexOf(value) !== -1;

				html += '<option value="' + escapeHtml(value) + '"' + (value === currentValue ? ' selected="selected"' : '') + (disabled ? ' disabled="disabled"' : '') + '>' + escapeHtml(getChoiceLabel(choice)) + '</option>';
			});

			$select.html(html);
		});
	}

	function getNextMappingRowKey() {
		var nextKey = 1;

		getAutomaticMappingRows().each(function() {
			nextKey = Math.max(nextKey, (Number($(this).data('row-key')) || 0) + 1);
		});

		return nextKey;
	}

	function getNextManualFieldRowKey() {
		var nextKey = 1;

		getManualFieldRows().each(function() {
			nextKey = Math.max(nextKey, (Number($(this).data('row-key')) || 0) + 1);
		});

		return nextKey;
	}

	function addMappingRow(selectedFieldId) {
		var $rows = getAutomaticMappingsRowsContainer();
		var rowKey;
		var $row;

		if (!$rows.length) {
			return $();
		}

		rowKey = getNextMappingRowKey();
		$row = $(buildMappingRowHtml(rowKey, selectedFieldId || ''));
		$rows.append($row);
		syncMappingsBuilderState();
		renderMappingRow($row);
		syncMappingFieldOptions();

		return $row;
	}

	function addManualFieldRow(selectedFieldId) {
		var $rows = getManualFieldsRowsContainer();
		var rowKey;
		var $row;

		if (!$rows.length) {
			return $();
		}

		rowKey = getNextManualFieldRowKey();
		$row = $(buildManualFieldRowHtml(rowKey, selectedFieldId || ''));
		$rows.append($row);
		syncManualFieldsBuilderState();
		syncManualFieldOptions();

		return $row;
	}

	function toggleDecisionUpdateFields() {
		var updateMode = getFieldInput(fieldNames.mode).val() || '';
		var isAutomatic = updateMode === automaticMode;
		var isManual = updateMode === manualMode;

		getFieldRow(fieldNames.target).toggle(false);
		getFieldRow(fieldNames.manualFields).toggle(isManual);
		getFieldRow(fieldNames.mappings).toggle(isAutomatic);
		syncManualFieldsBuilderState();
		syncMappingsBuilderState();

		if (isAutomatic) {
			initializeDecisionValueInputs(getAutomaticMappingsBuilder());
			syncMappingFieldOptions();
		}

		if (isManual) {
			syncManualFieldOptions();
		}
	}

	function ensureVisualSettingsLink() {
		var $panel = getApprovalPagesPanel();
		var $header = $();
		var tooltip = strings.visualSettingsTooltip || '';
		var visualSettingsUrl = settings.visualSettingsUrl || '';

		if (!$panel.length || !visualSettingsUrl) {
			return;
		}

		$header = getApprovalPagesPanelHeader($panel);

		$panel.addClass('gf-email-approvals-panel-has-settings-link');
		$header.addClass('gf-email-approvals-panel-header-has-settings-link');

		if ($header.children('.gf-email-approvals-panel-settings-link').length) {
			return;
		}

		var $link = $('<a />', {
			'class': 'gf-email-approvals-panel-settings-link',
			href: visualSettingsUrl,
			title: tooltip,
			'aria-label': tooltip
		});

		$link.append($('<span />', {
			'class': 'dashicons dashicons-admin-generic',
			'aria-hidden': 'true'
		}));

		$header.append($link);
	}

	$(function() {
		toggleDecisionUpdateFields();
		ensureVisualSettingsLink();
		initializeDecisionValueInputs(getAutomaticMappingsBuilder());
		syncManualFieldsBuilderState();
		syncMappingsBuilderState();
		syncManualFieldOptions();
		syncMappingFieldOptions();
	});

	$(document).on('change', '#' + fieldNames.mode, toggleDecisionUpdateFields);
	$(document).on('change', '[data-gf-email-approvals-manual-field]', syncManualFieldOptions);
	$(document).on('change', '[data-gf-email-approvals-mapping-field]', function() {
		var $row = $(this).closest('[data-gf-email-approvals-mapping-row]');

		renderMappingRow($row);
		syncMappingFieldOptions();
	});
	$(document).on('click', '[data-gf-email-approvals-manual-fields-add]', function(event) {
		var $row;

		event.preventDefault();
		$row = addManualFieldRow('');

		if ($row.length) {
			$row.find('[data-gf-email-approvals-manual-field]').trigger('focus');
		}
	});
	$(document).on('click', '[data-gf-email-approvals-manual-field-remove]', function(event) {
		event.preventDefault();
		$(this).closest('[data-gf-email-approvals-manual-field-row]').remove();
		syncManualFieldsBuilderState();
		syncManualFieldOptions();
	});
	$(document).on('click', '[data-gf-email-approvals-mappings-add]', function(event) {
		var $row;

		event.preventDefault();
		$row = addMappingRow('');

		if ($row.length) {
			$row.find('[data-gf-email-approvals-mapping-field]').trigger('focus');
		}
	});
	$(document).on('click', '[data-gf-email-approvals-mapping-remove]', function(event) {
		event.preventDefault();
		$(this).closest('[data-gf-email-approvals-mapping-row]').remove();
		syncMappingsBuilderState();
		syncMappingFieldOptions();
	});
})(jQuery, window);