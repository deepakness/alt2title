<?php
/*
Plugin Name: ALT2Title: Image Alt to Title Sync
Description: Syncs image alt texts in media library and replaces the current image titles. Includes bulk processing feature.
Version: 1.1
Author: DeepakNess
Author URI: https://deepakness.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: alt2title-image-alt-to-title-sync
Domain Path: /languages
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Load plugin text domain
function atttc_load_textdomain() {
    load_plugin_textdomain('alt2title-image-alt-to-title-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'atttc_load_textdomain');

// Add menu item under Tools
function atttc_add_admin_menu() {
    add_management_page(
        esc_html__('Alt Text to Title Sync', 'alt2title-image-alt-to-title-sync'),
        esc_html__('Alt to Title Sync', 'alt2title-image-alt-to-title-sync'),
        'manage_options',
        'alt-text-to-title-sync',
        'atttc_admin_page'
    );
}
add_action('admin_menu', 'atttc_add_admin_menu');

// Admin page content
function atttc_admin_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'alt2title-image-alt-to-title-sync'));
    }

    // Process form submission
    if (isset($_POST['atttc_process'])) {
        // Verify nonce
        if (!isset($_POST['atttc_nonce']) || !wp_verify_nonce(wp_unslash($_POST['atttc_nonce']), 'atttc_process_images')) {
            wp_die(esc_html__('Security check failed.', 'alt2title-image-alt-to-title-sync'));
        }

        // Process images
        atttc_process_images();
    }

    // Display the admin page
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Alt Text to Title Sync', 'alt2title-image-alt-to-title-sync'); ?></h1>
        <form method="post" action="">
            <?php wp_nonce_field('atttc_process_images', 'atttc_nonce'); ?>
            <p><?php esc_html_e('Click the button below to sync alt texts to titles for all images in the media library.', 'alt2title-image-alt-to-title-sync'); ?></p>
            <input type="submit" name="atttc_process" class="button button-primary" value="<?php esc_attr_e('Sync Images', 'alt2title-image-alt-to-title-sync'); ?>">
        </form>
    </div>
    <?php
}

// Process images
function atttc_process_images() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
        'post_status' => 'any',
    );

    $images = get_posts($args);
    $processed = 0;

    foreach ($images as $image) {
        $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        
        if (!empty($alt_text)) {
            wp_update_post(array(
                'ID' => $image->ID,
                'post_title' => sanitize_text_field($alt_text),
            ));
            $processed++;
        }
    }

    printf(
        '<div class="updated"><p>%s</p></div>',
        esc_html(sprintf(
            /* translators: %d: number of processed images */
            _n(
                'Processed %d image. Alt text has been copied to title where available.',
                'Processed %d images. Alt texts have been copied to titles where available.',
                $processed,
                'alt2title-image-alt-to-title-sync'
            ),
            $processed
        ))
    );
}

// Add a bulk action to the media library
function atttc_add_bulk_action($bulk_actions) {
    if (current_user_can('manage_options')) {
        $bulk_actions['atttc_bulk_process'] = esc_html__('Sync Alt Text to Title', 'alt2title-image-alt-to-title-sync');
    }
    return $bulk_actions;
}
add_filter('bulk_actions-upload', 'atttc_add_bulk_action');

// Handle the bulk action
function atttc_handle_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'atttc_bulk_process') {
        return $redirect_to;
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to perform this action.', 'alt2title-image-alt-to-title-sync'));
    }

    // Verify nonce
    $nonce = isset($_REQUEST['_wpnonce']) ? wp_unslash($_REQUEST['_wpnonce']) : '';
    if (!wp_verify_nonce($nonce, 'bulk-media')) {
        wp_die(esc_html__('Security check failed.', 'alt2title-image-alt-to-title-sync'));
    }

    $processed = 0;

    foreach ($post_ids as $post_id) {
        // Verify post ID is valid
        $post_id = absint($post_id);
        if (!$post_id) {
            continue;
        }

        // Verify post type
        if (get_post_type($post_id) !== 'attachment') {
            continue;
        }

        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        
        if (!empty($alt_text)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => sanitize_text_field($alt_text),
            ));
            $processed++;
        }
    }

    $redirect_to = add_query_arg('atttc_processed', $processed, $redirect_to);
    return $redirect_to;
}
add_filter('handle_bulk_actions-upload', 'atttc_handle_bulk_action', 10, 3);

// Display admin notice after bulk action
function atttc_admin_notice() {
    if (!isset($_REQUEST['atttc_processed'])) {
        return;
    }

    // Sanitize the processed count
    $processed = absint($_REQUEST['atttc_processed']);
    
    printf(
        '<div class="updated"><p>%s</p></div>',
        esc_html(sprintf(
            /* translators: %d: number of processed images */
            _n(
                'Processed %d image. Alt text has been copied to title where available.',
                'Processed %d images. Alt texts have been copied to titles where available.',
                $processed,
                'alt2title-image-alt-to-title-sync'
            ),
            $processed
        ))
    );
}
add_action('admin_notices', 'atttc_admin_notice');