<?php
/**
 * Merge Tag Formatting Class
 *
 * Formats repeater field JSON for {all_fields} (and related) merge tags in notifications.
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
 * Merge Tag Formatting Class
 */
class MergeTagFormatting {

	/**
	 * Merge tags that list all submitted fields (repeater value should be formatted).
	 *
	 * @var string[]
	 */
	private static $all_fields_merge_tags = [ 'all_fields', 'all_fields_display_empty' ];

	/**
	 * Constructor. Registers the merge tag filter.
	 */
	public function __construct() {
		add_filter( 'gform_merge_tag_filter', [ $this, 'format_repeater_for_merge_tag' ], 10, 7 );
	}

	/**
	 * When the field is repeater_start and the value is JSON, replace with label/value formatted output.
	 *
	 * @param string|false $field_value   Current merge tag value.
	 * @param string       $merge_tag    Merge tag name (e.g. 'all_fields').
	 * @param string       $options      Merge tag options string.
	 * @param \GF_Field    $field        The field object.
	 * @param mixed        $raw_value    Raw field value from entry.
	 * @param string       $format      'html' or 'text'.
	 * @return string|false Filtered value.
	 */
	public function format_repeater_for_merge_tag( $field_value, $merge_tag, $options, $field, $raw_value, $format ) {
		if ( ! $field || $field->type !== 'repeater_start' ) {
			return $field_value;
		}

		if ( ! in_array( $merge_tag, self::$all_fields_merge_tags, true ) ) {
			return $field_value;
		}

		$raw_string = is_string( $raw_value ) ? $raw_value : '';
		if ( $raw_string === '' ) {
			return $field_value;
		}

		$decoded = json_decode( $raw_string, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return $field_value;
		}

		$form = \GFAPI::get_form( (int) $field->formId );
		if ( is_wp_error( $form ) || empty( $form['fields'] ) ) {
			return $field_value;
		}

		$format = in_array( $format, [ 'html', 'text' ], true ) ? $format : 'html';
		return gf_repeater_field_format_merge_tag_value( $decoded, $form, $format );
	}
}
