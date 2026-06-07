<?php
/**
 * Admin pages for Simple Booking Manager.
 *
 * @package SimpleBookingManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register admin menu.
 */
function sbm_register_admin_menu() {
	add_menu_page(
		__( 'Bookings', 'simple-booking-manager' ),
		__( 'Bookings', 'simple-booking-manager' ),
		'manage_options',
		'simple-booking-manager',
		'sbm_admin_page_router',
		'dashicons-calendar-alt',
		30
	);

	add_submenu_page(
		'simple-booking-manager',
		__( 'All Bookings', 'simple-booking-manager' ),
		__( 'All Bookings', 'simple-booking-manager' ),
		'manage_options',
		'simple-booking-manager',
		'sbm_admin_page_router'
	);

	add_submenu_page(
		'simple-booking-manager',
		__( 'Settings', 'simple-booking-manager' ),
		__( 'Settings', 'simple-booking-manager' ),
		'manage_options',
		'simple-booking-manager-settings',
		'sbm_settings_page'
	);
}
add_action( 'admin_menu', 'sbm_register_admin_menu' );

/**
 * Route admin page actions.
 */
function sbm_admin_page_router() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions.', 'simple-booking-manager' ) );
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

	switch ( $action ) {
		case 'view':
			sbm_admin_view_booking();
			break;
		case 'edit':
			sbm_admin_edit_booking();
			break;
		default:
			sbm_admin_list_bookings();
			break;
	}
}

/**
 * Handle CSV export and process form submissions.
 */
function sbm_handle_admin_actions() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// CSV Export.
	if (
		isset( $_GET['page'], $_GET['action'] ) &&
		'simple-booking-manager' === $_GET['page'] &&
		'export_csv' === $_GET['action'] &&
		isset( $_GET['_wpnonce'] ) &&
		wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'sbm_export_csv' )
	) {
		$bookings = sbm_get_bookings();
		sbm_export_csv( $bookings );
	}

	// Save edited booking (non-AJAX).
	if (
		isset( $_POST['sbm_edit_booking'], $_POST['sbm_edit_nonce'] ) &&
		wp_verify_nonce( sanitize_key( $_POST['sbm_edit_nonce'] ), 'sbm_edit_booking' )
	) {
		$id = absint( $_POST['booking_id'] );
		if ( $id ) {
			$raw    = array(
				'full_name'    => isset( $_POST['full_name'] ) ? wp_unslash( $_POST['full_name'] ) : '',
				'email'        => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '',
				'phone'        => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '',
				'service_type' => isset( $_POST['service_type'] ) ? wp_unslash( $_POST['service_type'] ) : '',
				'booking_date' => isset( $_POST['booking_date'] ) ? wp_unslash( $_POST['booking_date'] ) : '',
				'booking_time' => isset( $_POST['booking_time'] ) ? wp_unslash( $_POST['booking_time'] ) : '',
				'notes'        => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '',
				'status'       => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'pending',
			);
			$errors = sbm_validate_booking_data( $raw );
			$custom_values = isset( $_POST['sbm_custom'] ) && is_array( $_POST['sbm_custom'] ) ? wp_unslash( $_POST['sbm_custom'] ) : array();
			if ( function_exists( 'sbm_validate_custom_field_values' ) ) {
				$errors = array_merge( $errors, sbm_validate_custom_field_values( $custom_values ) );
			}
			if ( empty( $errors ) ) {
				sbm_update_booking( $id, $raw );
				if ( function_exists( 'sbm_save_booking_meta' ) ) {
					sbm_save_booking_meta( $id, $custom_values );
				}
				wp_safe_redirect(
					add_query_arg(
						array(
							'page'    => 'simple-booking-manager',
							'updated' => '1',
						),
						admin_url( 'admin.php' )
					)
				);
				exit;
			}
		}
	}
}
add_action( 'admin_init', 'sbm_handle_admin_actions' );

/**
 * Render the bookings list page.
 */
