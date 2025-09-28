<?php
/**
 * Validation Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

/**
 * Handles server-side validation for repeater instances.
 */
class Validation {

	/**
	 * Constructor: register hooks.
	 */
	public function __construct() {
		add_filter( 'gform_validation', [ $this, 'validate_repeater_instances' ], 10, 1 );
	}

	/**
	 * Ensure required fields inside each repeater instance are validated.
	 *
	 * We rely on the hidden JSON payload stored by the Repeater Start field
	 * (input_{startFieldId}). If present, we decode instances and simulate
	 * per-instance required checks. If any required field is missing in an
	 * instance, we mark the form as failed and attach a validation message
	 * to the corresponding original field.
	 *
	 * @param array $validation_result
	 * @return array
	 */
	public function validate_repeater_instances( $validation_result ) {
		$form = rgar( $validation_result, 'form' );
		if ( empty( $form ) || empty( $form['fields'] ) ) {
			return $validation_result;
		}

		$failed = false;

		foreach ( $form['fields'] as $field ) {
			if ( ! isset( $field->type ) || 'repeater_start' !== $field->type ) {
				continue;
			}

			$start_id  = (int) $field->id;
			$input_key = 'input_' . $start_id;
			$payload   = rgpost( $input_key );
			if ( empty( $payload ) ) {
				continue;
			}

			$instances = json_decode( wp_unslash( $payload ), true );
			if ( ! is_array( $instances ) ) {
				continue;
			}

			// Build a map of original fields between Start and End so we know which are required
			$between_required = $this->get_required_fields_between( $form, $start_id );
			if ( empty( $between_required ) ) {
				continue;
			}

			foreach ( $instances as $idx => $instance_values ) {
				foreach ( $between_required as $req_field_id => $req_field ) {
					$base_name = 'input_' . $req_field_id;
					$values    = isset( $instance_values[ $base_name ] ) ? $instance_values[ $base_name ] : [];
					$has_val   = false;
					if ( is_array( $values ) ) {
						foreach ( $values as $v ) {
							if ( '' !== trim( (string) $v ) ) { $has_val = true; break; }
						}
					} else {
						$has_val = '' !== trim( (string) $values );
					}

					if ( ! $has_val ) {
						$failed = true;
						// Mark original field as failed; GF will show message at that field
						$req_field->failed_validation = true;
						$req_field->validation_message = sprintf(
							/* translators: 1: instance index (1-based) */
							__( 'This field is required for group %d.', 'gravityforms-repeater-field' ),
							(int) $idx + 1
						);
					}
				}
			}
		}

		if ( $failed ) {
			$validation_result['is_valid'] = false;
		}

		$validation_result['form'] = $form;
		return $validation_result;
	}

	/**
	 * Find required fields between a given Repeater Start and its matching End.
	 *
	 * @param array $form
	 * @param int   $start_id
	 * @return array<int, \GF_Field>
	 */
	private function get_required_fields_between( $form, $start_id ) {
		$fields   = $form['fields'];
		$collect  = false;
		$result  = [];

		foreach ( $fields as $f ) {
			if ( (int) $f->id === (int) $start_id ) {
				$collect = true;
				continue;
			}
			if ( $collect && isset( $f->type ) && 'repeater_end' === $f->type ) {
				break; // reached the end of the repeater block
			}
			if ( $collect ) {
				// Consider non-adminOnly fields that are marked as required
				$required = isset( $f->isRequired ) ? (bool) $f->isRequired : false;
				$admin    = isset( $f->adminOnly ) ? (bool) $f->adminOnly : false;
				if ( $required && ! $admin ) {
					$result[ (int) $f->id ] = $f;
				}
			}
		}

		return $result;
	}
}


