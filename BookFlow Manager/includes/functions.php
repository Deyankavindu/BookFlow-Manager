<?php
/**
 * Helper functions for Simple Booking Manager.
 *
 * @package SimpleBookingManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get available service types.
 *
 * @return array
 */
function sbm_get_service_types() {
	$defaults = array(
		'consultation'  => 'Consultation',
		'haircut'       => 'Haircut & Styling',
		'massage'       => 'Massage Therapy',
		'dental'        => 'Dental Checkup',
		'physiotherapy' => 'Physiotherapy',
		'coaching'      => 'Life Coaching',
		'photography'   => 'Photography Session',
		'other'         => 'Other',
	);

	/**
	 * Filter the available service types.
	 *
	 * @param array $defaults Default service types.
	 */
	return apply_filters( 'sbm_service_types', $defaults );
}

/**
 * Get status badge HTML.
 *
 * @param string $status Booking status.
 * @return string
 */
function sbm_status_badge( $status ) {
	$labels = array(
		'pending'   => array( 'label' => 'Pending',   'class' => 'sbm-badge--pending' ),
		'confirmed' => array( 'label' => 'Confirmed', 'class' => 'sbm-badge--confirmed' ),
		'cancelled' => array( 'label' => 'Cancelled', 'class' => 'sbm-badge--cancelled' ),
	);

	$info = isset( $labels[ $status ] ) ? $labels[ $status ] : array( 'label' => ucfirst( $status ), 'class' => '' );

	return sprintf(
		'<span class="sbm-badge %s">%s</span>',
		esc_attr( $info['class'] ),
		esc_html( $info['label'] )
	);
}

/**
 * Send admin notification email on new booking.
 *
 * @param int $booking_id Booking ID.
 */
