<?php
/**
 * Group Field Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField\Classes;

use GF_Field;

/**
 * Group Field Class
 */
class GroupField extends GF_Field {

	/**
	 * Field type
	 *
	 * @var string
	 */
	public $type = 'group';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->init();
	}

	/**
	 * Initialize the field
	 *
	 * @return void
	 */
	private function init(): void {
		// Add fieldset management
		add_action( 'gform_field_content', [ $this, 'manage_fieldsets' ], 10, 5 );
		add_action( 'gform_field_content', [ $this, 'close_fieldsets' ], 999, 5 );

		// Handle form submission
		add_action( 'gform_pre_submission', [ $this, 'process_repeater_data' ] );
	}

	/**
	 * Register the field type with Gravity Forms
	 *
	 * @return void
	 */
	public static function register_field(): void {
		\GF_Fields::register( new self() );
	}

	/**
	 * Get the field title
	 *
	 * @return string
	 */
	public function get_form_editor_field_title(): string {
		return esc_attr__( 'Group', 'gravityforms-repeater-field' );
	}

	/**
	 * Get the field settings
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings(): array {
		return [
			'conditional_logic_field_setting',
			'error_message_setting',
			'label_setting',
			'label_placement_setting',
			'admin_label_setting',
			'visibility_setting',
			'description_setting',
			'css_class_setting',
			'repeater_setting',
		];
	}

	/**
	 * Get repeater setting
	 *
	 * @return array
	 */
	public function get_repeater_setting(): array {
		return [
			'name'    => 'enableRepeater',
			'label'   => __( 'Make section repeatable', 'gravityforms-repeater-field' ),
			'type'    => 'checkbox',
			'choices' => [
				[
					'label' => __( 'Enable repeater functionality', 'gravityforms-repeater-field' ),
					'name'  => 'enableRepeater',
				],
			],
		];
	}

	/**
	 * Get the field button
	 *
	 * @return array
	 */
	public function get_form_editor_button(): array {
		return [
			'group' => 'layout_fields',
			'text'  => $this->get_form_editor_field_title(),
		];
	}

	/**
	 * Get the field input
	 *
	 * @param array $form
	 * @param string $value
	 * @param array $entry
	 * @param array $field
	 * @param array $context
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null, $field = null, $context = null ): string {
		$form_id = absint( $form['id'] );
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor = $this->is_form_editor();

		$id = $this->id;
		$field_id = $is_entry_detail || $is_form_editor || $form_id == 0 ? "input_$id" : 'input_' . $form_id . "_$id";
		$form_id = ( $is_entry_detail || $is_form_editor ) && empty( $form_id ) ? rgget( 'id' ) : $form_id;

		$size = $this->size;
		$class_suffix = $is_entry_detail ? '_admin' : '';
		$class = $size . $class_suffix;

		$tabindex = $this->get_tabindex();
		$css_class = trim( esc_attr( $class ) . ' ' . $this->get_css_class() );

		$is_repeater = $this->isRepeater();
		$repeater_id = $is_repeater ? "gf-repeater-{$form_id}-{$id}" : '';

		$output = '';

		if ( $is_repeater ) {
			$output .= $this->get_repeater_controls( $form_id, $id );
		}

		$output .= sprintf(
			'<div class="ginput_container ginput_container_section">%s</div>',
			$this->get_section_content( $form, $value, $entry, $field, $context )
		);

		return $output;
	}

	/**
	 * Get repeater controls
	 *
	 * @param int $form_id
	 * @param int $field_id
	 * @return string
	 */
	private function get_repeater_controls( int $form_id, int $field_id ): string {
		$controls = sprintf(
			'<div class="gf-repeater-controls" data-form-id="%d" data-field-id="%d">
				<button type="button" class="gf-repeater-add" title="%s">+</button>
				<button type="button" class="gf-repeater-remove" title="%s" style="display:none;">-</button>
				<button type="button" class="gf-repeater-prev" title="%s" disabled>←</button>
				<button type="button" class="gf-repeater-next" title="%s" disabled>→</button>
			</div>',
			$form_id,
			$field_id,
			esc_attr__( 'Add Section', 'gravityforms-repeater-field' ),
			esc_attr__( 'Remove Section', 'gravityforms-repeater-field' ),
			esc_attr__( 'Previous Section', 'gravityforms-repeater-field' ),
			esc_attr__( 'Next Section', 'gravityforms-repeater-field' )
		);

		return $controls;
	}

	/**
	 * Get section content
	 *
	 * @param array $form
	 * @param string $value
	 * @param array $entry
	 * @param array $field
	 * @param array $context
	 * @return string
	 */
	private function get_section_content( $form, $value, $entry, $field, $context ): string {
		$label = $this->get_field_label( false, $value );
		$description = $this->get_description( $this->description, 'gfield_description' );

		$output = '';

		if ( $label ) {
			$output .= sprintf( '<h3 class="gf-section-title">%s</h3>', $label );
		}

		if ( $description ) {
			$output .= $description;
		}

		return $output;
	}

	/**
	 * Check if field is repeater
	 *
	 * @return bool
	 */
	private function isRepeater(): bool {
		return ! empty( $this->enableRepeater );
	}

	/**
	 * Manage fieldsets
	 *
	 * @param string $content
	 * @param array $field
	 * @param array $value
	 * @param int $lead_id
	 * @param int $form_id
	 * @return string
	 */
	public function manage_fieldsets( $content, $field, $value, $lead_id, $form_id ): string {
		if ( $field->type === 'section' ) {
			// Close previous fieldset if exists
			$content = $this->close_previous_fieldset() . $content;

			// Open new fieldset
			$content .= $this->open_fieldset( $field );
		}

		return $content;
	}

	/**
	 * Close fieldsets at form end
	 *
	 * @param string $content
	 * @param array $field
	 * @param array $value
	 * @param int $lead_id
	 * @param int $form_id
	 * @return string
	 */
	public function close_fieldsets( $content, $field, $value, $lead_id, $form_id ): string {
		// This will be handled by JavaScript to close any open fieldsets
		return $content;
	}

	/**
	 * Open fieldset
	 *
	 * @param array $field
	 * @return string
	 */
	private function open_fieldset( $field ): string {
		$fieldset_id = 'gf-fieldset-' . $field->id;
		$classes = [ 'gf-fieldset', 'gf-section-fieldset' ];

		if ( ! empty( $field->enableRepeater ) ) {
			$classes[] = 'gf-repeater-fieldset';
		}

		return sprintf(
			'<fieldset id="%s" class="%s" data-field-id="%d">',
			esc_attr( $fieldset_id ),
			esc_attr( implode( ' ', $classes ) ),
			$field->id
		);
	}

	/**
	 * Close previous fieldset
	 *
	 * @return string
	 */
	private function close_previous_fieldset(): string {
		return '</fieldset>';
	}

	/**
	 * Process repeater data on form submission
	 *
	 * @param array $form
	 * @return void
	 */
	public function process_repeater_data( $form ): void {
		// Process repeater field data
		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'section' && ! empty( $field->enableRepeater ) ) {
				$this->process_section_repeater_data( $field );
			}
		}
	}

	/**
	 * Process section repeater data
	 *
	 * @param array $field
	 * @return void
	 */
	private function process_section_repeater_data( $field ): void {
		// This will be implemented to handle the repeater data processing
	}
}
