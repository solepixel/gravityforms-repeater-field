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
 * Format a single field value for display (entry detail or email). Uses Gravity Forms display logic
 * so file uploads show as links and other types are formatted consistently.
 *
 * @param \GF_Field $field         The field object.
 * @param mixed     $value         Raw value (string or array).
 * @param array     $instance_data Full instance data (for "other" choice etc.).
 * @param string    $context       'entry_detail' or 'email'.
 * @param int       $entry_id      Optional. Entry ID (for file URL resolution and GF display).
 * @param array     $form          Optional. Form array (for GF display).
 * @return string Formatted value (HTML or plain text; not escaped).
 */
function gf_repeater_field_format_field_value_for_display( $field, $value, array $instance_data, $context, $entry_id = 0, $form = [] ) {
	if ( ! $field ) {
		return '';
	}

	$is_email = ( $context === 'email' );
	$format   = $is_email ? 'html' : 'html';
	$media    = $is_email ? 'email' : 'screen';

	// File upload: use GF's display so we get proper links (and resolve fakepath when possible).
	if ( isset( $field->type ) && $field->type === 'fileupload' ) {
		$value = gf_repeater_field_resolve_file_value( $value, $field, $entry_id, $form );
		if ( empty( $value ) ) {
			return '';
		}
		// Pass as JSON string if array (multiple files), else single URL string.
		$value_for_gf = is_array( $value ) ? wp_json_encode( array_values( $value ) ) : (string) $value;
		$display      = $field->get_value_entry_detail( $value_for_gf, '', false, $format, $media );
		return $display !== '' ? $display : '';
	}

	// Radio/checkbox with "Other" option.
	if ( ( $field->type === 'radio' || $field->type === 'checkbox' ) && ! empty( $instance_data ) ) {
		$values = is_array( $value ) ? $value : [ $value ];
		$out    = [];
		foreach ( $values as $v ) {
			if ( $v === 'gf_other_choice' ) {
				$other_key = 'input_' . $field->id . '_other';
				$other_val = isset( $instance_data[ $other_key ] ) ? $instance_data[ $other_key ] : '';
				if ( is_array( $other_val ) ) {
					$other_val = $other_val[0] ?? '';
				}
				$out[] = ! empty( $other_val ) ? __( 'Other', 'gravityforms-repeater-field' ) . ' (' . esc_html( $other_val ) . ')' : __( 'Other', 'gravityforms-repeater-field' );
			} else {
				$out[] = $v;
			}
		}
		return implode( ', ', $out );
	}

	// Default: flatten to string.
	$values = is_array( $value ) ? array_filter( $value ) : [ $value ];
	return implode( ', ', array_map( 'strval', $values ) );
}

/**
 * Resolve file value so that fakepath is replaced with the real Gravity Forms file URL when possible.
 *
 * @param mixed     $value    Raw value (string or array of strings).
 * @param \GF_Field $field    File upload field.
 * @param int       $entry_id Entry ID.
 * @param array     $form     Form array.
 * @return mixed Resolved value (string or array of URLs); unchanged if not resolvable.
 */
function gf_repeater_field_resolve_file_value( $value, $field, $entry_id, $form ) {
	$urls = is_array( $value ) ? $value : [ $value ];
	$resolved = [];
	foreach ( $urls as $url ) {
		if ( ! is_string( $url ) || trim( $url ) === '' ) {
			continue;
		}
		// Already a URL that looks like GF or absolute.
		if ( preg_match( '#^https?://#i', $url ) ) {
			$resolved[] = $url;
			continue;
		}
		// Fakepath or local path: try to resolve to real GF URL.
		$is_fakepath = ( strpos( $url, 'fakepath' ) !== false || preg_match( '#^[A-Za-z]:\\\\#', $url ) );
		if ( $is_fakepath && $entry_id > 0 && ! empty( $form['id'] ) ) {
			$real_url = gf_repeater_field_find_file_url_by_basename( $url, (int) $form['id'], $entry_id );
			if ( $real_url !== '' ) {
				$resolved[] = $real_url;
				continue;
			}
		}
		$resolved[] = $url;
	}
	return is_array( $value ) ? $resolved : ( $resolved[0] ?? '' );
}

/**
 * Try to find the real GF file URL for an upload when the stored value is fakepath (basename only).
 *
 * @param string $filename  Stored value (e.g. "C:\fakepath\IMG_2443.jpg").
 * @param int    $form_id   Form ID.
 * @param int    $entry_id  Entry ID.
 * @return string URL if found, empty string otherwise.
 */
