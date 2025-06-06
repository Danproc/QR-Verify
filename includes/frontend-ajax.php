<?php
/**
 * Frontend AJAX Handlers for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Handle QR code generation from frontend
 */
function vqr_ajax_generate_qr_codes() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['vqr_generate_nonce'], 'vqr_generate_qr_codes')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    
    // Sanitize inputs
    $quantity = intval($_POST['qr_count']);
    $strain_id = intval($_POST['strain_id']);
    $base_url = esc_url_raw($_POST['base_url']);
    $category = sanitize_text_field($_POST['category']);
    $code_prefix = sanitize_text_field($_POST['code_prefix']);
    
    // Validation
    if ($quantity < 1 || $quantity > 1000) {
        wp_send_json_error('Invalid quantity. Must be between 1 and 1000.');
    }
    
    if (empty($strain_id) || !get_post($strain_id)) {
        wp_send_json_error('Please select a valid strain.');
    }
    
    if (empty($base_url) || !filter_var($base_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error('Invalid URL provided.');
    }
    
    if (empty($category)) {
        wp_send_json_error('Category is required.');
    }
    
    if (empty($code_prefix) || strlen($code_prefix) !== 4) {
        wp_send_json_error('Code prefix must be exactly 4 characters.');
    }
    
    // Check user quota
    if (!vqr_user_can_generate($quantity, $user_id)) {
        $quota = vqr_get_user_quota($user_id);
        $usage = vqr_get_user_usage($user_id);
        $remaining = $quota === -1 ? 'unlimited' : ($quota - $usage);
        
        wp_send_json_error([
            'message' => 'Insufficient quota. You have ' . $remaining . ' QR codes remaining this month.',
            'quota_exceeded' => true,
            'upgrade_url' => home_url('/app/billing')
        ]);
    }
    
    // Handle logo upload (only for paid plans)
    $logo_path = '';
    $user_plan = vqr_get_user_plan($user_id);
    
    if (!empty($_FILES['logo_file']['name'])) {
        // Check if user can upload custom logos
        if ($user_plan === 'free') {
            // Free plan users can't upload logos - silently ignore the upload
            // The QR generator will use the default Verify 420 logo instead
        } else {
            // Paid plan users can upload custom logos
            require_once ABSPATH . 'wp-admin/includes/file.php';
            
            $allowed_types = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($_FILES['logo_file']['type'], $allowed_types)) {
                wp_send_json_error('Logo must be PNG or JPEG format.');
            }
            
            if ($_FILES['logo_file']['size'] > 2 * 1024 * 1024) { // 2MB limit
                wp_send_json_error('Logo file too large. Maximum 2MB.');
            }
            
            $upload = wp_handle_upload($_FILES['logo_file'], [
                'test_form' => false,
                'mimes' => [
                    'png' => 'image/png',
                    'jpg|jpeg' => 'image/jpeg'
                ]
            ]);
            
            if (isset($upload['error'])) {
                wp_send_json_error('Logo upload failed: ' . $upload['error']);
            }
            
            $logo_path = $upload['file'];
        }
    }
    
    try {
        // Generate QR codes using existing function
        $result = vqr_generate_codes($quantity, $base_url, $category, $strain_id, $code_prefix, $logo_path, $user_id);
        
        if ($result === false) {
            wp_send_json_error('Failed to generate QR codes. Please try again.');
        }
        
        // Increment user usage
        $new_usage = vqr_increment_user_usage($quantity, $user_id);
        
        // Get updated quota info
        $quota = vqr_get_user_quota($user_id);
        $remaining = $quota === -1 ? 'unlimited' : ($quota - $new_usage);
        
        wp_send_json_success([
            'message' => "Successfully generated {$quantity} QR codes!",
            'quantity' => $quantity,
            'new_usage' => $new_usage,
            'quota' => $quota,
            'remaining' => $remaining,
            'redirect' => home_url('/app/codes'),
            'refresh_element' => '.vqr-quota-card'
        ]);
        
    } catch (Exception $e) {
        error_log('QR Generation Error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while generating QR codes. Please try again.');
    }
}
add_action('wp_ajax_vqr_generate_qr_codes', 'vqr_ajax_generate_qr_codes');

/**
 * Get user quota information via AJAX
 */
