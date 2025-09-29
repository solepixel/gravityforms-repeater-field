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
		add_filter( 'gform_entry_field_value', [ $this, 'modify_entry_display' ], 10, 4 );

		// Display structured repeater data
		add_action( 'gform_entries_detail_content_after', [ $this, 'display_repeater_data' ], 10, 2 );
	}

	/**
	 * Modify entry display for repeater fields
	 *
	 * @param string $value
	 * @param array $field
	 * @param array $entry
	 * @param int $form_id
	 * @return string
	 */
	public function modify_entry_display( $value, $field, $entry, $form_id ): string {
		// Handle repeater_start field - show submission count.
		if ( 'repeater_start' === $field->type ) {
			if ( ! empty( $value ) ) {
				$decoded_data = json_decode( $value, true );
				if ( is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
					$count = count( $decoded_data );
					return $count . ' ' . ( $count === 1 ? 'submission' : 'submissions' );
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
	 * Display repeater data in entry details
	 *
	 * @param int $form_id
	 * @param int $lead_id
	 * @return void
	 */
	public function display_repeater_data( $form_id, $lead_id ): void {
		$form = \GFAPI::get_form( $form_id );
		$entry = \GFAPI::get_entry( $lead_id );

		if ( is_wp_error( $entry ) ) {
			return;
		}

		$has_repeater_fields = false;
		$repeater_data = [];

		// Find all repeater_start fields and their data.
		foreach ( $form['fields'] as $field ) {
			if ( 'repeater_start' === $field->type ) {
				$has_repeater_fields = true;
				$field_value = rgar( $entry, $field->id );

				if ( ! empty( $field_value ) ) {
					$decoded_data = json_decode( $field_value, true );
					if ( is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
						$repeater_data[] = [
							'field' => $field,
							'data' => $decoded_data
						];
					}
				}
			}
		}

		if ( ! $has_repeater_fields || empty( $repeater_data ) ) {
			return;
		}

		// Display each repeater field's data
		foreach ( $repeater_data as $repeater_info ) {
			$field = $repeater_info['field'];
			$data = $repeater_info['data'];
			
			echo '<div class="gf-repeater-data-display" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">';
			echo '<h3 style="margin-top: 0;">' . esc_html( $field->label ) . '</h3>';
			echo '<p><strong>' . count( $data ) . '</strong> ' . ( count( $data ) === 1 ? 'instance' : 'instances' ) . ' submitted</p>';
			
			foreach ( $data as $instance_index => $instance_data ) {
				echo '<div class="gf-repeater-instance" style="margin: 15px 0; padding: 10px; border-left: 3px solid #0073aa; background: white;">';
				echo '<h4 style="margin: 0 0 10px 0; color: #0073aa;">' . esc_html( $field->label ) . ' ' . esc_html( $instance_index + 1 ) . '</h4>';
				
				if ( empty( $instance_data ) ) {
					echo '<p style="color: #666; font-style: italic;">No data submitted for this group.</p>';
				} else {
					echo '<table class="widefat" style="margin: 0;">';
					echo '<thead><tr><th style="width: 30%;">Field</th><th>Value</th></tr></thead>';
					echo '<tbody>';
					
					foreach ( $instance_data as $field_key => $field_values ) {
						// Get the actual field object to display proper labels
						$field_obj = $this->get_field_by_input_name( $form, $field_key );
						$field_label = $field_obj ? $field_obj->label : $field_key;
						
						// Handle array values (like radio buttons, checkboxes)
						$display_value = is_array( $field_values ) ? implode( ', ', $field_values ) : $field_values;
						
						echo '<tr>';
						echo '<td><strong>' . esc_html( $field_label ) . '</strong></td>';
						echo '<td>' . esc_html( $display_value ) . '</td>';
						echo '</tr>';
					}
					
					echo '</tbody>';
					echo '</table>';
				}
				
				echo '</div>';
			}
			
			echo '</div>';
		}
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
		foreach ( $form['fields'] as $field ) {
			// Check if this field's input name matches.
			if ( strpos( $input_name, 'input_' . $field->id ) === 0 ) {
				return $field;
			}
		}
		return null;
	}
}
