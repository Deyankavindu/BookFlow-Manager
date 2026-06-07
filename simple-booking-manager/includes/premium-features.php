<?php
/**
 * Premium upgrade features for Simple Booking Manager.
 * Adds custom fields, custom CSS, email templates, dashboard, and bulk actions.
 *
 * @package SimpleBookingManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SBM_FIELDS_TABLE' ) ) {
	define( 'SBM_FIELDS_TABLE', 'booking_fields' );
}
if ( ! defined( 'SBM_META_TABLE' ) ) {
	define( 'SBM_META_TABLE', 'booking_meta' );
}

/**
 * Add premium admin submenus.
 */
function sbm_register_premium_admin_menu() {
	add_submenu_page(
		'simple-booking-manager',
		__( 'Dashboard', 'simple-booking-manager' ),
		__( 'Dashboard', 'simple-booking-manager' ),
		'manage_options',
		'simple-booking-manager-dashboard',
		'sbm_dashboard_page'
	);

	add_submenu_page(
		'simple-booking-manager',
		__( 'Custom Fields', 'simple-booking-manager' ),
		__( 'Custom Fields', 'simple-booking-manager' ),
		'manage_options',
		'simple-booking-manager-fields',
		'sbm_custom_fields_page'
	);
}
add_action( 'admin_menu', 'sbm_register_premium_admin_menu', 20 );

/**
 * Get custom fields.
 *
 * @return array
 */
function sbm_get_custom_fields() {
	global $wpdb;
	$table = $wpdb->prefix . SBM_FIELDS_TABLE;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$fields = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY field_order ASC, id ASC" );
	return is_array( $fields ) ? $fields : array();
}

/**
 * Normalize field slug.
 */
function sbm_field_slug( $name ) {
	$name = sanitize_key( $name );
	return $name ? $name : 'field_' . time();
}

/**
 * Parse options from textarea to array.
 */
function sbm_parse_field_options( $options ) {
	$options = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $options ) ) );
	return array_values( $options );
}

/**
 * Render custom fields on the frontend form.
 */
function sbm_render_custom_fields_frontend() {
	$fields = sbm_get_custom_fields();
	if ( empty( $fields ) ) {
		return;
	}

	echo '<div class="sbm-custom-fields sbm-fields-grid">';
	foreach ( $fields as $field ) {
		sbm_render_single_custom_field( $field );
	}
	echo '</div>';
}

/**
 * Render one custom field.
 */
function sbm_render_single_custom_field( $field, $value = '' ) {
	$name        = sbm_field_slug( $field->field_name );
	$type        = sanitize_key( $field->field_type );
	$label       = $field->field_label ? $field->field_label : $field->field_name;
	$required    = ! empty( $field->is_required );
	$placeholder = isset( $field->placeholder ) ? $field->placeholder : '';
	$default     = '' !== $value ? $value : ( isset( $field->default_value ) ? $field->default_value : '' );
	$options     = sbm_parse_field_options( isset( $field->field_options ) ? $field->field_options : '' );
	$field_id    = 'sbm_custom_' . esc_attr( $name );

	echo '<div class="sbm-field sbm-custom-field sbm-custom-field--' . esc_attr( $type ) . '">';
	echo '<label for="' . esc_attr( $field_id ) . '" class="sbm-label">' . esc_html( $label );
	if ( $required ) {
		echo ' <span class="sbm-required" aria-hidden="true">*</span>';
	}
	echo '</label>';

	$required_attr = $required ? ' required' : '';
	$base_name     = 'sbm_custom[' . esc_attr( $name ) . ']';

	if ( 'textarea' === $type ) {
		echo '<textarea id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $base_name ) . '" class="sbm-input sbm-textarea" rows="4" placeholder="' . esc_attr( $placeholder ) . '"' . $required_attr . '>' . esc_textarea( $default ) . '</textarea>';
	} elseif ( 'select' === $type ) {
		echo '<select id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $base_name ) . '" class="sbm-input sbm-select"' . $required_attr . '>';
		echo '<option value="">' . esc_html__( '— Select —', 'simple-booking-manager' ) . '</option>';
		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $default, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</select>';
	} elseif ( 'radio' === $type ) {
		echo '<div class="sbm-choice-group">';
		foreach ( $options as $index => $option ) {
			$rid = $field_id . '_' . $index;
			echo '<label class="sbm-choice"><input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $base_name ) . '" value="' . esc_attr( $option ) . '" ' . checked( $default, $option, false ) . $required_attr . '> ' . esc_html( $option ) . '</label>';
		}
		echo '</div>';
	} elseif ( 'checkbox' === $type ) {
		echo '<label class="sbm-choice"><input type="checkbox" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $base_name ) . '" value="1" ' . checked( $default, '1', false ) . $required_attr . '> ' . esc_html( $placeholder ? $placeholder : __( 'Yes', 'simple-booking-manager' ) ) . '</label>';
	} else {
		$allowed_types = array( 'text', 'email', 'tel', 'number', 'date', 'time', 'url' );
		$input_type    = in_array( $type, $allowed_types, true ) ? $type : 'text';
		echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $field_id ) . '" name="' . esc_attr( $base_name ) . '" class="sbm-input" placeholder="' . esc_attr( $placeholder ) . '" value="' . esc_attr( $default ) . '"' . $required_attr . '>';
	}

	echo '</div>';
}