function sbm_admin_list_bookings() {
	$filters  = array(
		'status'    => isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '',
		'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
		'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
	);
	$bookings = sbm_get_bookings( $filters );
	$export_url = wp_nonce_url(
		add_query_arg(
			array(
				'page'   => 'simple-booking-manager',
				'action' => 'export_csv',
			),
			admin_url( 'admin.php' )
		),
		'sbm_export_csv'
	);
	?>
	<div class="wrap sbm-admin-wrap">
		<h1 class="wp-heading-inline">
			<span class="dashicons dashicons-calendar-alt"></span>
			<?php esc_html_e( 'Bookings', 'simple-booking-manager' ); ?>
		</h1>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action sbm-btn-export">
			<span class="dashicons dashicons-download"></span>
			<?php esc_html_e( 'Export CSV', 'simple-booking-manager' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Booking updated successfully.', 'simple-booking-manager' ); ?></p></div>
		<?php endif; ?>

		<div class="sbm-admin-stats">
			<?php
			$all_bookings = sbm_get_bookings();
			$counts = array( 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0 );
			foreach ( $all_bookings as $b ) {
				if ( isset( $counts[ $b->status ] ) ) {
					$counts[ $b->status ]++;
				}
			}
			?>
			<div class="sbm-stat sbm-stat--total">
				<span class="sbm-stat__number"><?php echo count( $all_bookings ); ?></span>
				<span class="sbm-stat__label"><?php esc_html_e( 'Total', 'simple-booking-manager' ); ?></span>
			</div>
			<div class="sbm-stat sbm-stat--pending">
				<span class="sbm-stat__number"><?php echo absint( $counts['pending'] ); ?></span>
				<span class="sbm-stat__label"><?php esc_html_e( 'Pending', 'simple-booking-manager' ); ?></span>
			</div>
			<div class="sbm-stat sbm-stat--confirmed">
				<span class="sbm-stat__number"><?php echo absint( $counts['confirmed'] ); ?></span>
				<span class="sbm-stat__label"><?php esc_html_e( 'Confirmed', 'simple-booking-manager' ); ?></span>
			</div>
			<div class="sbm-stat sbm-stat--cancelled">
				<span class="sbm-stat__number"><?php echo absint( $counts['cancelled'] ); ?></span>
				<span class="sbm-stat__label"><?php esc_html_e( 'Cancelled', 'simple-booking-manager' ); ?></span>
			</div>
		</div>

		<!-- Filter Bar -->
		<form method="get" class="sbm-filter-form">
			<input type="hidden" name="page" value="simple-booking-manager">
			<div class="sbm-filter-row">
				<input type="text" name="s" placeholder="<?php esc_attr_e( 'Search name, email, phone…', 'simple-booking-manager' ); ?>" value="<?php echo esc_attr( $filters['search'] ); ?>" class="sbm-filter-input">
				<select name="status" class="sbm-filter-select">
					<option value=""><?php esc_html_e( 'All Statuses', 'simple-booking-manager' ); ?></option>
					<option value="pending"   <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'simple-booking-manager' ); ?></option>
					<option value="confirmed" <?php selected( $filters['status'], 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'simple-booking-manager' ); ?></option>
					<option value="cancelled" <?php selected( $filters['status'], 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'simple-booking-manager' ); ?></option>
				</select>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" class="sbm-filter-input" placeholder="<?php esc_attr_e( 'From date', 'simple-booking-manager' ); ?>">
				<input type="date" name="date_to"   value="<?php echo esc_attr( $filters['date_to'] ); ?>"   class="sbm-filter-input" placeholder="<?php esc_attr_e( 'To date', 'simple-booking-manager' ); ?>">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'simple-booking-manager' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'simple-booking-manager' ); ?></a>
			</div>
		</form>

		<!-- Bookings Table -->
		<table class="wp-list-table widefat fixed striped sbm-table">
			<thead>
				<tr>
					<th width="40"><?php esc_html_e( 'ID', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Name', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Contact', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Service', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Date & Time', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Status', 'simple-booking-manager' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'simple-booking-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $bookings ) ) : ?>
					<tr>
						<td colspan="7" class="sbm-no-results">
							<span class="dashicons dashicons-calendar"></span>
							<?php esc_html_e( 'No bookings found.', 'simple-booking-manager' ); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $bookings as $booking ) : ?>
						<tr data-id="<?php echo absint( $booking->id ); ?>">
							<td><strong>#<?php echo absint( $booking->id ); ?></strong></td>
							<td>
								<strong><?php echo esc_html( $booking->full_name ); ?></strong>
							</td>
							<td>
								<a href="mailto:<?php echo esc_attr( $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a><br>
								<small><?php echo esc_html( $booking->phone ); ?></small>
							</td>
							<td><?php echo esc_html( $booking->service_type ); ?></td>
							<td>
								<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ); ?></strong><br>
								<small><?php echo esc_html( substr( $booking->booking_time, 0, 5 ) ); ?></small>
							</td>
							<td>
								<div class="sbm-status-wrap">
									<?php echo wp_kses_post( sbm_status_badge( $booking->status ) ); ?>
									<select class="sbm-status-select" data-id="<?php echo absint( $booking->id ); ?>">
										<option value="pending"   <?php selected( $booking->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'simple-booking-manager' ); ?></option>
										<option value="confirmed" <?php selected( $booking->status, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'simple-booking-manager' ); ?></option>
										<option value="cancelled" <?php selected( $booking->status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'simple-booking-manager' ); ?></option>
									</select>
								</div>
							</td>
							<td class="sbm-actions">
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'simple-booking-manager', 'action' => 'view', 'id' => $booking->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'View', 'simple-booking-manager' ); ?>">
									<span class="dashicons dashicons-visibility"></span>
								</a>
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'simple-booking-manager', 'action' => 'edit', 'id' => $booking->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small" title="<?php esc_attr_e( 'Edit', 'simple-booking-manager' ); ?>">
									<span class="dashicons dashicons-edit"></span>
								</a>
								<button class="button button-small sbm-delete-btn" data-id="<?php echo absint( $booking->id ); ?>" title="<?php esc_attr_e( 'Delete', 'simple-booking-manager' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p class="sbm-table-footer">
			<?php
			printf(
				/* translators: %d: number of bookings */
				esc_html( _n( '%d booking found.', '%d bookings found.', count( $bookings ), 'simple-booking-manager' ) ),
				count( $bookings )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Render the view single booking page.
 */
function sbm_admin_view_booking() {
	$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$booking = $id ? sbm_get_booking( $id ) : null;

	if ( ! $booking ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'simple-booking-manager' ) . '</p></div></div>';
		return;
	}
	?>
	<div class="wrap sbm-admin-wrap">
		<h1>
			<span class="dashicons dashicons-visibility"></span>
			<?php printf( esc_html__( 'Booking #%d', 'simple-booking-manager' ), absint( $booking->id ) ); ?>
		</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Bookings', 'simple-booking-manager' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'simple-booking-manager', 'action' => 'edit', 'id' => $booking->id ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary" style="margin-left:8px;"><?php esc_html_e( 'Edit Booking', 'simple-booking-manager' ); ?></a>

		<div class="sbm-detail-card">
			<div class="sbm-detail-header">
				<div>
					<h2><?php echo esc_html( $booking->full_name ); ?></h2>
					<p><?php echo esc_html( $booking->service_type ); ?></p>
				</div>
				<div><?php echo wp_kses_post( sbm_status_badge( $booking->status ) ); ?></div>
			</div>
			<div class="sbm-detail-grid">
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-email-alt"></span>
					<div>
						<label><?php esc_html_e( 'Email', 'simple-booking-manager' ); ?></label>
						<p><a href="mailto:<?php echo esc_attr( $booking->email ); ?>"><?php echo esc_html( $booking->email ); ?></a></p>
					</div>
				</div>
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-phone"></span>
					<div>
						<label><?php esc_html_e( 'Phone', 'simple-booking-manager' ); ?></label>
						<p><?php echo esc_html( $booking->phone ); ?></p>
					</div>
				</div>
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-calendar-alt"></span>
					<div>
						<label><?php esc_html_e( 'Booking Date', 'simple-booking-manager' ); ?></label>
						<p><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking->booking_date ) ) ); ?></p>
					</div>
				</div>
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-clock"></span>
					<div>
						<label><?php esc_html_e( 'Booking Time', 'simple-booking-manager' ); ?></label>
						<p><?php echo esc_html( substr( $booking->booking_time, 0, 5 ) ); ?></p>
					</div>
				</div>
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-clipboard"></span>
					<div>
						<label><?php esc_html_e( 'Service Type', 'simple-booking-manager' ); ?></label>
						<p><?php echo esc_html( $booking->service_type ); ?></p>
					</div>
				</div>
				<div class="sbm-detail-item">
					<span class="dashicons dashicons-admin-generic"></span>
					<div>
						<label><?php esc_html_e( 'Submitted At', 'simple-booking-manager' ); ?></label>
						<p><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $booking->created_at ) ) ); ?></p>
					</div>
				</div>
			</div>
			<?php if ( function_exists( 'sbm_admin_render_booking_custom_meta' ) ) { sbm_admin_render_booking_custom_meta( $booking->id ); } ?>

			<?php if ( $booking->notes ) : ?>
				<div class="sbm-detail-notes">
					<label><span class="dashicons dashicons-format-aside"></span> <?php esc_html_e( 'Notes', 'simple-booking-manager' ); ?></label>
					<p><?php echo nl2br( esc_html( $booking->notes ) ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render the edit booking page.
 */
function sbm_admin_edit_booking() {
	$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$booking = $id ? sbm_get_booking( $id ) : null;

	if ( ! $booking ) {
		echo '<div class="wrap"><div class="notice notice-error"><p>' . esc_html__( 'Booking not found.', 'simple-booking-manager' ) . '</p></div></div>';
		return;
	}

	$services = sbm_get_service_types();
	?>
	<div class="wrap sbm-admin-wrap">
		<h1>
			<span class="dashicons dashicons-edit"></span>
			<?php printf( esc_html__( 'Edit Booking #%d', 'simple-booking-manager' ), absint( $booking->id ) ); ?>
		</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Bookings', 'simple-booking-manager' ); ?></a>
		<hr class="wp-header-end">

		<form method="post" class="sbm-edit-form">
			<?php wp_nonce_field( 'sbm_edit_booking', 'sbm_edit_nonce' ); ?>
			<input type="hidden" name="sbm_edit_booking" value="1">
			<input type="hidden" name="booking_id" value="<?php echo absint( $booking->id ); ?>">

			<div class="sbm-form-grid">
				<div class="sbm-form-field">
					<label for="full_name"><?php esc_html_e( 'Full Name', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<input type="text" id="full_name" name="full_name" value="<?php echo esc_attr( $booking->full_name ); ?>" required class="regular-text">
				</div>
				<div class="sbm-form-field">
					<label for="email"><?php esc_html_e( 'Email', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<input type="email" id="email" name="email" value="<?php echo esc_attr( $booking->email ); ?>" required class="regular-text">
				</div>
				<div class="sbm-form-field">
					<label for="phone"><?php esc_html_e( 'Phone', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<input type="text" id="phone" name="phone" value="<?php echo esc_attr( $booking->phone ); ?>" required class="regular-text">
				</div>
				<div class="sbm-form-field">
					<label for="service_type"><?php esc_html_e( 'Service Type', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<select id="service_type" name="service_type" required>
						<?php foreach ( $services as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $booking->service_type, $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sbm-form-field">
					<label for="booking_date"><?php esc_html_e( 'Booking Date', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<input type="date" id="booking_date" name="booking_date" value="<?php echo esc_attr( $booking->booking_date ); ?>" required class="regular-text">
				</div>
				<div class="sbm-form-field">
					<label for="booking_time"><?php esc_html_e( 'Booking Time', 'simple-booking-manager' ); ?> <span class="required">*</span></label>
					<input type="time" id="booking_time" name="booking_time" value="<?php echo esc_attr( substr( $booking->booking_time, 0, 5 ) ); ?>" required class="regular-text">
				</div>
				<div class="sbm-form-field">
					<label for="status"><?php esc_html_e( 'Status', 'simple-booking-manager' ); ?></label>
					<select id="status" name="status">
						<option value="pending"   <?php selected( $booking->status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'simple-booking-manager' ); ?></option>
						<option value="confirmed" <?php selected( $booking->status, 'confirmed' ); ?>><?php esc_html_e( 'Confirmed', 'simple-booking-manager' ); ?></option>
						<option value="cancelled" <?php selected( $booking->status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'simple-booking-manager' ); ?></option>
					</select>
				</div>
				<div class="sbm-form-field sbm-form-field--full">
					<label for="notes"><?php esc_html_e( 'Notes', 'simple-booking-manager' ); ?></label>
					<textarea id="notes" name="notes" rows="4" class="large-text"><?php echo esc_textarea( $booking->notes ); ?></textarea>
				</div>
			</div>

			<?php if ( function_exists( 'sbm_admin_render_booking_custom_edit_fields' ) ) { sbm_admin_render_booking_custom_edit_fields( $booking->id ); } ?>

			<div class="sbm-form-actions">
				<button type="submit" class="button button-primary button-large">
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Save Changes', 'simple-booking-manager' ); ?>
				</button>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager' ) ); ?>" class="button button-large"><?php esc_html_e( 'Cancel', 'simple-booking-manager' ); ?></a>
			</div>
		</form>
	</div>
	<?php
}

/**
 * Settings page.
 */
function sbm_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['sbm_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['sbm_settings_nonce'] ), 'sbm_save_settings' ) ) {
		$admin_email = isset( $_POST['sbm_admin_email'] ) ? sanitize_email( wp_unslash( $_POST['sbm_admin_email'] ) ) : '';
		if ( is_email( $admin_email ) ) {
			update_option( 'sbm_admin_email', $admin_email );
		}

		$custom_css = isset( $_POST['sbm_custom_css'] ) ? wp_unslash( $_POST['sbm_custom_css'] ) : '';
		update_option( 'sbm_custom_css', wp_strip_all_tags( $custom_css ) );

		if ( function_exists( 'sbm_get_default_email_templates' ) ) {
			$templates = get_option( 'sbm_email_templates', sbm_get_default_email_templates() );
			foreach ( $templates as $key => $template ) {
				$templates[ $key ]['subject'] = isset( $_POST['sbm_email_templates'][ $key ]['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['sbm_email_templates'][ $key ]['subject'] ) ) : $template['subject'];
				$templates[ $key ]['body']    = isset( $_POST['sbm_email_templates'][ $key ]['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['sbm_email_templates'][ $key ]['body'] ) ) : $template['body'];
			}
			update_option( 'sbm_email_templates', $templates );
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'simple-booking-manager' ) . '</p></div>';
	}

	$admin_email = get_option( 'sbm_admin_email', get_option( 'admin_email' ) );
	$custom_css  = get_option( 'sbm_custom_css', '' );
	$email_templates = function_exists( 'sbm_get_default_email_templates' ) ? get_option( 'sbm_email_templates', sbm_get_default_email_templates() ) : array();
	?>
	<div class="wrap sbm-admin-wrap">
		<h1><span class="dashicons dashicons-admin-settings"></span> <?php esc_html_e( 'Booking Settings', 'simple-booking-manager' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'sbm_save_settings', 'sbm_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Notification Email', 'simple-booking-manager' ); ?></th>
					<td>
						<input type="email" name="sbm_admin_email" value="<?php echo esc_attr( $admin_email ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Admin email for booking notifications.', 'simple-booking-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Custom CSS', 'simple-booking-manager' ); ?></th>
					<td>
						<textarea name="sbm_custom_css" rows="10" class="large-text code" placeholder=".sbm-submit-btn { border-radius: 30px; }"><?php echo esc_textarea( $custom_css ); ?></textarea>
						<p class="description"><?php esc_html_e( 'This CSS loads on frontend booking forms and admin booking pages.', 'simple-booking-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Email Templates', 'simple-booking-manager' ); ?></th>
					<td>
						<p class="description"><?php esc_html_e( 'Available placeholders: {booking_id}, {customer_name}, {email}, {phone}, {service}, {booking_date}, {booking_time}, {status}, {site_name}', 'simple-booking-manager' ); ?></p>
						<?php foreach ( $email_templates as $key => $template ) : ?>
							<div class="sbm-template-box">
								<h3><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></h3>
								<input type="text" name="sbm_email_templates[<?php echo esc_attr( $key ); ?>][subject]" value="<?php echo esc_attr( $template['subject'] ); ?>" class="large-text">
								<textarea name="sbm_email_templates[<?php echo esc_attr( $key ); ?>][body]" rows="6" class="large-text"><?php echo esc_textarea( $template['body'] ); ?></textarea>
							</div>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Shortcode', 'simple-booking-manager' ); ?></th>
					<td>
						<code>[booking_form]</code>
						<p class="description"><?php esc_html_e( 'Add this shortcode to any page or post to show the booking form.', 'simple-booking-manager' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'simple-booking-manager' ) ); ?>
		</form>
	</div>
	<?php
}
