<?php
/**
 * Frontend booking form template.
 *
 * @package SimpleBookingManager
 * @var array $atts Shortcode attributes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$services = sbm_get_service_types();
?>
<div class="sbm-form-wrap" id="sbm-booking-wrap">
	<div class="sbm-form-header">
		<div class="sbm-form-header__icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
		</div>
		<div>
			<h2 class="sbm-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<p class="sbm-form-subtitle"><?php esc_html_e( 'Fill in the form below and we\'ll get back to you shortly.', 'simple-booking-manager' ); ?></p>
		</div>
	</div>

	<div id="sbm-message" class="sbm-message" role="alert" aria-live="polite" style="display:none;"></div>

	<form id="sbm-booking-form" novalidate>
		<?php wp_nonce_field( 'sbm_booking_nonce', 'sbm_frontend_nonce', false ); ?>

		<div class="sbm-fields-grid">
			<div class="sbm-field">
				<label for="sbm_full_name" class="sbm-label">
					<?php esc_html_e( 'Full Name', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
					<input type="text" id="sbm_full_name" name="full_name" class="sbm-input" placeholder="<?php esc_attr_e( 'John Smith', 'simple-booking-manager' ); ?>" required autocomplete="name">
				</div>
			</div>

			<div class="sbm-field">
				<label for="sbm_email" class="sbm-label">
					<?php esc_html_e( 'Email Address', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
					<input type="email" id="sbm_email" name="email" class="sbm-input" placeholder="<?php esc_attr_e( 'you@example.com', 'simple-booking-manager' ); ?>" required autocomplete="email">
				</div>
			</div>

			<div class="sbm-field">
				<label for="sbm_phone" class="sbm-label">
					<?php esc_html_e( 'Phone Number', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.42A2 2 0 0 1 3.6 1.27h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.91a16 16 0 0 0 6.35 6.35l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
					<input type="tel" id="sbm_phone" name="phone" class="sbm-input" placeholder="<?php esc_attr_e( '+1 234 567 8900', 'simple-booking-manager' ); ?>" required autocomplete="tel">
				</div>
			</div>

			<div class="sbm-field">
				<label for="sbm_service_type" class="sbm-label">
					<?php esc_html_e( 'Service Type', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap sbm-select-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
					<select id="sbm_service_type" name="service_type" class="sbm-input sbm-select" required>
						<option value=""><?php esc_html_e( '— Select a service —', 'simple-booking-manager' ); ?></option>
						<?php foreach ( $services as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="sbm-field">
				<label for="sbm_booking_date" class="sbm-label">
					<?php esc_html_e( 'Preferred Date', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
					<input type="text" id="sbm_booking_date" name="booking_date" class="sbm-input sbm-datepicker" placeholder="<?php esc_attr_e( 'Select date', 'simple-booking-manager' ); ?>" required readonly>
				</div>
			</div>

			<div class="sbm-field">
				<label for="sbm_booking_time" class="sbm-label">
					<?php esc_html_e( 'Preferred Time', 'simple-booking-manager' ); ?>
					<span class="sbm-required" aria-hidden="true">*</span>
				</label>
				<div class="sbm-input-wrap">
					<svg class="sbm-field-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
					<input type="text" id="sbm_booking_time" name="booking_time" class="sbm-input sbm-timepicker" placeholder="<?php esc_attr_e( 'Select time', 'simple-booking-manager' ); ?>" required readonly>
				</div>
			</div>
		</div>

		<div class="sbm-field sbm-field--full">
			<label for="sbm_notes" class="sbm-label"><?php esc_html_e( 'Additional Notes', 'simple-booking-manager' ); ?></label>
			<div class="sbm-input-wrap">
				<textarea id="sbm_notes" name="notes" class="sbm-input sbm-textarea" rows="4" placeholder="<?php esc_attr_e( 'Any special requests or additional information…', 'simple-booking-manager' ); ?>"></textarea>
			</div>
		</div>

		<?php if ( function_exists( 'sbm_render_custom_fields_frontend' ) ) { sbm_render_custom_fields_frontend(); } ?>

		<div class="sbm-form-footer">
			<button type="submit" id="sbm-submit-btn" class="sbm-submit-btn">
				<span class="sbm-submit-text"><?php esc_html_e( 'Book Appointment', 'simple-booking-manager' ); ?></span>
				<span class="sbm-submit-loading" style="display:none;">
					<svg class="sbm-spinner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
					<?php esc_html_e( 'Submitting…', 'simple-booking-manager' ); ?>
				</span>
			</button>
			<p class="sbm-privacy-note">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
				<?php esc_html_e( 'Your information is secure and will never be shared.', 'simple-booking-manager' ); ?>
			</p>
		</div>
	</form>
</div>
