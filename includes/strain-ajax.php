<?php
/**
 * Strain AJAX Handlers for Frontend
 */

defined('ABSPATH') || exit;

/**
 * Handle strain creation/update
 */
function vqr_ajax_save_strain() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    
    // Ensure strain capabilities are added for QR customer roles (failsafe)
    $user = wp_get_current_user();
    $qr_roles = ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'];
    if (array_intersect($qr_roles, $user->roles) && !user_can($user_id, 'create_strains')) {
        vqr_add_strain_capabilities();
        // Clear user cache to ensure capabilities are refreshed
        clean_user_cache($user_id);
    }
    
    $strain_id = intval($_POST['strain_id'] ?? 0);
    
    // Prepare strain data
    $strain_data = [
        'strain_name' => sanitize_text_field($_POST['strain_name']),
        'strain_genetics' => sanitize_text_field($_POST['strain_genetics'] ?? ''),
        'batch_id' => sanitize_text_field($_POST['batch_id'] ?? ''),
        'product_description' => wp_kses_post($_POST['product_description'] ?? ''),
        'thc_percentage' => sanitize_text_field($_POST['thc_percentage'] ?? ''),
        'thc_mg' => sanitize_text_field($_POST['thc_mg'] ?? ''),
        'cbd_percentage' => sanitize_text_field($_POST['cbd_percentage'] ?? ''),
        'cbd_mg' => sanitize_text_field($_POST['cbd_mg'] ?? ''),
        'instagram_url' => esc_url_raw($_POST['instagram_url'] ?? ''),
        'facebook_url' => esc_url_raw($_POST['facebook_url'] ?? ''),
        'twitter_url' => esc_url_raw($_POST['twitter_url'] ?? ''),
        'telegram_url' => esc_url_raw($_POST['telegram_url'] ?? '')
    ];
    
    // Handle file uploads
    $uploaded_files = [];
    $file_fields = ['product_logo', 'product_image'];
    
    foreach ($file_fields as $field) {
        if (!empty($_FILES[$field]['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($_FILES[$field]['type'], $allowed_types)) {
                wp_send_json_error(ucfirst(str_replace('_', ' ', $field)) . ' must be PNG or JPEG format.');
            }
            
            if ($_FILES[$field]['size'] > 2 * 1024 * 1024) { // 2MB limit
                wp_send_json_error(ucfirst(str_replace('_', ' ', $field)) . ' file too large. Maximum 2MB.');
            }
            
            $upload = wp_handle_upload($_FILES[$field], [
                'test_form' => false,
                'mimes' => [
                    'png' => 'image/png',
                    'jpg|jpeg' => 'image/jpeg'
                ]
            ]);
            
            if (isset($upload['error'])) {
                wp_send_json_error(ucfirst(str_replace('_', ' ', $field)) . ' upload failed: ' . $upload['error']);
            }
            
            // Store attachment ID
            $attachment_id = wp_insert_attachment([
                'post_title' => sanitize_file_name($_FILES[$field]['name']),
                'post_content' => '',
                'post_status' => 'inherit',
                'post_mime_type' => $_FILES[$field]['type']
            ], $upload['file']);
            
            if (!is_wp_error($attachment_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                $strain_data[$field] = $attachment_id;
            }
        }
    }
    
    // Validation
    if (empty($strain_data['strain_name'])) {
        wp_send_json_error('Strain name is required.');
    }
    
    try {
        if ($strain_id > 0) {
            // Update existing strain
            $result = vqr_update_user_strain($strain_id, $strain_data, $user_id);
        } else {
            // Create new strain
            $result = vqr_create_user_strain($strain_data, $user_id);
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => $strain_id > 0 ? 'Strain updated successfully!' : 'Strain created successfully!',
            'strain_id' => $result,
            'redirect' => home_url('/app/strains')
        ]);
        
    } catch (Exception $e) {
        error_log('Strain save error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while saving the strain. Please try again.');
    }
}
add_action('wp_ajax_vqr_save_strain', 'vqr_ajax_save_strain');

/**
 * Load strain data for editing
 */
function vqr_ajax_load_strain() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $strain_id = intval($_POST['strain_id']);
    $user_id = get_current_user_id();
    
    if (!vqr_user_can_manage_strain($strain_id, $user_id)) {
        wp_send_json_error('You do not have permission to edit this strain.');
    }
    
    $strain = get_post($strain_id);
    if (!$strain || $strain->post_type !== 'strain') {
        wp_send_json_error('Strain not found.');
    }
    
    // Get strain meta data
    $strain_data = [
        'strain_name' => $strain->post_title,
        'product_description' => $strain->post_content,
        'strain_genetics' => get_post_meta($strain_id, 'strain_genetics', true),
        'batch_id' => get_post_meta($strain_id, 'batch_id', true),
        'thc_percentage' => get_post_meta($strain_id, 'thc_percentage', true),
        'thc_mg' => get_post_meta($strain_id, 'thc_mg', true),
        'cbd_percentage' => get_post_meta($strain_id, 'cbd_percentage', true),
        'cbd_mg' => get_post_meta($strain_id, 'cbd_mg', true),
        'instagram_url' => get_post_meta($strain_id, 'instagram_url', true),
        'facebook_url' => get_post_meta($strain_id, 'facebook_url', true),
        'twitter_url' => get_post_meta($strain_id, 'twitter_url', true),
        'telegram_url' => get_post_meta($strain_id, 'telegram_url', true),
        'product_logo' => get_post_meta($strain_id, 'product_logo', true),
        'product_image' => get_post_meta($strain_id, 'product_image', true)
    ];
    
    wp_send_json_success([
        'strain_data' => $strain_data
    ]);
}
add_action('wp_ajax_vqr_load_strain', 'vqr_ajax_load_strain');

/**
 * Delete strain
 */
function vqr_ajax_delete_strain() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $strain_id = intval($_POST['strain_id']);
    $user_id = get_current_user_id();
    
    try {
        $result = vqr_delete_user_strain($strain_id, $user_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success([
            'message' => 'Strain deleted successfully!',
            'redirect' => home_url('/app/strains')
        ]);
        
    } catch (Exception $e) {
        error_log('Strain delete error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while deleting the strain. Please try again.');
    }
}
add_action('wp_ajax_vqr_delete_strain', 'vqr_ajax_delete_strain');

/**
 * Get user's strains for dropdowns/selects
 */
function vqr_ajax_get_user_strains() {
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    $strains = vqr_get_user_strains($user_id);
    
    $strain_options = [];
    foreach ($strains as $strain) {
        $strain_options[] = [
            'id' => $strain->ID,
            'title' => $strain->post_title,
            'url' => get_permalink($strain->ID)
        ];
    }
    
    wp_send_json_success([
        'strains' => $strain_options
    ]);
}
add_action('wp_ajax_vqr_get_user_strains', 'vqr_ajax_get_user_strains');