<?php
/**
 * Plugin Name: GF Job Submission Handler
 * Description: Automatically creates a private Portal Job post from Gravity Forms submissions and assigns category based on affiliate status. Maps Gravity Forms checkboxes to ACF checkboxes.
 * Version: 1.1.0
 * Author: CREOL Web Team, Katrina Gumerov
 * Author URI: https://creol.ucf.edu/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Deny direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Confirm the creation of portal_job post type
 */
function gf_confirm_portal_job_post_type() {
    if (post_type_exists('portal_job')) {
        return;
    } else {
        // Register the post type
        $labels = array(
            'name'               => 'Portal Jobs',
            'singular_name'      => 'Portal Job',
            'menu_name'          => 'Portal Jobs',
            'name_admin_bar'     => 'Portal Job',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Portal Job',
            'new_item'           => 'New Portal Job',
            'edit_item'          => 'Edit Portal Job',
            'view_item'          => 'View Portal Job',
            'all_items'          => 'All Portal Jobs',
            'search_items'       => 'Search Portal Jobs',
            'parent_item_colon'  => 'Parent Portal Jobs:',
            'not_found'          => 'No portal jobs found.',
            'not_found_in_trash' => 'No portal jobs found in Trash.'
        );

        register_post_type('portal_job', array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'portal_job'),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments'),
        ));
    }
}
add_action('init', 'gf_confirm_portal_job_post_type');

/**
 * Helper function to get mapping of data keyed by input name of Gravity Forms field
 */
function gf_get_map_from_form($entry, $form) {
    $map = array();
    foreach ($form['fields'] as $field) {
        $input_name = rgar($field, 'inputName');
        if ($input_name) {
            $map[$input_name] = rgar($entry, (string)$field['id']);
        }
    }
    return $map;
}

// Handle Gravity Forms submission for form ID 3
add_action('gform_after_submission_3', 'gf_handle_creol_job_submission', 10, 2);
function gf_handle_creol_job_submission($entry, $form) {
    error_log( print_r( $entry, true ) );
    error_log( print_r( $form, true ) );

    $map = gf_get_map_from_form($entry, $form);

    // Get and sanitize basic fields
    $job_title    = sanitize_text_field( $map['job_title'] ?? ''); // names come from GF field inputNames
    $company_name = sanitize_text_field( $map['company'] ?? '');
    $location     = sanitize_text_field( $map['location'] ?? '');
    $description  = wp_kses_post( $map['job_description'] ?? '');
    $apply_link   = esc_url_raw( $map['application_link'] ?? '');
    $contact_email = sanitize_text_field( $map['contact'] ?? '');
    $duration     = intval( $map['job_duration'] ?? 60 ) ?: 60;

    // Get job type from dropdown field
    $job_type = sanitize_text_field( $map['job_type'] ?? '');

    // Get affiliate status from dropdown (Yes/No)
    $is_affiliate = sanitize_text_field( $map['affiliate'] ?? 'No' );


    // Set the post category based on affiliate status
    $category = strtolower($is_affiliate) === 'yes' 
        ? [intval(get_category_by_slug('affiliate-job')->term_id)]
        : [intval(get_category_by_slug('portal-job')->term_id)];
    
    // Create the post
    $post_id = wp_insert_post([
        'post_title'     => $job_title,
        'post_content'   => $description,
        'post_type'      => 'portal_job',
        'post_status'    => 'pending',
        'post_category'  => $category,
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
// register_activation_hook(__FILE__, 'gf_schedule_old_job_cleanup');
// function gf_schedule_old_job_cleanup() {
//     if (!wp_next_scheduled('gf_delete_old_jobs')) {
//         wp_schedule_event(time(), 'daily', 'gf_delete_old_jobs');
//     }
// }

// register_deactivation_hook(__FILE__, 'gf_clear_old_job_cron');
// function gf_clear_old_job_cron() {
//     wp_clear_scheduled_hook('gf_delete_old_jobs');
// }

// add_action('gf_delete_old_jobs', 'gf_delete_old_jobs_callback');
// function gf_delete_old_jobs_callback() {
//     $post_types = ['portal_job', 'jobs'];

//     foreach ($post_types as $post_type) {
//         $posts = get_posts([
//             'post_type'      => $post_type,
//             'posts_per_page' => -1,
//             'post_status'    => 'publish',
//             'fields'         => 'ids',
//         ]);

//         foreach ($posts as $post_id) {
//             $duration = intval(get_post_meta($post_id, 'job_duration', true)) ?: 60;
//             $post_date = get_post_field('post_date', $post_id);
//             $expire_time = strtotime("$post_date +{$duration} days");

//             if (time() > $expire_time) {
//                 wp_delete_post($post_id, true);
//             }
//         }
//     }
// }
