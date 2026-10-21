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

    $template_path = __DIR__ . '/../templates/' . $template . '.php';

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

    foreach ( $fields as $field ) {
        if ( ! is_array( $field ) || empty( $field['name'] ) ) {
            continue;
        }

        $name = sanitize_key( $field['name'] );
        $rows[] = array(
            'label' => isset( $field['label'] ) ? $field['label'] : $name,
            'value' => isset( $answers[ $name ] ) && is_scalar( $answers[ $name ] )
                ? (string) $answers[ $name ]
                : '',
        );
    }

    return $rows;
}
