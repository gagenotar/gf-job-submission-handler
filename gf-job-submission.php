<?php
/**
 * Plugin Name: GF Job Submission Handler
 * Description: Automatically creates a pending Portal Job post from Gravity Forms submissions and assigns category based on affiliate status. Maps Gravity Forms checkboxes to ACF checkboxes.
 * Version: 1.0
 * Author: CREOL Web Team, Katrina Gumerov
 * Author URI: https://creol.ucf.edu/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

add_action('gform_after_submission_3', 'gf_handle_creol_job_submission', 10, 2);
function gf_handle_creol_job_submission($entry, $form) {
    // Get and sanitize basic fields
    $job_title    = sanitize_text_field(rgar($entry, '6'));
    $company_name = sanitize_text_field(rgar($entry, '4'));
    $location     = sanitize_text_field(rgar($entry, '5'));
    $description  = wp_kses_post(rgar($entry, '8'));
    $apply_link   = esc_url_raw(rgar($entry, '9'));
    $contact_email = sanitize_text_field(rgar($entry, '10'));
    $duration     = intval(rgar($entry, '12')) ?: 60;

    // Get job type from dropdown field
    $job_type = sanitize_text_field(rgar($entry, '7'));
    
    // Debug job type
    error_log('Job Type from form: ' . $job_type);

    // Get affiliate status from dropdown (Yes/No)
    $is_affiliate = rgar($entry, '11');
    
    // Debug affiliate status
    error_log('Affiliate status from form: ' . print_r($is_affiliate, true));

    // Set the post category based on affiliate status
    $categories = strtolower($is_affiliate) === 'yes' 
        ? [get_cat_ID('Affiliate Job')]
        : [get_cat_ID('Portal Job')];

    // Create the post
    $post_id = wp_insert_post([
        'post_title'     => $job_title,
        'post_content'   => $description,
        'post_type'      => 'portal_job',
        'post_status'    => 'pending',
        'post_category'  => $categories,
    ]);

    if (is_wp_error($post_id)) {
        error_log('Error creating portal job post: ' . $post_id->get_error_message());
        return;
    }

    // Save ACF + meta fields
    update_field('company_name', $company_name, $post_id);
    update_field('location', $location, $post_id);
    update_field('job_type', $job_type, $post_id);
    update_field('is_affiliate', $is_affiliate, $post_id);
    update_field('apply_link', $apply_link, $post_id);
    update_field('contact', $contact_email, $post_id);
    update_field('job_duration', $duration, $post_id); // assuming you use ACF here too

    // Set AE Template
    update_post_meta($post_id, 'ae_template', 30479);
}

// Set AE template only on first creation
add_action('save_post_portal_job', 'gf_set_portal_job_template', 10, 3);
function gf_set_portal_job_template($post_id, $post, $update) {
    if ($update) return;
    update_post_meta($post_id, 'ae_template', 30479);
}

// === CRON FOR CLEANUP === //
register_activation_hook(__FILE__, 'gf_schedule_old_job_cleanup');
function gf_schedule_old_job_cleanup() {
    if (!wp_next_scheduled('gf_delete_old_jobs')) {
        wp_schedule_event(time(), 'daily', 'gf_delete_old_jobs');
    }
}

register_deactivation_hook(__FILE__, 'gf_clear_old_job_cron');
function gf_clear_old_job_cron() {
    wp_clear_scheduled_hook('gf_delete_old_jobs');
}

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
            $duration = intval(get_post_meta($post_id, 'job_duration', true)) ?: 60;
            $post_date = get_post_field('post_date', $post_id);
            $expire_time = strtotime("$post_date +{$duration} days");

            if (time() > $expire_time) {
                wp_delete_post($post_id, true);
            }
        }
    }
}
