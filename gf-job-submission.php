<?php
/**
 * Plugin Name: GF Job Submission Handler
 * Description: Automatically creates a pending Portal Job or Affiliate Job post from Gravity Forms submissions. Routes to the correct custom post type based on whether the submitter checks the CREOL Industrial Affiliate box.
 * Version: 1.0
 * Author: CREOL Web Team, Katrina Gumerov
 * Author URI: https://creol.ucf.edu/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */
add_action('gform_after_submission_3', 'gf_handle_creol_job_submission', 10, 2);
function gf_handle_creol_job_submission($entry, $form) {
    // Sanitize form field values with correct field IDs
    $job_title    = sanitize_text_field(rgar($entry, '6'));
    $company_name = sanitize_text_field(rgar($entry, '4'));
    $location     = sanitize_text_field(rgar($entry, '5'));
    // Get job type and convert to ACF-compatible format
    $job_type_raw = rgar($entry, '7');
    $job_type = [];
    if (!empty($job_type_raw)) {
        // Convert Gravity Forms checkbox format to ACF array format
        foreach ($job_type_raw as $key => $value) {
            if ($value) {
                $job_type[] = $key;
            }
        }
    }

    $description  = wp_kses_post(rgar($entry, '8'));
    $apply_link   = esc_url_raw(rgar($entry, '9'));
    $contact_email = sanitize_text_field(rgar($entry, '10'));

    // Convert affiliate checkbox to ACF-compatible format
    $is_affiliate_raw = rgar($entry, '11');
    $is_affiliate = !empty($is_affiliate_raw) ? 'yes' : 'no';  // ACF typically uses yes/no for checkboxes

    $duration     = intval(rgar($entry, '12'));

    // Set default duration if not provided
    if (!$duration) {
        $duration = 60; // default to 60 days
    }

    // Determine category based on affiliate status
    if ($is_affiliate) {
        $categories = [get_cat_ID('Affiliate Job')];
    } else {
        $categories = [get_cat_ID('Portal Job')];
    }

    // Debug logging
    error_log('Job Type Raw: ' . print_r($job_type_raw, true));
    error_log('Job Type Processed: ' . print_r($job_type, true));
    error_log('Affiliate Raw: ' . print_r($is_affiliate_raw, true));
    error_log('Affiliate Processed: ' . $is_affiliate);

    // Create the post
    $post_id = wp_insert_post(array(
        'post_title'     => $job_title,
        'post_content'   => $description,
        'post_type'      => 'portal_job',
        'post_status'    => 'pending',
        'post_category'  => $categories,
    ));

    if (is_wp_error($post_id)) {
        error_log('Error creating portal job post: ' . $post_id->get_error_message());
        return;
    }

    // Save custom meta fields
    update_post_meta($post_id, 'company_name', $company_name);
    update_post_meta($post_id, 'location', $location);
    // Save job type as ACF field
    update_field('job_type', $job_type, $post_id);
    update_post_meta($post_id, 'apply_link', $apply_link);
    update_post_meta($post_id, 'contact', $contact_email);
    update_field('is_affiliate', $is_affiliate, $post_id);
    update_post_meta($post_id, 'job_duration', $duration);
}

// Set the AE Post Template on initial creation
add_action('save_post_portal_job', 'gf_set_portal_job_template', 10, 3);
function gf_set_portal_job_template($post_id, $post, $update) {
    // Only set on first creation, not on update
    if ($update) return;

    // Set the AE Post Template to your template's post ID
    update_post_meta($post_id, 'ae_template', 30479);
}


// 1. Register the cron event if it’s not already scheduled
register_activation_hook(__FILE__, 'gf_schedule_old_job_cleanup');
function gf_schedule_old_job_cleanup() {
    if (!wp_next_scheduled('gf_delete_old_jobs')) {
        wp_schedule_event(time(), 'daily', 'gf_delete_old_jobs');
    }
}

// 2. Clear it when the plugin is deactivated
register_deactivation_hook(__FILE__, 'gf_clear_old_job_cron');
function gf_clear_old_job_cron() {
    wp_clear_scheduled_hook('gf_delete_old_jobs');
}

// 3. Hook into the cron to delete old job posts
add_action('gf_delete_old_jobs', 'gf_delete_old_jobs_callback');
function gf_delete_old_jobs_callback() {
    $post_types = ['portal_job', 'jobs'];

    foreach ($post_types as $post_type) {
        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        foreach ($posts as $post_id) {
            $duration = intval(get_post_meta($post_id, 'duration', true)); // meta key you store
            if (!$duration) {
                $duration = 60; // default to 60 days
            }

            $post_date = get_post_field('post_date', $post_id);
            $expire_time = strtotime($post_date . " +{$duration} days");

            if (time() > $expire_time) {
                wp_delete_post($post_id, true);
            }
        }
    }
}
