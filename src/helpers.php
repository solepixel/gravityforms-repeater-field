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
 * Get repeater field data from entry.
 *
 * @param int $entry_id Entry ID.
 * @param int $field_id Field ID for the repeater_start field.
 * @return array Parsed repeater data for this field; empty array if none.
 */
function gf_repeater_field_get_entry_data( int $entry_id, int $field_id ): array {
	$entry = \GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) ) {
		return [];
	}

	$repeater_data = [];
	$field_value   = rgar( $entry, $field_id );

	if ( ! empty( $field_value ) ) {
		$repeater_data = maybe_unserialize( $field_value );
	}

	return is_array( $repeater_data ) ? $repeater_data : [];
}

/**
 * Format repeater field value for display.
 *
 * Note: This returns raw HTML without escaping as escaping should occur at the point of output.
 *
 * @param array $repeater_data Repeater data structure.
 * @param array $form          Gravity Forms form array.
 * @return string HTML markup representing the repeater data.
 */
function gf_repeater_field_format_display_value( array $repeater_data, array $form ): string {
	if ( empty( $repeater_data ) ) {
		return __( 'No data available', 'gravityforms-repeater-field' );
	}

	$output = '<div class="gf-repeater-display">';

	foreach ( $repeater_data as $index => $instance ) {
		$output .= '<div class="gf-repeater-instance">';
		$output .= sprintf( '<h4>%s</h4>', sprintf( __( 'Instance %d', 'gravityforms-repeater-field' ), $index + 1 ) );

		foreach ( $instance as $field_id => $value ) {
			$field = \GFAPI::get_field( $form, $field_id );
			if ( $field ) {
				$output .= '<div class="gf-repeater-field">';
				$output .= sprintf( '<strong>%s:</strong> ', $field->label );
				$output .= sprintf( '<span>%s</span>', is_array( $value ) ? implode( ', ', $value ) : $value );
				$output .= '</div>';
			}
		}

		$output .= '</div>';
	}

	$output .= '</div>';

	return $output;
}

/**
 * Format repeater field value for email/merge tags (labels and values, html or text).
 *
 * @param array  $repeater_data Decoded repeater data (array of instances, each with input_id => value).
 * @param array  $form          Gravity Forms form array.
 * @param string $format        'html' or 'text'.
 * @return string Formatted output for email.
 */
function gf_repeater_field_format_merge_tag_value( array $repeater_data, array $form, string $format = 'html' ): string {
	if ( empty( $repeater_data ) ) {
		return '';
	}

	$output = '';
	foreach ( $repeater_data as $index => $instance ) {
		$group_label = sprintf(
			/* translators: %d: 1-based group index */
			__( 'Group %d', 'gravityforms-repeater-field' ),
			$index + 1
		);

		if ( 'text' === $format ) {
			$output .= "--------------------------------\n" . $group_label . "\n\n";
		} else {
			$output .= sprintf(
				'<tr><td colspan="2" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px">%s</td></tr>',
				esc_html( $group_label )
			);
		}

		foreach ( $instance as $input_key => $value ) {
			if ( '' === $input_key || strpos( $input_key, 'input_' ) !== 0 ) {
				continue;
			}
			$field_id = (int) str_replace( 'input_', '', $input_key );
			if ( $field_id <= 0 ) {
				continue;
			}
			$field = \GFAPI::get_field( $form, $field_id );
			if ( ! $field ) {
				continue;
			}
			$label   = $field->label;
			$display = is_array( $value ) ? implode( ', ', array_filter( $value ) ) : (string) $value;
			$display = trim( $display );
			if ( 'text' === $format ) {
				$output .= $label . ': ' . $display . "\n\n";
			} else {
				$output .= sprintf(
					'<tr bgcolor="#EAF2FA"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>%s</strong></font></td></tr>' .
					'<tr bgcolor="#FFFFFF"><td width="20">&nbsp;</td><td><font style="font-family: sans-serif; font-size:12px;">%s</font></td></tr>',
					esc_html( $label ),
					esc_html( $display ) ?: '&nbsp;'
				);
			}
		}
	}

	// Repeater output is inserted inside a single <td>; wrap in a nested table for valid HTML.
	if ( 'html' === $format && $output !== '' ) {
		$output = '<table width="100%" border="0" cellpadding="2" cellspacing="0" style="margin-top:4px"><tbody>' . $output . '</tbody></table>';
	}

	return $output;
}

/**
 * Get section fields for a form.
 *
 * @param array $form              Gravity Forms form array.
 * @param int   $section_field_id  The starting section field ID.
 * @return array Ordered list of field objects inside the section.
 */
function gf_repeater_field_get_section_fields( array $form, int $section_field_id ): array {
	$section_fields = [];
	$in_section     = false;

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
 * Duplicate field for repeater.
 *
 * @param array $field          Field array to duplicate.
 * @param int   $instance_index Instance index to append.
 * @return array Duplicated field array with adjusted identifiers.
 */
function gf_repeater_field_duplicate_field( array $field, int $instance_index ): array {
	$duplicated_field = $field;

	// Update field ID to include instance index.
	$duplicated_field['id'] = $field['id'] . '_' . $instance_index;

	// Update field name to include array notation.
	$duplicated_field['name'] = $field['name'] . '[]';

	// Update field input ID.
	$duplicated_field['inputId'] = $field['inputId'] . '_' . $instance_index;

	return $duplicated_field;
}

/**
 * Apply conditional logic to duplicated field.
 *
 * @param array $field          Field array.
 * @param int   $instance_index Instance index.
 * @return array Field array with adjusted conditional logic field IDs.
 */
function gf_repeater_field_apply_conditional_logic( array $field, int $instance_index ): array {
	if ( empty( $field['conditionalLogic'] ) ) {
		return $field;
	}

	$conditional_logic = $field['conditionalLogic'];

	// Update conditional logic field IDs to match duplicated fields.
	foreach ( $conditional_logic['rules'] as &$rule ) {
		$rule['fieldId'] = $rule['fieldId'] . '_' . $instance_index;
	}

	$field['conditionalLogic'] = $conditional_logic;

	return $field;
}
