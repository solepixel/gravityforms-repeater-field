<?php
/**
 * Helper Functions
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get plugin version
 *
 * @return string
 */
function gf_repeater_field_get_version(): string {
	return GF_REPEATER_FIELD_VERSION;
}

/**
 * Get plugin directory path
 *
 * @return string
 */
function gf_repeater_field_get_plugin_dir(): string {
	return GF_REPEATER_FIELD_PLUGIN_DIR;
}

/**
 * Get plugin URL
 *
 * @return string
 */
function gf_repeater_field_get_plugin_url(): string {
	return GF_REPEATER_FIELD_PLUGIN_URL;
}

/**
 * Check if Gravity Forms is active
 *
 * @return bool
 */
function gf_repeater_field_is_gravity_forms_active(): bool {
	return class_exists( 'GFForms' );
}

/**
 * Get repeater field data from entry
 *
 * @param int $entry_id
 * @param int $field_id
 * @return array
 */
function gf_repeater_field_get_entry_data( int $entry_id, int $field_id ): array {
	$entry = \GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		return [];
	}

	$repeater_data = [];
	$field_value = rgar( $entry, $field_id );

	if ( ! empty( $field_value ) ) {
		$repeater_data = maybe_unserialize( $field_value );
	}

	return is_array( $repeater_data ) ? $repeater_data : [];
}

/**
 * Format repeater field value for display
 *
 * @param array $repeater_data
 * @param array $form
 * @return string
 */
function gf_repeater_field_format_display_value( array $repeater_data, array $form ): string {
	if ( empty( $repeater_data ) ) {
		return __( 'No data available', 'gravityforms-repeater-field' );
	}

	$output = '<div class="gf-repeater-display">';

	foreach ( $repeater_data as $index => $instance ) {
		$output .= '<div class="gf-repeater-instance">';
		$output .= '<h4>' . sprintf( __( 'Instance %d', 'gravityforms-repeater-field' ), $index + 1 ) . '</h4>';

		foreach ( $instance as $field_id => $value ) {
			$field = \GFAPI::get_field( $form, $field_id );
			if ( $field ) {
				$output .= '<div class="gf-repeater-field">';
				$output .= '<strong>' . esc_html( $field->label ) . ':</strong> ';
				$output .= '<span>' . esc_html( $value ) . '</span>';
				$output .= '</div>';
			}
		}

		$output .= '</div>';
	}

	$output .= '</div>';

	return $output;
}

/**
 * Get section fields for a form
 *
 * @param array $form
 * @param int $section_field_id
 * @return array
 */
function gf_repeater_field_get_section_fields( array $form, int $section_field_id ): array {
	$section_fields = [];
	$in_section = false;

	foreach ( $form['fields'] as $field ) {
		if ( $field->id === $section_field_id ) {
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
 * Duplicate field for repeater
 *
 * @param array $field
 * @param int $instance_index
 * @return array
 */
function gf_repeater_field_duplicate_field( array $field, int $instance_index ): array {
	$duplicated_field = $field;

	// Update field ID to include instance index
	$duplicated_field['id'] = $field['id'] . '_' . $instance_index;

	// Update field name to include array notation
	$duplicated_field['name'] = $field['name'] . '[]';

	// Update field input ID
	$duplicated_field['inputId'] = $field['inputId'] . '_' . $instance_index;

	return $duplicated_field;
}

/**
 * Apply conditional logic to duplicated field
 *
 * @param array $field
 * @param int $instance_index
 * @return array
 */
function gf_repeater_field_apply_conditional_logic( array $field, int $instance_index ): array {
	if ( empty( $field['conditionalLogic'] ) ) {
		return $field;
	}

	$conditional_logic = $field['conditionalLogic'];

	// Update conditional logic field IDs to match duplicated fields
	foreach ( $conditional_logic['rules'] as &$rule ) {
		$rule['fieldId'] = $rule['fieldId'] . '_' . $instance_index;
	}

	$field['conditionalLogic'] = $conditional_logic;

	return $field;
}
