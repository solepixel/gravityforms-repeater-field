<?php
/**
 * Repeater End Field Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

use GF_Field;

/**
 * Repeater End Field Class
 */
class RepeaterEndField extends GF_Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'repeater_end';

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
		return esc_attr__( 'Repeater End', 'gravityforms-repeater-field' );
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
	 * Hide label on frontend; allow editor to manage label display.
	 *
	 * @param bool   $force_frontend_label
	 * @param string $value
	 * @return string
	 */
	public function get_field_label( $force_frontend_label = false, $value = '' ) { // phpcs:ignore
		if ( $this->is_form_editor() ) {
			return parent::get_field_label( $force_frontend_label, $value );
		}

		return '';
	}

	/**
	 * Do not output a wrapper or input HTML on frontend for End field.
	 *
	 * @param array       $form
	 * @param string      $value
	 * @param null|array  $entry
	 * @param null|array  $field
	 * @param null|string $context
	 * @return string
	 */
	public function get_field_content( $value, $force_frontend_label, $form ) { // phpcs:ignore
		if ( $this->is_form_editor() ) {
			return sprintf(
				'<div class="ginput_container"><strong>%s</strong><hr /></div>',
				esc_html__( 'End Repeater', 'gravityforms-repeater-field' )
			);
		}

		// Frontend: return empty string so no extra spacing is introduced.
		return '';
	}

	/**
	 * Field settings available in editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings(): array {
		// No configurable settings for End field; render-only.
		return [];
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
		// In the form editor, output a simple, clickable preview block with label.
		if ( $this->is_form_editor() ) {
			// Render an identifier; the main label area is updated via editor JS to mirror the Start label.
			return '<div class="ginput_container"><strong>' . esc_html__( 'End Repeater', 'gravityforms-repeater-field' ) . '</strong><hr /></div>';
		}

		// Close the fieldset opened by Repeater Start.
		return '</fieldset>';
	}
}