function gf_repeater_field_find_file_url_by_basename( $filename, $form_id, $entry_id ) {
	$basename = wp_basename( $filename );
	if ( $basename === '' ) {
		return '';
	}
	$entry = \GFAPI::get_entry( $entry_id );
	if ( is_wp_error( $entry ) || empty( $entry['date_created'] ) ) {
		return '';
	}
	$date = $entry['date_created'];
	$y    = substr( $date, 0, 4 );
	$m    = substr( $date, 5, 2 );
	$dir  = \GFFormsModel::get_upload_path( $form_id ) . "/{$y}/{$m}/";
	if ( ! is_dir( $dir ) ) {
		return '';
	}
	$found = null;
	try {
		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		foreach ( $it as $file ) {
			if ( $file->isFile() && $file->getFilename() === $basename ) {
				$found = $file->getPathname();
				break;
			}
		}
	} catch ( \Exception $e ) {
		return '';
	}
	if ( $found === null ) {
		return '';
	}
	$url_root = \GFFormsModel::get_upload_url( $form_id ) . "/{$y}/{$m}/";
	$rel      = str_replace( $dir, '', $found );
	return $url_root . str_replace( '\\', '/', $rel );
}

/**
 * Get repeater data as a list of groups, each with label and rows (label + formatted value).
 * Shared by entry detail and email merge tag so display logic stays in one place.
 *
 * @param array  $repeater_data Decoded repeater data (array of instances).
 * @param array  $form          Gravity Forms form array.
 * @param string $context       'entry_detail' or 'email'.
 * @param int    $entry_id      Optional. Entry ID (for file resolution).
 * @return array List of [ 'group_label' => string, 'rows' => [ [ 'label' => string, 'value' => string, 'field' => \GF_Field ], ... ] ].
 */
function gf_repeater_field_get_formatted_instance_rows( array $repeater_data, array $form, $context = 'entry_detail', $entry_id = 0 ) {
	if ( empty( $repeater_data ) ) {
		return [];
	}

	$groups = [];
	foreach ( $repeater_data as $index => $instance ) {
		$group_label = sprintf(
			/* translators: %d: 1-based group index */
			__( 'Group %d', 'gravityforms-repeater-field' ),
			$index + 1
		);
		$rows = [];
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
			$label   = $context === 'email' ? $field->label : ( $field->adminLabel ?: $field->label );
			$display = gf_repeater_field_format_field_value_for_display( $field, $value, $instance, $context, $entry_id, $form );
			$rows[]  = [ 'label' => $label, 'value' => $display, 'field' => $field ];
		}
		if ( empty( $rows ) && $context === 'entry_detail' ) {
			$rows[] = [ 'label' => '', 'value' => __( 'No data submitted for this group.', 'gravityforms-repeater-field' ), 'field' => null ];
		}
		$groups[] = [ 'group_label' => $group_label, 'rows' => $rows ];
	}
	return $groups;
}

/**
 * Format repeater field value for email/merge tags (labels and values, html or text).
 * Uses the same formatted rows as the entry detail page (GF default pattern).
 *
 * @param array  $repeater_data Decoded repeater data (array of instances, each with input_id => value).
 * @param array  $form          Gravity Forms form array.
 * @param string $format        'html' or 'text'.
 * @param int    $entry_id      Optional. Entry ID for file URL resolution.
 * @return string Formatted output for email.
 */
function gf_repeater_field_format_merge_tag_value( array $repeater_data, array $form, string $format = 'html', $entry_id = 0 ): string {
	$groups = gf_repeater_field_get_formatted_instance_rows( $repeater_data, $form, 'email', $entry_id );
	if ( empty( $groups ) ) {
		return '';
	}

	$output = '';
	foreach ( $groups as $group ) {
		if ( 'text' === $format ) {
			$output .= "--------------------------------\n" . $group['group_label'] . "\n\n";
		} else {
			$output .= sprintf(
				'<tr><td colspan="2" style="font-size:14px; font-weight:bold; background-color:#EEE; border-bottom:1px solid #DFDFDF; padding:7px 7px">%s</td></tr>',
				esc_html( $group['group_label'] )
			);
		}
		foreach ( $group['rows'] as $row ) {
			$label   = $row['label'];
			$display = $row['value'];
			$display = trim( $display );
			$field   = isset( $row['field'] ) ? $row['field'] : null;
			if ( 'text' === $format ) {
				$output .= $label . ': ' . strip_tags( $display ) . "\n\n";
			} else {
				// Same pattern as Gravity Forms default: label and data row with filterable bgcolor (defaults #EAF2FA / #FFFFFF).
				$label_bg = esc_attr( apply_filters( 'gform_email_background_color_label', '#EAF2FA', $field, [] ) );
				$data_bg  = esc_attr( apply_filters( 'gform_email_background_color_data', '#FFFFFF', $field, [] ) );
				$output  .= sprintf(
					'<tr bgcolor="%3$s"><td colspan="2"><font style="font-family: sans-serif; font-size:12px;"><strong>%1$s</strong></font></td></tr>' .
					'<tr bgcolor="%4$s"><td width="20">&nbsp;</td><td><font style="font-family: sans-serif; font-size:12px;">%2$s</font></td></tr>',
					esc_html( $label ),
					$display ?: '&nbsp;',
					$label_bg,
					$data_bg
				);
			}
		}
	}

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
