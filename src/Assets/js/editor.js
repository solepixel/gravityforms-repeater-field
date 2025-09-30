(function($){
	'use strict';

	function safeText($el) {
		if (!$el || $el.length === 0) return '';
		var t = $el.text();
		return (typeof t === 'string') ? t : '';
	}

	function findNearestStartLabel($endField) {
		if (!$endField || $endField.length === 0) return 'Repeater';
		// Find nearest previous GF field row that is a Repeater Start
		var $rows = $endField.prevAll('li.gfield');
		if (!$rows) return 'Repeater';
		for (var i = 0; i < $rows.length; i++) {
			var $row = $rows.eq(i);
			if (!$row || $row.length === 0) continue;
			var type = $row.data('type');
			if (type === 'repeater_start') {
				var $title = $row.find('.gfield_label').first();
				var labelText = $title && $title.length ? safeText($title).trim() : '';
				if (!labelText || labelText === 'Untitled') {
					// Try reading the settings panel field label for the start field specifically
					var settingsVal = $('input#field_label').val();
					if (settingsVal && typeof settingsVal === 'string') {
						labelText = settingsVal;
					}
				}
				return labelText || 'Repeater';
			}
		}
		return 'Repeater';
	}

	function updateEndEditorLabels() {
		$('li.gfield[data-type="repeater_end"]').each(function(){
			var $end = $(this);
			var label = findNearestStartLabel($end);
			// Place the label into the normal field label location for editor view only.
			var $labelEl = $end.find('.gfield_label').first();
			if ($labelEl && $labelEl.length) {
				$labelEl.text(label);
			}
			// Ensure the field content still shows identifier text.
		});
	}

	$(document).on('gform_field_added gform_field_moved gform_load_field_settings fieldSettingsOpened change keyup input', function(){
		updateEndEditorLabels();
	});

	$(function(){
		updateEndEditorLabels();
	// Load saved min/max values into the settings inputs when a field is opened.
	$(document).on('gform_load_field_settings', function(event, field){
        if (!field || field.type !== 'repeater_start') {
            return;
        }
        var minVal = (typeof field.repeaterMin !== 'undefined' && field.repeaterMin !== null) ? field.repeaterMin : '';
        var maxVal = (typeof field.repeaterMax !== 'undefined' && field.repeaterMax !== null) ? field.repeaterMax : '';
        var $min = $('#repeater_min');
        var $max = $('#repeater_max');
        if ($min.length) { $min.val(minVal); }
        if ($max.length) { $max.val(maxVal); }
    });
	});

})(jQuery);
