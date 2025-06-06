<?php
/**
 * Front-end template system for strain display
 */

defined('ABSPATH') || exit;

/**
 * Handle strain display on frontend when QR code is scanned
 */
function vqr_handle_strain_display() {
    // Check if we're on a strain page (has strain parameter)
    if (empty($_GET['strain'])) {
        return;
    }
    
    // Check for preview mode (for strain owners to preview their strain)
    $is_preview = isset($_GET['preview']) && $_GET['preview'] === '1';
    
    if (!$is_preview && empty($_GET['qr_id'])) {
        vqr_show_verification_error('Missing QR verification code. This product could not be verified.');
        exit;
    }
    
    $strain_id = intval($_GET['strain']);
    $qr_code = null;
    
    if ($is_preview) {
        // Preview mode - verify user owns the strain
        if (!is_user_logged_in()) {
            vqr_show_verification_error('You must be logged in to preview strains.');
            exit;
        }
        
        if (!vqr_user_can_manage_strain($strain_id, get_current_user_id())) {
            vqr_show_verification_error('You do not have permission to preview this strain.');
            exit;
        }
        
        // Create fake QR data for preview
        $qr_code = (object) [
            'post_id' => $strain_id,
            'scan_count' => 1,
            'first_scanned_at' => current_time('mysql'),
            'qr_key' => 'preview-mode'
        ];
    } else {
        // Normal QR scan mode
        global $wpdb;
        $table_name = $wpdb->prefix . 'vqr_codes';
        $qr_id = sanitize_text_field($_GET['qr_id']);
        
        // Get QR code data
        $qr_code = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE qr_key = %s",
                $qr_id
            )
        );
        
        // Check if QR code is invalid - show error page
        if (!$qr_code || !$qr_code->post_id) {
            vqr_show_verification_error('Invalid QR verification code. This product could not be verified.');
            exit;
        }
        
        $strain_id = $qr_code->post_id;
    }
    
    // Load the strain template
    vqr_load_strain_template($strain_id, $qr_code, $is_preview);
    exit;
}
add_action('template_redirect', 'vqr_handle_strain_display', 10);

/**
 * Show verification error page
 */
function vqr_show_verification_error($error_message) {
    // Enqueue frontend styles for error page
    wp_enqueue_style('vqr-frontend', VQR_PLUGIN_URL . 'assets/frontend-style.css', array(), '1.0.0');
    
    // Set up error data
    $error_data = array(
        'title' => 'Verification Failed',
        'message' => $error_message
    );
    
    // Load error template (or modified strain template for errors)
    include VQR_PLUGIN_DIR . 'templates/verification-error.php';
}

/**
 * Load and display the strain template
 */
function vqr_load_strain_template($post_id, $qr_code, $is_preview = false) {
    $strain = get_post($post_id);
    if (!$strain || $strain->post_type !== 'strain') {
        wp_die('Strain not found.');
    }
    
    // Get all strain meta data
    $strain_data = vqr_get_strain_data($post_id, $qr_code, $is_preview);
    
    // Scan tracking is handled by qr-scanner.php (skip for preview mode)
    
    // Load template
    include VQR_PLUGIN_DIR . 'templates/strain-display.php';
}

/**
 * Get all strain data with conditional checks
 */
function vqr_get_strain_data($post_id, $qr_code = null, $is_preview = false) {
    $strain = get_post($post_id);
    $data = array(
        'title' => $strain->post_title,
        'content' => $strain->post_content,
    );
    
    // Use provided QR code data or get from database
    $qr_data = $qr_code;
    if (!$qr_data && !$is_preview && !empty($_GET['qr_id'])) {
        global $wpdb;
        $qr_id = sanitize_text_field($_GET['qr_id']);
        $table_name = $wpdb->prefix . 'vqr_codes';
        $qr_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT scan_count, first_scanned_at FROM $table_name WHERE qr_key = %s",
                $qr_id
            )
        );
    }
    
    // Add product description from post_content if it exists
    if (!empty($strain->post_content)) {
        $data['meta']['product_description'] = array(
            'label' => 'Description',
            'value' => $strain->post_content
        );
    }
    
    // Meta fields with conditional display
    $meta_fields = array(
        'strain_genetics' => 'Genetics',
        'batch_id' => 'Batch ID',
        'batch_code' => 'Batch Code',
        'thc_mg' => 'THC (mg)',
        'thc_percentage' => 'THC (%)',
        'cbd_mg' => 'CBD (mg)',
        'cbd_percentage' => 'CBD (%)',
        'instagram_url' => 'Instagram',
        'telegram_url' => 'Telegram',
        'facebook_url' => 'Facebook',
        'twitter_url' => 'Twitter'
    );
    
    // Add real-time scan data
    if ($qr_data) {
        $data['scan_count'] = $qr_data->scan_count;
        $data['first_scanned_at'] = $qr_data->first_scanned_at;
    }
    
    foreach ($meta_fields as $key => $label) {
        $value = get_post_meta($post_id, $key, true);
        if (!empty($value)) {
            // Format THC/CBD percentages to 3 decimal places
            if (in_array($key, ['thc_percentage', 'cbd_percentage']) && is_numeric($value)) {
                $value = number_format((float)$value, 3);
            }
            $data['meta'][$key] = array(
                'label' => $label,
                'value' => $value
            );
        }
    }
    
    // Image fields - Handle logo based on user plan
    $product_logo = get_post_meta($post_id, 'product_logo', true);
    $strain_author_id = get_post_field('post_author', $post_id);
    
    if ($product_logo) {
        // User has uploaded a custom logo
        $logo_data = wp_get_attachment_image_src($product_logo, 'medium');
        if ($logo_data) {
            $data['logo'] = array(
                'url' => $logo_data[0],
                'width' => $logo_data[1],
                'height' => $logo_data[2],
                'is_custom' => true
            );
        }
    } else {
        // No custom logo uploaded
        if ($strain_author_id) {
            $user_plan = vqr_get_user_plan($strain_author_id);
            if ($user_plan === 'free') {
                // Free plan: Show Verify 420 logo
                $default_logo_path = vqr_create_default_logo();
                if ($default_logo_path) {
                    $upload_dir = wp_upload_dir();
                    $logo_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $default_logo_path);
                    $data['logo'] = array(
                        'url' => $logo_url,
                        'width' => 300,
                        'height' => 80,
                        'is_custom' => false,
                        'is_verify420' => true,
                        'home_url' => home_url()
                    );
                }
            }
            // Paid plans with no logo: No logo section will be shown
        }
    }
    
    $product_image = get_post_meta($post_id, 'product_image', true);
    if ($product_image) {
        $image_data = wp_get_attachment_image_src($product_image, 'large');
        if ($image_data) {
            $data['image'] = array(
                'url' => $image_data[0],
                'width' => $image_data[1],
                'height' => $image_data[2]
            );
        }
    }
    
    // Categories/taxonomy
    $companies = wp_get_post_terms($post_id, 'company');
    if (!empty($companies) && !is_wp_error($companies)) {
        $data['companies'] = $companies;
    }
    
    return $data;
}


/**
 * Enqueue front-end styles
 */
function vqr_enqueue_frontend_styles() {
    if (isset($_GET['qr_id'])) {
        wp_enqueue_style('vqr-frontend', VQR_PLUGIN_URL . 'assets/frontend-style.css', array(), '1.0.0');
    }
}
add_action('wp_enqueue_scripts', 'vqr_enqueue_frontend_styles');