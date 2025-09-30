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
		// Only apply admin filters in admin area.
		if ( ! is_admin() ) {
			return;
		}

		// Modify entry display for repeater fields in entries table.
		add_filter(
			'gform_entries_field_value',
			[ $this, 'modify_entries_field_value' ],
			10,
			4
		);

		// Modify field content in entry detail page.
		add_filter(
			'gform_field_content',
			[ $this, 'modify_field_content' ],
			10,
			5
		);

		// Repeater details will be rendered within the repeater_start field content.
	}

	/**
	 * Modify entries field value for entries table
	 *
	 * @param string $value
	 * @param int $form_id
	 * @param int $field_id
	 * @param array $entry
	 * @return string
	 */
	public function modify_entries_field_value( $value, $form_id, $field_id, $entry ): string {
		$form = \GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) ) {
			return $value;
		}

		$field = \GFAPI::get_field( $form, $field_id );
		if ( is_wp_error( $field ) ) {
			return $value;
		}

		// Handle repeater_start field - show submission count.
		if ( 'repeater_start' === $field->type ) {
			// Get the actual value from the entry data.
			$field_value = rgar( $entry, $field_id );
			if ( ! empty( $field_value ) ) {
				$decoded_data = json_decode( $field_value, true );
				if ( is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
					$count = count( $decoded_data );
					return sprintf( '%d %s', $count, ( 1 === $count ? 'submission' : 'submissions' ) );
				}
			}
			return '0 submissions';
		}

		// Hide repeater_end field from entry display.
		if ( 'repeater_end' === $field->type ) {
			return '';
		}

		// Hide individual fields that are within repeater groups.
		if ( $this->is_field_in_repeater_group( $field, $form_id ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Modify field content in entry detail page
	 *
	 * @param string $content
	 * @param object $field
	 * @param string $value
	 * @param int $lead_id
	 * @param int $form_id
	 * @return string
	 */
	public function modify_field_content( $content, $field, $value, $lead_id, $form_id ): string {
		// Only apply in admin area and on entry detail pages
		if ( ! is_admin() || ! $this->is_entry_detail_page() ) {
			return $content;
		}

		// Render grouped repeater instances within the repeater_start field block.
		if ( 'repeater_start' === $field->type ) {
			$decoded = [];
			if ( ! empty( $value ) ) {
				$maybe = json_decode( $value, true );
				if ( is_array( $maybe ) ) {
					$decoded = $maybe;
				}
			}

			// Build rows that will be nested inside the field value cell.
			$nested_rows = '';
			if ( ! empty( $decoded ) ) {
				$form = \GFAPI::get_form( $form_id );
				foreach ( $decoded as $i => $instance_data ) {
					$nested_rows .= sprintf(
						'<tr><td colspan="2" class="entry-view-field-name" style="font-weight:bold;color:#0073aa;background:#f9f9f9;border-left:4px solid #0073aa;padding:10px;">%s %d</td></tr>',
						$field->label,
						( $i + 1 )
					);
					if ( empty( $instance_data ) ) {
						$nested_rows .= sprintf(
							'<tr><td colspan="2" class="entry-view-field-value" style="color:#666;font-style:italic;padding:10px;">%s</td></tr>',
							__( 'No data submitted for this group.', 'gravityforms-repeater-field' )
						);
						continue;
					}
					foreach ( $instance_data as $key => $val ) {
						if ( strpos( $key, '_other' ) !== false ) {
							continue;
						}
						$field_obj   = $this->get_field_by_input_name( $form, $key );
						$label       = $field_obj ? ( $field_obj->adminLabel ?: $field_obj->label ) : $key;
						$display_val = $this->format_field_value_for_display( $field_obj, $val, $instance_data );
						$nested_rows .= sprintf(
							'<tr><td colspan="2" class="entry-view-field-name" style="padding-left:20px;font-weight:bold;">%s</td></tr>',
							$label
						);
						$nested_rows .= sprintf(
							'<tr><td colspan="2" class="entry-view-field-value" style="padding-left:20px;color:#666;">%s</td></tr>',
							$display_val
						);
					}
				}
			}

			// Return two rows in the main table: label row and a value row containing a nested table.
			$label_row = sprintf( '<tr><td colspan="2" class="entry-view-field-name">%s</td></tr>', $field->label );
			$value_row = sprintf( '<tr><td colspan="2" class="entry-view-field-value"><table cellspacing="0" class="entry-details-table"><tbody>%s</tbody></table></td></tr>', $nested_rows );
			return $label_row . $value_row;
		}

		// Hide repeater_end field from entry detail.
		if ( 'repeater_end' === $field->type ) {
			return '';
		}

		// Hide individual fields that are within repeater groups.
		if ( $this->is_field_in_repeater_group( $field, $form_id ) ) {
			return '';
		}

		return $content;
	}

    /**
     * Inject repeater rows into the entry detail table markup (server-side string replacement).
     *
     * @param string $content
     * @param array  $form
     * @param array  $entry
     * @param bool   $allow_display_empty_fields
     * @param bool   $include_html
     * @return string
     */
    // Removed server-side string filter; using after-content action with JS insertion.

	/**
	 * Check if we're on an entry detail page
	 *
	 * @return bool
	 */
	private function is_entry_detail_page(): bool {
		global $pagenow;
		return 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'gf_entries' === $_GET['page'] && isset( $_GET['view'] ) && 'entry' === $_GET['view'];
	}

	/**
	 * Check if a field is within a repeater group
	 *
	 * @param object $field
	 * @param int $form_id
	 * @return bool
	 */
	private function is_field_in_repeater_group( $field, $form_id ): bool {
		$form = \GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) ) {
			return false;
		}

		$in_repeater_group = false;
		foreach ( $form['fields'] as $form_field ) {
			// Start of repeater group.
			if ( 'repeater_start' === $form_field->type ) {
				$in_repeater_group = true;
				continue;
			}

			// End of repeater group.
			if ( 'repeater_end' === $form_field->type ) {
				$in_repeater_group = false;
				continue;
			}

			// Check if current field is within repeater group.
			if ( $in_repeater_group && $form_field->id === $field->id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get field object by input name
	 *
	 * @param array $form
	 * @param string $input_name
	 * @return object|null
	 */
	private function get_field_by_input_name( $form, $input_name ): ?object {
		// Extract numeric field ID from patterns like input_12, input_12.3, input_12_other
		if ( preg_match( '/^input_(\d+)/', (string) $input_name, $m ) ) {
			$field_id = (int) $m[1];
			foreach ( $form['fields'] as $field ) {
				if ( (int) $field->id === $field_id ) {
					return $field;
				}
			}
		}
		return null;
	}

	/**
	 * Format field value for display, handling special cases like "Other" radio buttons
	 *
	 * @param object $field_obj The field object
	 * @param mixed  $field_values The field values
	 * @param array  $instance_data The full instance data
	 * @return string Formatted display value
	 */
	private function format_field_value_for_display( $field_obj, $field_values, $instance_data = [] ) {
		if ( ! $field_obj || empty( $field_values ) ) {
			return '';
		}

		// Handle array values (like radio buttons, checkboxes).
		$values = is_array( $field_values ) ? $field_values : [ $field_values ];

		// Special handling for radio/checkbox with "Other" option.
		if ( $field_obj->type === 'radio' || $field_obj->type === 'checkbox' ) {
			$formatted_values = [];

			foreach ( $values as $value ) {
				if ( $value === 'gf_other_choice' ) {
					$other_field_key = 'input_' . $field_obj->id . '_other';
					$other_value = isset( $instance_data[ $other_field_key ] ) ? $instance_data[ $other_field_key ] : '';
					// other value can be string or [string]
					if ( is_array( $other_value ) ) {
						$other_value = $other_value[0] ?? '';
					}
					$formatted_values[] = ! empty( $other_value ) ? __( 'Other', 'gravityforms-repeater-field' ) . ' (' . esc_html( $other_value ) . ')' : __( 'Other', 'gravityforms-repeater-field' );
				} else {
					$formatted_values[] = $value;
				}
			}

			return implode( ', ', $formatted_values );
		}

		// Default handling for other field types
		return implode( ', ', $values );
	}
}
