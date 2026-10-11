<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

?>

<main class="b2b-event-page">

    <?php

    while ( have_posts() ) {

        the_post();

        echo b2b_event_public_profile(
            get_the_ID()
        );
    }

    ?>

</main>

<?php

get_footer();