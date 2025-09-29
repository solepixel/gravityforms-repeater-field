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
		// Only apply admin filters in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Modify entry display for repeater fields in entries table
		add_filter( 'gform_entries_field_value', [ $this, 'modify_entries_field_value' ], 10, 4 );

		// Modify field content in entry detail page
		add_filter( 'gform_field_content', [ $this, 'modify_field_content' ], 10, 5 );

		// Note: Repeater data is now displayed directly in the field content via modify_field_content
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
			// Get the actual value from the entry data
			$field_value = rgar( $entry, $field_id );
			if ( ! empty( $field_value ) ) {
				$decoded_data = json_decode( $field_value, true );
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

		// Handle repeater_start field - show count and display grouped data.
		if ( 'repeater_start' === $field->type ) {
			$count = 0;
			$decoded_data = [];
			if ( ! empty( $value ) ) {
				$decoded_data = json_decode( $value, true );
				if ( is_array( $decoded_data ) && ! empty( $decoded_data ) ) {
					$count = count( $decoded_data );
				}
			}

			$content = '<div class="gf-repeater-field-display">';
			$content .= '<label class="gfield_label">' . esc_html( $field->label ) . '</label>';
			$content .= '<div class="gfield_value">' . $count . ' ' . ( $count === 1 ? 'submission' : 'submissions' ) . '</div>';

			// Display grouped repeater data
			if ( ! empty( $decoded_data ) ) {
				$content .= '<div class="gf-repeater-data-display" style="margin: 10px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; border-left: 4px solid #0073aa;">';

				foreach ( $decoded_data as $instance_index => $instance_data ) {
					$content .= '<div class="gf-repeater-instance" style="margin: 10px 0; padding: 10px; background: white; border: 1px solid #e1e1e1;">';
					$content .= '<h4 style="margin: 0 0 10px 0; color: #0073aa; font-size: 14px;">' . esc_html( $field->label ) . ' ' . esc_html( $instance_index + 1 ) . '</h4>';

					if ( empty( $instance_data ) ) {
						$content .= '<p style="color: #666; font-style: italic; margin: 0;">No data submitted for this group.</p>';
					} else {
						foreach ( $instance_data as $field_key => $field_values ) {
							// Get the actual field object to display proper labels
							$field_obj = $this->get_field_by_input_name( \GFAPI::get_form( $form_id ), $field_key );
							$field_label = $field_obj ? $field_obj->label : $field_key;

							// Handle array values (like radio buttons, checkboxes)
							$display_value = is_array( $field_values ) ? implode( ', ', $field_values ) : $field_values;

							$content .= '<div style="margin: 5px 0; padding: 5px 0; border-bottom: 1px solid #f0f0f0;">';
							$content .= '<strong style="display: inline-block; width: 150px; color: #333;">' . esc_html( $field_label ) . ':</strong> ';
							$content .= '<span style="color: #666;">' . esc_html( $display_value ) . '</span>';
							$content .= '</div>';
						}
					}

					$content .= '</div>';
				}

				$content .= '</div>';
			}

			$content .= '</div>';
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

			echo '<div class="gf-repeater-data-display" style="margin: 10px 0; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; border-left: 4px solid #0073aa;">';

			foreach ( $data as $instance_index => $instance_data ) {
				echo '<div class="gf-repeater-instance" style="margin: 10px 0; padding: 10px; background: white; border: 1px solid #e1e1e1;">';
				echo '<h4 style="margin: 0 0 10px 0; color: #0073aa; font-size: 14px;">' . esc_html( $field->label ) . ' ' . esc_html( $instance_index + 1 ) . '</h4>';

				if ( empty( $instance_data ) ) {
					echo '<p style="color: #666; font-style: italic; margin: 0;">No data submitted for this group.</p>';
				} else {
					foreach ( $instance_data as $field_key => $field_values ) {
						// Get the actual field object to display proper labels
						$field_obj = $this->get_field_by_input_name( $form, $field_key );
						$field_label = $field_obj ? $field_obj->label : $field_key;

						// Handle array values (like radio buttons, checkboxes)
						$display_value = is_array( $field_values ) ? implode( ', ', $field_values ) : $field_values;

						echo '<div style="margin: 5px 0; padding: 5px 0; border-bottom: 1px solid #f0f0f0;">';
						echo '<strong style="display: inline-block; width: 150px; color: #333;">' . esc_html( $field_label ) . ':</strong> ';
						echo '<span style="color: #666;">' . esc_html( $display_value ) . '</span>';
						echo '</div>';
					}
				}

				echo '</div>';
			}

			echo '</div>';
		}
	}

	/**
	 * Check if we're on an entry detail page
	 *
	 * @return bool
	 */
	private function is_entry_detail_page(): bool {
		global $pagenow;
		return ( 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'gf_entries' === $_GET['page'] && isset( $_GET['view'] ) && 'entry' === $_GET['view'] );
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