function vqr_ajax_get_quota_info() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in.');
    }
    
    $user_id = get_current_user_id();
    $quota = vqr_get_user_quota($user_id);
    $usage = vqr_get_user_usage($user_id);
    $remaining = $quota === -1 ? -1 : ($quota - $usage);
    $plan = vqr_get_user_plan($user_id);
    
    wp_send_json_success([
        'quota' => $quota,
        'usage' => $usage,
        'remaining' => $remaining,
        'plan' => $plan,
        'percentage' => $quota === -1 ? 0 : min(($usage / $quota) * 100, 100)
    ]);
}
add_action('wp_ajax_vqr_get_quota_info', 'vqr_ajax_get_quota_info');

/**
 * Delete a single QR code via AJAX
 */
function vqr_ajax_delete_qr_code() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    // Check if user has permission to delete QR codes
    if (!vqr_user_can_delete_qr_codes()) {
        wp_send_json_error('You do not have permission to delete QR codes. Upgrade to Pro plan.');
    }
    
    $user_id = get_current_user_id();
    $qr_id = intval($_POST['qr_id']);
    
    if (!$qr_id) {
        wp_send_json_error('Invalid QR code ID.');
    }
    
    global $wpdb;
    $qr_table = $wpdb->prefix . 'vqr_codes';
    
    // Get QR code details and verify ownership
    $qr_code = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$qr_table} WHERE id = %d AND user_id = %d",
        $qr_id, $user_id
    ));
    
    if (!$qr_code) {
        wp_send_json_error('QR code not found or you do not have permission to delete it.');
    }
    
    // Check if QR code has sticker orders that prevent deletion
    $sticker_orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $sticker_order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    $sticker_order_check = $wpdb->get_row($wpdb->prepare(
        "SELECT so.order_number, so.status 
         FROM {$sticker_order_items_table} soi 
         INNER JOIN {$sticker_orders_table} so ON soi.order_id = so.id 
         WHERE soi.qr_code_id = %d 
         AND so.status IN ('pending', 'processing', 'shipped') 
         ORDER BY so.created_at DESC 
         LIMIT 1",
        $qr_id
    ));
    
    if ($sticker_order_check) {
        wp_send_json_error([
            'message' => "This QR code cannot be deleted because it has an active sticker order (Order: {$sticker_order_check->order_number}, Status: {$sticker_order_check->status}). <a href='" . home_url('/app/basket') . "' style='color: #059669; text-decoration: underline;'>View your pending orders</a> to cancel or manage them.",
            'sticker_order_blocking' => true,
            'order_number' => $sticker_order_check->order_number,
            'order_status' => $sticker_order_check->status
        ]);
    }
    
    try {
        // Delete from security scans table first (foreign key constraint)
        $security_scans_table = $wpdb->prefix . 'vqr_security_scans';
        $wpdb->delete($security_scans_table, array('qr_key' => $qr_code->qr_key), array('%s'));
        
        // Delete from security alerts table
        $security_alerts_table = $wpdb->prefix . 'vqr_security_alerts';
        $wpdb->delete($security_alerts_table, array('qr_key' => $qr_code->qr_key), array('%s'));
        
        // Delete the QR code image file if it exists
        if (!empty($qr_code->qr_code)) {
            $file_path = str_replace(home_url(), ABSPATH, $qr_code->qr_code);
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
            }
        }
        
        // Delete the QR code record
        $result = $wpdb->delete($qr_table, array('id' => $qr_id), array('%d'));
        
        if ($result === false) {
            wp_send_json_error('Failed to delete QR code from database.');
        }
        
        wp_send_json_success([
            'message' => 'QR code deleted successfully.',
            'batch_code' => $qr_code->batch_code
        ]);
        
    } catch (Exception $e) {
        error_log('QR Delete Error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while deleting the QR code. Please try again.');
    }
}
add_action('wp_ajax_vqr_delete_qr_code', 'vqr_ajax_delete_qr_code');

/**
 * Delete multiple QR codes via AJAX (bulk delete)
 */
