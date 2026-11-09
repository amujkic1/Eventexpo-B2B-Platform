<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$location_values = array( 'country' => $country, 'city' => $city, 'address' => $address );
?>
<div class="ee-crumb"><a href="<?php echo esc_url( b2b_page_url( 'b2b_dashboard', 'b2b-dashboard' ) ); ?>">Dashboard</a><?php echo b2b_icon( 'chevron-right' ); ?><span>My company</span></div>
<div class="ee-page-heading"><div><h1>My company</h1><p><?php echo esc_html( $company->post_title ); ?></p></div><a class="ee-button ee-button-outline" href="<?php echo esc_url( get_permalink( $company_id ) ); ?>"><?php echo b2b_icon( 'external-link' ); ?>View public profile</a></div>
<?php if ( $message ) : ?><div class="ee-notice" role="status"><?php echo esc_html( $message ); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="ee-form">
    <?php wp_nonce_field( 'b2b_update_company', 'b2b_company_nonce' ); ?>
    <div class="ee-grid ee-two-column">
        <div class="ee-stack">
            <section class="ee-form-section"><h2>Company information</h2><div class="ee-fields">
                <div class="ee-field ee-field-full"><label for="company_name">Company name <span aria-hidden="true">*</span></label><input type="text" id="company_name" name="company_name" value="<?php echo esc_attr( $company->post_title ); ?>" required></div>
                <div class="ee-field ee-field-full"><label for="company_description">Description</label><textarea id="company_description" name="company_description" rows="6"><?php echo esc_textarea( $company->post_content ); ?></textarea></div>
                <div class="ee-field"><label for="industry">Industry</label><select id="industry" name="industry"><option value="">Select industry</option><?php if ( ! is_wp_error( $industries ) ) : foreach ( $industries as $industry ) : ?><option value="<?php echo esc_attr( $industry->term_id ); ?>" <?php selected( $selected_industry, $industry->term_id ); ?>><?php echo esc_html( $industry->name ); ?></option><?php endforeach; endif; ?></select></div>
                <div class="ee-field"><label for="website">Website</label><input type="url" id="website" name="website" value="<?php echo esc_attr( $website ); ?>" placeholder="https://example.com"></div>
            </div></section>
            <section class="ee-form-section"><h2>Location</h2><div class="ee-fields">
                <?php foreach ( array( 'country' => 'Country', 'city' => 'City', 'address' => 'Address' ) as $field => $label ) : ?>
                    <div class="ee-field <?php echo 'address' === $field ? 'ee-field-full' : ''; ?>"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label><input type="text" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $location_values[ $field ] ); ?>"></div>
                <?php endforeach; ?>
            </div></section>
            <section class="ee-form-section"><h2>Contact information</h2><div class="ee-fields">
                <div class="ee-field"><label for="email">Company email</label><input type="email" id="email" name="email" value="<?php echo esc_attr( $email ); ?>"></div>
                <div class="ee-field"><label for="phone">Phone</label><input type="text" id="phone" name="phone" value="<?php echo esc_attr( $phone ); ?>"></div>
            </div></section>
        </div>
        <aside class="ee-stack"><section class="ee-card"><h2>Company logo</h2><div class="ee-upload" style="margin-top:20px"><div class="ee-logo ee-logo-large" data-ee-logo-preview><?php echo has_post_thumbnail( $company_id ) ? get_the_post_thumbnail( $company_id, 'medium' ) : esc_html( b2b_initials( $company->post_title ) ); ?></div><div class="ee-field"><label for="company_logo">Logo image</label><input type="file" id="company_logo" name="company_logo" accept="image/*" data-ee-logo-input></div></div></section></aside>
    </div>
    <div class="ee-form-actions"><a class="ee-button ee-button-subtle" href="<?php echo esc_url( b2b_page_url( 'b2b_my_company', 'my-company' ) ); ?>">Cancel</a><button class="ee-button" type="submit"><?php echo b2b_icon( 'save' ); ?>Save changes</button></div>
</form>
