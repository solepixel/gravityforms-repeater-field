/**
 * Form JavaScript for Gravity Forms Repeater Field
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

(function($) {
    'use strict';

    /**
     * Gravity Forms Repeater Field Form Handler
     */
    class GFRepeaterForm {
        constructor() {
            this.init();
        }

        /**
         * Initialize form-specific functionality
         */
        init() {
            this.bindEvents();
            this.initializeFormRepeaters();
        }

        /**
         * Bind form-specific events
         */
        bindEvents() {
            // Handle fieldset management
            $(document).on('gform_field_content', this.handleFieldsetManagement.bind(this));

            // Handle conditional logic in repeaters
            $(document).on('gform_conditional_logic', this.handleConditionalLogic.bind(this));

            // Handle validation
            $(document).on('gform_validation', this.handleValidation.bind(this));
        }

        /**
         * Initialize form repeaters
         */
        initializeFormRepeaters() {
            // Find all repeater fieldsets in the current form
            $('.gf-repeater-fieldset').each((index, element) => {
                this.initializeFormRepeater($(element));
            });
        }

        /**
         * Initialize a specific form repeater
         */
        initializeFormRepeater($fieldset) {
            const fieldId = $fieldset.data('field-id');
            const formId = $fieldset.closest('form').attr('id').replace('gform_', '');

            if (!fieldId || !formId) {
                return;
            }

            // Set up fieldset management
            this.setupFieldsetManagement($fieldset, fieldId, formId);
        }

        /**
         * Handle fieldset management
         */
        handleFieldsetManagement(content, field, value, leadId, formId) {
            if (field.type === 'section') {
                // Add fieldset wrapper if not already present
                if (!content.includes('<fieldset')) {
                    content = this.wrapInFieldset(content, field);
                }
            }

            return content;
        }

        /**
         * Wrap content in fieldset
         */
        wrapInFieldset(content, field) {
            const fieldsetId = `gf-fieldset-${field.id}`;
            const classes = ['gf-fieldset', 'gf-section-fieldset'];

            if (field.enableRepeater) {
                classes.push('gf-repeater-fieldset');
            }

            return `<fieldset id="${fieldsetId}" class="${classes.join(' ')}" data-field-id="${field.id}">${content}</fieldset>`;
        }

        /**
         * Set up fieldset management
         */
        setupFieldsetManagement($fieldset, fieldId, formId) {
            // Add fieldset management attributes
            $fieldset.attr('data-form-id', formId);
            $fieldset.attr('data-field-id', fieldId);

            // Set up conditional logic handling
            this.setupConditionalLogic($fieldset, fieldId, formId);
        }

        /**
         * Set up conditional logic for repeater fields
         */
        setupConditionalLogic($fieldset, fieldId, formId) {
            // Handle conditional logic for fields within the repeater
            $fieldset.find('.gfield').each((index, element) => {
                const $field = $(element);
                const fieldId = $field.attr('id');

                if (fieldId) {
                    this.setupFieldConditionalLogic($field, fieldId, formId);
                }
            });
        }

        /**
         * Set up conditional logic for individual fields
         */
        setupFieldConditionalLogic($field, fieldId, formId) {
            // This will be implemented to handle conditional logic
            // for fields within repeater fieldsets
        }

        /**
         * Handle conditional logic events
         */
        handleConditionalLogic(e, formId, fieldId, isInit) {
            // Handle conditional logic for repeater fields
            const $fieldset = $(`#gf-fieldset-${fieldId}`);
            if ($fieldset.length > 0) {
                this.updateRepeaterConditionalLogic($fieldset, formId);
            }
        }

        /**
         * Update conditional logic for repeater
         */
        updateRepeaterConditionalLogic($fieldset, formId) {
            // Update conditional logic for all instances of the repeater
            $fieldset.find('.gfield').each((index, element) => {
                const $field = $(element);
                this.updateFieldConditionalLogic($field, formId);
            });
        }

        /**
         * Update conditional logic for individual field
         */
        updateFieldConditionalLogic($field, formId) {
            // This will be implemented to update conditional logic
            // for individual fields within repeaters
        }

        /**
         * Handle validation
         */
        handleValidation(e, form) {
            // Validate repeater fields
            this.validateRepeaterFields(form);
        }

        /**
         * Validate repeater fields
         */
        validateRepeaterFields(form) {
            // Find all repeater fieldsets
            $('.gf-repeater-fieldset').each((index, element) => {
                const $fieldset = $(element);
                this.validateRepeaterFieldset($fieldset, form);
            });
        }

        /**
         * Validate a specific repeater fieldset
         */
        validateRepeaterFieldset($fieldset, form) {
            // Validate all fields within the fieldset
            $fieldset.find('.gfield').each((index, element) => {
                const $field = $(element);
                this.validateField($field, form);
            });
        }

        /**
         * Validate individual field
         */
        validateField($field, form) {
            // This will be implemented to validate individual fields
            // within repeater fieldsets
        }

        /**
         * Get repeater data for form submission
         */
        getRepeaterData(formId) {
            const repeaterData = {};

            $('.gf-repeater-fieldset').each((index, element) => {
                const $fieldset = $(element);
                const fieldId = $fieldset.data('field-id');
                const formId = $fieldset.data('form-id');

                if (fieldId && formId) {
                    repeaterData[fieldId] = this.getFieldsetData($fieldset);
                }
            });

            return repeaterData;
        }

        /**
         * Get data from a specific fieldset
         */
        getFieldsetData($fieldset) {
            const data = {};

            $fieldset.find('input, textarea, select').each((index, element) => {
                const $field = $(element);
                const name = $field.attr('name');
                const value = $field.val();

                if (name && value) {
                    if (name.includes('[]')) {
                        const baseName = name.replace('[]', '');
                        if (!data[baseName]) {
                            data[baseName] = [];
                        }
                        data[baseName].push(value);
                    } else {
                        data[name] = value;
                    }
                }
            });

            return data;
        }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        new GFRepeaterForm();
    });

})(jQuery);