/**
 * Validate dynamic custom fields.
 */
function sbm_validate_custom_field_values( $posted_values ) {
	$errors = array();
	$fields = sbm_get_custom_fields();
	$posted_values = is_array( $posted_values ) ? $posted_values : array();

	foreach ( $fields as $field ) {
		$name  = sbm_field_slug( $field->field_name );
		$label = $field->field_label ? $field->field_label : $field->field_name;
		$type  = sanitize_key( $field->field_type );
		$value = isset( $posted_values[ $name ] ) ? wp_unslash( $posted_values[ $name ] ) : '';

		if ( ! empty( $field->is_required ) && '' === trim( (string) $value ) ) {
			$errors[] = sprintf( __( '%s is required.', 'simple-booking-manager' ), $label );
			continue;
		}

		if ( '' !== trim( (string) $value ) ) {
			if ( 'email' === $type && ! is_email( $value ) ) {
				$errors[] = sprintf( __( '%s must be a valid email address.', 'simple-booking-manager' ), $label );
			}
			if ( 'url' === $type && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$errors[] = sprintf( __( '%s must be a valid URL.', 'simple-booking-manager' ), $label );
			}
		}
	}

	return $errors;
}

/**
 * Save booking meta values.
 */
function sbm_save_booking_meta( $booking_id, $posted_values ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_META_TABLE;
	$fields = sbm_get_custom_fields();
	$posted_values = is_array( $posted_values ) ? $posted_values : array();

	$wpdb->delete( $table, array( 'booking_id' => absint( $booking_id ) ), array( '%d' ) );

	foreach ( $fields as $field ) {
		$name  = sbm_field_slug( $field->field_name );
		$type  = sanitize_key( $field->field_type );
		$value = isset( $posted_values[ $name ] ) ? wp_unslash( $posted_values[ $name ] ) : '';
		$value = 'textarea' === $type ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );

		if ( '' === $value && empty( $field->is_required ) ) {
			continue;
		}

		$wpdb->insert(
			$table,
			array(
				'booking_id' => absint( $booking_id ),
				'field_name' => $name,
				'field_label' => sanitize_text_field( $field->field_label ),
				'field_value' => $value,
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}
}

/**
 * Get booking meta values.
 */
function sbm_get_booking_meta_values( $booking_id ) {
	global $wpdb;
	$table = $wpdb->prefix . SBM_META_TABLE;
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id ASC", absint( $booking_id ) ) );
	$values = array();
	foreach ( (array) $rows as $row ) {
		$values[ $row->field_name ] = $row;
	}
	return $values;
}

/**
 * Show custom meta in admin view.
 */
function sbm_admin_render_booking_custom_meta( $booking_id ) {
	$values = sbm_get_booking_meta_values( $booking_id );
	if ( empty( $values ) ) {
		return;
	}
	echo '<div class="sbm-detail-notes sbm-custom-meta-box"><label><span class="dashicons dashicons-feedback"></span> ' . esc_html__( 'Custom Field Details', 'simple-booking-manager' ) . '</label>';
	echo '<div class="sbm-custom-meta-grid">';
	foreach ( $values as $row ) {
		echo '<p><strong>' . esc_html( $row->field_label ) . ':</strong><br>' . nl2br( esc_html( $row->field_value ) ) . '</p>';
	}
	echo '</div></div>';
}

/**
 * Render custom meta edit fields.
 */
function sbm_admin_render_booking_custom_edit_fields( $booking_id ) {
	$fields = sbm_get_custom_fields();
	if ( empty( $fields ) ) {
		return;
	}
	$values = sbm_get_booking_meta_values( $booking_id );
	echo '<div class="sbm-form-field sbm-form-field--full"><h2>' . esc_html__( 'Custom Fields', 'simple-booking-manager' ) . '</h2></div>';
	foreach ( $fields as $field ) {
		$key   = sbm_field_slug( $field->field_name );
		$value = isset( $values[ $key ] ) ? $values[ $key ]->field_value : '';
		echo '<div class="sbm-form-field">';
		sbm_render_single_custom_field( $field, $value );
		echo '</div>';
	}
}

/**
 * Allowed custom field types.
 *
 * @return array
 */
function sbm_allowed_custom_field_types() {
	return array( 'text', 'email', 'tel', 'number', 'date', 'time', 'url', 'textarea', 'select', 'radio', 'checkbox' );
}

/**
 * Create or update a custom field.
 *
 * @param array $raw Raw posted field data.
 * @return int|WP_Error Saved field ID or error.
 */
function sbm_save_custom_field_definition( $raw ) {
	global $wpdb;

	$table      = $wpdb->prefix . SBM_FIELDS_TABLE;
	$meta_table = $wpdb->prefix . SBM_META_TABLE;
	$id         = isset( $raw['field_id'] ) ? absint( $raw['field_id'] ) : 0;
	$types      = sbm_allowed_custom_field_types();
	$field_type = isset( $raw['field_type'] ) ? sanitize_key( wp_unslash( $raw['field_type'] ) ) : 'text';

	if ( ! in_array( $field_type, $types, true ) ) {
		$field_type = 'text';
	}

	$field_name  = sbm_field_slug( isset( $raw['field_name'] ) ? wp_unslash( $raw['field_name'] ) : '' );
	$field_label = sanitize_text_field( isset( $raw['field_label'] ) ? wp_unslash( $raw['field_label'] ) : '' );

	if ( '' === $field_name || '' === $field_label ) {
		return new WP_Error( 'missing_field_data', __( 'Field name and label are required.', 'simple-booking-manager' ) );
	}

	$existing_id = (int) $wpdb->get_var(
		$wpdb->prepare( "SELECT id FROM {$table} WHERE field_name = %s AND id != %d", $field_name, $id )
	);

	if ( $existing_id ) {
		return new WP_Error( 'duplicate_field_name', __( 'This field name already exists. Please use a unique field name.', 'simple-booking-manager' ) );
	}

	$old_field_name = '';
	if ( $id ) {
		$old_field_name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT field_name FROM {$table} WHERE id = %d", $id ) );
		if ( '' === $old_field_name ) {
			return new WP_Error( 'field_not_found', __( 'Custom field not found.', 'simple-booking-manager' ) );
		}
	}

	$data = array(
		'field_name'    => $field_name,
		'field_label'   => $field_label,
		'field_type'    => $field_type,
		'is_required'   => isset( $raw['is_required'] ) ? 1 : 0,
		'placeholder'   => sanitize_text_field( isset( $raw['placeholder'] ) ? wp_unslash( $raw['placeholder'] ) : '' ),
		'default_value' => sanitize_text_field( isset( $raw['default_value'] ) ? wp_unslash( $raw['default_value'] ) : '' ),
		'field_order'   => isset( $raw['field_order'] ) ? absint( $raw['field_order'] ) : 0,
		'field_options' => sanitize_textarea_field( isset( $raw['field_options'] ) ? wp_unslash( $raw['field_options'] ) : '' ),
	);
	$formats = array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s' );

	if ( $id ) {
		$updated = $wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		if ( false === $updated ) {
			return new WP_Error( 'field_update_failed', __( 'Could not update the custom field.', 'simple-booking-manager' ) );
		}

		// Keep existing booking meta connected when the field name or label is changed.
		if ( $old_field_name && $old_field_name !== $field_name ) {
			$wpdb->update(
				$meta_table,
				array(
					'field_name'  => $field_name,
					'field_label' => $field_label,
				),
				array( 'field_name' => $old_field_name ),
				array( '%s', '%s' ),
				array( '%s' )
			);
		} else {
			$wpdb->update(
				$meta_table,
				array( 'field_label' => $field_label ),
				array( 'field_name' => $field_name ),
				array( '%s' ),
				array( '%s' )
			);
		}

		return $id;
	}

	$inserted = $wpdb->insert( $table, $data, $formats );
	if ( false === $inserted ) {
		return new WP_Error( 'field_insert_failed', __( 'Could not add the custom field.', 'simple-booking-manager' ) );
	}

	return (int) $wpdb->insert_id;
}

/**
 * Delete a custom field and its saved booking values.
 *
 * @param int $field_id Field ID.
 * @return bool|WP_Error
 */
function sbm_delete_custom_field_definition( $field_id ) {
	global $wpdb;

	$table      = $wpdb->prefix . SBM_FIELDS_TABLE;
	$meta_table = $wpdb->prefix . SBM_META_TABLE;
	$field_id   = absint( $field_id );

	if ( ! $field_id ) {
		return new WP_Error( 'invalid_field_id', __( 'Invalid custom field ID.', 'simple-booking-manager' ) );
	}

	$field_name = (string) $wpdb->get_var( $wpdb->prepare( "SELECT field_name FROM {$table} WHERE id = %d", $field_id ) );
	if ( '' === $field_name ) {
		return new WP_Error( 'field_not_found', __( 'Custom field not found.', 'simple-booking-manager' ) );
	}

	$deleted = $wpdb->delete( $table, array( 'id' => $field_id ), array( '%d' ) );
	if ( false === $deleted ) {
		return new WP_Error( 'field_delete_failed', __( 'Could not delete the custom field.', 'simple-booking-manager' ) );
	}

	$wpdb->delete( $meta_table, array( 'field_name' => $field_name ), array( '%s' ) );
	return true;
}

/**
 * Custom Fields admin page.
 */
function sbm_custom_fields_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have sufficient permissions.', 'simple-booking-manager' ) );
	}

	global $wpdb;
	$table  = $wpdb->prefix . SBM_FIELDS_TABLE;
	$notice = '';
	$error  = '';

	if ( isset( $_GET['message'] ) ) {
		$message = sanitize_key( wp_unslash( $_GET['message'] ) );
		if ( 'added' === $message ) {
			$notice = __( 'Field added successfully.', 'simple-booking-manager' );
		} elseif ( 'updated' === $message ) {
			$notice = __( 'Field updated successfully.', 'simple-booking-manager' );
		} elseif ( 'deleted' === $message ) {
			$notice = __( 'Field deleted successfully.', 'simple-booking-manager' );
		}
	}

	if ( isset( $_GET['delete'], $_GET['_wpnonce'] ) ) {
		$field_id = absint( $_GET['delete'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'sbm_delete_field_' . $field_id ) ) {
			$error = __( 'Security check failed. Please try again.', 'simple-booking-manager' );
		} else {
			$deleted = sbm_delete_custom_field_definition( $field_id );
			if ( is_wp_error( $deleted ) ) {
				$error = $deleted->get_error_message();
			} else {
				wp_safe_redirect( add_query_arg( array( 'page' => 'simple-booking-manager-fields', 'message' => 'deleted' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}

	if ( isset( $_POST['sbm_field_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sbm_field_nonce'] ) ), 'sbm_save_field' ) ) {
			$error = __( 'Security check failed. Please try again.', 'simple-booking-manager' );
		} else {
			$saved = sbm_save_custom_field_definition( $_POST );
			if ( is_wp_error( $saved ) ) {
				$error = $saved->get_error_message();
			} else {
				$message = ! empty( $_POST['field_id'] ) ? 'updated' : 'added';
				wp_safe_redirect( add_query_arg( array( 'page' => 'simple-booking-manager-fields', 'message' => $message ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}

	$edit = null;
	if ( isset( $_GET['edit'] ) ) {
		$edit_id = absint( $_GET['edit'] );
		$edit    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ) );
		if ( ! $edit ) {
			$error = __( 'Custom field not found.', 'simple-booking-manager' );
		}
	}

	$fields        = sbm_get_custom_fields();
	$types         = sbm_allowed_custom_field_types();
	$is_editing    = (bool) $edit;
	$form_action   = $is_editing ? __( 'Update Existing Field', 'simple-booking-manager' ) : __( 'Add New Field', 'simple-booking-manager' );
	$form_subtitle = $is_editing ? __( 'Change the selected field details below, then click Update Field. Existing saved booking values will stay connected.', 'simple-booking-manager' ) : __( 'Create a new field and it will automatically appear in the frontend booking form and dashboard preview.', 'simple-booking-manager' );
	?>
	<div class="wrap sbm-admin-wrap">
		<h1><span class="dashicons dashicons-feedback"></span> <?php esc_html_e( 'Custom Fields', 'simple-booking-manager' ); ?></h1>
		<p><?php esc_html_e( 'Add, edit, or delete fields that appear automatically in the frontend booking form and admin booking details.', 'simple-booking-manager' ); ?></p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<?php if ( $error ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endif; ?>

		<div class="sbm-admin-two-col">
			<div class="sbm-admin-card sbm-field-editor-card" id="sbm-field-editor">
				<div class="sbm-field-editor-header">
					<div>
						<h2><?php echo esc_html( $form_action ); ?></h2>
						<p><?php echo esc_html( $form_subtitle ); ?></p>
					</div>
					<?php if ( $is_editing ) : ?>
						<span class="sbm-editing-pill"><?php esc_html_e( 'Editing', 'simple-booking-manager' ); ?>: <?php echo esc_html( $edit->field_label ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $is_editing ) : ?>
					<div class="notice notice-info inline sbm-inline-edit-notice"><p><?php esc_html_e( 'You are editing an existing custom field. Changing the field name will also update old saved booking meta keys for this field.', 'simple-booking-manager' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager-fields' ) ); ?>">
					<?php wp_nonce_field( 'sbm_save_field', 'sbm_field_nonce' ); ?>
					<input type="hidden" name="field_id" value="<?php echo esc_attr( $edit ? $edit->id : 0 ); ?>">
					<table class="form-table" role="presentation">
						<tr>
							<th><label for="sbm-field-name"><?php esc_html_e( 'Field Name', 'simple-booking-manager' ); ?></label></th>
							<td>
								<input id="sbm-field-name" type="text" name="field_name" value="<?php echo esc_attr( $edit ? $edit->field_name : '' ); ?>" class="regular-text" required pattern="[a-zA-Z0-9_\-]+">
								<p class="description"><?php esc_html_e( 'Unique key used in the database. Example: passport_number', 'simple-booking-manager' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="sbm-field-label"><?php esc_html_e( 'Label', 'simple-booking-manager' ); ?></label></th>
							<td><input id="sbm-field-label" type="text" name="field_label" value="<?php echo esc_attr( $edit ? $edit->field_label : '' ); ?>" class="regular-text" required></td>
						</tr>
						<tr>
							<th><label for="sbm-field-type"><?php esc_html_e( 'Type', 'simple-booking-manager' ); ?></label></th>
							<td>
								<select id="sbm-field-type" name="field_type">
									<?php foreach ( $types as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $edit ? $edit->field_type : 'text', $type ); ?>><?php echo esc_html( ucfirst( $type ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Required', 'simple-booking-manager' ); ?></th>
							<td><label><input type="checkbox" name="is_required" value="1" <?php checked( $edit ? $edit->is_required : 0, 1 ); ?>> <?php esc_html_e( 'Make this field required', 'simple-booking-manager' ); ?></label></td>
						</tr>
						<tr>
							<th><label for="sbm-field-placeholder"><?php esc_html_e( 'Placeholder', 'simple-booking-manager' ); ?></label></th>
							<td><input id="sbm-field-placeholder" type="text" name="placeholder" value="<?php echo esc_attr( $edit ? $edit->placeholder : '' ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="sbm-field-default"><?php esc_html_e( 'Default Value', 'simple-booking-manager' ); ?></label></th>
							<td><input id="sbm-field-default" type="text" name="default_value" value="<?php echo esc_attr( $edit ? $edit->default_value : '' ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="sbm-field-order"><?php esc_html_e( 'Order', 'simple-booking-manager' ); ?></label></th>
							<td><input id="sbm-field-order" type="number" name="field_order" value="<?php echo esc_attr( $edit ? $edit->field_order : 0 ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="sbm-field-options"><?php esc_html_e( 'Options', 'simple-booking-manager' ); ?></label></th>
							<td>
								<textarea id="sbm-field-options" name="field_options" rows="5" class="large-text" placeholder="<?php esc_attr_e( 'One option per line', 'simple-booking-manager' ); ?>"><?php echo esc_textarea( $edit ? $edit->field_options : '' ); ?></textarea>
								<p class="description"><?php esc_html_e( 'Used by Select and Radio fields. Add one option per line.', 'simple-booking-manager' ); ?></p>
							</td>
						</tr>
					</table>
					<div class="sbm-field-editor-actions">
						<?php submit_button( $is_editing ? __( 'Update Field', 'simple-booking-manager' ) : __( 'Add Field', 'simple-booking-manager' ), 'primary', 'submit', false ); ?>
						<?php if ( $is_editing ) : ?>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager-fields' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Cancel Edit', 'simple-booking-manager' ); ?></a>
						<?php else : ?>
							<button type="reset" class="button button-secondary"><?php esc_html_e( 'Clear Form', 'simple-booking-manager' ); ?></button>
						<?php endif; ?>
					</div>
				</form>
			</div>
			<div class="sbm-admin-card sbm-saved-fields-card">
				<div class="sbm-saved-fields-header">
					<div>
						<h2><?php esc_html_e( 'Saved Custom Fields', 'simple-booking-manager' ); ?></h2>
						<p><?php esc_html_e( 'Click Edit to load any existing field into the editor.', 'simple-booking-manager' ); ?></p>
					</div>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager-dashboard' ) ); ?>#sbm-booking-preview-wrap"><?php esc_html_e( 'View Form Preview', 'simple-booking-manager' ); ?></a>
				</div>
				<table class="widefat striped sbm-fields-table">
					<thead><tr><th><?php esc_html_e( 'Order', 'simple-booking-manager' ); ?></th><th><?php esc_html_e( 'Label', 'simple-booking-manager' ); ?></th><th><?php esc_html_e( 'Name', 'simple-booking-manager' ); ?></th><th><?php esc_html_e( 'Type', 'simple-booking-manager' ); ?></th><th><?php esc_html_e( 'Actions', 'simple-booking-manager' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $fields ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No custom fields yet.', 'simple-booking-manager' ); ?></td></tr>
					<?php else : foreach ( $fields as $field ) : ?>
						<?php
						$edit_url   = add_query_arg( array( 'page' => 'simple-booking-manager-fields', 'edit' => absint( $field->id ) ), admin_url( 'admin.php' ) );
						$delete_url = wp_nonce_url(
							add_query_arg( array( 'page' => 'simple-booking-manager-fields', 'delete' => absint( $field->id ) ), admin_url( 'admin.php' ) ),
							'sbm_delete_field_' . absint( $field->id )
						);
						?>
						<tr class="<?php echo ( $is_editing && (int) $edit->id === (int) $field->id ) ? 'sbm-row-editing' : ''; ?>">
							<td><?php echo absint( $field->field_order ); ?></td>
							<td><strong><?php echo esc_html( $field->field_label ); ?></strong><?php echo $field->is_required ? ' <span class="sbm-required">*</span>' : ''; ?></td>
							<td><code><?php echo esc_html( $field->field_name ); ?></code></td>
							<td><?php echo esc_html( ucfirst( $field->field_type ) ); ?></td>
							<td>
								<a class="button button-small button-primary" href="<?php echo esc_url( $edit_url ); ?>#sbm-field-editor"><?php esc_html_e( 'Edit Field', 'simple-booking-manager' ); ?></a>
								<a class="button button-small button-link-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this field and its saved booking values?', 'simple-booking-manager' ) ); ?>');"><?php esc_html_e( 'Delete', 'simple-booking-manager' ); ?></a>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Dashboard page.
 */
function sbm_dashboard_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$bookings = sbm_get_bookings();
	$today = current_time( 'Y-m-d' );
	$month = current_time( 'Y-m' );
	$counts = array( 'total' => count( $bookings ), 'today' => 0, 'month' => 0, 'upcoming' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0 );
	$by_month = array();
	foreach ( $bookings as $booking ) {
		if ( isset( $counts[ $booking->status ] ) ) { $counts[ $booking->status ]++; }
		if ( $booking->booking_date === $today ) { $counts['today']++; }
		if ( 0 === strpos( $booking->booking_date, $month ) ) { $counts['month']++; }
		if ( $booking->booking_date >= $today ) { $counts['upcoming']++; }
		$key = substr( $booking->booking_date, 0, 7 );
		$by_month[ $key ] = isset( $by_month[ $key ] ) ? $by_month[ $key ] + 1 : 1;
	}
	ksort( $by_month );
	$recent = array_slice( array_reverse( $bookings ), 0, 6 );
	?>
	<div class="wrap sbm-admin-wrap">
		<h1><span class="dashicons dashicons-chart-bar"></span> <?php esc_html_e( 'Booking Dashboard', 'simple-booking-manager' ); ?></h1>
		<div class="sbm-admin-stats sbm-dashboard-stats">
			<div class="sbm-stat"><span class="sbm-stat__number"><?php echo absint( $counts['total'] ); ?></span><span class="sbm-stat__label">Total Bookings</span></div>
			<div class="sbm-stat"><span class="sbm-stat__number"><?php echo absint( $counts['today'] ); ?></span><span class="sbm-stat__label">Today</span></div>
			<div class="sbm-stat"><span class="sbm-stat__number"><?php echo absint( $counts['month'] ); ?></span><span class="sbm-stat__label">This Month</span></div>
			<div class="sbm-stat"><span class="sbm-stat__number"><?php echo absint( $counts['upcoming'] ); ?></span><span class="sbm-stat__label">Upcoming</span></div>
		</div>
		<div class="sbm-admin-two-col">
			<div class="sbm-admin-card"><h2>Status Distribution</h2><p>Pending: <?php echo absint( $counts['pending'] ); ?> | Confirmed: <?php echo absint( $counts['confirmed'] ); ?> | Cancelled: <?php echo absint( $counts['cancelled'] ); ?></p></div>
			<div class="sbm-admin-card"><h2>Bookings by Month</h2><?php foreach ( $by_month as $key => $value ) : ?><div class="sbm-analytics-row"><span><?php echo esc_html( $key ); ?></span><strong><?php echo absint( $value ); ?></strong></div><?php endforeach; ?></div>
		</div>
		<div class="sbm-admin-card"><h2>Recent Bookings</h2><table class="widefat striped"><thead><tr><th>Name</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ( $recent as $booking ) : ?><tr><td><?php echo esc_html( $booking->full_name ); ?></td><td><?php echo esc_html( $booking->booking_date . ' ' . substr( $booking->booking_time, 0, 5 ) ); ?></td><td><?php echo wp_kses_post( sbm_status_badge( $booking->status ) ); ?></td><td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'simple-booking-manager', 'action' => 'edit', 'id' => $booking->id ), admin_url( 'admin.php' ) ) ); ?>">Edit</a></td></tr><?php endforeach; ?></tbody></table></div>
		<?php sbm_render_dashboard_booking_form_preview(); ?>
	</div>
	<?php
}

/**
 * Render a non-submitting booking form preview inside the admin dashboard.
 */
function sbm_render_dashboard_booking_form_preview() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$preview = do_shortcode( '[booking_form title="Booking Form Preview"]' );
	$preview = str_replace( 'id="sbm-booking-wrap"', 'id="sbm-booking-preview-wrap"', $preview );
	$preview = str_replace( 'id="sbm-message"', 'id="sbm-preview-message"', $preview );
	$preview = str_replace( 'id="sbm-booking-form" novalidate', 'id="sbm-booking-form-preview" class="sbm-preview-form" novalidate onsubmit="return false;"', $preview );
	$preview = str_replace( 'type="submit" id="sbm-submit-btn"', 'type="button" id="sbm-submit-btn-preview" disabled aria-disabled="true"', $preview );
	$preview = str_replace( 'Book Appointment', 'Preview Only', $preview );
	?>
	<div class="sbm-admin-card sbm-dashboard-form-preview-card">
		<div class="sbm-preview-card-header">
			<div>
				<h2><?php esc_html_e( 'Booking Form Preview', 'simple-booking-manager' ); ?></h2>
				<p><?php esc_html_e( 'Preview how the frontend booking form looks with your current services, custom fields, and custom CSS. This preview does not submit bookings.', 'simple-booking-manager' ); ?></p>
			</div>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=simple-booking-manager-fields' ) ); ?>">
				<?php esc_html_e( 'Manage Custom Fields', 'simple-booking-manager' ); ?>
			</a>
		</div>
		<div class="sbm-dashboard-form-preview">
			<?php echo $preview; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php
}

/**
 * Custom CSS output.
 */
function sbm_print_custom_css() {
	$css = get_option( 'sbm_custom_css', '' );
	if ( '' !== trim( $css ) ) {
		echo '<style id="sbm-custom-css">' . wp_strip_all_tags( $css ) . '</style>';
	}
}
add_action( 'wp_head', 'sbm_print_custom_css', 50 );
add_action( 'admin_head', 'sbm_print_custom_css', 50 );

/**
 * Email templates and custom CSS settings handler/renderer helpers.
 */
function sbm_get_default_email_templates() {
	return array(
		'new_booking_admin' => array( 'subject' => '[{site_name}] New Booking #{booking_id}', 'body' => "A new booking has been created.\n\nName: {customer_name}\nEmail: {email}\nPhone: {phone}\nService: {service}\nDate: {booking_date}\nTime: {booking_time}\nStatus: {status}" ),
		'new_booking_customer' => array( 'subject' => 'Your booking request #{booking_id}', 'body' => "Dear {customer_name},\n\nThank you for your booking request.\n\nService: {service}\nDate: {booking_date}\nTime: {booking_time}\nStatus: {status}\n\nWe will contact you shortly." ),
		'status_changed_customer' => array( 'subject' => 'Booking #{booking_id} status updated', 'body' => "Dear {customer_name},\n\nYour booking status is now: {status}.\n\nService: {service}\nDate: {booking_date}\nTime: {booking_time}" ),
	);
}

function sbm_replace_email_placeholders( $content, $booking ) {
	$replacements = array(
		'{site_name}' => get_bloginfo( 'name' ),
		'{booking_id}' => $booking->id,
		'{customer_name}' => $booking->full_name,
		'{email}' => $booking->email,
		'{phone}' => $booking->phone,
		'{service}' => $booking->service_type,
		'{booking_date}' => $booking->booking_date,
		'{booking_time}' => substr( $booking->booking_time, 0, 5 ),
		'{status}' => $booking->status,
	);
	return strtr( $content, $replacements );
}

function sbm_send_template_email( $template_key, $booking_id, $to ) {
	$booking = sbm_get_booking( $booking_id );
	if ( ! $booking || ! is_email( $to ) ) { return; }
	$templates = get_option( 'sbm_email_templates', sbm_get_default_email_templates() );
	$template = isset( $templates[ $template_key ] ) ? $templates[ $template_key ] : null;
	if ( ! $template ) { return; }
	wp_mail( $to, sbm_replace_email_placeholders( $template['subject'], $booking ), sbm_replace_email_placeholders( $template['body'], $booking ) );
}
