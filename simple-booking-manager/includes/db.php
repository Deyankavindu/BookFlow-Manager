<?php
/**
 * Database setup for Simple Booking Manager.
 *
 * @package SimpleBookingManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create the custom bookings table.
 */
function sbm_create_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . SBM_TABLE_NAME;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
		id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		full_name   VARCHAR(100)        NOT NULL,
		email       VARCHAR(150)        NOT NULL,
		phone       VARCHAR(30)         NOT NULL,
		service_type VARCHAR(100)       NOT NULL,
		booking_date DATE               NOT NULL,
		booking_time TIME               NOT NULL,
		notes       TEXT,
		status      ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
		created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_status       (status),
		KEY idx_booking_date (booking_date),
		KEY idx_email        (email(20))
	) {$charset_collate};";



	$fields_table = $wpdb->prefix . 'booking_fields';
	$meta_table   = $wpdb->prefix . 'booking_meta';

	$fields_sql = "CREATE TABLE IF NOT EXISTS {$fields_table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		field_name VARCHAR(100) NOT NULL,
		field_label VARCHAR(150) NOT NULL,
		field_type VARCHAR(40) NOT NULL DEFAULT 'text',
		is_required TINYINT(1) NOT NULL DEFAULT 0,
		placeholder VARCHAR(255) DEFAULT '',
		default_value VARCHAR(255) DEFAULT '',
		field_order INT(11) NOT NULL DEFAULT 0,
		field_options TEXT,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY field_name (field_name),
		KEY field_order (field_order)
	) {$charset_collate};";

	$meta_sql = "CREATE TABLE IF NOT EXISTS {$meta_table} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id BIGINT(20) UNSIGNED NOT NULL,
		field_name VARCHAR(100) NOT NULL,
		field_label VARCHAR(150) NOT NULL,
		field_value LONGTEXT,
		PRIMARY KEY (id),
		KEY booking_id (booking_id),
		KEY field_name (field_name)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	dbDelta( $fields_sql );
	dbDelta( $meta_sql );
}

/**
 * Get all bookings with optional filters.
 *
 * @param array $args Filter arguments.
 * @return array
 */
function sbm_get_bookings( $args = array() ) {
	global $wpdb;

	$table      = $wpdb->prefix . SBM_TABLE_NAME;
	$where      = array( '1=1' );
	$values     = array();

	if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
		$where[]  = 'status = %s';
		$values[] = $args['status'];
	}

	if ( ! empty( $args['date_from'] ) ) {
		$where[]  = 'booking_date >= %s';
		$values[] = sanitize_text_field( $args['date_from'] );
	}

	if ( ! empty( $args['date_to'] ) ) {
		$where[]  = 'booking_date <= %s';
		$values[] = sanitize_text_field( $args['date_to'] );
	}

	if ( ! empty( $args['search'] ) ) {
		$search   = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		$where[]  = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
		$values[] = $search;
		$values[] = $search;
		$values[] = $search;
	}

	$where_clause = implode( ' AND ', $where );
	$order        = 'ORDER BY booking_date ASC, booking_time ASC';
	$sql          = "SELECT * FROM {$table} WHERE {$where_clause} {$order}";

	if ( ! empty( $values ) ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	return $wpdb->get_results( $sql );
}

/**
 * Get a single booking by ID.
 *
 * @param int $id Booking ID.
 * @return object|null
 */
function sbm_get_booking( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_TABLE_NAME;

	return $wpdb->get_row(
		$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) )
	);
}

/**
 * Insert a new booking.
 *
 * @param array $data Booking data.
 * @return int|false Inserted ID or false on failure.
 */
function sbm_insert_booking( $data ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_TABLE_NAME;

	$result = $wpdb->insert(
		$table,
		array(
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'email'        => sanitize_email( $data['email'] ),
			'phone'        => sanitize_text_field( $data['phone'] ),
			'service_type' => sanitize_text_field( $data['service_type'] ),
			'booking_date' => sanitize_text_field( $data['booking_date'] ),
			'booking_time' => sanitize_text_field( $data['booking_time'] ),
			'notes'        => sanitize_textarea_field( $data['notes'] ),
			'status'       => 'pending',
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);

	return $result ? $wpdb->insert_id : false;
}

/**
 * Update an existing booking.
 *
 * @param int   $id   Booking ID.
 * @param array $data Updated data.
 * @return bool
 */
function sbm_update_booking( $id, $data ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_TABLE_NAME;

	$allowed_statuses = array( 'pending', 'confirmed', 'cancelled' );
	$status           = in_array( $data['status'], $allowed_statuses, true ) ? $data['status'] : 'pending';

	$result = $wpdb->update(
		$table,
		array(
			'full_name'    => sanitize_text_field( $data['full_name'] ),
			'email'        => sanitize_email( $data['email'] ),
			'phone'        => sanitize_text_field( $data['phone'] ),
			'service_type' => sanitize_text_field( $data['service_type'] ),
			'booking_date' => sanitize_text_field( $data['booking_date'] ),
			'booking_time' => sanitize_text_field( $data['booking_time'] ),
			'notes'        => sanitize_textarea_field( $data['notes'] ),
			'status'       => $status,
		),
		array( 'id' => absint( $id ) ),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
		array( '%d' )
	);

	return false !== $result;
}

/**
 * Delete a booking by ID.
 *
 * @param int $id Booking ID.
 * @return bool
 */
function sbm_delete_booking( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_TABLE_NAME;

	$result = $wpdb->delete(
		$table,
		array( 'id' => absint( $id ) ),
		array( '%d' )
	);

	return false !== $result;
}
