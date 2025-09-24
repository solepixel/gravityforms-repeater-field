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
            // Locate Start/End field rows in GF markup
            const $startGField = $(`#field_${formId}_${fieldId}`);
            if ($startGField.length === 0) return;

            const $endGField = $startGField.nextAll('li.gfield.gfield--type-repeater_end').first();
            if ($endGField.length === 0) return;

            // Remove the End field's label "for" on frontend and hide its wrapper spacing later
            $endGField.find('label.gfield_label').removeAttr('for');

            // Remove the Start label's "for" (no input is associated)
            $startGField.find('label.gfield_label').removeAttr('for');

            // Create fieldset wrapper right after Start
            let $fieldset = $(`#gf-fieldset-${fieldId}`);
            if ($fieldset.length === 0) {
                $fieldset = $('<fieldset/>', {
                    id: `gf-fieldset-${fieldId}`,
                    class: 'gf-fieldset gf-group-fieldset gf-repeater-fieldset active'
                });
                $fieldset.insertAfter($startGField);
            }

            // Move fields between Start and End into the first instance container
            const $between = $startGField.nextUntil($endGField, 'li.gfield');
            let $firstInstance = $fieldset.find('.gf-repeater-instance').first();
            if ($firstInstance.length === 0) {
                $firstInstance = $('<div class="gf-repeater-instance"/>');
                $fieldset.empty().append($firstInstance);
            } else {
                $firstInstance.empty();
            }
            $between.appendTo($firstInstance);

            // Remove the End field from frontend DOM to prevent spacing
            $endGField.remove();

            // Store repeater instance
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
            $newFieldset.addClass('gf-repeater-clone');

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
                    $field.attr('name', `${baseName}[]`);
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

            // Ensure DOM contains all instances in correct order
            const $container = instance.$fieldset;
            if ($container && $container.length) {
                // Remove any previously appended clones to avoid duplicates
                $container.find('.gf-repeater-instance').remove();
                // Append all instances in order
                instance.instances.forEach(($inst, index) => {
                    $container.append($inst);
                    if (index === instance.currentIndex) {
                        $inst.show().addClass('active');
                    } else {
                        $inst.hide().removeClass('active');
                    }
                });
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

                // Store serialized data on hidden input for submission, if desired
                let $hidden = $(`#input_${formId}_${fieldId}`);
                if ($hidden.length === 0) {
                    $hidden = $('<input/>', {
                        type: 'hidden',
                        id: `input_${formId}_${fieldId}`,
                        name: `input_${fieldId}`
                    });
                    $fieldset.append($hidden);
                }
                $hidden.val(JSON.stringify(instancesData));
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new GFRepeaterField();
    });

})(jQuery);
