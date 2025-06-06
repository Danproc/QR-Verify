<?php
/**
 * Admin AJAX Handlers for User Quota Management
 */

defined('ABSPATH') || exit;

/**
 * AJAX handler to get user quota information
 */
function vqr_ajax_admin_get_user_quota() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to manage user quotas.');
    }
    
    $user_id = intval($_POST['user_id']);
    if (!$user_id) {
        wp_send_json_error('Invalid user ID.');
    }
    
    $quota_info = vqr_admin_get_user_quota_info($user_id);
    
    if (is_wp_error($quota_info)) {
        wp_send_json_error($quota_info->get_error_message());
    }
    
    wp_send_json_success($quota_info);
}
add_action('wp_ajax_vqr_admin_get_user_quota', 'vqr_ajax_admin_get_user_quota');

/**
 * AJAX handler to set user quota
 */
function vqr_ajax_admin_set_quota() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to manage user quotas.');
    }
    
    $user_id = intval($_POST['user_id']);
    $quota = intval($_POST['quota']);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID.');
    }
    
    $result = vqr_admin_set_user_quota($user_id, $quota);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Get updated user info
    $quota_info = vqr_admin_get_user_quota_info($user_id);
    
    wp_send_json_success([
        'message' => $quota === -1 ? 'User quota set to unlimited.' : "User quota set to {$quota} QR codes.",
        'quota_info' => $quota_info
    ]);
}
add_action('wp_ajax_vqr_admin_set_quota', 'vqr_ajax_admin_set_quota');

/**
 * AJAX handler to set user usage
 */
function vqr_ajax_admin_set_usage() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to manage user usage.');
    }
    
    $user_id = intval($_POST['user_id']);
    $usage = intval($_POST['usage']);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID.');
    }
    
    $result = vqr_admin_set_user_usage($user_id, $usage);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Get updated user info
    $quota_info = vqr_admin_get_user_quota_info($user_id);
    
    wp_send_json_success([
        'message' => "User usage set to {$usage} QR codes.",
        'quota_info' => $quota_info
    ]);
}
add_action('wp_ajax_vqr_admin_set_usage', 'vqr_ajax_admin_set_usage');

/**
 * AJAX handler to reset user usage
 */
function vqr_ajax_admin_reset_usage() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to reset user usage.');
    }
    
    $user_id = intval($_POST['user_id']);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID.');
    }
    
    $result = vqr_admin_reset_user_usage($user_id);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Get updated user info
    $quota_info = vqr_admin_get_user_quota_info($user_id);
    
    wp_send_json_success([
        'message' => 'User usage reset to 0.',
        'quota_info' => $quota_info
    ]);
}
add_action('wp_ajax_vqr_admin_reset_usage', 'vqr_ajax_admin_reset_usage');

/**
 * AJAX handler to add quota tokens
 */
function vqr_ajax_admin_add_tokens() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to manage user quotas.');
    }
    
    $user_id = intval($_POST['user_id']);
    $tokens = intval($_POST['tokens']);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID.');
    }
    
    $result = vqr_admin_add_quota_tokens($user_id, $tokens);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Get updated user info
    $quota_info = vqr_admin_get_user_quota_info($user_id);
    
    if ($result === -1) {
        $message = 'User already has unlimited quota.';
    } else {
        $action = $tokens >= 0 ? 'added' : 'removed';
        $abs_tokens = abs($tokens);
        $message = "{$abs_tokens} quota tokens {$action}. New quota: {$result}";
    }
    
    wp_send_json_success([
        'message' => $message,
        'quota_info' => $quota_info
    ]);
}
add_action('wp_ajax_vqr_admin_add_tokens', 'vqr_ajax_admin_add_tokens');

/**
 * AJAX handler to search for users
 */
function vqr_ajax_admin_search_users() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_nonce')) {
        wp_send_json_error('Security check failed.');
    }
    
    // Check admin permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to search users.');
    }
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    
    if (strlen($search) < 2) {
        wp_send_json_error('Search term must be at least 2 characters.');
    }
    
    // Search for QR customer users
    $users = get_users([
        'search' => '*' . $search . '*',
        'search_columns' => ['user_login', 'user_email', 'display_name'],
        'role__in' => ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'],
        'number' => 20
    ]);
    
    $user_results = [];
    foreach ($users as $user) {
        $user_results[] = [
            'id' => $user->ID,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'subscription_plan' => get_user_meta($user->ID, 'vqr_subscription_plan', true) ?: 'free',
            'current_usage' => vqr_get_user_usage($user->ID),
            'monthly_quota' => vqr_get_user_quota($user->ID)
        ];
    }
    
    wp_send_json_success([
        'users' => $user_results,
        'total' => count($user_results)
    ]);
}
add_action('wp_ajax_vqr_admin_search_users', 'vqr_ajax_admin_search_users');