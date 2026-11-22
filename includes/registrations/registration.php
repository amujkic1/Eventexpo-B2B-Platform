<?php
/** Compatibility loader for integrations using the former account-registration path. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once dirname( __DIR__ ) . '/companies/company-service.php';
require_once dirname( __DIR__ ) . '/auth/organizer-auth.php';
