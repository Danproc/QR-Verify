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
    
    // Check if qr_id is missing - show error page
    if (empty($_GET['qr_id'])) {
        vqr_show_verification_error('Missing QR verification code. This product could not be verified.');
        exit;
    }
    
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
    
    // Load the strain template
    vqr_load_strain_template($qr_code->post_id, $qr_code);
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
function vqr_load_strain_template($post_id, $qr_code) {
    $strain = get_post($post_id);
    if (!$strain || $strain->post_type !== 'strain') {
        wp_die('Strain not found.');
    }
    
    // Get all strain meta data
    $strain_data = vqr_get_strain_data($post_id);
    
    // Scan tracking is handled by qr-scanner.php
    
    // Load template
    include VQR_PLUGIN_DIR . 'templates/strain-display.php';
}

/**
 * Get all strain data with conditional checks
 */
function vqr_get_strain_data($post_id) {
    global $wpdb;
    $strain = get_post($post_id);
    $data = array(
        'title' => $strain->post_title,
        'content' => $strain->post_content,
    );
    
    // Get current QR code data for accurate scan count
    $qr_id = sanitize_text_field($_GET['qr_id']);
    $table_name = $wpdb->prefix . 'vqr_codes';
    $qr_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT scan_count, first_scanned_at FROM $table_name WHERE qr_key = %s",
            $qr_id
        )
    );
    
    // Meta fields with conditional display
    $meta_fields = array(
        'strain_genetics' => 'Genetics',
        'batch_id' => 'Batch ID',
        'batch_code' => 'Batch Code',
        'product_description' => 'Description',
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
    
    // Image fields
    $product_logo = get_post_meta($post_id, 'product_logo', true);
    if ($product_logo) {
        $logo_data = wp_get_attachment_image_src($product_logo, 'medium');
        if ($logo_data) {
            $data['logo'] = array(
                'url' => $logo_data[0],
                'width' => $logo_data[1],
                'height' => $logo_data[2]
            );
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