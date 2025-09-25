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

            // Handle form submission
            $(document).on('gform_pre_submission', this.handleFormSubmission.bind(this));
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
            const $startGField = $(`#field_${formId}_${fieldId}`);
            if ($startGField.length === 0) return;

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
            if ($firstInstance.length) {
                $firstInstance.addClass('gform_fields top_label form_sublabel_below description_below validation_below');
            } else {
                // Fallback (should not happen): create an empty instance to avoid null refs
                $firstInstance = $('<div class="gf-repeater-instance gform_fields top_label form_sublabel_below description_below validation_below"/>');
            }

            // Create a fieldset immediately after the Start field and move the wrapped instance inside
            let $fieldset = $(`#gf-fieldset-${fieldId}`);
            if ($fieldset.length === 0) {
                $fieldset = $('<fieldset/>', {
                    id: `gf-fieldset-${fieldId}`,
                    class: 'gfield gfield--type-repeater_fieldsets gfield--input-type-repeater_fieldsets gfield--width-full field_sublabel_below gfield--no-description field_description_below field_validation_below gfield_visibility_visible gf-fieldset gf-group-fieldset gf-repeater-fieldset active'
                }).attr('data-field-id', fieldId);
                $fieldset.insertAfter($startGField);
                $fieldset.append('<div class="gf-repeater-viewport"><div class="gf-repeater-track"/></div>');
            } else {
                // Ensure fieldset has the classes even if created previously
                $fieldset.addClass('gfield gfield--type-repeater_fieldsets gfield--input-type-repeater_fieldsets gfield--width-full field_sublabel_below gfield--no-description field_description_below field_validation_below gfield_visibility_visible gf-fieldset gf-group-fieldset gf-repeater-fieldset active');
            }
            $fieldset.find('.gf-repeater-track').append($firstInstance);

            // GF conditional logic will be applied via adapter

            // Mark and apply GF logic for first instance
            this.setInstanceMeta($firstInstance, formId);
            this.applyGFConditionalToInstance($firstInstance, formId);
            this.bindInstanceInputs($firstInstance, formId);

            // Remove the End field from frontend DOM
            $endGField.remove();

            // Store repeater instance
            this.updateFieldsetIds($firstInstance, 0);
            this.instances.set(repeaterId, {
                formId: formId,
                fieldId: fieldId,
                currentIndex: 0,
                totalInstances: 1,
                $controls: $controls,
                $fieldset: $fieldset,
                instances: [$firstInstance]
            });

            // Set up initial state
            this.updateControls(repeaterId);
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
        addInstance(repeaterId) {
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

            // Move to the new instance immediately
            instance.currentIndex = instance.totalInstances - 1;

            // Update display
            this.updateDisplay(repeaterId);
            this.updateControls(repeaterId);
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
            const $original = instance.instances[0];
            const $newFieldset = $original.clone(true, true);
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

            return $newFieldset;
        }

        /**
         * Update fieldset IDs and names
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

                if (originalId) {
                    $field.attr('id', `${originalId}_${instanceIndex}`);
                }

                if (originalName) {
                    const baseName = originalName.endsWith('[]') ? originalName.slice(0, -2) : originalName;
                    const type = ($field.attr('type') || '').toLowerCase();
                    // Make radio/checkbox names unique per instance to prevent cross-instance coupling
                    if (type === 'radio' || type === 'checkbox') {
                        $field.attr('name', `${baseName}__i${instanceIndex}`);
                    } else {
                        $field.attr('name', `${baseName}[]`);
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

            // Apply conditional logic inside the active instance
            const $active = instance.instances[instance.currentIndex];
            // GF conditional logic will be applied via adapter
            this.applyGFConditionalToInstance($active, instance.formId);
            this.bindInstanceInputs($active, instance.formId);

            // Re-run GF conditional logic to re-evaluate visibility
            if (window.gform && typeof window.gform.doConditionalLogic === 'function') {
                try { window.gform.doConditionalLogic(instance.formId, true); } catch(e) {}
            }
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
            // Process repeater data before submission
            this.processRepeaterData(form);
        }

        /**
         * Process repeater data for submission
         */
        processRepeaterData(form) {
            // Flatten repeater fields into array format capturing each instance's values
            if (!form || !form.id) {
                return;
            }

            const formId = form.id;
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
                        if (!instanceData[nameKey]) {
                            instanceData[nameKey] = [];
                        }
                        instanceData[nameKey].push($field.val());
                    });

                    instancesData.push(instanceData);
                });

                const $hidden = $(`#input_${formId}_${fieldId}`);
                if ($hidden && $hidden.length) {
                    $hidden.val(JSON.stringify(instancesData));
                }
            });
        }

        // Removed custom per-field conditional logic; using GF map exclusively

        // Mark all gfield containers in an instance with data-gf-id for safe scoping
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

        // Evaluate GF conditional logic map for a single instance
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

        // Listen to GF conditional logic runs and mirror results into all instances
        attachGFListeners() {
            if (this.gfListenerAttached) return;
            this.gfListenerAttached = true;
            const self = this;
            $(document).on('gform_post_conditional_logic.gfRepeater', function(e, fid){
                // Re-apply to all instances for this form id
                self.instances.forEach((inst) => {
                    if (inst.formId === fid) {
                        inst.instances.forEach(($inst) => self.applyGFConditionalToInstance($inst, fid));
                    }
                });
            });
        }

        // Bind per-instance input changes to trigger immediate logic application
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
			});
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new GFRepeaterField();
    });

})(jQuery);
