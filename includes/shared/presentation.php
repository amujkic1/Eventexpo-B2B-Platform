<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render a plugin template with data supplied by its handler.
 */
function b2b_render_template( $template, array $context = array() ) {

    if ( ! preg_match( '/\A[a-z0-9]+(?:[-\/][a-z0-9]+)*\z/', $template ) ) {
        throw new InvalidArgumentException( 'Invalid template name.' );
    }

    $template_path = dirname( __DIR__, 2 ) . '/templates/' . $template . '.php';

    if ( ! is_file( $template_path ) ) {
        throw new InvalidArgumentException( 'Template not found: ' . $template );
    }

    extract( $context, EXTR_SKIP );

    $buffer_level = ob_get_level();
    ob_start();

    try {
        require $template_path;
        return ob_get_clean();
    } catch ( Throwable $error ) {
        while ( ob_get_level() > $buffer_level ) {
            ob_end_clean();
        }

        throw $error;
    }
}

/**
 * Prepare registration answers for both admin and frontend templates.
 */
function b2b_registration_answer_rows( array $fields, $answers ) {

    $rows = array();

    if ( ! is_array( $answers ) ) {
        return $rows;
    }

    $seen = array();
    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            continue;
        }

        $name = sanitize_key( $field['name'] );
        if ( ! array_key_exists( $name, $answers ) ) { continue; }
        $seen[ $name ] = true;
        $rows[] = array(
            'label' => isset( $field['label'] ) ? $field['label'] : $name,
            'value' => isset( $answers[ $name ] ) && is_scalar( $answers[ $name ] )
                ? (string) $answers[ $name ]
                : '',
        );
    }

    // Legacy or externally removed field definitions must not hide stored answers.
    foreach ( $answers as $name => $value ) {
        if ( ! isset( $seen[ $name ] ) && is_scalar( $value ) ) {
            $rows[] = array( 'label' => (string) $name . ' (historical field)', 'value' => (string) $value );
        }
    }

    return $rows;
}

function b2b_asset_url( $file ) {
    return plugins_url( 'assets/' . $file, dirname( __DIR__, 2 ) . '/eventexpo-b2b-core.php' );
}

