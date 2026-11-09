<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

?>

<main class="b2b-company-page">

    <?php

    while ( have_posts() ) {

        the_post();

        echo b2b_company_public_profile(
            get_the_ID()
        );
    }

    ?>

</main>

<?php

get_footer();