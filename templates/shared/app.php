<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$account_name = $account->display_name ?: $account->user_login;
?>
<div class="ee-app">
    <header class="ee-header">
        <div class="ee-header-in">
            <a class="ee-brand" href="<?php echo esc_url( $navigation['dashboard']['url'] ); ?>"><span class="ee-mark">EE</span><span>EventExpo</span><small>B2B</small></a>
            <button class="ee-icon-button ee-menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" data-ee-menu><?php echo b2b_icon( 'menu' ); ?></button>
            <nav class="ee-nav" aria-label="EventExpo">
                <?php foreach ( $navigation as $key => $item ) : ?>
                    <a href="<?php echo esc_url( $item['url'] ); ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ( is_user_logged_in() ) : ?>
                <a class="ee-user" href="<?php echo esc_url( $profile_url ); ?>" aria-label="My company profile"><span class="ee-account-name"><?php echo esc_html( $account_name ); ?></span><span class="ee-avatar"><?php echo esc_html( b2b_initials( $account_name ) ); ?></span></a>
            <?php else : ?>
                <a class="ee-button ee-button-outline" href="<?php echo esc_url( wp_login_url() ); ?>">Log in</a>
            <?php endif; ?>
        </div>
    </header>
    <div class="ee-main">
        <?php echo $content; ?>
    </div>
    <footer class="ee-footer"><div class="ee-footer-in"><span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> EventExpo B2B</span><nav aria-label="Footer"><a href="<?php echo esc_url( $navigation['companies']['url'] ); ?>">Companies</a><a href="<?php echo esc_url( $profile_url ); ?>">My company</a><?php if ( get_privacy_policy_url() ) : ?><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy</a><?php endif; ?></nav></div></footer>
</div>
