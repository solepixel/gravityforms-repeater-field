<?php
/**
 * Assets Class
 *
 * @package GravityFormsRepeaterField
 * @since 1.0.0
 */

namespace GravityFormsRepeaterField;

/**
 * Assets Class
 */
class Assets {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize assets
	 *
	 * @return void
	 */
	private function init(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_action( 'gform_enqueue_scripts', [ $this, 'enqueue_form_assets' ], 10, 2 );
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		wp_enqueue_style(
			'gf-repeater-field-style',
			GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/css/frontend.css',
			[],
			GF_REPEATER_FIELD_VERSION
		);

		wp_enqueue_script(
			'gf-repeater-field-script',
			GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/js/frontend.js',
			[ 'jquery' ],
			GF_REPEATER_FIELD_VERSION,
			true
		);

		wp_localize_script(
			'gf-repeater-field-script',
			'gfRepeaterField',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gf_repeater_field_nonce' ),
				'strings' => [
					'addSection'    => __( 'Add Section', 'gravityforms-repeater-field' ),
					'removeSection' => __( 'Remove Section', 'gravityforms-repeater-field' ),
					'prevSection'   => __( 'Previous Section', 'gravityforms-repeater-field' ),
					'nextSection'   => __( 'Next Section', 'gravityforms-repeater-field' ),
				],
			]
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Entries screen styles.
		if ( strpos( $screen->id, 'gf_entries' ) !== false ) {
			wp_enqueue_style(
				'gf-repeater-field-admin-style',
				GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/css/admin.css',
				[],
				GF_REPEATER_FIELD_VERSION
			);

			wp_enqueue_script(
				'gf-repeater-field-admin-script',
				GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/js/admin.js',
				[ 'jquery' ],
				GF_REPEATER_FIELD_VERSION,
				true
			);
		}

		// Form editor page: live label sync for Repeater End.
		if ( isset( $_GET['page'] ) && 'gf_edit_forms' === $_GET['page'] ) { // phpcs:ignore
			wp_enqueue_script(
				'gf-repeater-field-editor-script',
				GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/js/editor.js',
				[ 'jquery' ],
				GF_REPEATER_FIELD_VERSION,
				true
			);

			// Provide fieldSettings mappings required by Gravity Forms editor JS to avoid undefined errors.
			add_action( 'admin_print_footer_scripts', function () {
				// Output without JS-escaped newlines to avoid stray backslashes in source.
				echo '<script type="text/javascript">';
				echo 'window.fieldSettings = window.fieldSettings || {};';
				// Comma-separated selectors of settings panels for each field type.
				echo "fieldSettings['repeater_start'] = '.label_setting, .label_placement_setting, .description_setting, .css_class_setting, .visibility_setting';";
				echo "fieldSettings['repeater_end'] = '';";
				echo '</script>';
			} );
		}
	}

	/**
	 * Enqueue form assets
	 *
	 * @param array $form
	 * @param bool $is_ajax
	 * @return void
	 */
	public function enqueue_form_assets( $form, $is_ajax ): void {
		// Check if form has repeater start fields
		$has_repeater = false;
		foreach ( $form['fields'] as $field ) {
			if ( $field->type === 'repeater_start' ) {
				$has_repeater = true;
				break;
			}
		}

		if ( ! $has_repeater ) {
			return;
		}

		wp_enqueue_style(
			'gf-repeater-field-form-style',
			GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/css/form.css',
			[],
			GF_REPEATER_FIELD_VERSION
		);

		wp_enqueue_script(
			'gf-repeater-field-form-script',
			GF_REPEATER_FIELD_PLUGIN_URL . 'src/Assets/js/form.js',
			[ 'jquery' ],
			GF_REPEATER_FIELD_VERSION,
			true
		);
	}
}
