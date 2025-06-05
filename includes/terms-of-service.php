<?php
/**
 * Terms of Service Management for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Get current Terms of Service version
 */
function vqr_get_current_tos_version() {
    return get_option('vqr_tos_version', '1.0');
}

/**
 * Set Terms of Service version
 */
function vqr_set_tos_version($version) {
    return update_option('vqr_tos_version', $version);
}

/**
 * Get Terms of Service URL
 */
function vqr_get_tos_url() {
    $tos_page_id = get_option('vqr_tos_page_id');
    if ($tos_page_id && get_post_status($tos_page_id) === 'publish') {
        return get_permalink($tos_page_id);
    }
    
    // Try to find page by title as fallback
    $pages = get_pages(array(
        'post_status' => 'publish',
        'number' => 100
    ));
    
    foreach ($pages as $page) {
        if ($page->post_title === 'Terms of Service') {
            update_option('vqr_tos_page_id', $page->ID);
            return get_permalink($page->ID);
        }
    }
    
    // Auto-create the page if it doesn't exist and we can
    if (current_user_can('manage_options')) {
        vqr_create_default_legal_pages();
        $tos_page_id = get_option('vqr_tos_page_id');
        if ($tos_page_id && get_post_status($tos_page_id) === 'publish') {
            return get_permalink($tos_page_id);
        }
    }
    
    // Fallback to external URL if set
    return get_option('vqr_tos_url', home_url('/terms-of-service/'));
}

/**
 * Get Privacy Policy URL
 */
function vqr_get_privacy_policy_url() {
    $privacy_page_id = get_option('vqr_privacy_page_id');
    if ($privacy_page_id && get_post_status($privacy_page_id) === 'publish') {
        return get_permalink($privacy_page_id);
    }
    
    // Try to find page by title as fallback
    $pages = get_pages(array(
        'post_status' => 'publish',
        'number' => 100
    ));
    
    foreach ($pages as $page) {
        if ($page->post_title === 'Privacy Policy') {
            update_option('vqr_privacy_page_id', $page->ID);
            return get_permalink($page->ID);
        }
    }
    
    // Auto-create the page if it doesn't exist and we can
    if (current_user_can('manage_options')) {
        vqr_create_default_legal_pages();
        $privacy_page_id = get_option('vqr_privacy_page_id');
        if ($privacy_page_id && get_post_status($privacy_page_id) === 'publish') {
            return get_permalink($privacy_page_id);
        }
    }
    
    // Fallback to WordPress privacy policy page
    if (function_exists('get_privacy_policy_url') && get_privacy_policy_url()) {
        return get_privacy_policy_url();
    }
    return get_option('vqr_privacy_url', home_url('/privacy-policy/'));
}

/**
 * Record Terms of Service acceptance
 */
function vqr_record_tos_acceptance($user_id, $tos_version = null) {
    if (!$tos_version) {
        $tos_version = vqr_get_current_tos_version();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_tos_acceptance';
    
    // Get user's IP and user agent
    $ip_address = vqr_get_user_ip_address();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '';
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'tos_version' => $tos_version,
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'accepted_at' => current_time('mysql')
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        // Also store in user meta for quick access
        update_user_meta($user_id, 'vqr_tos_accepted_version', $tos_version);
        update_user_meta($user_id, 'vqr_tos_accepted_date', current_time('mysql'));
        
        // Log the acceptance
        error_log("TOS Acceptance: User {$user_id} accepted Terms of Service version {$tos_version}");
        
        return true;
    }
    
    return false;
}

/**
 * Check if user has accepted current Terms of Service
 */
function vqr_user_has_accepted_current_tos($user_id) {
    $current_version = vqr_get_current_tos_version();
    $user_accepted_version = get_user_meta($user_id, 'vqr_tos_accepted_version', true);
    
    return $user_accepted_version === $current_version;
}

/**
 * Check if user has accepted any Terms of Service
 */
function vqr_user_has_accepted_tos($user_id) {
    return !empty(get_user_meta($user_id, 'vqr_tos_accepted_version', true));
}

/**
 * Get user's TOS acceptance history
 */
function vqr_get_user_tos_history($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_tos_acceptance';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY accepted_at DESC",
        $user_id
    ));
}

/**
 * Get users who need to accept updated Terms of Service
 */
function vqr_get_users_needing_tos_acceptance() {
    $current_version = vqr_get_current_tos_version();
    
    $users = get_users(array(
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'vqr_tos_accepted_version',
                'compare' => 'NOT EXISTS'
            ),
            array(
                'key' => 'vqr_tos_accepted_version',
                'value' => $current_version,
                'compare' => '!='
            )
        ),
        'role__in' => array('qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise')
    ));
    
    return $users;
}

