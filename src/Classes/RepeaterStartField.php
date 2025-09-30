<?php
/**
 * Repeater Start Field Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

use GF_Field;

/**
 * Repeater Start Field Class
 */
class RepeaterStartField extends GF_Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'repeater_start';

	/**
	 * Register the field type with Gravity Forms
	 *
	 * @return void
	 */
	public static function register_field(): void {
		\GF_Fields::register( new self() );
	}

	/**
	 * Get the field title
	 *
	 * @return string
	 */
	public function get_form_editor_field_title(): string {
		return esc_attr__( 'Repeater Start', 'gravityforms-repeater-field' );
	}

	/**
	 * Get the field button (Advanced Fields group)
	 *
	 * @return array
	 */
	public function get_form_editor_button(): array {
		return [
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		];
	}

    /**
     * Field settings available in editor.
     *
     * @return array
     */
    public function get_form_editor_field_settings(): array {
        return [
            'label_setting',
            'label_placement_setting',
            'description_setting',
            'css_class_setting',
            'visibility_setting',
        ];
    }

    /**
     * Render the input (frontend + editor preview-safe).
     *
     * @param array       $form
     * @param string      $value
     * @param null|array  $entry
     * @param null|array  $field
     * @param null|string $context
     * @return string
     */
    public function get_field_input( $form, $value = '', $entry = null, $field = null, $context = null ): string {
        $form_id  = isset( $form['id'] ) ? (int) $form['id'] : 0;
        $field_id = (int) $this->id;

        // In the form editor, output a simple, clickable preview block.
		if ( $this->is_form_editor() ) {
			// Avoid duplicating the label in the editor; only show helper text.
			return sprintf(
				'<div class="ginput_container"><div class="description">%s</div><hr /></div>',
				esc_html__( 'Fields placed after this will be repeatable until Repeater End.', 'gravityforms-repeater-field' )
			);
		}

        $label = $this->label ?: esc_html__( 'Repeatable Group', 'gravityforms-repeater-field' );

		$controls = sprintf(
			'<div class="gf-repeater-controls" data-form-id="%d" data-field-id="%d">
				<button type="button" class="gf-repeater-remove" title="%s" style="display:none;">-</button>
				<button type="button" class="gf-repeater-prev" title="%s" disabled>←</button>
				<span class="gf-repeater-count" aria-live="polite">1/1</span>
				<button type="button" class="gf-repeater-next" title="%s" disabled>→</button>
				<button type="button" class="gf-repeater-add" title="%s">+</button>
			</div>',
			$form_id,
			$field_id,
			esc_attr__( 'Remove Group', 'gravityforms-repeater-field' ),
			esc_attr__( 'Previous Group', 'gravityforms-repeater-field' ),
			esc_attr__( 'Next Group', 'gravityforms-repeater-field' ),
			esc_attr__( 'Add Group', 'gravityforms-repeater-field' )
		);

        $title = sprintf( '<h3 class="gf-group-title gsection_title">%s</h3>', esc_html( $label ) );

		$header = sprintf(
			'<div class="gf-repeater-header">%s%s</div>',
			$title,
			$controls
		);

		$hidden_input = sprintf(
			'<input type="hidden" class="gf-repeater-data-input" name="input_%1$d" id="input_%2$d_%1$d" />',
			$this->id,
			$form_id
		);

		$val = is_string( $value ) ? $value : '';
		return sprintf(
			'<div class="gf-repeater-header">%s%s</div><input type="hidden" class="gf-repeater-data-input" name="input_%d" id="input_%d_%d" value="%s">',
			$title,
			$controls,
			$field_id,
			$form_id,
			$field_id,
			esc_attr( $val )
		);
    }

			/**
			 * Return submitted value (JSON) for saving and re-render during validation.
			 *
			 * @param array $field_values
			 * @param bool  $get_from_post_global
			 * @return mixed
			 */
			public function get_value_submission( $field_values, $get_from_post_global = false ) {
				$input_name = 'input_' . (int) $this->id;
				$value = rgpost( $input_name );
				// Ensure we return the value for re-rendering
				return $value;
			}

			/**
			 * Store JSON as-is in the entry value (string).
			 *
			 * @param mixed $value
			 * @param int   $form_id
			 * @param string $field_id
			 * @param array $entry
			 * @param array $form
			 * @return string
			 */
			public function sanitize_entry_value( $value, $form_id, $field_id = '', $entry = array(), $form = array() ) {
				return (string) $value;
			}

			/**
			 * Override to ensure the field value is properly set for re-rendering.
			 *
			 * @param mixed $value
			 * @return void
			 */
			public function set_value( $value ) {
				$this->value = $value;
			}
}