function vqr_ajax_bulk_delete_qr() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    // Check if user has permission to delete QR codes
    if (!vqr_user_can_delete_qr_codes()) {
        wp_send_json_error('You do not have permission to delete QR codes. Upgrade to Pro plan.');
    }
    
    $user_id = get_current_user_id();
    $qr_ids = isset($_POST['qr_ids']) ? array_map('intval', $_POST['qr_ids']) : array();
    
    if (empty($qr_ids)) {
        wp_send_json_error('No QR codes selected for deletion.');
    }
    
    // Limit bulk delete to reasonable number
    if (count($qr_ids) > 100) {
        wp_send_json_error('Cannot delete more than 100 QR codes at once.');
    }
    
    global $wpdb;
    $qr_table = $wpdb->prefix . 'vqr_codes';
    $security_scans_table = $wpdb->prefix . 'vqr_security_scans';
    $security_alerts_table = $wpdb->prefix . 'vqr_security_alerts';
    $sticker_orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $sticker_order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Check if any QR codes have active sticker orders
    $placeholders = implode(',', array_fill(0, count($qr_ids), '%d'));
    $sticker_blocked_qrs = $wpdb->get_results($wpdb->prepare(
        "SELECT soi.qr_code_id, qr.batch_code, so.order_number, so.status 
         FROM {$sticker_order_items_table} soi 
         INNER JOIN {$sticker_orders_table} so ON soi.order_id = so.id 
         INNER JOIN {$qr_table} qr ON soi.qr_code_id = qr.id 
         WHERE soi.qr_code_id IN ({$placeholders}) 
         AND qr.user_id = %d 
         AND so.status IN ('pending', 'processing', 'shipped')",
        array_merge($qr_ids, [$user_id])
    ));
    
    if (!empty($sticker_blocked_qrs)) {
        $blocked_codes = array();
        foreach ($sticker_blocked_qrs as $blocked) {
            $blocked_codes[] = "#{$blocked->batch_code} (Order: {$blocked->order_number})";
        }
        
        wp_send_json_error([
            'message' => "Some QR codes cannot be deleted because they have active sticker orders: " . implode(', ', $blocked_codes) . ". <a href='" . home_url('/app/basket') . "' style='color: #059669; text-decoration: underline;'>View your pending orders</a> to cancel or manage them.",
            'sticker_order_blocking' => true,
            'blocked_qr_codes' => $blocked_codes
        ]);
    }
    
    try {
        $deleted_count = 0;
        $deleted_files = 0;
        
        // Start transaction for data integrity
        $wpdb->query('START TRANSACTION');
        
        foreach ($qr_ids as $qr_id) {
            // Get QR code details and verify ownership
            $qr_code = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$qr_table} WHERE id = %d AND user_id = %d",
                $qr_id, $user_id
            ));
            
            if (!$qr_code) {
                continue; // Skip if not found or not owned by user
            }
            
            // Delete from security tables first
            $wpdb->delete($security_scans_table, array('qr_key' => $qr_code->qr_key), array('%s'));
            $wpdb->delete($security_alerts_table, array('qr_key' => $qr_code->qr_key), array('%s'));
            
            // Delete the QR code image file if it exists
            if (!empty($qr_code->qr_code)) {
                $file_path = str_replace(home_url(), ABSPATH, $qr_code->qr_code);
                if (file_exists($file_path)) {
                    if (wp_delete_file($file_path)) {
                        $deleted_files++;
                    }
                }
            }
            
            // Delete the QR code record
            $result = $wpdb->delete($qr_table, array('id' => $qr_id), array('%d'));
            
            if ($result !== false) {
                $deleted_count++;
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        if ($deleted_count === 0) {
            wp_send_json_error('No QR codes were deleted. Please check your permissions.');
        }
        
        wp_send_json_success([
            'message' => "Successfully deleted {$deleted_count} QR code(s).",
            'deleted_count' => $deleted_count,
            'deleted_files' => $deleted_files,
            'total_requested' => count($qr_ids)
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $wpdb->query('ROLLBACK');
        error_log('Bulk QR Delete Error: ' . $e->getMessage());
        wp_send_json_error('An error occurred while deleting QR codes. Please try again.');
    }
}
add_action('wp_ajax_vqr_bulk_delete_qr', 'vqr_ajax_bulk_delete_qr');

/**
 * Refresh quota display
 */
function vqr_ajax_refresh_quota() {
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in.');
    }
    
    $user_id = get_current_user_id();
    $user_plan = vqr_get_user_plan($user_id);
    $plan_details = vqr_get_plan_details($user_plan);
    $monthly_quota = vqr_get_user_quota($user_id);
    $current_usage = vqr_get_user_usage($user_id);
    $remaining_quota = $monthly_quota === -1 ? 'Unlimited' : ($monthly_quota - $current_usage);
    
    ob_start();
    ?>
    <div class="vqr-quota-header">
        <h3>Monthly Usage</h3>
        <span class="vqr-plan-badge vqr-badge vqr-badge-success"><?php echo esc_html($plan_details['name']); ?> Plan</span>
    </div>
    
    <div class="vqr-quota-bar-container">
        <?php if ($monthly_quota === -1): ?>
            <div class="vqr-quota-unlimited">
                <svg class="vqr-quota-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                <span>Unlimited QR Codes</span>
            </div>
        <?php else: ?>
            <div class="vqr-quota-numbers">
                <span class="vqr-quota-used"><?php echo number_format($current_usage); ?></span>
                <span class="vqr-quota-separator">/</span>
                <span class="vqr-quota-total"><?php echo number_format($monthly_quota); ?></span>
                <span class="vqr-quota-label">QR codes used this month</span>
            </div>
            
            <div class="vqr-quota-bar">
                <div class="vqr-quota-progress" style="width: <?php echo min(($current_usage / $monthly_quota) * 100, 100); ?>%;"></div>
            </div>
            
            <div class="vqr-quota-remaining">
                <?php if ($remaining_quota > 0): ?>
                    <span class="vqr-text-success">✓ <?php echo number_format($remaining_quota); ?> QR codes remaining</span>
                <?php else: ?>
                    <span class="vqr-text-error">⚠️ Monthly quota reached</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($user_plan === 'free' && ($current_usage / $monthly_quota) > 0.8): ?>
        <div class="vqr-upgrade-prompt">
            <p>Running low on QR codes?</p>
            <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                Upgrade Plan
            </a>
        </div>
    <?php endif; ?>
    <?php
    
    $html = ob_get_clean();
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_vqr_refresh_quota', 'vqr_ajax_refresh_quota');

/**
 * Handle email verification resend via AJAX
 */
function vqr_ajax_resend_verification() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    
    // Check if already verified
    if (vqr_is_email_verified($user_id)) {
        wp_send_json_error('Your email is already verified.');
    }
    
    // Attempt to resend verification email
    $result = vqr_resend_verification_email($user_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success([
        'message' => 'Verification email sent successfully! Please check your inbox.',
        'email' => $result['email'],
        'resent_count' => $result['resent_count']
    ]);
}
add_action('wp_ajax_vqr_resend_verification', 'vqr_ajax_resend_verification');

/**
 * Handle QR code scan reset via AJAX
 */
function vqr_ajax_reset_qr_scans() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    
    // Check if user can reset QR codes (Pro and Enterprise only)
    if (!vqr_user_can_reset_qr_codes($user_id)) {
        wp_send_json_error([
            'message' => 'Upgrade to Pro plan to reset QR code scan counts.',
            'upgrade_required' => true,
            'upgrade_url' => home_url('/app/billing')
        ]);
    }
    
    // Sanitize and validate QR code ID
    $qr_id = intval($_POST['qr_id']);
    if (empty($qr_id)) {
        wp_send_json_error('Invalid QR code ID.');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    
    // Verify the QR code belongs to the current user
    $qr_code = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
        $qr_id,
        $user_id
    ));
    
    if (!$qr_code) {
        wp_send_json_error('QR code not found or you do not have permission to modify it.');
    }
    
    // Get the current scan count for logging
    $old_scan_count = $qr_code->scan_count;
    
    // Reset the scan count to 0
    $result = $wpdb->update(
        $table_name,
        ['scan_count' => 0],
        ['id' => $qr_id, 'user_id' => $user_id],
        ['%d'],
        ['%d', '%d']
    );
    
    if ($result === false) {
        wp_send_json_error('Failed to reset scan count. Please try again.');
    }
    
    // Log the reset action for audit purposes
    if (function_exists('error_log')) {
        error_log(sprintf(
            'QR Scan Reset: User %d reset QR code %d (%s) from %d scans to 0',
            $user_id,
            $qr_id,
            $qr_code->batch_code,
            $old_scan_count
        ));
    }
    
    wp_send_json_success([
        'message' => 'Scan count reset successfully.',
        'qr_id' => $qr_id,
        'batch_code' => $qr_code->batch_code,
        'old_scan_count' => $old_scan_count,
        'new_scan_count' => 0
    ]);
}
add_action('wp_ajax_vqr_reset_qr_scans', 'vqr_ajax_reset_qr_scans');

