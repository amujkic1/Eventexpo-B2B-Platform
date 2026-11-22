<?php
/** Compatibility view for callers using the former template path. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo b2b_render_template( 'auth/organizer-signup', compact( 'registration_content' ) );
