<?php
/**
 * Notification Enhancements Class
 *
 * Attaches files from repeater field instances to notification emails when attachments are enabled.
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notification Enhancements Class
 */
class NotificationEnhancements {

	/**
	 * Constructor. Registers the pre-send email filter.
	 */
	public function __construct() {
		add_filter( 'gform_pre_send_email', [ $this, 'enhance_notification_email' ], 10, 4 );
	}

	/**
	 * Add repeater file attachments to notification emails.
	 *
	 * @param array  $email_data     Compact array: to, subject, message, headers, attachments, abort_email.
	 * @param string $message_format 'html' or 'text'.
	 * @param array  $notification   The notification object.
	 * @param array  $entry          The entry (lead).
	 * @return array Modified email data.
	 */
	public function enhance_notification_email( $email_data, $message_format, $notification, $entry ) {
		if ( ! is_array( $email_data ) || empty( $entry ) ) {
			return $email_data;
		}

		// Add file uploads from repeater instances to attachments when attachments are enabled.
		if ( ! empty( $email_data['attachments'] ) ) {
			$email_data['attachments'] = is_array( $email_data['attachments'] ) ? $email_data['attachments'] : [ $email_data['attachments'] ];
		} else {
			$email_data['attachments'] = [];
		}

		if ( rgar( $notification, 'enableAttachments', false ) ) {
			$repeater_files = $this->get_repeater_file_attachments( $entry, $notification );
			$email_data['attachments'] = array_merge( $email_data['attachments'], $repeater_files );
			$email_data['attachments'] = array_unique( array_values( $email_data['attachments'] ) );
		}

		return $email_data;
	}

	/**
	 * Collect physical file paths for all file uploads stored inside repeater field JSON.
	 *
	 * @param array $entry        The entry.
	 * @param array $notification The notification (for context).
	 * @return array List of absolute file paths to attach.
	 */
	private function get_repeater_file_attachments( $entry, $notification ) {
		$form_id = (int) rgar( $entry, 'form_id' );
		if ( $form_id <= 0 ) {
			return [];
		}

		$form = \GFAPI::get_form( $form_id );
		if ( is_wp_error( $form ) || empty( $form['fields'] ) ) {
			return [];
		}

		$repeater_fields = \GFCommon::get_fields_by_type( $form, [ 'repeater_start' ] );
		if ( empty( $repeater_fields ) ) {
			return [];
		}

		$entry_id = (int) rgar( $entry, 'id' );
		$paths    = [];

		foreach ( $repeater_fields as $repeater_field ) {

			// All fields belonging to this repeater group (between Start and matching End).
			$group_fields   = gf_repeater_field_get_repeater_child_fields( $form, (int) $repeater_field->id );
			$file_field_ids = array();

			foreach ( $group_fields as $group_field ) {
				if ( isset( $group_field->type ) && $group_field->type === 'fileupload' ) {
					$file_field_ids[] = (int) $group_field->id;
				}
			}

			if ( empty( $file_field_ids ) ) {
				continue;
			}

			// For each fileupload field in this repeater group, use the entry's field value
			// (Gravity Forms' own storage) to discover all uploaded files and attach them.
			foreach ( $file_field_ids as $field_id ) {
				$raw = rgar( $entry, (string) $field_id );
				if ( empty( $raw ) ) {
					continue;
				}

				$files = json_decode( $raw, true );
				if ( ! is_array( $files ) ) {
					$files = array_filter( array( $raw ) );
				}

				foreach ( $files as $file ) {
					// Multi-file fields store an array with tmp_url/uploaded_name; single-file may just be a URL string.
					if ( is_array( $file ) ) {
						$file_url = rgar( $file, 'tmp_url' );
					} else {
						$file_url = (string) $file;
					}

					if ( ! is_string( $file_url ) || trim( $file_url ) === '' ) {
						continue;
					}

					$file_path = \GFFormsModel::get_physical_file_path( $file_url, $entry_id );
					if ( $file_path !== '' && file_exists( $file_path ) ) {
						$paths[] = $file_path;
					}
				}
			}
		}

		return $paths;
	}
}
