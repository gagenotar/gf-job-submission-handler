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


 add_action('gform_after_submission_2', 'gf_handle_creol_job_submission', 10, 2);
function gf_handle_creol_job_submission($entry, $form) {
    // Replace field IDs with your actual form field IDs
    $job_title        = rgar($entry, '1'); // Job Title
    $company_name     = rgar($entry, '2'); // Company
    $location         = rgar($entry, '3'); // Location
    $job_type         = rgar($entry, '4'); // Job Type (Checkboxes)
    $description      = rgar($entry, '5'); // Job Description
    $apply_link       = rgar($entry, '6'); // Link to Apply
    $contact_email    = rgar($entry, '7'); // Contact
    $is_affiliate     = rgar($entry, '8'); // Affiliate checkbox
    $duration         = rgar($entry, '9'); // Duration dropdown

    // Determine post type based on checkbox
    $post_type = empty($is_affiliate) ? 'portal_jobs' : 'jobs';

    // Create the post
    $post_id = wp_insert_post(array(
        'post_title'   => $job_title,
        'post_content' => $description,
        'post_type'    => $post_type,
        'post_status'  => 'pending',
    ));

    // Save additional meta
    update_post_meta($post_id, 'company', $company_name);
    update_post_meta($post_id, 'location', $location);
    update_post_meta($post_id, 'job_type', $job_type);
    update_post_meta($post_id, 'apply_link', $apply_link);
    update_post_meta($post_id, 'contact', $contact_email);
    update_post_meta($post_id, 'duration', $duration);
    update_post_meta($post_id, 'is_affiliate', !empty($is_affiliate) ? '1' : '0');
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
    $post_types = ['portal_jobs', 'jobs'];

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
