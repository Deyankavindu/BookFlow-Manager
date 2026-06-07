<?php
/**
 * AJAX handlers for Simple Booking Manager.
 *
 * @package SimpleBookingManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Frontend booking submission (logged-in and guests).
add_action( 'wp_ajax_sbm_submit_booking', 'sbm_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_sbm_submit_booking', 'sbm_ajax_submit_booking' );

/**
 * Handle frontend booking form submission via AJAX.
 */
function sbm_ajax_submit_booking() {
	// Verify nonce.
	if ( ! check_ajax_referer( 'sbm_booking_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh and try again.', 'simple-booking-manager' ) ), 403 );
	}

	// Collect & sanitize raw data for validation.
	$raw = array(
		'full_name'    => isset( $_POST['full_name'] ) ? wp_unslash( $_POST['full_name'] ) : '',
		'email'        => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
		'phone'        => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
		'service_type' => isset( $_POST['service_type'] ) ? wp_unslash( $_POST['service_type'] ) : '',
		'booking_date' => isset( $_POST['booking_date'] ) ? wp_unslash( $_POST['booking_date'] ) : '',
		'booking_time' => isset( $_POST['booking_time'] ) ? wp_unslash( $_POST['booking_time'] ) : '',
		'notes'        => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
	);

	// Validate.
	$errors = sbm_validate_booking_data( $raw );
	$custom_values = isset( $_POST['sbm_custom'] ) && is_array( $_POST['sbm_custom'] ) ? wp_unslash( $_POST['sbm_custom'] ) : array();
	if ( function_exists( 'sbm_validate_custom_field_values' ) ) {
		$errors = array_merge( $errors, sbm_validate_custom_field_values( $custom_values ) );
	}
	if ( ! empty( $errors ) ) {
		wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
	}

	// Insert booking.
	$booking_id = sbm_insert_booking( $raw );
	if ( ! $booking_id ) {
		wp_send_json_error( array( 'message' => __( 'Could not save your booking. Please try again.', 'simple-booking-manager' ) ) );
	}

	// Save dynamic custom field values.
	if ( function_exists( 'sbm_save_booking_meta' ) ) {
		sbm_save_booking_meta( $booking_id, $custom_values );
	}

	// Send notifications.
	if ( function_exists( 'sbm_send_template_email' ) ) {
		sbm_send_template_email( 'new_booking_admin', $booking_id, get_option( 'sbm_admin_email', get_option( 'admin_email' ) ) );
		sbm_send_template_email( 'new_booking_customer', $booking_id, $raw['email'] );
	} else {
		sbm_send_admin_notification( $booking_id );
		sbm_send_customer_confirmation( $booking_id );
	}

	wp_send_json_success(
		array(
			'message'    => __( 'Your booking has been submitted successfully! We will contact you shortly to confirm.', 'simple-booking-manager' ),
			'booking_id' => $booking_id,
		)
	);
}

// Admin: update booking status (inline quick-action).
add_action( 'wp_ajax_sbm_update_status', 'sbm_ajax_update_status' );

/**
 * Handle inline status update via AJAX.
 */
function sbm_ajax_update_status() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'simple-booking-manager' ) ), 403 );
	}

	if ( ! check_ajax_referer( 'sbm_booking_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'simple-booking-manager' ) ), 403 );
	}

	$id     = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
	$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

	if ( ! $id || ! in_array( $status, array( 'pending', 'confirmed', 'cancelled' ), true ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid data.', 'simple-booking-manager' ) ) );
	}

	$booking = sbm_get_booking( $id );
	if ( ! $booking ) {
		wp_send_json_error( array( 'message' => __( 'Booking not found.', 'simple-booking-manager' ) ) );
	}

	$data           = (array) $booking;
	$data['status'] = $status;
	$updated        = sbm_update_booking( $id, $data );

	if ( $updated ) {
		wp_send_json_success( array( 'message' => __( 'Status updated.', 'simple-booking-manager' ), 'status' => $status ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Could not update status.', 'simple-booking-manager' ) ) );
	}
}

// Admin: delete booking via AJAX.
add_action( 'wp_ajax_sbm_delete_booking', 'sbm_ajax_delete_booking' );

/**
 * Handle booking deletion via AJAX.
 */
function sbm_ajax_delete_booking() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'simple-booking-manager' ) ), 403 );
	}

	if ( ! check_ajax_referer( 'sbm_booking_nonce', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => __( 'Security check failed.', 'simple-booking-manager' ) ), 403 );
	}

	$id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
	if ( ! $id ) {
		wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'simple-booking-manager' ) ) );
	}

	$deleted = sbm_delete_booking( $id );
	if ( $deleted ) {
		wp_send_json_success( array( 'message' => __( 'Booking deleted successfully.', 'simple-booking-manager' ) ) );
	} else {
		wp_send_json_error( array( 'message' => __( 'Could not delete booking.', 'simple-booking-manager' ) ) );
	}
}
