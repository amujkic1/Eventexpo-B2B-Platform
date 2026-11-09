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

require_once plugin_dir_path( __FILE__ ) . 'includes/shared/presentation.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/companies/companies.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/registrations/registration.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/dashboard/dashboard.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/companies/company-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/companies/companies-directory.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/companies/company-public-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/events/events.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/events/event-public-profile.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/events/manage-event.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/registrations/event-registration-fields.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/registrations/event-registrations.php';
