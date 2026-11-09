<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$profile_url = b2b_page_url( 'b2b_my_company', 'my-company' );
$events_url = b2b_page_url( 'b2b_manage_event', 'manage-event' );
$completion = (int) round( count( array_filter( $profile_checks ) ) / count( $profile_checks ) * 100 );
?>
<div class="ee-page-heading">
    <div><h1>Welcome, <?php echo esc_html( $user->first_name ?: $user->display_name ); ?></h1><p><?php echo esc_html( $company_title ?: 'Your EventExpo workspace' ); ?><?php if ( $company_industry ) : ?> &middot; <?php echo esc_html( $company_industry ); ?><?php endif; ?></p></div>
    <?php if ( $company_id ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( $profile_url ); ?>">Edit company profile</a><?php endif; ?>
</div>
<?php if ( $company_id ) : ?>
<div class="ee-grid ee-two-column">
    <div class="ee-stack">
        <?php if ( $next_event ) : $date = b2b_date_parts( $event_dates[ $next_event->ID ] ); ?>
        <article class="ee-card ee-row ee-event-feature">
            <div class="ee-date"><span><?php echo esc_html( $date['month'] ); ?></span><strong><?php echo esc_html( $date['day'] ); ?></strong></div>
            <div class="ee-grow"><div class="ee-kicker">Next event</div><h2><?php echo esc_html( $next_event->post_title ); ?></h2><div class="ee-meta"><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $next_event_data['location'], $next_event_data['city'], $next_event_data['country'] ) ) ) ); ?></span></div></div>
            <a class="ee-button" href="<?php echo esc_url( get_permalink( $next_event->ID ) ); ?>">Open event</a>
        </article>
        <?php endif; ?>
        <section class="ee-panel">
            <div class="ee-panel-heading"><h2>Your events</h2><a href="<?php echo esc_url( $events_url ); ?>">View all <?php echo b2b_icon( 'arrow-right' ); ?></a></div>
            <?php if ( $events ) : ?>
            <div class="ee-table-scroll"><table class="ee-table"><thead><tr><th>Event</th><th>Date</th><th>Status</th><th><span class="screen-reader-text">Actions</span></th></tr></thead><tbody>
            <?php foreach ( $events as $event ) : $date = b2b_date_parts( $event_dates[ $event->ID ] ); ?>
                <tr><td><strong><?php echo esc_html( $event->post_title ); ?></strong></td><td><?php echo esc_html( $date['label'] ); ?></td><td><span class="ee-badge ee-badge-<?php echo esc_attr( $event->post_status ); ?>"><?php echo esc_html( b2b_status_label( $event->post_status ) ); ?></span></td><td><a href="<?php echo esc_url( b2b_get_manage_event_url( $event->ID ) ); ?>">Manage <?php echo b2b_icon( 'chevron-right' ); ?></a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'calendar-days' ); ?><h2>No events yet</h2><p>Your company's events will appear here.</p></div><?php endif; ?>
        </section>
    </div>
    <aside class="ee-stack">
        <section class="ee-card"><h2>Profile completeness</h2><div class="ee-progress-value"><?php echo esc_html( $completion ); ?>%</div><progress class="ee-progress" value="<?php echo esc_attr( $completion ); ?>" max="100" aria-label="Company profile completeness"></progress><ul class="ee-checklist">
        <?php foreach ( $profile_checks as $label => $complete ) : ?><li><?php echo b2b_icon( $complete ? 'check' : 'plus' ); ?><span><?php echo esc_html( $label ); ?></span><span class="screen-reader-text"><?php echo $complete ? 'Complete' : 'Incomplete'; ?></span></li><?php endforeach; ?>
        </ul></section>
        <section class="ee-card"><h2>Your company</h2><p class="ee-muted" style="margin:10px 0 18px"><?php echo esc_html( $company_title ); ?></p><a class="ee-button ee-button-outline" href="<?php echo esc_url( get_permalink( $company_id ) ); ?>">View public profile <?php echo b2b_icon( 'arrow-right' ); ?></a></section>
    </aside>
</div>
<?php else : ?><div class="ee-empty"><?php echo b2b_icon( 'building-2' ); ?><h2>No company connected</h2><p>No company profile is connected to your account.</p></div><?php endif; ?>
