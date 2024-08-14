<?php
/*
Plugin Name: ALT2Title: Image Alt to Title Sync
Description: Syncs image alt texts in media library and replaces the current image titles. Includes bulk processing feature.
Version: 1.1
Author: DeepakNess
Author URI: https://deepakness.com
*/

// Add menu item under Tools
function atttc_add_admin_menu() {
    add_management_page('Alt Text to Title Copier', 'Alt to Title Copier', 'manage_options', 'alt-text-to-title-copier', 'atttc_admin_page');
}
add_action('admin_menu', 'atttc_add_admin_menu');

// Admin page content
function atttc_admin_page() {
    ?>
    <div class="wrap">
        <h1>Alt Text to Title Copier</h1>
        <form method="post" action="">
            <?php wp_nonce_field('atttc_process_images', 'atttc_nonce'); ?>
            <p>Click the button below to copy alt texts to titles for all images in the media library.</p>
            <input type="submit" name="atttc_process" class="button button-primary" value="Process Images">
        </form>
    </div>
    <?php

    if (isset($_POST['atttc_process']) && check_admin_referer('atttc_process_images', 'atttc_nonce')) {
        atttc_process_images();
    }
}

// Process images
function atttc_process_images() {
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => -1,
    );

    $images = get_posts($args);
    $processed = 0;

    foreach ($images as $image) {
        $alt_text = get_post_meta($image->ID, '_wp_attachment_image_alt', true);
        
        if (!empty($alt_text)) {
            wp_update_post(array(
                'ID' => $image->ID,
                'post_title' => $alt_text,
            ));
            $processed++;
        }
    }

    echo "<div class='updated'><p>Processed $processed images. Alt texts have been copied to titles where available.</p></div>";
}

// Add a bulk action to the media library
function atttc_add_bulk_action($bulk_actions) {
    $bulk_actions['atttc_bulk_process'] = 'Copy Alt Text to Title';
    return $bulk_actions;
}
add_filter('bulk_actions-upload', 'atttc_add_bulk_action');

// Handle the bulk action
function atttc_handle_bulk_action($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'atttc_bulk_process') {
        return $redirect_to;
    }

    $processed = 0;

    foreach ($post_ids as $post_id) {
        $alt_text = get_post_meta($post_id, '_wp_attachment_image_alt', true);
        
        if (!empty($alt_text)) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_title' => $alt_text,
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
    if (!empty($_REQUEST['atttc_processed'])) {
        $processed = intval($_REQUEST['atttc_processed']);
        echo "<div class='updated'><p>Processed $processed images. Alt texts have been copied to titles where available.</p></div>";
    }
}
add_action('admin_notices', 'atttc_admin_notice');