/**
 * Get user's IP address safely
 */
function vqr_get_user_ip_address() {
    // Check for IP from various sources
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_FORWARDED_FOR',        // Proxy
        'HTTP_FORWARDED',            // Proxy
        'REMOTE_ADDR'                // Standard
    );
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            // Validate IP address
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR even if it's private/reserved
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * Force TOS acceptance check for logged-in users
 */
function vqr_check_tos_acceptance_required() {
    // Only check on frontend app pages
    if (!get_query_var('vqr_app_page') || is_admin()) {
        return;
    }
    
    // Skip for public pages
    $public_pages = array('login', 'register', 'forgot-password', 'verify-email', 'terms-of-service', 'privacy-policy', 'terms-acceptance');
    $current_page = get_query_var('vqr_app_page');
    
    if (in_array($current_page, $public_pages)) {
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Skip for admin users
    if (current_user_can('manage_options')) {
        return;
    }
    
    // Check if user has accepted current TOS
    if (!vqr_user_has_accepted_current_tos($user_id)) {
        // Redirect to TOS acceptance page
        wp_redirect(home_url('/app/terms-acceptance'));
        exit;
    }
}
add_action('template_redirect', 'vqr_check_tos_acceptance_required');

/**
 * Handle TOS acceptance form submission
 */
function vqr_handle_tos_acceptance() {
    if (!isset($_POST['vqr_accept_tos_nonce']) || !wp_verify_nonce($_POST['vqr_accept_tos_nonce'], 'vqr_accept_tos')) {
        wp_die('Security check failed.');
    }
    
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to accept Terms of Service.');
    }
    
    $user_id = get_current_user_id();
    
    // Check if TOS checkbox was checked
    if (!isset($_POST['accept_tos']) || $_POST['accept_tos'] !== '1') {
        wp_redirect(add_query_arg('error', 'tos_required', home_url('/app/terms-acceptance')));
        exit;
    }
    
    // Record the acceptance
    if (vqr_record_tos_acceptance($user_id)) {
        // Redirect to dashboard with success message
        wp_redirect(add_query_arg('tos_accepted', '1', home_url('/app/dashboard')));
        exit;
    } else {
        // Error recording acceptance
        wp_redirect(add_query_arg('error', 'acceptance_failed', home_url('/app/terms-acceptance')));
        exit;
    }
}
add_action('init', function() {
    if (isset($_POST['action']) && $_POST['action'] === 'accept_tos') {
        vqr_handle_tos_acceptance();
    }
});

/**
 * Create default Terms of Service and Privacy Policy pages
 */
function vqr_create_default_legal_pages() {
    // Only create if pages don't exist
    if (get_option('vqr_legal_pages_created')) {
        return;
    }
    
    // Create Terms of Service page
    $tos_page = array(
        'post_title' => 'Terms of Service',
        'post_content' => vqr_get_default_tos_content(),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1
    );
    
    $tos_page_id = wp_insert_post($tos_page);
    if ($tos_page_id && !is_wp_error($tos_page_id)) {
        update_option('vqr_tos_page_id', $tos_page_id);
        error_log("VQR: Created Terms of Service page with ID: " . $tos_page_id);
    } else if (is_wp_error($tos_page_id)) {
        error_log("VQR: Failed to create Terms of Service page: " . $tos_page_id->get_error_message());
    }
    
    // Create Privacy Policy page
    $privacy_page = array(
        'post_title' => 'Privacy Policy',
        'post_content' => vqr_get_default_privacy_content(),
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_author' => 1
    );
    
    $privacy_page_id = wp_insert_post($privacy_page);
    if ($privacy_page_id && !is_wp_error($privacy_page_id)) {
        update_option('vqr_privacy_page_id', $privacy_page_id);
        error_log("VQR: Created Privacy Policy page with ID: " . $privacy_page_id);
    } else if (is_wp_error($privacy_page_id)) {
        error_log("VQR: Failed to create Privacy Policy page: " . $privacy_page_id->get_error_message());
    }
    
    // Mark as created
    update_option('vqr_legal_pages_created', true);
}

/**
 * Get default Terms of Service content
 */
