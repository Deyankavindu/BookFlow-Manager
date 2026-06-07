<?php
/**
 * Plugin Name:       Simple Booking Manager
 * Plugin URI:        https://example.com/simple-booking-manager
 * Description:       A complete booking management system with CRUD functionality, AJAX form submission, email notifications, and CSV export.
 * Version:           1.1.4-network-fix
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       simple-booking-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'SBM_VERSION', '1.1.4-network-fix' );
define( 'SBM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SBM_TABLE_NAME', 'bookings' );

// Include required files.
require_once SBM_PLUGIN_DIR . 'includes/db.php';
require_once SBM_PLUGIN_DIR . 'includes/functions.php';
require_once SBM_PLUGIN_DIR . 'includes/premium-features.php';
require_once SBM_PLUGIN_DIR . 'includes/ajax-handler.php';
require_once SBM_PLUGIN_DIR . 'includes/admin-page.php';

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, 'sbm_activate' );
register_deactivation_hook( __FILE__, 'sbm_deactivate' );

/**
 * Plugin activation: create DB table and set version option.
 */
function sbm_activate() {
	sbm_create_table();
	add_option( 'sbm_version', SBM_VERSION );
	add_option( 'sbm_admin_email', get_option( 'admin_email' ) );
}

/**
 * Plugin deactivation.
 */
function sbm_deactivate() {
	// Intentionally left blank. Table is preserved on deactivation.
}

/**
 * Run lightweight upgrade checks after plugin updates.
 * This fixes AJAX 500/network errors caused by missing new premium tables
 * when the plugin ZIP is replaced without deactivating/reactivating.
 */
function sbm_maybe_upgrade() {
	$installed_version = get_option( 'sbm_version' );

	if ( SBM_VERSION !== $installed_version || ! sbm_required_tables_exist() ) {
		sbm_create_table();
		update_option( 'sbm_version', SBM_VERSION );
		if ( ! get_option( 'sbm_admin_email' ) ) {
			update_option( 'sbm_admin_email', get_option( 'admin_email' ) );
		}
	}
}
add_action( 'plugins_loaded', 'sbm_maybe_upgrade', 20 );

/**
 * Check whether required plugin tables exist.
 *
 * @return bool
 */
function sbm_required_tables_exist() {
	global $wpdb;

	$tables = array(
		$wpdb->prefix . SBM_TABLE_NAME,
		$wpdb->prefix . 'booking_fields',
		$wpdb->prefix . 'booking_meta',
	);

	foreach ( $tables as $table ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return false;
		}
	}

	return true;
}

/**
 * Enqueue frontend scripts and styles.
 */
function sbm_enqueue_frontend_assets() {
	if ( ! is_admin() ) {
		wp_enqueue_style(
			'sbm-style',
			SBM_PLUGIN_URL . 'assets/css/style.css',
			array(),
			SBM_VERSION
		);

		wp_enqueue_script( 'jquery' );

		// Flatpickr date/time picker (CDN).
		wp_enqueue_style(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);
		wp_enqueue_script(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr',
			array(),
			'4.6.13',
			true
		);

		wp_enqueue_script(
			'sbm-script',
			SBM_PLUGIN_URL . 'assets/js/script.js',
			array( 'jquery', 'flatpickr' ),
			SBM_VERSION,
			true
		);

		wp_localize_script(
			'sbm-script',
			'sbm_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sbm_booking_nonce' ),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'sbm_enqueue_frontend_assets' );

/**
 * Enqueue admin scripts and styles.
 */
function sbm_enqueue_admin_assets( $hook ) {
	if ( strpos( $hook, 'simple-booking-manager' ) === false ) {
		return;
	}
	wp_enqueue_style(
		'sbm-admin-style',
		SBM_PLUGIN_URL . 'assets/css/style.css',
		array(),
		SBM_VERSION
	);
	wp_enqueue_script( 'jquery' );

	// Flatpickr for admin edit.
	wp_enqueue_style(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
		array(),
		'4.6.13'
	);
	wp_enqueue_script(
		'flatpickr',
		'https://cdn.jsdelivr.net/npm/flatpickr',
		array(),
		'4.6.13',
		true
	);

	wp_enqueue_script(
		'sbm-admin-script',
		SBM_PLUGIN_URL . 'assets/js/script.js',
		array( 'jquery', 'flatpickr' ),
		SBM_VERSION,
		true
	);

	wp_localize_script(
		'sbm-admin-script',
		'sbm_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'sbm_booking_nonce' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'sbm_enqueue_admin_assets' );

/**
 * Register the [booking_form] shortcode.
 */
function sbm_booking_form_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'title' => 'Book an Appointment',
		),
		$atts,
		'booking_form'
	);

	ob_start();
	include SBM_PLUGIN_DIR . 'includes/booking-form.php';
	return ob_get_clean();
}
add_shortcode( 'booking_form', 'sbm_booking_form_shortcode' );
