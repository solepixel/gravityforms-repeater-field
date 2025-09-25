<?php
/**
 * Core Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

// Classes are in the same namespace; no "Classes" segment required.

/**
 * Core Class
 */
class Core {

	/**
	 * @since 1.0.0
	 * @var ?Core
	 */
	private static ?Core $instance = null;

	/**
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! $this->initialized ) {
			$this->init();
		}
	}

	/**
	 * Get the singleton instance of this class
	 *
	 * @since 1.0.0
	 *
	 * @return Core
	 */
	public static function get_instance(): Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initialize the plugin
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Load plugin dependencies
		$this->load_dependencies();

		// Initialize modules
		$this->init_modules();

		// Load the text domain for translations
		$this->load_text_domain();

		$this->initialized = true;
	}

	/**
	 * Load plugin dependencies
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_dependencies(): void {
		// Load helper functions
		require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/helpers.php';
	}

	/**
	 * Initialize modules
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_modules(): void {
		// Register the Repeater fields immediately so they're available in the editor.
		RepeaterStartField::register_field();
		RepeaterEndField::register_field();

		// Editor-time structural validation: ensure Start has End.
		add_filter( 'gform_pre_form_settings_save', function( $form ) {
			if ( empty( $form['fields'] ) ) {
				return $form;
			}
			$stack = 0;
			foreach ( $form['fields'] as $field ) {
				$type = isset( $field->type ) ? $field->type : '';
				if ( 'repeater_start' === $type ) {
					$stack++;
				}
				if ( 'repeater_end' === $type ) {
					$stack--;
				}
			}
			if ( $stack !== 0 ) {
				// Surface an admin notice after save attempt.
				add_action( 'admin_notices', function() {
					printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Gravity Forms Repeater: Each Repeater Start must have a matching Repeater End.', 'gravityforms-repeater-field' ) );
				} );
			}
			return $form;
		} );

		// Initialize assets
		new Assets();

		// Initialize admin display
		new AdminDisplay();
	}

	/**
	 * Load Text Domain
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_text_domain(): void {
		load_plugin_textdomain(
			'gravityforms-repeater-field',
			false,
			dirname( plugin_basename( GF_REPEATER_FIELD_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
