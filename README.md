# BookFlow Manager

BookFlow Manager is a modern WordPress booking management plugin designed to help website owners create, manage, and customize booking forms easily from the WordPress admin dashboard.

The plugin includes booking form management, custom fields, dashboard previews, email notifications, custom CSS styling, AJAX booking submission, and an improved admin booking workflow.

## Features

### Booking Management

* Add and manage customer bookings
* View booking details from the admin panel
* Edit booking information
* Delete bookings
* Update booking status
* AJAX-powered status updates
* Export bookings as CSV

### Custom Field Manager

BookFlow Manager allows admins to create custom booking form fields without editing code.

Supported custom field features:

* Add custom fields
* Edit existing custom fields
* Delete custom fields
* Set field label
* Set field name
* Select field type
* Set required or optional status
* Add placeholder text
* Add default values
* Add selectable field options
* Control field order

Custom fields are displayed automatically on the frontend booking form and saved with each booking.

### Booking Form Preview

The dashboard includes a booking form preview section where admins can view how the booking form looks before using it on the frontend.

The preview includes:

* Default booking fields
* Custom fields
* Current styling
* Custom CSS preview
* Preview-only submit button

### Custom CSS Editor

Admins can add custom CSS directly from the WordPress dashboard to style the booking form and booking portal pages.

Features:

* Custom CSS input area
* Save CSS option
* Reset CSS option
* Automatically loads saved styles on the frontend

### Email Notifications

BookFlow Manager includes a flexible email notification system.

Supported templates:

* New booking notification
* Booking updated notification
* Booking deleted notification
* Booking status changed notification

Supported placeholders:

* `{booking_id}`
* `{customer_name}`
* `{email}`
* `{phone}`
* `{service}`
* `{booking_date}`
* `{booking_time}`
* `{status}`

Email notifications can be sent to both customers and admins.

### Dashboard Overview

The plugin includes a modern dashboard page with booking statistics and recent booking details.

Dashboard sections include:

* Total bookings
* Pending bookings
* Confirmed bookings
* Cancelled bookings
* Today’s bookings
* This month’s bookings
* Upcoming bookings
* Recent bookings widget
* Booking form preview

### Security

BookFlow Manager follows WordPress security best practices.

Security features include:

* Nonce verification
* Capability checks
* Input sanitization
* Output escaping
* Prepared database queries
* Secure AJAX request handling

## Installation

1. Download the plugin ZIP file.
2. Go to your WordPress admin dashboard.
3. Navigate to **Plugins → Add New**.
4. Click **Upload Plugin**.
5. Choose the plugin ZIP file.
6. Click **Install Now**.
7. Activate the plugin.

After activation, you will see a new **Bookings** menu in the WordPress admin dashboard.

## Usage

### Add a Booking Form to a Page

Use the plugin shortcode on any page or post:

```text
[booking_form]
```

Then publish or update the page.

### Manage Bookings

Go to:

```text
WordPress Admin → Bookings
```

From there, you can view, edit, delete, and manage booking status.

### Manage Custom Fields

Go to:

```text
WordPress Admin → Bookings → Custom Fields
```

From this page, you can add, edit, and delete custom fields for the booking form.

### Preview Booking Form

Go to:

```text
WordPress Admin → Bookings → Dashboard
```

The dashboard includes a booking form preview section showing the current form layout and custom fields.

### Customize Form Styling

Go to:

```text
WordPress Admin → Bookings → Settings → Styling
```

Add your custom CSS and save the settings.

## Recommended Folder Structure

```text
bookflow-manager/
│
├── assets/
│   ├── css/
│   └── js/
│
├── includes/
│   ├── class-booking-manager.php
│   ├── class-custom-fields.php
│   ├── class-email-notifications.php
│   └── class-dashboard.php
│
├── templates/
│   ├── booking-form.php
│   └── admin/
│
├── bookflow-manager.php
├── README.md
└── uninstall.php
```

## Development Roadmap

Planned future improvements:

* Payment gateway integration
* PayHere support
* Stripe support
* PayPal support
* Google Calendar sync
* Outlook Calendar sync
* WhatsApp notifications
* Customer portal
* Booking approval workflow
* PDF invoice generation
* CSV export improvements
* Multi-location booking support
* Multi-vendor booking support
* Advanced analytics dashboard

## Changelog

### Version 1.1.4

* Added dashboard booking form preview
* Improved custom field edit flow
* Fixed AJAX network error handling
* Added automatic database upgrade checks
* Improved custom field database handling
* Added safer admin notices
* Improved frontend booking form compatibility

### Version 1.1.0

* Added custom field manager
* Added dynamic frontend form rendering
* Added custom CSS editor
* Added email template manager
* Added dashboard overview page

### Version 1.0.0

* Initial booking management system
* Booking form shortcode
* Admin booking list
* Booking status management
* Basic email notification support

## License

This project is open-source and can be modified based on your project requirements.

## Author

Developed by Deyan Kavindu.

## Project Goal

The goal of BookFlow Manager is to transform a basic WordPress booking plugin into a premium-level booking management solution with dynamic forms, modern dashboard tools, flexible styling, and advanced notification features.