function b2b_enqueue_assets() {
    wp_enqueue_style( 'eventexpo-b2b', b2b_asset_url( 'css/ui.css' ), array(), filemtime( dirname( __DIR__, 2 ) . '/assets/css/ui.css' ) );
    wp_enqueue_style( 'eventexpo-fonts', 'https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@400;500;600;700&display=swap', array(), null );
    wp_enqueue_style( 'eventexpo-global', b2b_asset_url( 'css/global.css' ), array( 'eventexpo-b2b', 'eventexpo-fonts' ), filemtime( dirname( __DIR__, 2 ) . '/assets/css/global.css' ) );
    wp_enqueue_script( 'eventexpo-b2b', b2b_asset_url( 'js/ui.js' ), array(), filemtime( dirname( __DIR__, 2 ) . '/assets/js/ui.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'b2b_enqueue_assets' );

/** Identify app routes by their content, so custom page slugs also work. */
function b2b_is_app_page() {
    if ( is_singular( array( 'event', 'company' ) ) ) {
        return true;
    }
    if ( ! is_page() ) {
        return false;
    }
    $page = get_queried_object();
    if ( ! $page instanceof WP_Post ) {
        return false;
    }
    foreach ( array( 'b2b_dashboard', 'b2b_companies_directory', 'b2b_my_company', 'b2b_manage_event' ) as $shortcode ) {
        if ( has_shortcode( $page->post_content, $shortcode ) ) {
            return true;
        }
    }
    return false;
}

/** Keep theme headers, page titles and content wrappers outside the app. */
function b2b_app_page_template( $template ) {
    return b2b_is_app_page()
        ? dirname( __DIR__, 2 ) . '/templates/shared/document.php'
        : $template;
}
add_filter( 'template_include', 'b2b_app_page_template', 99 );

function b2b_app_body_class( $classes ) {
    if ( b2b_is_app_page() ) {
        $classes[] = 'ee-app-page';
    }
    return $classes;
}
add_filter( 'body_class', 'b2b_app_body_class' );

/**
 * Resolve navigation against published shortcode pages, including custom slugs.
 */
function b2b_page_url( $shortcode, $fallback ) {
    static $urls = array();
    if ( 'b2b_manage_event' === $shortcode ) {
        $event_path = apply_filters( 'b2b_manage_event_page_path', 'manage-event' );
        if ( 'manage-event' !== $event_path ) {
            return apply_filters( 'b2b_page_url', home_url( '/' . trailingslashit( $event_path ) ), $shortcode );
        }
    }
    if ( ! isset( $urls[ $shortcode ] ) ) {
        $urls[ $shortcode ] = home_url( '/' . $fallback . '/' );
        $pages = get_posts( array(
            'post_type' => 'page', 'post_status' => 'publish',
            'posts_per_page' => -1, 's' => '[' . $shortcode,
        ) );
        foreach ( $pages as $page ) {
            if ( has_shortcode( $page->post_content, $shortcode ) ) {
                $urls[ $shortcode ] = get_permalink( $page->ID );
                break;
            }
        }
    }
    return apply_filters( 'b2b_page_url', $urls[ $shortcode ], $shortcode );
}

function b2b_render_app( $template, array $context = array(), $active = '' ) {
    $content = b2b_render_template( $template, $context );
    $account = wp_get_current_user();
    $navigation = array(
        'dashboard' => array( 'label' => 'Dashboard', 'url' => b2b_page_url( 'b2b_dashboard', 'b2b-dashboard' ) ),
        'companies' => array( 'label' => 'Companies', 'url' => b2b_page_url( 'b2b_companies_directory', 'companies' ) ),
        'events' => array( 'label' => 'My events', 'url' => b2b_page_url( 'b2b_manage_event', 'manage-event' ) ),
    );
    $profile_url = b2b_page_url( 'b2b_my_company', 'my-company' );
    return b2b_render_template( 'shared/app', compact( 'content', 'account', 'navigation', 'profile_url', 'active' ) );
}

function b2b_initials( $name ) {
    $words = preg_split( '/\s+/u', trim( wp_strip_all_tags( $name ) ), -1, PREG_SPLIT_NO_EMPTY );
    $initials = '';
    if ( 1 === count( $words ) ) {
        $initials = function_exists( 'mb_substr' ) ? mb_substr( $words[0], 0, 2 ) : substr( $words[0], 0, 2 );
        return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );
    }
    foreach ( array_slice( $words, 0, 2 ) as $word ) {
        $initials .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
    }
    return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $initials ) : strtoupper( $initials );
}

function b2b_icon( $name ) {
    $icons = array( 'arrow-right', 'arrow-left', 'building-2', 'calendar-days', 'check', 'chevron-right', 'external-link', 'globe', 'mail', 'map-pin', 'menu', 'phone', 'plus', 'rotate-ccw', 'save', 'search', 'settings-2', 'users', 'x' );
    if ( ! in_array( $name, $icons, true ) ) {
        return '';
    }
    return '<svg class="ee-icon" width="18" height="18" aria-hidden="true" focusable="false"><use href="' . esc_url( b2b_asset_url( 'icons/lucide.svg' ) . '#' . $name ) . '"></use></svg>';
}

function b2b_date_parts( $value ) {
    $date = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $value );
    if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
        return array( 'month' => 'TBA', 'day' => '-', 'label' => 'Date to be announced' );
    }
    return array( 'month' => $date->format( 'M' ), 'day' => $date->format( 'j' ), 'label' => $date->format( 'j M Y' ) );
}

function b2b_status_label( $status ) {
    return 'publish' === $status ? 'Published' : ucfirst( $status );
}
