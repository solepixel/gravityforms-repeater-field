/**
 * Frontend JavaScript for Gravity Forms Repeater Field
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Gravity Forms Repeater Field Frontend
     */
    class GFRepeaterField {
        constructor() {
            this.instances = new Map();
            this.gfListenerAttached = false;
            this.init();
        }

        /**
         * Initialize the repeater field functionality
         */
        init() {

            this.bindEvents();
            this.initializeRepeaters();
        }

        /**
         * Bind event handlers
         */
		bindEvents() {
			$(document).on('click', '.gf-repeater-add', this.handleAdd.bind(this));
			$(document).on('click', '.gf-repeater-remove', this.handleRemove.bind(this));
			$(document).on('click', '.gf-repeater-prev', this.handlePrev.bind(this));
			$(document).on('click', '.gf-repeater-next', this.handleNext.bind(this));

			// Handle form submission (GF lifecycle)
			$(document).on('gform_pre_submission', this.handleFormSubmission.bind(this));

			// Also pack data on form submit (before validation)
			$(document).on('submit', 'form[id^="gform_"]', (e) => {
				const $form = $(e.currentTarget);
				const idMatch = ($form.attr('id') || '').match(/^gform_(\d+)$/);
				if (idMatch) {
					this.processRepeaterData(parseInt(idMatch[1], 10));
				}
			});
		}

        /**
         * Initialize existing repeaters on page load
         */
        initializeRepeaters() {

            $('.gf-repeater-controls').each((index, element) => {
                const $controls = $(element);
                const formId = $controls.data('form-id');
                const fieldId = $controls.data('field-id');

                if (formId && fieldId) {

                    this.initializeRepeater(formId, fieldId, $controls);
                    // Attach GF listeners once globally
                    this.attachGFListeners();
                }
            });
        }

        /**
         * Initialize a specific repeater
         */
        initializeRepeater(formId, fieldId, $controls) {

            const repeaterId = `gf-repeater-${formId}-${fieldId}`;

            // Check if already initialized
            if (this.instances.has(repeaterId)) {

                return;
            }

            const $startGField = $(`#field_${formId}_${fieldId}`);
            if ($startGField.length === 0) {

                return;
            }

            const $endGField = $startGField.nextAll('div.gfield.gfield--type-repeater_end').first();
            if ($endGField.length === 0) return;

            // Remove for attributes on labels (no inputs associated) and hide the start field label in frontend
            $endGField.find('label.gfield_label').removeAttr('for');
            $startGField.find('label.gfield_label').removeAttr('for').remove();

            // Determine container (Gravity Forms uses .gform_fields)
            const $container = $startGField.parent();

            // Get all siblings inside the container, regardless of tag (div/fieldset) but with .gfield
            const $siblings = $container.children('.gfield');
            const startIdx = $siblings.index($startGField);
            const endIdx = $siblings.index($endGField);
            if (startIdx < 0 || endIdx < 0 || endIdx <= startIdx + 1) {
                return; // nothing to wrap
            }

            // Slice the fields strictly between Start and End
            const $range = $siblings.slice(startIdx + 1, endIdx);

            // Wrap the exact range (do this BEFORE inserting our fieldset so relative order remains correct)
            $range.wrapAll('<div class="gf-repeater-instance"/>');

            // Apply GF container classes to the instance so inner fields keep layout widths
            let $firstInstance = $startGField.next('.gf-repeater-instance');
            if ($firstInstance.length === 0) {
                $firstInstance = $('<div class="gf-repeater-instance"/>');
            }
            // Copy classes from the container (e.g., gform_fields top_label ...)
            const containerClassAttr = ($container.attr('class') || '').trim();
            if (containerClassAttr) {
                containerClassAttr.split(/\s+/).forEach(function(cls){ if (cls) { $firstInstance.addClass(cls); } });
            }

            // Create a fieldset immediately after the Start field and move the wrapped instance inside
            let $fieldset = $(`#gf-fieldset-${fieldId}`);
            if ($fieldset.length === 0) {
                $fieldset = $('<fieldset/>', {
                    id: `gf-fieldset-${fieldId}`,
                    class: 'gf-fieldset gf-group-fieldset gf-repeater-fieldset active'
                }).attr('data-field-id', fieldId);
                $fieldset.insertAfter($startGField);
                $fieldset.append('<div class="gf-repeater-viewport"><div class="gf-repeater-track"/></div>');
            }
            // Copy classes from the repeater_start gfield, replacing repeater_start with repeater_fieldsets
            const startClassAttr = ($startGField.attr('class') || '').trim();
            if (startClassAttr) {
                startClassAttr.split(/\s+/).forEach(function(cls){
                    if (!cls) return;
                    if (cls.indexOf('repeater_start') !== -1) {
                        $fieldset.addClass(cls.replace('repeater_start', 'repeater_fieldsets'));
                    } else {
                        $fieldset.addClass(cls);
                    }
                });
            }
            $fieldset.find('.gf-repeater-track').append($firstInstance);

            // GF conditional logic will be applied via adapter

            // Mark and apply GF logic for first instance
            this.setInstanceMeta($firstInstance, formId);
            this.applyGFConditionalToInstance($firstInstance, formId);
            this.bindInstanceInputs($firstInstance, formId);
            this.reinitGFUI(formId);

            // Remove the End field from frontend DOM
            $endGField.remove();

            // Prepare a pristine template for future clones
            const $templateInstance = $firstInstance.clone(false, false);
            // Clear values and validation from template
            $templateInstance.find('input, textarea, select').each(function(){
                const $field = $(this);
                const type = ($field.attr('type') || '').toLowerCase();
                if (type === 'radio' || type === 'checkbox') { $field.prop('checked', false); }
                else if ($field.is('select')) { $field.prop('selectedIndex', 0); }
                else { $field.val(''); }
            });
            this.sanitizeInstanceUI($templateInstance);

            // Store repeater instance
            this.updateFieldsetIds($firstInstance, 0);
            this.instances.set(repeaterId, {
                formId: formId,
                fieldId: fieldId,
                currentIndex: 0,
                totalInstances: 1,
                $controls: $controls,
                $fieldset: $fieldset,
                instances: [$firstInstance],
                $template: $templateInstance
            });

            // Set up initial state
            this.updateControls(repeaterId);

			// If hidden input has JSON data (after validation reload), restore instances and values
			const $hidden = $(`#input_${formId}_${fieldId}`);
			if ($hidden.length) {
				const raw = $hidden.val();

				if (raw && raw.trim() !== '') {
					try {
						const data = JSON.parse(raw);

						if (Array.isArray(data) && data.length > 1) {
							// Clear the first instance if it's empty and we have data to restore
							const instObj = this.instances.get(repeaterId);
							if (instObj.totalInstances === 1 && Object.keys(data[0]).length === 0) {
								instObj.instances[0].remove();
								instObj.instances.splice(0, 1);
								instObj.totalInstances = 0;
							}

            // Create instances to match data length
            for (let i = 0; i < data.length; i++) {
                let $instanceToPopulate;
                if (i === 0 && instObj.instances.length > 0) {
                    $instanceToPopulate = instObj.instances[0];
                } else {
                    $instanceToPopulate = this.addInstance(repeaterId, false);
                }

                if ($instanceToPopulate && $instanceToPopulate.length) {
                    this.populateInstance($instanceToPopulate, data[i], formId);
                } else {

                }
            }

            // Wait a moment for GF to apply validation errors, then copy them
            setTimeout(() => {
                this.copyValidationErrorsToInstances(instObj.instances, data);
            }, 100);

							// Ensure current index is valid and update display
							instObj.currentIndex = Math.min(instObj.currentIndex, instObj.totalInstances - 1);
							this.updateDisplay(repeaterId);
							this.updateControls(repeaterId);
						}
					} catch(e) {

					}
				}
			}
        }

        /**
         * Populate an instance with values.
         * @param {jQuery} $instance The instance to populate.
         * @param {object} values The values to set.
         * @param {number} formId The form ID.
         * @returns {void}
         */
        populateInstance($instance, values, formId) {
            Object.keys(values).forEach((baseKey) => {
                const arr = values[baseKey];
                if (!Array.isArray(arr)) return;

                // Find inputs by their data-gf-repeater-original-name attribute
                const $inputs = $instance.find(`:input[data-gf-repeater-original-name='${baseKey}']`);
                if ($inputs.length === 0) return;

                const type = ($inputs.first().attr('type') || '').toLowerCase();
                if (type === 'radio') {
                    $inputs.prop('checked', false);
                    // Only select if we have a valid value that's not empty
                    if (arr.length && arr[0] && arr[0] !== '') {
                        $inputs.filter(`[value='${arr[0]}']`).prop('checked', true).trigger('change');
                    }
                } else if (type === 'checkbox') {
                    $inputs.prop('checked', false);
                    arr.forEach((v) => {
                        if (v && v !== '') {
                            $inputs.filter(`[value='${v}']`).prop('checked', true).trigger('change');
                        }
                    });
                } else if ($inputs.is('select')) {
                    $inputs.val(arr[0] || '').trigger('change');
                } else {
                    $inputs.val(arr[0] || '').trigger('input');
                }
            });
            this.applyGFConditionalToInstance($instance, formId);
            this.reinitGFUI(formId);
        }

        /**
         * Copy validation errors to instances based on their specific data.
         * @param {Array} instances Array of instance jQuery objects.
         * @param {Array} data Array of data for each instance.
         * @returns {void}
         */
        copyValidationErrorsToInstances(instances, data) {


            if (instances.length <= 1) return;

            // For each instance, check if it has validation errors based on its data
            instances.forEach(($instance, instanceIndex) => {

                const instanceData = data[instanceIndex];
                if (!instanceData) {

                    return;
                }



                // Check ALL required fields in this instance, not just the ones with data
                $instance.find('.gfield.gfield_contains_required').each(function() {
                    const $field = $(this);
                    const $input = $field.find(':input[data-gf-repeater-original-name]').first();
                    if (!$input.length) return;

                    const fieldKey = $input.data('gf-repeater-original-name');
                    if (!fieldKey) return;



                    // Check if this field has data in the instanceData
                    const fieldValues = instanceData[fieldKey];
                    const hasValue = fieldValues && Array.isArray(fieldValues) && fieldValues.some(val => val && val !== '');



                    if (!hasValue) {
                        // This field should have a validation error

                        $field.addClass('gfield_error');
                        $field.attr('aria-invalid', 'true');
                        $field.find(':input').attr('aria-invalid', 'true');

                        // Add or update validation message
                        let $message = $field.find('.validation_message');
                        if (!$message.length) {
                            $message = $('<div class="gfield_description validation_message gfield_validation_message"></div>');
                            $field.append($message);
                        }
                        $message.text(`This field is required for group ${instanceIndex + 1}.`);
                    } else {
                        // This field should not have validation errors

                        $field.removeClass('gfield_error');
                        $field.attr('aria-invalid', 'false');
                        $field.find(':input').attr('aria-invalid', 'false');
                        $field.find('.validation_message').remove();
                    }
                });
            });
        }

        /**
         * Handle add button click
         */
        handleAdd(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $controls = $button.closest('.gf-repeater-controls');
            const formId = $controls.data('form-id');
            const fieldId = $controls.data('field-id');
            const repeaterId = `gf-repeater-${formId}-${fieldId}`;

            this.addInstance(repeaterId);
        }

        /**
         * Handle remove button click
         */
        handleRemove(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $controls = $button.closest('.gf-repeater-controls');
            const formId = $controls.data('form-id');
            const fieldId = $controls.data('field-id');
            const repeaterId = `gf-repeater-${formId}-${fieldId}`;

            this.removeInstance(repeaterId);
        }

        /**
         * Handle previous button click
         */
        handlePrev(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $controls = $button.closest('.gf-repeater-controls');
            const formId = $controls.data('form-id');
            const fieldId = $controls.data('field-id');
            const repeaterId = `gf-repeater-${formId}-${fieldId}`;

            this.navigateToInstance(repeaterId, -1);
        }

        /**
         * Handle next button click
         */
        handleNext(e) {
            e.preventDefault();

            const $button = $(e.currentTarget);
            const $controls = $button.closest('.gf-repeater-controls');
            const formId = $controls.data('form-id');
            const fieldId = $controls.data('field-id');
            const repeaterId = `gf-repeater-${formId}-${fieldId}`;

            this.navigateToInstance(repeaterId, 1);
        }

        /**
         * Add a new instance
         */
        addInstance(repeaterId, navigate = true) {
            const instance = this.instances.get(repeaterId);
            if (!instance) return;

            // Create new fieldset instance
            const $newFieldset = this.createFieldsetInstance(instance);
            instance.instances.push($newFieldset);
            instance.totalInstances++;

            // Append into DOM (append to track inside fieldset)
            if (instance.$fieldset && instance.$fieldset.length) {
                const $track = instance.$fieldset.find('.gf-repeater-track');
                if ($track.length) {
                    $track.append($newFieldset);
                } else {
                    instance.$fieldset.append($newFieldset);
                }
            }

            // Apply GF conditional logic for the new instance
            this.setInstanceMeta($newFieldset, instance.formId);
            this.applyGFConditionalToInstance($newFieldset, instance.formId);
            this.bindInstanceInputs($newFieldset, instance.formId);
            this.reinitGFUI(instance.formId);

            // Move to the new instance immediately if navigate is true
            if (navigate) {
                instance.currentIndex = instance.totalInstances - 1;
                // Update display
                this.updateDisplay(repeaterId);
                this.updateControls(repeaterId);
            }

            return $newFieldset;
        }

        /**
         * Remove current instance
         */
        removeInstance(repeaterId) {
            const instance = this.instances.get(repeaterId);
            if (!instance || instance.totalInstances <= 1) return;

            // Remove current instance
            const $current = instance.instances[instance.currentIndex];
            if ($current && $current.remove) { $current.remove(); }
            instance.instances.splice(instance.currentIndex, 1);
            instance.totalInstances--;

            // Adjust current index if necessary
            if (instance.currentIndex >= instance.totalInstances) {
                instance.currentIndex = instance.totalInstances - 1;
            }

            // Update display
            this.updateDisplay(repeaterId);
            this.updateControls(repeaterId);
        }

        /**
         * Navigate to a different instance
         */
        navigateToInstance(repeaterId, direction) {
            const instance = this.instances.get(repeaterId);
            if (!instance) return;

            const newIndex = instance.currentIndex + direction;

            if (newIndex >= 0 && newIndex < instance.totalInstances) {
                instance.currentIndex = newIndex;
                this.updateDisplay(repeaterId);
                this.updateControls(repeaterId);
            }
        }

        /**
         * Create a new fieldset instance
         */
        createFieldsetInstance(instance) {
            const $template = instance.$template ? instance.$template : instance.instances[0];
            const $newFieldset = $template.clone(false, false);
            $newFieldset.addClass('gf-repeater-instance gform_fields top_label form_sublabel_below description_below validation_below');

            // Update IDs and names for the new instance
            this.updateFieldsetIds($newFieldset, instance.totalInstances);

            // Clear field values (but keep radios/checkboxes unchecked by default without breaking logic)
            $newFieldset.find('input, textarea, select').each(function(){
                const $field = $(this);
                const type = ($field.attr('type') || '').toLowerCase();
                if (type === 'radio' || type === 'checkbox') {
                    $field.prop('checked', false);
                } else if ($field.is('select')) {
                    $field.prop('selectedIndex', 0).trigger('change');
                } else if ($field.is('textarea') || type === 'text' || type === 'number') {
                    $field.val('');
                }
            });

            // Ensure we don't copy validation error UI from other instances
            this.sanitizeInstanceUI($newFieldset);

            return $newFieldset;
        }

        /**
         * Update fieldset element IDs and input names for a given instance index,
         * and remap label `for` attributes to the actual input ids.
         */
        updateFieldsetIds($fieldset, instanceIndex) {
            $fieldset.find('input, textarea, select').each(function() {
                const $field = $(this);
                const originalId = $field.data('gfRepeaterOriginalId') || $field.attr('id');
                const originalName = $field.data('gfRepeaterOriginalName') || $field.attr('name');

                if (!$field.data('gfRepeaterOriginalId')) {
                    $field.data('gfRepeaterOriginalId', originalId);
                }
                if (!$field.data('gfRepeaterOriginalName')) {
                    $field.data('gfRepeaterOriginalName', originalName);
                }

                // Set the data attribute for easy lookup during population
                if (originalName) {
                    const baseName = originalName.endsWith('[]') ? originalName.slice(0, -2) : originalName;
                    $field.attr('data-gf-repeater-original-name', baseName);
                }

                if (originalId) {
                    // Keep original id for instance 0 (so existing GF hooks bound to original ids still work)
                    $field.attr('id', instanceIndex === 0 ? originalId : `${originalId}_${instanceIndex}`);
                }

                if (originalName) {
                    const baseName = originalName.endsWith('[]') ? originalName.slice(0, -2) : originalName;
                    const type = ($field.attr('type') || '').toLowerCase();
                    const isOther = /_other$/.test(baseName);
                    // Radios/checkboxes: keep base for instance 0, unique name for clones
                    if (type === 'radio' || type === 'checkbox') {
                        $field.attr('name', instanceIndex === 0 ? baseName : `${baseName}__i${instanceIndex}`);
                    } else {
                        // Non-choice fields: instance 0 keeps original base (no []), clones get unique names
                        if (instanceIndex === 0) {
                            // Preserve *_other[] exactly as GF outputs; otherwise use baseName
                            $field.attr('name', isOther && originalName.endsWith('[]') ? originalName : baseName);
                        } else {
                            $field.attr('name', `${baseName}__i${instanceIndex}`);
                        }
                    }
                }
            });

            // Update label `for` attributes to match actual input ids
            // For choice inputs: map each label to its sibling input id
            $fieldset.find('.gfield_radio .gchoice, .gfield_checkbox .gchoice').each(function(){
                const $gc = $(this);
                const $inp = $gc.find('input').first();
                const $lbl = $gc.find('label').first();
                if ($inp.length && $lbl.length) {
                    $lbl.attr('for', $inp.attr('id'));
                }
            });
            // For non-choice fields: map the field label to the first input inside the container
            $fieldset.find('.gfield').each(function(){
                const $g = $(this);
                const $mainLabel = $g.children('.gfield_label[for]').first();
                if ($mainLabel.length) {
                    const $firstInput = $g.find('.ginput_container :input').first();
                    if ($firstInput.length) {
                        $mainLabel.attr('for', $firstInput.attr('id'));
                    }
                }
            });
        }

        /**
         * Update display to show current instance
         */
        updateDisplay(repeaterId) {
            const instance = this.instances.get(repeaterId);
            if (!instance) return;

            // Ensure viewport/track exists
            let $viewport = instance.$fieldset.find('.gf-repeater-viewport');
            if ($viewport.length === 0) {
                instance.$fieldset.append('<div class="gf-repeater-viewport"><div class="gf-repeater-track"/></div>');
                $viewport = instance.$fieldset.find('.gf-repeater-viewport');
            }
            let $track = $viewport.find('.gf-repeater-track');
            $track.empty();

            // Populate track with instances
            instance.instances.forEach(($inst) => {
                $inst.css({ minWidth: '100%' });
                $track.append($inst);
            });

            // Slide to current
            const offset = -(instance.currentIndex * 100);
            $track.css({ transform: `translateX(${offset}%)` });
            instance.instances.forEach(($inst) => {
                $inst.css({ minWidth: '100%' });
            });

            // Adjust viewport height to active instance to prevent residual gap from other instances
            const $active = instance.instances[instance.currentIndex];
            const activeHeight = $active.outerHeight(true);
            $viewport.css('height', activeHeight + 'px');

            // Apply conditional logic inside the active instance
            this.applyGFConditionalToInstance($active, instance.formId);
            this.bindInstanceInputs($active, instance.formId);
            this.reinitGFUI(instance.formId);

            // Re-run GF conditional logic to re-evaluate visibility
            if (window.gform && typeof window.gform.doConditionalLogic === 'function') {
                try { window.gform.doConditionalLogic(instance.formId, true); } catch(e) {}
            }

            // Final height adjustment after potential DOM changes
            this.adjustViewportHeight(repeaterId);
        }

        /**
         * Update control button states
         */
        updateControls(repeaterId) {
            const instance = this.instances.get(repeaterId);
            if (!instance) return;

            const $controls = instance.$controls;
            const $addBtn = $controls.find('.gf-repeater-add');
            const $removeBtn = $controls.find('.gf-repeater-remove');
            const $prevBtn = $controls.find('.gf-repeater-prev');
            const $nextBtn = $controls.find('.gf-repeater-next');
            const $count = $controls.find('.gf-repeater-count');

            // Update remove button visibility
            if (instance.totalInstances > 1) {
                $removeBtn.show();
            } else {
                $removeBtn.hide();
            }

            // Update navigation buttons
            $prevBtn.prop('disabled', instance.currentIndex === 0);
            $nextBtn.prop('disabled', instance.currentIndex >= instance.totalInstances - 1);

            // Update count text (1-based index)
            if ($count.length) {
                const current = instance.currentIndex + 1;
                $count.text(`${current}/${instance.totalInstances}`);
            }
        }

        /**
         * Handle form submission
         */
        handleFormSubmission(e, form) {
            // Process repeater data before submission in GF’s lifecycle
            this.processRepeaterData(form);
        }

        /**
         * Process repeater data for submission
         */
        processRepeaterData(form) {
            // Accept form object or numeric formId
            let formId = null;
            if (typeof form === 'number') {
                formId = form;
            } else if (form && form.id) {
                formId = form.id;
            }
            if (!formId) return;

            // Flatten repeater fields into array format capturing each instance's values
            $('.gf-fieldset.gf-repeater-fieldset').each(function(){
                const $fieldset = $(this);
                const fieldId = $fieldset.data('fieldId');
                if (!fieldId) {
                    return;
                }

                const instancesData = [];

                $fieldset.find('.gf-repeater-instance').each(function(instanceIndex){
                    const $instance = $(this);
                    const instanceData = {};

                    $instance.find('input, textarea, select').each(function(){
                        const $field = $(this);
                        const baseName = $field.data('gfRepeaterOriginalName') || $field.attr('name');
                        if (!baseName) {
                            return;
                        }

                        let nameKey = baseName.endsWith('[]') ? baseName.slice(0, -2) : baseName;
                        // Normalize per-instance names like input_10__i2 back to input_10
                        nameKey = nameKey.replace(/__i\d+$/, '');

                        const type = ($field.attr('type') || '').toLowerCase();
                        const value = $field.val();
                        const isChecked = $field.is(':checked');

                        // For radios/checkboxes, only include if they are actually checked
                        if (type === 'radio' || type === 'checkbox') {
                            if (!isChecked) {
                                return; // Skip unchecked radio/checkbox inputs
                            }
                        }

                        // For text inputs, only include if they have a meaningful value
                        if (type === 'text' || type === 'number' || type === 'email' || type === 'url' || type === 'tel') {
                            if (!value || value.trim() === '') {
                                return; // Skip empty text inputs
                            }
                        }

                        // For textareas, only include if they have a meaningful value
                        if ($field.is('textarea')) {
                            if (!value || value.trim() === '') {
                                return; // Skip empty textareas
                            }
                        }

                        // For selects, only include if they have a selected value (not placeholder)
                        if ($field.is('select')) {
                            if (!value || value === '' || $field.find('option:selected').hasClass('gf_placeholder')) {
                                return; // Skip empty selects or placeholder selections
                            }
                        }

                        if (!instanceData[nameKey]) {
                            instanceData[nameKey] = [];
                        }
                        instanceData[nameKey].push(value);
                    });

                    instancesData.push(instanceData);
                });

                const $hidden = $(`#input_${formId}_${fieldId}`);
                if ($hidden && $hidden.length) {
                    $hidden.val(JSON.stringify(instancesData));
                }

                // After packing JSON, disable only cloned inputs (names with __iN) so GF doesn't process them
                $fieldset.find('.gf-repeater-instance :input[name*="__i"]').prop('disabled', true);
            });
        }

        // Removed custom per-field conditional logic; using GF map exclusively

        /**
         * Annotate a repeater instance with form id and per-field data-gf-id markers.
         * @param {jQuery} $instance
         * @param {number} formId
         */
        setInstanceMeta($instance, formId) {
            if (!$instance || !$instance.length) return;
            $instance.attr('data-gf-form', formId);
            $instance.find('.gfield').each(function(){
                const $f = $(this);
                const idAttr = $f.attr('id') || '';
                const m = idAttr.match(/field_(\d+)_(\d+)/);
                if (m && m[2]) {
                    $f.attr('data-gf-id', m[2]);
                }
            });
        }

        /**
         * Apply Gravity Forms conditional logic map to a single repeater instance.
         * @param {jQuery} $instance
         * @param {number} formId
         */
        applyGFConditionalToInstance($instance, formId) {
            if (!$instance || !$instance.length) return;
            const map = window.gf_form_conditional_logic && window.gf_form_conditional_logic[formId];
            if (!map || !map.logic) return;

            const evalRule = (rule) => {
                // Locate inputs for rule.fieldId inside instance by name pattern input_{id}
                const name = `input_${rule.fieldId}`;
                // Match exact name, array name, or per-instance name with __i{n}
                const $inputs = $instance.find(`[name='${name}'], [name='${name}[]'], [name^='${name}__i']`);
                if ($inputs.length === 0) return false;
                // Support radio/checkbox and text/select basic operators
                let val = '';
                const type = ($inputs.attr('type') || '').toLowerCase();
                if (type === 'radio' || type === 'checkbox') {
                    val = $inputs.filter(':checked').val() || '';
                } else if ($inputs.is('select')) {
                    val = $inputs.val() || '';
                } else {
                    val = $inputs.val() || '';
                }
                const rVal = (rule.value || '').toString();
                switch ((rule.operator || 'is')) {
                    case 'is':
                        return (val || '').toString() === rVal;
                    case 'isnot':
                        return (val || '').toString() !== rVal;
                    default:
                        // fallback to equality
                        return (val || '').toString() === rVal;
                }
            };

            Object.keys(map.logic).forEach((depId) => {
                const logic = map.logic[depId];
                const fieldLogic = logic && logic.field;
                if (!fieldLogic || !fieldLogic.enabled) return;

                const rules = fieldLogic.rules || [];
                let matched = false;
                if (fieldLogic.logicType === 'all') {
                    matched = rules.every(evalRule);
                } else {
                    matched = rules.some(evalRule);
                }

                const $dep = $instance.find(`[data-gf-id='${depId}']`);
                if ($dep.length) {
                    const actionType = fieldLogic.actionType || 'show';
                    const shouldShow = (actionType === 'show') ? matched : !matched;
                    const $inputs = $dep.find(':input');
                    if (shouldShow) {
                        $dep.css('display', '');
                        $dep.attr('data-conditional-logic', 'visible');
                        $inputs.prop('disabled', false);
                    } else {
                        $dep.css('display', 'none');
                        $dep.attr('data-conditional-logic', 'hidden');
                        $inputs.prop('disabled', true);
                    }
                }
            });
        }

        /**
         * Listen to GF conditional logic runs and mirror results into all instances of the form.
         */
        attachGFListeners() {
            if (this.gfListenerAttached) return;
            this.gfListenerAttached = true;
            const self = this;
            $(document).on('gform_post_conditional_logic.gfRepeater', function(e, fid){
                // Re-apply to all instances for this form id
                self.instances.forEach((inst) => {
                    if (inst.formId === fid) {
                        inst.instances.forEach(($inst) => self.applyGFConditionalToInstance($inst, fid));
                        // Also adjust heights for each repeater on this form
                        const repeaterId = `gf-repeater-${fid}-${inst.fieldId}`;
                        self.adjustViewportHeight(repeaterId);
                    }
                });
            });
        }

        /**
         * Bind per-instance input changes to trigger immediate GF logic + per-instance mirroring.
         * @param {jQuery} $instance
         * @param {number} formId
         */
        bindInstanceInputs($instance, formId) {
            if (!$instance || !$instance.length) return;
			$instance.off('change.gfRepeater input.gfRepeater').on('change.gfRepeater input.gfRepeater', ':input', () => {
				// Let GF evaluate any native targets, then mirror per-instance
				if (window.gform && typeof window.gform.doConditionalLogic === 'function') {
					try { window.gform.doConditionalLogic(formId, true); } catch(e) {}
				}
				// Apply immediately and also after a microtask to ensure GF completed
				this.applyGFConditionalToInstance($instance, formId);
				setTimeout(() => this.applyGFConditionalToInstance($instance, formId), 0);
				setTimeout(() => this.applyGFConditionalToInstance($instance, formId), 50);
				// Adjust viewport height after potential content changes
				this.instances.forEach((inst, key) => {
					if (inst.formId === formId) {
						const repeaterId = `gf-repeater-${formId}-${inst.fieldId}`;
						this.adjustViewportHeight(repeaterId);
					}
				});
			});
			// On window resize, recalc height for this form's repeaters
			$(window).off('resize.gfRepeaterHeight').on('resize.gfRepeaterHeight', () => {
				this.instances.forEach((inst) => {
					if (inst.formId === formId) {
						const repeaterId = `gf-repeater-${formId}-${inst.fieldId}`;
						this.adjustViewportHeight(repeaterId);
					}
				});
			});
        }

		/**
		 * Re-initialize Gravity Forms UI features (currency formatting, datepickers, etc.) for this form.
		 * Triggers gform_post_render which GF listens to for re-binding behaviors on dynamic content.
		 * @param {number} formId
		 */
		reinitGFUI(formId) {
			try {
				$(document).trigger('gform_post_render', [formId, 1]);
			} catch(e) {}
			// Some GF builds expose helpers; call defensively if present
			try {
				if (typeof window.gformInitDatepicker === 'function') { window.gformInitDatepicker(); }
			} catch(e) {}
			// Try to initialize currency formatting for inputs inside repeater instances heuristically
			try {
				if (typeof window.gformInitCurrencyFormatFields === 'function') {
					const $scope = jQuery(`#gform_fields_${formId}`);
					const selectors = [];
					// Focus specifically on number/currency-related containers within repeater instances
					$scope.find('.gf-repeater-instance .ginput_container_number :input, .gf-repeater-instance .ginput_container_currency :input, .gf-repeater-instance .ginput_amount').each(function(){
						const id = jQuery(this).attr('id');
						if (id) { selectors.push(`#${id}`); }
					});
					if (selectors.length) {
						window.gformInitCurrencyFormatFields(selectors.join(','));
					}
				}
			} catch(e) {}
		}

		/**
		 * Remove any validation UI artifacts from a cloned instance.
		 * @param {jQuery} $instance
		 */
		sanitizeInstanceUI($instance) {
			if (!$instance || !$instance.length) return;
			$instance.find('.gfield.gfield_error').removeClass('gfield_error');
			$instance.find('.validation_message, .gfield_description.validation_message').remove();
			$instance.find(':input[aria-invalid="true"]').attr('aria-invalid', 'false');
		}

		/**
		 * Adjust the viewport height to match the active instance's outer height.
		 * @param {string} repeaterId
		 */
		adjustViewportHeight(repeaterId) {
			const instance = this.instances.get(repeaterId);
			if (!instance) return;
			const $viewport = instance.$fieldset.find('.gf-repeater-viewport');
			if ($viewport.length === 0) return;
			const $active = instance.instances[instance.currentIndex];
			if (!$active || !$active.length) return;
			const h = $active.outerHeight(true);
			$viewport.css('height', h + 'px');
		}
    }

    // Initialize when document is ready
    $(document).ready(function() {
        window.gfRepeaterController = new GFRepeaterField();
    });

    // Re-initialize on GF post render (e.g., after AJAX or validation re-render)
    $(document).on('gform_post_render', function(event, formId){
        try {
            if (window.gfRepeaterController) {
                window.gfRepeaterController.initializeRepeaters();
            }
        } catch(e) {}
    });

})(jQuery);
