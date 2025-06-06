<?php
/**
 * User Profile Image Management for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Handle profile picture upload for user
 */
function vqr_handle_profile_picture_upload($user_id, $uploaded_file) {
    // Include required WordPress files for upload handling
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }
    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    }
    if (!function_exists('wp_insert_attachment')) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
    }
    
    // Validate user
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('invalid_user', 'User not found.');
    }
    
    // Validate file upload
    if (!isset($uploaded_file['tmp_name']) || empty($uploaded_file['tmp_name'])) {
        return new WP_Error('no_file', 'No file was uploaded.');
    }
    
    // Check for upload errors
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        return new WP_Error('upload_error', 'File upload failed.');
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = wp_check_filetype($uploaded_file['name']);
    
    if (!in_array($uploaded_file['type'], $allowed_types)) {
        return new WP_Error('invalid_type', 'Only JPEG, PNG, GIF, and WebP images are allowed.');
    }
    
    // Validate file size (2MB limit)
    if ($uploaded_file['size'] > 2 * 1024 * 1024) {
        return new WP_Error('file_too_large', 'Image file size must be less than 2MB.');
    }
    
    // Validate image dimensions and content
    $image_info = getimagesize($uploaded_file['tmp_name']);
    if ($image_info === false) {
        return new WP_Error('invalid_image', 'The uploaded file is not a valid image.');
    }
    
    // Prepare file for WordPress upload
    $upload_overrides = array(
        'test_form' => false,
        'unique_filename_callback' => function($dir, $name, $ext) use ($user_id) {
            return 'profile-' . $user_id . '-' . time() . $ext;
        }
    );
    
    // Handle the upload
    $uploaded = wp_handle_upload($uploaded_file, $upload_overrides);
    
    if (isset($uploaded['error'])) {
        return new WP_Error('upload_failed', $uploaded['error']);
    }
    
    // Create attachment
    $attachment_data = array(
        'post_mime_type' => $uploaded['type'],
        'post_title' => 'Profile Picture - ' . $user->display_name,
        'post_content' => '',
        'post_status' => 'inherit',
        'post_author' => $user_id
    );
    
    $attachment_id = wp_insert_attachment($attachment_data, $uploaded['file']);
    
    if (is_wp_error($attachment_id)) {
        // Clean up uploaded file if attachment creation fails
        @unlink($uploaded['file']);
        return new WP_Error('attachment_failed', 'Failed to create attachment.');
    }
    
    // Generate attachment metadata
    $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $uploaded['file']);
    wp_update_attachment_metadata($attachment_id, $attachment_metadata);
    
    // Remove old profile picture if exists
    $old_attachment_id = get_user_meta($user_id, 'vqr_profile_picture_id', true);
    if ($old_attachment_id) {
        wp_delete_attachment($old_attachment_id, true);
    }
    
    // Store the attachment ID in user meta
    update_user_meta($user_id, 'vqr_profile_picture_id', $attachment_id);
    update_user_meta($user_id, 'vqr_profile_picture_url', $uploaded['url']);
    
    return array(
        'attachment_id' => $attachment_id,
        'url' => $uploaded['url'],
        'file' => $uploaded['file']
    );
}

/**
 * Get user's profile picture URL
 */
function vqr_get_user_profile_picture($user_id, $size = 'thumbnail') {
    $attachment_id = get_user_meta($user_id, 'vqr_profile_picture_id', true);
    
    if ($attachment_id) {
        $image_url = wp_get_attachment_image_url($attachment_id, $size);
        if ($image_url) {
            return $image_url;
        }
    }
    
    // Fallback to WordPress Gravatar
    return null;
}

/**
 * Remove user's profile picture
 */
function vqr_remove_user_profile_picture($user_id) {
    $attachment_id = get_user_meta($user_id, 'vqr_profile_picture_id', true);
    
    if ($attachment_id) {
        wp_delete_attachment($attachment_id, true);
        delete_user_meta($user_id, 'vqr_profile_picture_id');
        delete_user_meta($user_id, 'vqr_profile_picture_url');
        return true;
    }
    
    return false;
}

/**
 * Custom avatar filter to use uploaded profile pictures
 */
function vqr_custom_avatar($avatar, $id_or_email, $size, $default, $alt, $args) {
    // Get user ID
    $user_id = null;
    if (is_numeric($id_or_email)) {
        $user_id = $id_or_email;
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        if ($user) {
            $user_id = $user->ID;
        }
    } elseif (is_object($id_or_email) && isset($id_or_email->user_id)) {
        $user_id = $id_or_email->user_id;
    }
    
    if (!$user_id) {
        return $avatar;
    }
    
    // Get custom profile picture
    $profile_picture_url = vqr_get_user_profile_picture($user_id, array($size, $size));
    
    if ($profile_picture_url) {
        $safe_alt = esc_attr($alt);
        $avatar = "<img alt='{$safe_alt}' src='{$profile_picture_url}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
    }
    
    return $avatar;
}

// Hook into WordPress avatar filter
add_filter('get_avatar', 'vqr_custom_avatar', 10, 6);

/**
 * AJAX handler for profile picture upload
 */
function vqr_ajax_upload_profile_picture() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_upload_profile_picture')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    if (!isset($_FILES['profile_picture'])) {
        wp_send_json_error('No file uploaded');
        return;
    }
    
    $result = vqr_handle_profile_picture_upload($user_id, $_FILES['profile_picture']);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'message' => 'Profile picture updated successfully!',
            'url' => $result['url']
        ));
    }
}

add_action('wp_ajax_vqr_upload_profile_picture', 'vqr_ajax_upload_profile_picture');

/**
 * AJAX handler for profile picture removal
 */
function vqr_ajax_remove_profile_picture() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_remove_profile_picture')) {
        wp_die('Security check failed');
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $result = vqr_remove_user_profile_picture($user_id);
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'Profile picture removed successfully!'
        ));
    } else {
        wp_send_json_error('Failed to remove profile picture');
    }
}

add_action('wp_ajax_vqr_remove_profile_picture', 'vqr_ajax_remove_profile_picture');