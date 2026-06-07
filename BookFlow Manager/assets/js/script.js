/**
 * Simple Booking Manager — script.js
 * Handles: frontend AJAX form, flatpickr init, admin status/delete AJAX.
 */

( function ( $ ) {
	'use strict';

	/* ─────────────────────────────────────────────────────
	   Flatpickr — Date & Time pickers (frontend)
	───────────────────────────────────────────────────── */
	if ( typeof flatpickr !== 'undefined' ) {
		// Date picker: no past dates.
		flatpickr( '.sbm-datepicker', {
			dateFormat:  'Y-m-d',
			minDate:     'today',
			disableMobile: true,
			allowInput:  false,
		} );

		// Time picker: 30-minute slots 08:00–20:00.
		flatpickr( '.sbm-timepicker', {
			enableTime:   true,
			noCalendar:   true,
			dateFormat:   'H:i',
			time_24hr:    false,
			minuteIncrement: 30,
			minTime:      '08:00',
			maxTime:      '20:00',
			disableMobile: true,
			allowInput:   false,
		} );
	}

	/* ─────────────────────────────────────────────────────
	   Frontend: Booking Form AJAX Submission
	───────────────────────────────────────────────────── */
	var $form    = $( '#sbm-booking-form' ).not( '.sbm-preview-form' );
	var $message = $( '#sbm-message' );

	if ( $form.length ) {
		$form.on( 'submit', function ( e ) {
			e.preventDefault();

			var $btn         = $( '#sbm-submit-btn' );
			var $submitText  = $btn.find( '.sbm-submit-text' );
			var $loadingText = $btn.find( '.sbm-submit-loading' );

			// Loading state.
			$btn.prop( 'disabled', true );
			$submitText.hide();
			$loadingText.show();
			$message.hide().removeClass( 'sbm-message--success sbm-message--error' );

			var formData = $form.serializeArray();
			formData.push( { name: 'action', value: 'sbm_submit_booking' } );
			formData.push( { name: 'nonce', value: $( '#sbm_frontend_nonce' ).val() || sbm_ajax.nonce } );

			var requiredData = {
				full_name:    $( '#sbm_full_name' ).val().trim(),
				email:        $( '#sbm_email' ).val().trim(),
				phone:        $( '#sbm_phone' ).val().trim(),
				service_type: $( '#sbm_service_type' ).val(),
				booking_date: $( '#sbm_booking_date' ).val(),
				booking_time: $( '#sbm_booking_time' ).val(),
			};

			// Basic client-side presence check (server re-validates).
			var required = [ 'full_name', 'email', 'phone', 'service_type', 'booking_date', 'booking_time' ];
			var missing  = required.filter( function ( k ) { return ! requiredData[ k ]; } );
			if ( missing.length ) {
				showMessage( 'Please fill in all required fields.', 'error' );
				resetBtn( $btn, $submitText, $loadingText );
				return;
			}

			$.ajax( {
				url:      sbm_ajax.ajax_url,
				type:     'POST',
				data:     formData,
				dataType: 'json',
			} )
			.done( function ( response ) {
				if ( response.success ) {
					showMessage( response.data.message, 'success' );
					$form[ 0 ].reset();
					// Reset Flatpickr instances.
					if ( typeof flatpickr !== 'undefined' ) {
						document.querySelectorAll( '.sbm-datepicker, .sbm-timepicker' ).forEach( function ( el ) {
							if ( el._flatpickr ) { el._flatpickr.clear(); }
						} );
					}
				} else {
					var msg = ( response.data && response.data.message ) ? response.data.message : 'Something went wrong. Please try again.';
					showMessage( msg, 'error' );
				}
			} )
			.fail( function ( xhr ) {
				var msg = getAjaxErrorMessage( xhr, 'Could not connect to the booking server. Please refresh the page and try again.' );
				showMessage( msg, 'error' );
			} )
			.always( function () {
				resetBtn( $btn, $submitText, $loadingText );
			} );
		} );
	}


	function getAjaxErrorMessage( xhr, fallback ) {
		if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
			return xhr.responseJSON.data.message;
		}
		if ( xhr && xhr.responseText ) {
			try {
				var parsed = JSON.parse( xhr.responseText );
				if ( parsed && parsed.data && parsed.data.message ) {
					return parsed.data.message;
				}
			} catch ( e ) {}
			if ( window.console && window.console.error ) {
				console.error( 'SBM AJAX error response:', xhr.responseText );
			}
		}
		if ( xhr && xhr.status ) {
			return fallback + ' Error code: ' + xhr.status + '.';
		}
		return fallback;
	}

	function showMessage( text, type ) {
		$message
			.html( text )
			.addClass( 'sbm-message--' + type )
			.slideDown( 300 );
		$( 'html, body' ).animate( { scrollTop: $message.offset().top - 100 }, 400 );
	}

	function resetBtn( $btn, $submitText, $loadingText ) {
		$btn.prop( 'disabled', false );
		$submitText.show();
		$loadingText.hide();
	}

	/* ─────────────────────────────────────────────────────
	   Admin: Inline Status Update
	───────────────────────────────────────────────────── */
	$( document ).on( 'change', '.sbm-status-select', function () {
		var $select    = $( this );
		var bookingId  = $select.data( 'id' );
		var newStatus  = $select.val();
		var $row       = $select.closest( 'tr' );
		var $badge     = $row.find( '.sbm-badge' );

		$.ajax( {
			url:      sbm_ajax.ajax_url,
			type:     'POST',
			dataType: 'json',
			data: {
				action:     'sbm_update_status',
				nonce:      sbm_ajax.nonce,
				booking_id: bookingId,
				status:     newStatus,
			},
		} )
		.done( function ( response ) {
			if ( response.success ) {
				// Update badge.
				var labels = { pending: 'Pending', confirmed: 'Confirmed', cancelled: 'Cancelled' };
				$badge
					.text( labels[ newStatus ] || newStatus )
					.removeClass( 'sbm-badge--pending sbm-badge--confirmed sbm-badge--cancelled' )
					.addClass( 'sbm-badge--' + newStatus );
				sbmAdminNotice( response.data.message, 'success' );
			} else {
				sbmAdminNotice( response.data.message || 'Update failed.', 'error' );
			}
		} )
		.fail( function ( xhr ) {
			sbmAdminNotice( getAjaxErrorMessage( xhr, 'Could not update. Please refresh and try again.' ), 'error' );
		} );
	} );

	/* ─────────────────────────────────────────────────────
	   Admin: Delete Booking
	───────────────────────────────────────────────────── */
	$( document ).on( 'click', '.sbm-delete-btn', function () {
		var $btn      = $( this );
		var bookingId = $btn.data( 'id' );

		if ( ! window.confirm( 'Are you sure you want to permanently delete this booking? This cannot be undone.' ) ) {
			return;
		}

		$btn.prop( 'disabled', true ).css( 'opacity', '.5' );

		$.ajax( {
			url:      sbm_ajax.ajax_url,
			type:     'POST',
			dataType: 'json',
			data: {
				action:     'sbm_delete_booking',
				nonce:      sbm_ajax.nonce,
				booking_id: bookingId,
			},
		} )
		.done( function ( response ) {
			if ( response.success ) {
				$btn.closest( 'tr' ).fadeOut( 300, function () { $( this ).remove(); } );
				sbmAdminNotice( response.data.message, 'success' );
			} else {
				sbmAdminNotice( response.data.message || 'Delete failed.', 'error' );
				$btn.prop( 'disabled', false ).css( 'opacity', '1' );
			}
		} )
		.fail( function ( xhr ) {
			sbmAdminNotice( getAjaxErrorMessage( xhr, 'Could not delete. Please refresh and try again.' ), 'error' );
			$btn.prop( 'disabled', false ).css( 'opacity', '1' );
		} );
	} );

	/* ─────────────────────────────────────────────────────
	   Admin: Toast-style notice
	───────────────────────────────────────────────────── */
	function sbmAdminNotice( message, type ) {
		var $notice = $( '<div>' )
			.addClass( 'notice notice-' + ( type === 'success' ? 'success' : 'error' ) + ' is-dismissible sbm-inline-notice' )
			.html( '<p>' + message + '</p>' )
			.css( { position: 'fixed', top: '60px', right: '20px', zIndex: 99999, minWidth: '260px', boxShadow: '0 4px 20px rgba(0,0,0,.15)' } );

		$( 'body' ).append( $notice );

		setTimeout( function () {
			$notice.fadeOut( 300, function () { $( this ).remove(); } );
		}, 3000 );
	}

} )( jQuery );
