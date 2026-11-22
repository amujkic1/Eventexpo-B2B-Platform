<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$date = b2b_date_parts( $data['start_date'] );
$approved = $registration_results['counts']['approved'];
$pending = $registration_results['counts']['pending'];
?>
<div class="ee-crumb"><a href="<?php echo esc_url( b2b_page_url( 'b2b_manage_event', 'manage-event' ) ); ?>">My events</a><?php echo b2b_icon( 'chevron-right' ); ?><span><?php echo esc_html( $event->post_title ); ?></span></div>
<div class="ee-page-heading"><div><h1><?php echo esc_html( $event->post_title ); ?></h1><div class="ee-meta"><span><?php echo b2b_icon( 'calendar-days' ); ?><?php echo esc_html( $date['label'] ); ?></span><span><?php echo b2b_icon( 'map-pin' ); ?><?php echo esc_html( implode( ', ', array_filter( array( $data['location'], $data['city'], $data['country'] ) ) ) ?: 'Location to be announced' ); ?></span><span class="ee-badge ee-badge-<?php echo esc_attr( $event->post_status ); ?>"><?php echo esc_html( b2b_status_label( $event->post_status ) ); ?></span></div></div><div class="ee-actions"><?php if ( current_user_can( 'manage_options' ) ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( get_edit_post_link( $event_id ) ); ?>">Review in WordPress Admin</a><?php endif; ?><?php if ( 'publish' === $event->post_status ) : ?><a class="ee-button ee-button-outline" href="<?php echo esc_url( get_permalink( $event_id ) ); ?>"><?php echo b2b_icon( 'external-link' ); ?>View event</a><?php endif; ?></div></div>
<?php if ( $message ) : ?><div class="ee-notice <?php echo $field_failure ? 'ee-notice-error' : ''; ?>" role="<?php echo $field_failure ? 'alert' : 'status'; ?>"><?php echo esc_html( $message ); ?></div><?php endif; ?>
<?php if ( ! $can_edit_event ) : ?>
<p class="ee-notice" role="status">This event is pending approval. Event details and registration fields are locked while EventExpo reviews it. You can edit again if it is returned to draft.</p>
<?php elseif ( 'draft' === $event->post_status ) : ?>
<form method="post" class="ee-card ee-form" style="margin-bottom:24px">
    <?php wp_nonce_field( 'b2b_submit_event_' . $event_id, 'b2b_lifecycle_nonce' ); ?>
    <p>Save your event details and registration fields before submitting. Editing will be locked during review.</p>
    <button class="ee-button" type="submit" name="b2b_submit_event" value="1">Submit for approval</button>
</form>
<?php endif; ?>
<div class="ee-stats"><div class="ee-stat"><span>Total registrations</span><strong><?php echo esc_html( $registration_results['counts']['total'] ); ?></strong></div><div class="ee-stat"><span>Approved</span><strong><?php echo esc_html( $approved ); ?></strong></div><div class="ee-stat"><span>Awaiting approval</span><strong><?php echo esc_html( $pending ); ?></strong></div></div>
<div data-ee-tabs>
    <div class="ee-tabs" role="tablist" aria-label="Event management">
        <button type="button" id="ee-registrations-tab" role="tab" aria-controls="ee-registrations" aria-selected="true">Registrations <span class="ee-count"><?php echo esc_html( $registration_results['counts']['total'] ); ?></span></button>
        <button type="button" id="ee-fields-tab" role="tab" aria-controls="ee-registration-fields" aria-selected="false">Registration form <span class="ee-count"><?php echo esc_html( count( $registration_fields ) ); ?></span></button>
        <button type="button" id="ee-details-tab" role="tab" aria-controls="ee-event-details" aria-selected="false">Event details</button>
    </div>
    <section id="ee-registrations" role="tabpanel" aria-labelledby="ee-registrations-tab">
        <?php echo b2b_render_template( 'registrations/event-registrations', compact( 'event_id', 'registration_rows', 'registration_results', 'registration_search', 'registration_status', 'registration_url', 'registration_page_id' ) ); ?>
    </section>
    <section id="ee-registration-fields" role="tabpanel" aria-labelledby="ee-fields-tab">
        <?php echo b2b_render_template( 'registrations/event-registration-fields', compact( 'event_id', 'registration_fields', 'field_failure', 'can_edit_fields' ) ); ?>
    </section>
    <section id="ee-event-details" role="tabpanel" aria-labelledby="ee-details-tab">
        <form method="post" action="#ee-event-details" class="ee-form">
            <?php wp_nonce_field( 'b2b_save_event_' . $event_id, 'b2b_event_nonce' ); ?>
            <input type="hidden" name="b2b_save_event" value="1">
            <fieldset class="ee-fields" <?php disabled( ! $can_edit_event ); ?> style="border:0;padding:0;margin:0;min-width:0">
                <div class="ee-field ee-field-full"><label for="event_title">Event title <span aria-hidden="true">*</span></label><input type="text" id="event_title" name="event_title" value="<?php echo esc_attr( $event->post_title ); ?>" required></div>
                <div class="ee-field ee-field-full"><label for="event_description">Description</label><textarea id="event_description" name="event_description" rows="7"><?php echo esc_textarea( $event->post_content ); ?></textarea></div>
                <div class="ee-field ee-field-full"><label for="event_type">Event type</label><select id="event_type" name="event_type"><option value="">Select event type</option><?php foreach ( $event_types as $event_type ) : ?><option value="<?php echo esc_attr( $event_type->term_id ); ?>" <?php selected( $selected_event_type, $event_type->term_id ); ?>><?php echo esc_html( $event_type->name ); ?></option><?php endforeach; ?></select></div>
                <?php foreach ( array( 'start_date' => 'Start date', 'end_date' => 'End date', 'location' => 'Venue', 'city' => 'City', 'country' => 'Country' ) as $field => $label ) : ?>
                    <div class="ee-field"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label><input type="<?php echo in_array( $field, array( 'start_date', 'end_date' ), true ) ? 'date' : 'text'; ?>" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $data[ $field ] ); ?>" <?php if ( 'country' === $field ) : ?>list="ee-event-countries" autocomplete="country-name"<?php endif; ?>></div>
                <?php endforeach; ?>
                <?php echo b2b_country_datalist( 'ee-event-countries' ); ?>
            </fieldset>
            <?php if ( $can_edit_event ) : ?><div class="ee-form-actions"><button type="submit" class="ee-button"><?php echo b2b_icon( 'save' ); ?>Save changes</button></div><?php endif; ?>
        </form>
    </section>
</div>
