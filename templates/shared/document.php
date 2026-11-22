<?php
/** Standalone document for app routes; WordPress hooks and admin bar stay intact. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) {
    the_post();
    if ( post_password_required() ) {
        echo get_the_password_form();
    } elseif ( is_singular( 'event' ) ) {
        echo b2b_event_public_profile( get_the_ID() );
    } elseif ( is_singular( 'company' ) ) {
        echo b2b_company_public_profile( get_the_ID() );
    } elseif ( function_exists( 'um_is_core_page' ) && um_is_core_page( 'register' ) ) {
        // Keep the original page content and UM shortcode processing intact.
        echo b2b_render_template( 'auth/organizer-signup', array(
            'registration_content' => apply_filters( 'the_content', get_the_content() ),
        ) );
    } else {
        the_content();
    }
}
?>
<?php wp_footer(); ?>
</body>
</html>
