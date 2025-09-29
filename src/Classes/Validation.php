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
					// Check if this field should be visible based on conditional logic
					if ( ! $this->is_field_visible_in_instance( $req_field, $instance_values, $form ) ) {
						continue; // Skip validation for hidden fields
					}

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

	/**
	 * Check if a field should be visible based on conditional logic in a repeater instance.
	 *
	 * @param \GF_Field $field The field to check
	 * @param array     $instance_values The values for this instance
	 * @param array     $form The form object
	 * @return bool True if field should be visible, false if hidden
	 */
	private function is_field_visible_in_instance( $field, $instance_values, $form ) {
		// If field has no conditional logic, it's always visible
		if ( empty( $field->conditionalLogic ) || ! is_array( $field->conditionalLogic ) ) {
			return true;
		}

		$logic = $field->conditionalLogic;
		if ( empty( $logic['rules'] ) || ! is_array( $logic['rules'] ) ) {
			return true;
		}

		$rules = $logic['rules'];
		$logic_type = isset( $logic['logicType'] ) ? $logic['logicType'] : 'all';
		$action_type = isset( $logic['actionType'] ) ? $logic['actionType'] : 'show';

		// Evaluate each rule
		$rule_results = [];
		foreach ( $rules as $rule ) {
			if ( empty( $rule['fieldId'] ) || ! isset( $rule['operator'] ) || ! isset( $rule['value'] ) ) {
				continue;
			}

			$trigger_field_id = (int) $rule['fieldId'];
			$trigger_field_name = 'input_' . $trigger_field_id;
			$trigger_value = isset( $instance_values[ $trigger_field_name ] ) ? $instance_values[ $trigger_field_name ] : [];

			// Handle array values (radio/checkbox)
			$actual_value = '';
			if ( is_array( $trigger_value ) && ! empty( $trigger_value ) ) {
				$actual_value = $trigger_value[0]; // Get first value for radio buttons
			} elseif ( ! is_array( $trigger_value ) ) {
				$actual_value = $trigger_value;
			}

			$rule_value = (string) $rule['value'];
			$operator = $rule['operator'];

			$rule_result = false;
			switch ( $operator ) {
				case 'is':
					$rule_result = ( $actual_value === $rule_value );
					break;
				case 'isnot':
					$rule_result = ( $actual_value !== $rule_value );
					break;
				case 'contains':
					$rule_result = ( strpos( $actual_value, $rule_value ) !== false );
					break;
				case 'not_contains':
					$rule_result = ( strpos( $actual_value, $rule_value ) === false );
					break;
				case 'starts_with':
					$rule_result = ( strpos( $actual_value, $rule_value ) === 0 );
					break;
				case 'ends_with':
					$rule_result = ( substr( $actual_value, -strlen( $rule_value ) ) === $rule_value );
					break;
			}

			$rule_results[] = $rule_result;
		}

		// Apply logic type (all/some)
		$matched = false;
		if ( $logic_type === 'all' ) {
			$matched = ! empty( $rule_results ) && ! in_array( false, $rule_results, true );
		} else {
			$matched = in_array( true, $rule_results, true );
		}

		// Apply action type (show/hide)
		$should_show = ( $action_type === 'show' ) ? $matched : ! $matched;

		return $should_show;
	}
}


