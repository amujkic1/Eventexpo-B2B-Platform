<?php

/**
 * Plugin Name: EventExpo B2B Core
 * Description: Core functionality for the EventExpo B2B platform.
 * Version: 1.0.0
 * Author: EventExpo
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/companies.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/registration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/company-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/companies-directory.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/company-public-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/events.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/event-public-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/manage-event.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/event-registration-fields.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/event-registrations.php';