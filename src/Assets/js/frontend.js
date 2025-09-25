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

            // Remove for attributes on labels (no inputs associated)
            $endGField.find('label.gfield_label').removeAttr('for');
            $startGField.find('label.gfield_label').removeAttr('for');

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

            // Find the newly created instance (now adjacent to Start)
            let $firstInstance = $startGField.next('.gf-repeater-instance');
            if ($firstInstance.length === 0) {
                // Fallback (should not happen): create empty instance to avoid null refs
                $firstInstance = $('<div class="gf-repeater-instance"/>');
            }

            // Create a fieldset immediately after the Start field and move the wrapped instance inside
            let $fieldset = $(`#gf-fieldset-${fieldId}`);
            if ($fieldset.length === 0) {
                $fieldset = $('<fieldset/>', {
                    id: `gf-fieldset-${fieldId}`,
                    class: 'gf-fieldset gf-group-fieldset gf-repeater-fieldset active'
                }).attr('data-field-id', fieldId);
                $fieldset.insertAfter($startGField);
                // Add viewport + track
                $fieldset.append('<div class="gf-repeater-viewport"><div class="gf-repeater-track"/></div>');
            }
            // Ensure track exists
            if ($fieldset.find('.gf-repeater-track').length === 0) {
                $fieldset.append('<div class="gf-repeater-viewport"><div class="gf-repeater-track"/></div>');
            }
            $fieldset.find('.gf-repeater-track').append($firstInstance);

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

            // Append into DOM
            if (instance.$fieldset && instance.$fieldset.length) {
                instance.$fieldset.append($newFieldset);
            }

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
            $newFieldset.addClass('gf-repeater-instance');

            // Update IDs and names for the new instance
            this.updateFieldsetIds($newFieldset, instance.totalInstances);

            // Clear field values
            $newFieldset.find('input, textarea, select').val('');

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
                    // Keep controller radios unindexed for GF conditional logic to continue working
                    if (type === 'radio') {
                        $field.attr('name', baseName);
                    } else {
                        $field.attr('name', `${baseName}[]`);
                    }
                }
            });

            // Update label for attributes
            $fieldset.find('label[for]').each(function() {
                const $label = $(this);
                const originalFor = $label.attr('for');
                if (originalFor) {
                    $label.attr('for', `${originalFor}_${instanceIndex}`);
                }
            });
        }

        /**
         * Update display to show current instance
         */
        updateDisplay(repeaterId) {
            const instance = this.instances.get(repeaterId);
            if (!instance) return;

            // Ensure all instances are present inside the track
            let $track = instance.$fieldset.children('.gf-repeater-track');
            if ($track.length === 0) {
                instance.$fieldset.append('<div class="gf-repeater-track"/>');
                $track = instance.$fieldset.children('.gf-repeater-track');
            }
            instance.instances.forEach(($inst) => {
                if (!$.contains($track[0], $inst[0])) {
                    $track.append($inst);
                }
            });

            // Slide transition using the track
            const offset = -(instance.currentIndex * 100);
            $track.css({ transform: `translateX(${offset}%)` });
            instance.instances.forEach(($inst) => {
                $inst.css({ minWidth: '100%' });
            });

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

            // Update remove button visibility
            if (instance.totalInstances > 1) {
                $removeBtn.show();
            } else {
                $removeBtn.hide();
            }

            // Update navigation buttons
            $prevBtn.prop('disabled', instance.currentIndex === 0);
            $nextBtn.prop('disabled', instance.currentIndex >= instance.totalInstances - 1);
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

                        const nameKey = baseName.endsWith('[]') ? baseName.slice(0, -2) : baseName;
                        if (!instanceData[nameKey]) {
                            instanceData[nameKey] = [];
                        }
                        instanceData[nameKey].push($field.val());
                    });

                    instancesData.push(instanceData);
                });

                const $hidden = instance.$wrapper.find(`#input_${formId}_${fieldId}`);
                $hidden.val(JSON.stringify(instancesData));
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new GFRepeaterField();
    });

})(jQuery);