function vqr_get_default_tos_content() {
    return '
<h2>Terms of Service for Verify 420</h2>

<p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing and using Verify 420, you accept and agree to be bound by the terms and provision of this agreement.</p>

<h3>2. Description of Service</h3>
<p>Verify 420 is a QR code generation and management platform designed for cannabis product verification.</p>

<h3>3. User Accounts</h3>
<p>You are responsible for maintaining the confidentiality of your account and password. You agree to accept responsibility for all activities that occur under your account.</p>

<h3>4. Subscription and Billing</h3>
<p>Paid subscriptions are billed in advance on a monthly basis. You may cancel your subscription at any time.</p>

<h3>5. Acceptable Use</h3>
<p>You agree not to use the service for any unlawful purposes or to violate any laws in your jurisdiction.</p>

<h3>6. Data and Privacy</h3>
<p>Your privacy is important to us. Please review our Privacy Policy, which also governs your use of the service.</p>

<h3>7. Termination</h3>
<p>We may terminate or suspend your account immediately, without prior notice, for conduct that we believe violates these Terms of Service.</p>

<h3>8. Changes to Terms</h3>
<p>We reserve the right to modify these terms at any time. Users will be notified of changes and required to accept updated terms.</p>

<h3>9. Contact Information</h3>
<p>If you have any questions about these Terms of Service, please contact us through our support system.</p>

<p><em>This is a template. Please customize this content to match your specific business requirements and consult with legal counsel.</em></p>
    ';
}

/**
 * Get default Privacy Policy content
 */
function vqr_get_default_privacy_content() {
    return '
<h2>Privacy Policy for Verify 420</h2>

<p><strong>Last Updated:</strong> ' . date('F j, Y') . '</p>

<h3>1. Information We Collect</h3>
<p>We collect information you provide directly to us, such as when you create an account, use our services, or contact us for support.</p>

<h3>2. How We Use Your Information</h3>
<p>We use the information we collect to provide, maintain, and improve our services, process transactions, and communicate with you.</p>

<h3>3. Information Sharing</h3>
<p>We do not sell, trade, or otherwise transfer your personal information to third parties without your consent, except as described in this policy.</p>

<h3>4. Data Security</h3>
<p>We implement appropriate security measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>

<h3>5. Your Rights</h3>
<p>You have the right to access, update, or delete your personal information. You may also request a copy of your data.</p>

<h3>6. Cookies and Tracking</h3>
<p>We use cookies and similar tracking technologies to enhance your experience and analyze service usage.</p>

<h3>7. Data Retention</h3>
<p>We retain your information only as long as necessary to provide our services and fulfill the purposes described in this policy.</p>

<h3>8. International Data Transfers</h3>
<p>Your information may be transferred to and processed in countries other than your country of residence.</p>

<h3>9. Changes to This Policy</h3>
<p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new policy on this page.</p>

<h3>10. Contact Us</h3>
<p>If you have any questions about this Privacy Policy, please contact us through our support system.</p>

<p><em>This is a template. Please customize this content to match your specific business requirements and consult with legal counsel.</em></p>
    ';
}

// Create legal pages on plugin activation
add_action('vqr_plugin_activated', 'vqr_create_default_legal_pages');

/**
 * Ensure legal pages exist (call this on admin init)
 */
function vqr_ensure_legal_pages_exist() {
    if (!get_option('vqr_legal_pages_created')) {
        vqr_create_default_legal_pages();
    }
}

// Also create pages on admin init if they don't exist
add_action('admin_init', function() {
    if (current_user_can('manage_options')) {
        vqr_ensure_legal_pages_exist();
        
        // Handle manual page creation request
        if (isset($_GET['vqr_create_legal_pages']) && wp_verify_nonce($_GET['_wpnonce'], 'vqr_create_legal_pages')) {
            // Force recreation by removing the flag first
            delete_option('vqr_legal_pages_created');
            delete_option('vqr_tos_page_id');
            delete_option('vqr_privacy_page_id');
            
            // Force creation
            vqr_create_default_legal_pages();
            
            wp_redirect(add_query_arg('legal_pages_created', '1', admin_url('admin.php?page=verification_qr_manager')));
            exit;
        }
        
        // Debug action to show page status
        if (isset($_GET['vqr_debug_legal_pages']) && wp_verify_nonce($_GET['_wpnonce'], 'vqr_debug_legal_pages')) {
            $tos_id = get_option('vqr_tos_page_id');
            $privacy_id = get_option('vqr_privacy_page_id');
            $pages_created = get_option('vqr_legal_pages_created');
            
            echo '<div class="notice notice-info"><p>';
            echo '<strong>Debug Info:</strong><br>';
            echo 'TOS Page ID: ' . ($tos_id ? $tos_id . ' (Status: ' . get_post_status($tos_id) . ')' : 'Not set') . '<br>';
            echo 'Privacy Page ID: ' . ($privacy_id ? $privacy_id . ' (Status: ' . get_post_status($privacy_id) . ')' : 'Not set') . '<br>';
            echo 'Legal Pages Created Flag: ' . ($pages_created ? 'Yes' : 'No') . '<br>';
            echo 'TOS URL: ' . vqr_get_tos_url() . '<br>';
            echo 'Privacy URL: ' . vqr_get_privacy_policy_url();
            echo '</p></div>';
        }
    }
});