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

// Load plugin classes.
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/helpers.php';
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/Classes/Core.php';
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/Classes/RepeaterStartField.php';
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/Classes/RepeaterEndField.php';
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/Classes/Assets.php';
require_once GF_REPEATER_FIELD_PLUGIN_DIR . 'src/Classes/AdminDisplay.php';

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