function sbm_send_admin_notification( $booking_id ) {
	$booking = sbm_get_booking( $booking_id );
	if ( ! $booking ) {
		return;
	}

	$admin_email = get_option( 'sbm_admin_email', get_option( 'admin_email' ) );
	$site_name   = get_bloginfo( 'name' );

	$subject = sprintf(
		/* translators: 1: Site name */
		__( '[%s] New Booking Received', 'simple-booking-manager' ),
		$site_name
	);

	$message  = sprintf( __( 'A new booking has been submitted on %s.', 'simple-booking-manager' ), $site_name ) . "\n\n";
	$message .= __( 'Booking Details:', 'simple-booking-manager' ) . "\n";
	$message .= str_repeat( '-', 30 ) . "\n";
	$message .= sprintf( __( 'Name:    %s', 'simple-booking-manager' ), $booking->full_name ) . "\n";
	$message .= sprintf( __( 'Email:   %s', 'simple-booking-manager' ), $booking->email ) . "\n";
	$message .= sprintf( __( 'Phone:   %s', 'simple-booking-manager' ), $booking->phone ) . "\n";
	$message .= sprintf( __( 'Service: %s', 'simple-booking-manager' ), $booking->service_type ) . "\n";
	$message .= sprintf( __( 'Date:    %s', 'simple-booking-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ) . "\n";
	$message .= sprintf( __( 'Time:    %s', 'simple-booking-manager' ), $booking->booking_time ) . "\n";
	if ( $booking->notes ) {
		$message .= sprintf( __( 'Notes:   %s', 'simple-booking-manager' ), $booking->notes ) . "\n";
	}
	$message .= "\n" . admin_url( 'admin.php?page=simple-booking-manager&action=view&id=' . $booking->id );

	wp_mail( $admin_email, $subject, $message );
}

/**
 * Send confirmation email to the customer.
 *
 * @param int $booking_id Booking ID.
 */
function sbm_send_customer_confirmation( $booking_id ) {
	$booking = sbm_get_booking( $booking_id );
	if ( ! $booking ) {
		return;
	}

	$site_name = get_bloginfo( 'name' );
	$subject   = sprintf(
		/* translators: 1: Site name */
		__( '[%s] Your Booking is Confirmed', 'simple-booking-manager' ),
		$site_name
	);

	$message  = sprintf( __( 'Dear %s,', 'simple-booking-manager' ), $booking->full_name ) . "\n\n";
	$message .= __( 'Thank you for your booking. Here are your details:', 'simple-booking-manager' ) . "\n\n";
	$message .= sprintf( __( 'Service: %s', 'simple-booking-manager' ), $booking->service_type ) . "\n";
	$message .= sprintf( __( 'Date:    %s', 'simple-booking-manager' ), date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ) . "\n";
	$message .= sprintf( __( 'Time:    %s', 'simple-booking-manager' ), $booking->booking_time ) . "\n\n";
	$message .= __( 'We will contact you to confirm your appointment.', 'simple-booking-manager' ) . "\n\n";
	$message .= sprintf( __( 'Regards,', 'simple-booking-manager' ) ) . "\n" . $site_name;

	wp_mail( $booking->email, $subject, $message );
}

/**
 * Export bookings to CSV and force download.
 *
 * @param array $bookings Array of booking objects.
 */
function sbm_export_csv( $bookings ) {
	$filename = 'bookings-' . date( 'Y-m-d' ) . '.csv';

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );

	// BOM for Excel UTF-8 compatibility.
	fputs( $output, "\xEF\xBB\xBF" );

	// Header row.
	fputcsv( $output, array( 'ID', 'Full Name', 'Email', 'Phone', 'Service Type', 'Booking Date', 'Booking Time', 'Notes', 'Status', 'Created At' ) );

	foreach ( $bookings as $booking ) {
		fputcsv(
			$output,
			array(
				$booking->id,
				$booking->full_name,
				$booking->email,
				$booking->phone,
				$booking->service_type,
				$booking->booking_date,
				$booking->booking_time,
				$booking->notes,
				$booking->status,
				$booking->created_at,
			)
		);
	}

	fclose( $output );
	exit;
}

/**
 * Validate booking form data.
 *
 * @param array $data Raw POST data.
 * @return array List of error messages (empty if valid).
 */
function sbm_validate_booking_data( $data ) {
	$errors        = array();
	$service_types = array_keys( sbm_get_service_types() );

	if ( empty( $data['full_name'] ) || mb_strlen( trim( $data['full_name'] ) ) < 2 ) {
		$errors[] = __( 'Please enter your full name (at least 2 characters).', 'simple-booking-manager' );
	}

	if ( empty( $data['email'] ) || ! is_email( $data['email'] ) ) {
		$errors[] = __( 'Please enter a valid email address.', 'simple-booking-manager' );
	}

	if ( empty( $data['phone'] ) || ! preg_match( '/^[+\d\s\-\(\)]{7,20}$/', trim( $data['phone'] ) ) ) {
		$errors[] = __( 'Please enter a valid phone number.', 'simple-booking-manager' );
	}

	if ( empty( $data['service_type'] ) || ! in_array( $data['service_type'], $service_types, true ) ) {
		$errors[] = __( 'Please select a valid service type.', 'simple-booking-manager' );
	}

	if ( empty( $data['booking_date'] ) ) {
		$errors[] = __( 'Please select a booking date.', 'simple-booking-manager' );
	} else {
		$date = DateTime::createFromFormat( 'Y-m-d', $data['booking_date'] );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $data['booking_date'] ) {
			$errors[] = __( 'Please enter a valid date (YYYY-MM-DD).', 'simple-booking-manager' );
		} elseif ( $date < new DateTime( 'today' ) ) {
			$errors[] = __( 'Booking date cannot be in the past.', 'simple-booking-manager' );
		}
	}

	if ( empty( $data['booking_time'] ) || ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $data['booking_time'] ) ) {
		$errors[] = __( 'Please enter a valid booking time.', 'simple-booking-manager' );
	}

	return $errors;
}