/**
 * Handle bulk QR code download
 */
function vqr_ajax_bulk_download_qr() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    // Check if user has permission for bulk downloads
    if (!vqr_user_can_download_bulk_zip()) {
        wp_send_json_error('Upgrade to Starter plan or higher to download multiple QR codes as ZIP files.');
    }
    
    $user_id = get_current_user_id();
    
    // Debug the incoming POST data
    error_log("Bulk download POST data: " . print_r($_POST, true));
    
    // Handle both qr_ids and qr_ids[] array formats
    $qr_ids = array();
    if (isset($_POST['qr_ids']) && is_array($_POST['qr_ids'])) {
        $qr_ids = array_map('intval', $_POST['qr_ids']);
    } elseif (isset($_POST['qr_ids'])) {
        // Handle single value or comma-separated string
        $qr_ids = array_map('intval', explode(',', $_POST['qr_ids']));
    }
    
    error_log("Parsed QR IDs: " . print_r($qr_ids, true));
    
    if (empty($qr_ids)) {
        wp_send_json_error('No QR codes selected for download.');
    }
    
    if (count($qr_ids) > 100) {
        wp_send_json_error('Maximum 100 QR codes can be downloaded at once.');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    
    // Get QR codes that belong to the user
    $placeholders = implode(',', array_fill(0, count($qr_ids), '%d'));
    $query = $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE id IN ({$placeholders}) AND user_id = %d",
        array_merge($qr_ids, [$user_id])
    );
    
    $qr_codes = $wpdb->get_results($query);
    
    // Debug logging
    error_log("Bulk download debug: User {$user_id} requested " . count($qr_ids) . " QR codes");
    error_log("Query: " . $query);
    error_log("Found " . count($qr_codes) . " QR codes in database");
    
    // Log each QR code found
    foreach ($qr_codes as $idx => $qr_code) {
        error_log("QR Code {$idx}: ID={$qr_code->id}, Batch={$qr_code->batch_code}, URL={$qr_code->qr_code}");
    }
    
    if (empty($qr_codes)) {
        error_log("No QR codes found for user {$user_id} with IDs: " . implode(', ', $qr_ids));
        wp_send_json_error('No valid QR codes found for download.');
    }
    
    // Create temporary directory for ZIP file
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/vqr-temp/';
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    // Generate unique filename
    $zip_filename = 'qr-codes-' . date('Y-m-d-H-i-s') . '-' . uniqid() . '.zip';
    $zip_path = $temp_dir . $zip_filename;
    
    // Create ZIP file
    if (!class_exists('ZipArchive')) {
        wp_send_json_error('ZIP functionality not available on this server.');
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        wp_send_json_error('Could not create ZIP file.');
    }
    
    $added_files = 0;
    foreach ($qr_codes as $qr_code) {
        // Reset variables for each iteration
        $qr_image_path = null;
        $qr_image_data = null;
        $file_added = false;
        
        // Get the QR code image URL
        $qr_image_url = $qr_code->qr_code;
        
        if (empty($qr_image_url)) {
            error_log("QR Code {$qr_code->id} has empty image URL");
            continue; // Skip if no URL
        }
        
        // Convert URL to local path if it's a local file
        if (strpos($qr_image_url, home_url()) === 0) {
            $qr_image_path = str_replace(home_url(), ABSPATH, $qr_image_url);
            $qr_image_path = str_replace('//', '/', $qr_image_path);
            
            if (!file_exists($qr_image_path)) {
                error_log("QR Code {$qr_code->id} file not found at: {$qr_image_path}");
                continue; // Skip if file doesn't exist
            }
        } else {
            // For remote URLs, download the content
            $qr_image_response = wp_remote_get($qr_image_url, array('timeout' => 30));
            if (is_wp_error($qr_image_response)) {
                error_log("Failed to download QR Code {$qr_code->id} from: {$qr_image_url}");
                continue; // Skip this QR code
            }
            $qr_image_data = wp_remote_retrieve_body($qr_image_response);
            
            if (empty($qr_image_data)) {
                error_log("QR Code {$qr_code->id} downloaded but data is empty");
                continue; // Skip if no data
            }
        }
        
        // Determine file extension
        $extension = 'png'; // Default to PNG
        if ($qr_image_path && file_exists($qr_image_path)) {
            $file_info = pathinfo($qr_image_path);
            $extension = isset($file_info['extension']) ? $file_info['extension'] : 'png';
        }
        
        // Create a clean filename - ensure uniqueness
        $clean_batch_code = preg_replace('/[^a-zA-Z0-9-_]/', '', $qr_code->batch_code);
        $file_name = 'qr-code-' . $clean_batch_code . '.' . $extension;
        
        // Ensure filename uniqueness within ZIP
        $counter = 1;
        $original_file_name = $file_name;
        while ($zip->locateName($file_name) !== false) {
            $file_name = str_replace('.' . $extension, '-' . $counter . '.' . $extension, $original_file_name);
            $counter++;
        }
        
        // Add file to ZIP - use addFromString for both local and remote files for consistency
        $file_content = null;
        
        if ($qr_image_path && file_exists($qr_image_path)) {
            $file_content = file_get_contents($qr_image_path);
            if ($file_content === false) {
                error_log("Failed to read file content: {$qr_image_path}");
                continue;
            }
        } else if ($qr_image_data) {
            $file_content = $qr_image_data;
        }
        
        if ($file_content) {
            $file_added = $zip->addFromString($file_name, $file_content);
            if ($file_added) {
                error_log("Added to ZIP: {$file_name} (" . strlen($file_content) . " bytes)");
            } else {
                error_log("Failed to add to ZIP: {$file_name} (ZipArchive error: " . $zip->getStatusString() . ")");
            }
        } else {
            error_log("No file content available for QR Code {$qr_code->id}");
        }
        
        if ($file_added) {
            $added_files++;
        } else {
            error_log("QR Code {$qr_code->id} ({$qr_code->batch_code}) could not be added to ZIP");
        }
    }
    
    // Close ZIP file
    $zip_close_result = $zip->close();
    
    if (!$zip_close_result) {
        error_log("Failed to close ZIP file: {$zip_path}");
        wp_send_json_error('Failed to create ZIP file properly.');
    }
    
    // Verify ZIP file was created and has content
    if (!file_exists($zip_path)) {
        error_log("ZIP file does not exist after creation: {$zip_path}");
        wp_send_json_error('ZIP file was not created.');
    }
    
    $zip_size = filesize($zip_path);
    error_log("ZIP file created: {$zip_path} ({$zip_size} bytes, {$added_files} files)");
    
    if ($added_files === 0) {
        unlink($zip_path);
        wp_send_json_error('No QR code images could be added to the ZIP file.');
    }
    
    if ($zip_size < 100) { // Very small ZIP files are likely empty or corrupted
        error_log("ZIP file suspiciously small: {$zip_size} bytes");
        unlink($zip_path);
        wp_send_json_error('ZIP file appears to be empty or corrupted.');
    }
    
    // Generate download URL
    $zip_url = $upload_dir['baseurl'] . '/vqr-temp/' . $zip_filename;
    
    // Schedule cleanup of temp file (after 1 hour)
    wp_schedule_single_event(time() + 3600, 'vqr_cleanup_temp_file', [$zip_path]);
    
    // Log the bulk download
    if (function_exists('error_log')) {
        error_log(sprintf(
            'Bulk QR Download: User %d downloaded %d QR codes as ZIP file %s',
            $user_id,
            $added_files,
            $zip_filename
        ));
    }
    
    wp_send_json_success([
        'download_url' => $zip_url,
        'filename' => $zip_filename,
        'qr_count' => $added_files,
        'message' => "Successfully created ZIP file with {$added_files} QR codes."
    ]);
}
add_action('wp_ajax_vqr_bulk_download_qr', 'vqr_ajax_bulk_download_qr');

