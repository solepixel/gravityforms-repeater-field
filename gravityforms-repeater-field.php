<?php
/**
 * Plugin Name: Gravity Forms Repeater Field Add-on
 * Plugin URI: https://b7s.co
 * Description: A robust repeater field add-on for Gravity Forms that allows grouping and repeating form sections.
 * Version: 1.0.0
 * Author: Briantics, Inc.
 * Author URI: https://b7s.co
 * Text Domain: gravityforms-repeater-field
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * GitHub Plugin URI: https://github.com/solepixel/gravityforms-repeater-field
 *
 * @package GravityFormsRepeaterField
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'GF_REPEATER_FIELD_VERSION', '1.0.0' );
define( 'GF_REPEATER_FIELD_PLUGIN_FILE', __FILE__ );
define( 'GF_REPEATER_FIELD_PLUGIN_DIR', plugin_dir_path( GF_REPEATER_FIELD_PLUGIN_FILE ) );
define( 'GF_REPEATER_FIELD_PLUGIN_URL', plugin_dir_url( GF_REPEATER_FIELD_PLUGIN_FILE ) );

// Composer autoload (PSR-4) — prefer autoloader over manual requires.
$autoload = GF_REPEATER_FIELD_PLUGIN_DIR . 'vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function gf_repeater_field_init() {
	// Check if Gravity Forms is active.
	if ( ! class_exists( 'GFForms' ) ) {
		return;
	}

    // Initialize the main plugin class.
    new \GravityFormsRepeaterField\Core();
}

add_action( 'gform_loaded', 'gf_repeater_field_init', 5 );

// Initialize GitHub-based updater (admin only).
if ( is_admin() ) {
	add_action( 'admin_init', function () {
		// phpcs:ignore WordPress.Security.NonceVerification
		$token = (string) apply_filters( 'gfrf_github_token', '' );
		$assetPrefix = 'gravityforms-repeater-field';
		new \GravityFormsRepeaterField\GitHubUpdater( __FILE__, 'solepixel', 'gravityforms-repeater-field', $assetPrefix, $token );
	} );
}
