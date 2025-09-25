<?php
/**
 * Admin Display Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

/**
 * Admin Display Class
 */
class AdminDisplay {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize admin display
	 *
	 * @return void
	 */
	private function init(): void {
		// Modify entry display for repeater fields
		add_filter( 'gform_field_content', [ $this, 'modify_entry_display' ], 10, 5 );

		// Add custom display for repeater data
		add_action( 'gform_entry_detail_content_before', [ $this, 'display_repeater_data' ], 10, 2 );
	}

	/**
	 * Modify entry display for repeater fields
	 *
	 * @param string $content
	 * @param array $field
	 * @param string $value
	 * @param int $lead_id
	 * @param int $form_id
	 * @return string
	 */
	public function modify_entry_display( $content, $field, $value, $lead_id, $form_id ): string {
		if ( $field->type === 'group' ) {
			$content = $this->get_repeater_entry_display( $field, $value, $lead_id, $form_id );
		}

		return $content;
	}

	/**
	 * Get repeater entry display
	 *
	 * @param array $field
	 * @param string $value
	 * @param int $lead_id
	 * @param int $form_id
	 * @return string
	 */
	private function get_repeater_entry_display( $field, $value, $lead_id, $form_id ): string {
		$repeater_data = $this->get_repeater_data( $field, $lead_id, $form_id );

		if ( empty( $repeater_data ) ) {
			return '<div class="gf-repeater-empty">' . __( 'No data available', 'gravityforms-repeater-field' ) . '</div>';
		}

		$output = '<div class="gf-repeater-entry-display">';
		$output .= '<h4>' . esc_html( $field->label ) . '</h4>';
		$output .= '<div class="gf-repeater-instances">';

		foreach ( $repeater_data as $index => $instance ) {
			$output .= '<div class="gf-repeater-instance">';
			$output .= '<h5>' . sprintf( __( 'Instance %d', 'gravityforms-repeater-field' ), $index + 1 ) . '</h5>';
			$output .= '<div class="gf-repeater-fields">';

			foreach ( $instance as $field_id => $field_value ) {
				$field_obj = \GFAPI::get_field( $form_id, $field_id );
				if ( $field_obj ) {
					$output .= '<div class="gf-repeater-field">';
					$output .= '<label>' . esc_html( $field_obj->label ) . ':</label> ';
					$output .= '<span>' . esc_html( $field_value ) . '</span>';
					$output .= '</div>';
				}
			}

			$output .= '</div>';
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	/**
	 * Get repeater data for entry
	 *
	 * @param array $field
	 * @param int $lead_id
	 * @param int $form_id
	 * @return array
	 */
	private function get_repeater_data( $field, $lead_id, $form_id ): array {
		$entry = \GFAPI::get_entry( $lead_id );
		if ( is_wp_error( $entry ) ) {
			return [];
		}

		$repeater_data = [];
		$section_fields = $this->get_section_fields( $field, $form_id );

		// Group fields by instance
		foreach ( $section_fields as $section_field ) {
			$field_value = rgar( $entry, $section_field->id );
			if ( ! empty( $field_value ) ) {
				$repeater_data[0][$section_field->id] = $field_value;
			}
		}

		return $repeater_data;
	}

	/**
	 * Get section fields
	 *
	 * @param array $section_field
	 * @param int $form_id
	 * @return array
	 */
	private function get_section_fields( $section_field, $form_id ): array {
		$form = \GFAPI::get_form( $form_id );
		$section_fields = [];

		$in_section = false;
		foreach ( $form['fields'] as $field ) {
			if ( $field->id === $section_field->id ) {
				$in_section = true;
				continue;
			}

			if ( $in_section && $field->type === 'section' ) {
				break;
			}

			if ( $in_section ) {
				$section_fields[] = $field;
			}
		}

		return $section_fields;
	}

	/**
	 * Display repeater data
	 *
	 * @param int $form_id
	 * @param int $lead_id
	 * @return void
	 */
	public function display_repeater_data( $form_id, $lead_id ): void {
		$form = \GFAPI::get_form( $form_id );
		$has_repeater_sections = false;

		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'group' ) {
				$has_repeater_sections = true;
				break;
			}
		}

		if ( ! $has_repeater_sections ) {
			return;
		}

		echo '<div class="gf-repeater-data-display">';
		echo '<h3>' . __( 'Repeater Field Data', 'gravityforms-repeater-field' ) . '</h3>';
		echo '<div class="gf-repeater-summary">';
		echo '<p>' . __( 'This entry contains repeater field data. Use the controls above to navigate between instances.', 'gravityforms-repeater-field' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}
}