/**
 * Cleanup temporary ZIP files
 */
function vqr_cleanup_temp_file($file_path) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}
add_action('vqr_cleanup_temp_file', 'vqr_cleanup_temp_file');

/**
 * Handle sticker order creation via AJAX
 */
function vqr_ajax_create_sticker_order() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    
    // Sanitize and validate inputs
    $qr_ids = isset($_POST['qr_ids']) ? array_map('intval', $_POST['qr_ids']) : array();
    $sticker_type = sanitize_text_field($_POST['sticker_type'] ?? 'standard');
    $shipping_name = sanitize_text_field($_POST['shipping_name'] ?? '');
    $shipping_email = sanitize_email($_POST['shipping_email'] ?? '');
    $shipping_address = sanitize_textarea_field($_POST['shipping_address'] ?? '');
    $shipping_city = sanitize_text_field($_POST['shipping_city'] ?? '');
    $shipping_state = sanitize_text_field($_POST['shipping_state'] ?? '');
    $shipping_zip = sanitize_text_field($_POST['shipping_zip'] ?? '');
    $shipping_country = sanitize_text_field($_POST['shipping_country'] ?? '');
    $notes = sanitize_textarea_field($_POST['order_notes'] ?? '');
    
    // Validation
    if (empty($qr_ids)) {
        wp_send_json_error('No QR codes selected for sticker order.');
    }
    
    if (count($qr_ids) > 100) {
        wp_send_json_error('Maximum 100 stickers can be ordered at once.');
    }
    
    if (empty($shipping_name)) {
        wp_send_json_error('Shipping name is required.');
    }
    
    if (empty($shipping_email) || !is_email($shipping_email)) {
        wp_send_json_error('Valid shipping email is required.');
    }
    
    if (empty($shipping_address)) {
        wp_send_json_error('Shipping address is required.');
    }
    
    if (empty($shipping_city)) {
        wp_send_json_error('Shipping city is required.');
    }
    
    if (empty($shipping_state)) {
        wp_send_json_error('Shipping state/province is required.');
    }
    
    if (empty($shipping_zip)) {
        wp_send_json_error('Shipping ZIP/postal code is required.');
    }
    
    if (empty($shipping_country)) {
        wp_send_json_error('Shipping country is required.');
    }
    
    // Validate sticker type and get pricing
    $sticker_types = [
        'standard' => 0.20,     // £0.20 per sticker
        'iridescent' => 0.50    // £0.50 per sticker
    ];
    
    if (!isset($sticker_types[$sticker_type])) {
        wp_send_json_error('Invalid sticker type selected.');
    }
    
    // Check if sticker type is in stock
    if (!vqr_is_sticker_in_stock($sticker_type)) {
        wp_send_json_error('The selected sticker type is currently out of stock. Please choose a different type or try again later.');
    }
    
    $unit_price = $sticker_types[$sticker_type];
    
    global $wpdb;
    $qr_table = $wpdb->prefix . 'vqr_codes';
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    try {
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        // Verify QR codes belong to user and get details
        $placeholders = implode(',', array_fill(0, count($qr_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT * FROM {$qr_table} WHERE id IN ({$placeholders}) AND user_id = %d",
            array_merge($qr_ids, [$user_id])
        );
        
        $qr_codes = $wpdb->get_results($query);
        
        if (empty($qr_codes)) {
            throw new Exception('No valid QR codes found for your account.');
        }
        
        if (count($qr_codes) !== count($qr_ids)) {
            throw new Exception('Some selected QR codes could not be found or do not belong to your account.');
        }
        
        // Check if any QR codes already have pending sticker orders
        $qr_code_ids = array_column($qr_codes, 'id');
        $existing_orders_query = $wpdb->prepare(
            "SELECT DISTINCT soi.qr_code_id, so.order_number 
             FROM {$order_items_table} soi 
             INNER JOIN {$orders_table} so ON soi.order_id = so.id 
             WHERE soi.qr_code_id IN ({$placeholders}) 
             AND so.status IN ('pending', 'processing')",
            $qr_code_ids
        );
        
        $existing_orders = $wpdb->get_results($existing_orders_query);
        
        if (!empty($existing_orders)) {
            $existing_qr_id = $existing_orders[0]->qr_code_id;
            $existing_order_number = $existing_orders[0]->order_number;
            throw new Exception("Some QR codes already have pending sticker orders (Order: {$existing_order_number}). Please wait for processing or cancel existing orders first.");
        }
        
        // Calculate pricing based on sticker type
        $qr_count = count($qr_codes);
        $total_amount = $qr_count * $unit_price;
        
        // Generate unique order number
        $order_number = 'STK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Ensure order number is unique
        $order_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$orders_table} WHERE order_number = %s",
            $order_number
        ));
        
        while ($order_exists > 0) {
            $order_number = 'STK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $order_exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$orders_table} WHERE order_number = %s",
                $order_number
            ));
        }
        
        // Create sticker order
        $order_result = $wpdb->insert(
            $orders_table,
            [
                'user_id' => $user_id,
                'order_number' => $order_number,
                'status' => 'processing',
                'qr_count' => $qr_count,
                'total_amount' => $total_amount,
                'shipping_name' => $shipping_name,
                'shipping_email' => $shipping_email,
                'shipping_address' => $shipping_address,
                'shipping_city' => $shipping_city,
                'shipping_state' => $shipping_state,
                'shipping_zip' => $shipping_zip,
                'shipping_country' => $shipping_country,
                'notes' => $notes,
                'created_at' => current_time('mysql')
            ],
            [
                '%d', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        if ($order_result === false) {
            throw new Exception('Failed to create sticker order.');
        }
        
        $order_id = $wpdb->insert_id;
        
        // Create order items for each QR code
        foreach ($qr_codes as $qr_code) {
            $item_result = $wpdb->insert(
                $order_items_table,
                [
                    'order_id' => $order_id,
                    'qr_code_id' => $qr_code->id,
                    'batch_code' => $qr_code->batch_code,
                    'sticker_type' => $sticker_type,
                    'quantity' => 1,
                    'unit_price' => $unit_price,
                    'created_at' => current_time('mysql')
                ],
                [
                    '%d', '%d', '%s', '%s', '%d', '%f', '%s'
                ]
            );
            
            if ($item_result === false) {
                throw new Exception('Failed to create order item for QR code: ' . $qr_code->batch_code);
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Send order confirmation email to customer
        vqr_send_order_confirmation_email($order_id);
        
        // Send new order notification to admin
        vqr_send_new_order_admin_email($order_id);
        
        // Log the order creation
        error_log(sprintf(
            'Sticker Order Created: User %d created order %s for %d QR codes (Total: $%.2f)',
            $user_id,
            $order_number,
            $qr_count,
            $total_amount
        ));
        
        wp_send_json_success([
            'message' => "Sticker order created successfully! Order number: {$order_number}",
            'order_number' => $order_number,
            'qr_count' => $qr_count,
            'total_amount' => $total_amount,
            'shipping_info' => [
                'name' => $shipping_name,
                'email' => $shipping_email,
                'address' => $shipping_address,
                'city' => $shipping_city,
                'state' => $shipping_state,
                'zip' => $shipping_zip,
                'country' => $shipping_country
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $wpdb->query('ROLLBACK');
        error_log('Sticker Order Error: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_vqr_create_sticker_order', 'vqr_ajax_create_sticker_order');

/**
 * Handle sticker order cancellation via AJAX
 */
function vqr_ajax_cancel_sticker_order() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in.');
    }
    
    $user_id = get_current_user_id();
    $order_id = intval($_POST['order_id'] ?? 0);
    
    if (empty($order_id)) {
        wp_send_json_error('Invalid order ID.');
    }
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    
    try {
        // Get order details and verify ownership
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table} WHERE id = %d AND user_id = %d",
            $order_id, $user_id
        ));
        
        if (!$order) {
            wp_send_json_error('Order not found or you do not have permission to cancel it.');
        }
        
        // Only allow cancellation of pending and processing orders (before shipping)
        if (!in_array($order->status, ['pending', 'processing'])) {
            wp_send_json_error("Cannot cancel order with status '{$order->status}'. Only pending and processing orders can be cancelled.");
        }
        
        // Update order status to cancelled
        $result = $wpdb->update(
            $orders_table,
            [
                'status' => 'cancelled',
                'updated_at' => current_time('mysql')
            ],
            [
                'id' => $order_id,
                'user_id' => $user_id
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        if ($result === false) {
            throw new Exception('Failed to cancel order.');
        }
        
        // Log the cancellation
        error_log(sprintf(
            'Sticker Order Cancelled: User %d cancelled order %s',
            $user_id,
            $order->order_number
        ));
        
        wp_send_json_success([
            'message' => "Order #{$order->order_number} has been cancelled successfully.",
            'order_number' => $order->order_number
        ]);
        
    } catch (Exception $e) {
        error_log('Sticker Order Cancellation Error: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}
add_action('wp_ajax_vqr_cancel_sticker_order', 'vqr_ajax_cancel_sticker_order');