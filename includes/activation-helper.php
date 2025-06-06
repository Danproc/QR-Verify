<?php
/**
 * Plugin Activation Helper
 * Run this once to set up the new user role system
 */

defined('ABSPATH') || exit;

/**
 * Helper function to upgrade existing users to QR customer roles
 */
function vqr_upgrade_existing_users() {
    // Update existing users to use new role system
    $users = get_users(array(
        'role__in' => array('subscriber', 'customer') // Get users who might need role updates
    ));
    
    foreach ($users as $user) {
        // Skip admins
        if (user_can($user->ID, 'manage_options')) {
            continue;
        }
        
        // Update to QR customer role
        $user_obj = new WP_User($user->ID);
        $user_obj->set_role('qr_customer_free');
        
        // Set user meta if not exists
        if (!get_user_meta($user->ID, 'vqr_subscription_plan', true)) {
            add_user_meta($user->ID, 'vqr_subscription_plan', 'free');
            add_user_meta($user->ID, 'vqr_monthly_quota', 50);
            add_user_meta($user->ID, 'vqr_current_usage', 0);
            add_user_meta($user->ID, 'vqr_registration_date', current_time('mysql'));
            add_user_meta($user->ID, 'vqr_last_quota_reset', date('Y-m-01'));
        }
    }
    
    return count($users) . ' users updated to new role system.';
}

// Uncomment the line below and visit any page to trigger setup
// add_action('init', function() { if (current_user_can('manage_options')) { echo vqr_upgrade_existing_users(); } });