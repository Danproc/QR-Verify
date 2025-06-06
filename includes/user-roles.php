<?php
/**
 * Custom User Roles and Capabilities for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Create custom user roles on plugin activation
 */
function vqr_create_custom_roles() {
    // Remove default subscriber role capabilities that we don't want
    $subscriber = get_role('subscriber');
    if ($subscriber) {
        $subscriber->remove_cap('read');
    }
    
    // Add QR Customer role (Free plan)
    add_role('qr_customer_free', 'QR Customer (Free)', array(
        'read' => false, // Prevent access to admin dashboard
        'vqr_generate_codes' => true,
        'vqr_view_analytics' => true,
        'vqr_manage_account' => true,
    ));
    
    // Add QR Customer Starter role
    add_role('qr_customer_starter', 'QR Customer (Starter)', array(
        'read' => false,
        'vqr_generate_codes' => true,
        'vqr_view_analytics' => true,
        'vqr_manage_account' => true,
        'vqr_advanced_analytics' => true,
    ));
    
    // Add QR Customer Pro role
    add_role('qr_customer_pro', 'QR Customer (Pro)', array(
        'read' => false,
        'vqr_generate_codes' => true,
        'vqr_view_analytics' => true,
        'vqr_manage_account' => true,
        'vqr_advanced_analytics' => true,
        'vqr_api_access' => true,
        'vqr_white_label' => true,
    ));
    
    // Add QR Customer Enterprise role
    add_role('qr_customer_enterprise', 'QR Customer (Enterprise)', array(
        'read' => false,
        'vqr_generate_codes' => true,
        'vqr_view_analytics' => true,
        'vqr_manage_account' => true,
        'vqr_advanced_analytics' => true,
        'vqr_api_access' => true,
        'vqr_white_label' => true,
        'vqr_unlimited_codes' => true,
        'vqr_priority_support' => true,
    ));
}

/**
 * Set default role for new registrations
 */
function vqr_set_default_role($user_id) {
    $user = new WP_User($user_id);
    $user->set_role('qr_customer_free');
    
    // Set initial user meta
    add_user_meta($user_id, 'vqr_subscription_plan', 'free');
    add_user_meta($user_id, 'vqr_monthly_quota', 50);
    add_user_meta($user_id, 'vqr_current_usage', 0);
    add_user_meta($user_id, 'vqr_registration_date', current_time('mysql'));
    add_user_meta($user_id, 'vqr_last_quota_reset', date('Y-m-01')); // First of current month
}
add_action('user_register', 'vqr_set_default_role');

/**
 * Hide admin bar for QR customers
 */
function vqr_hide_admin_bar() {
    if (!current_user_can('manage_options')) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'vqr_hide_admin_bar');

/**
 * Redirect QR customers away from wp-admin
 */
function vqr_redirect_customers_from_admin() {
    if (is_admin() && !current_user_can('manage_options') && !wp_doing_ajax()) {
        wp_redirect(home_url('/app/'));
        exit;
    }
}
add_action('admin_init', 'vqr_redirect_customers_from_admin');

/**
 * Remove admin bar CSS for QR customers
 */
function vqr_remove_admin_bar_css() {
    if (!current_user_can('manage_options')) {
        remove_action('wp_head', '_admin_bar_bump_cb');
    }
}
add_action('get_header', 'vqr_remove_admin_bar_css');

/**
 * Check if user has specific QR capability
 */
function vqr_user_can($capability) {
    return current_user_can($capability);
}

/**
 * Get user's subscription plan
 */
function vqr_get_user_plan($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return 'free';
    }
    
    return get_user_meta($user_id, 'vqr_subscription_plan', true) ?: 'free';
}

/**
 * Get user's monthly quota
 */
function vqr_get_user_quota($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if admin has set a custom quota
    $custom_quota = get_user_meta($user_id, 'vqr_monthly_quota', true);
    if ($custom_quota !== '') {
        return intval($custom_quota);
    }
    
    // Fall back to plan-based quotas
    $plan = vqr_get_user_plan($user_id);
    
    $quotas = array(
        'free' => 50,
        'starter' => 300,
        'pro' => 2500,
        'enterprise' => -1 // Unlimited
    );
    
    return $quotas[$plan] ?? 50;
}

/**
 * Get user's current usage for this month
 */
function vqr_get_user_usage($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if we need to reset monthly usage
    $last_reset = get_user_meta($user_id, 'vqr_last_quota_reset', true);
    $current_month = date('Y-m-01');
    
    if ($last_reset !== $current_month) {
        // Reset usage for new month
        update_user_meta($user_id, 'vqr_current_usage', 0);
        update_user_meta($user_id, 'vqr_last_quota_reset', $current_month);
        return 0;
    }
    
    return (int) get_user_meta($user_id, 'vqr_current_usage', true);
}

/**
 * Check if user can generate more QR codes
 */
function vqr_user_can_generate($quantity = 1, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $quota = vqr_get_user_quota($user_id);
    $usage = vqr_get_user_usage($user_id);
    
    // Unlimited plan
    if ($quota === -1) {
        return true;
    }
    
    return ($usage + $quantity) <= $quota;
}

/**
 * Increment user's QR code usage
 */
function vqr_increment_user_usage($quantity, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $current_usage = vqr_get_user_usage($user_id);
    $new_usage = $current_usage + $quantity;
    
    update_user_meta($user_id, 'vqr_current_usage', $new_usage);
    
    return $new_usage;
}

/**
 * Upgrade user's subscription plan
 */
function vqr_upgrade_user_plan($user_id, $new_plan) {
    $valid_plans = array('free', 'starter', 'pro', 'enterprise');
    
    if (!in_array($new_plan, $valid_plans)) {
        return new WP_Error('invalid_plan', 'Invalid subscription plan.');
    }
    
    $user = new WP_User($user_id);
    
    // Update user role based on plan
    $role_map = array(
        'free' => 'qr_customer_free',
        'starter' => 'qr_customer_starter',
        'pro' => 'qr_customer_pro',
        'enterprise' => 'qr_customer_enterprise'
    );
    
    $user->set_role($role_map[$new_plan]);
    
    // Update user meta for backward compatibility
    update_user_meta($user_id, 'vqr_subscription_plan', $new_plan);
    update_user_meta($user_id, 'vqr_plan_upgraded_date', current_time('mysql'));
    
    // Update quota based on new plan
    $plan_quotas = array(
        'free' => 50,
        'starter' => 300,
        'pro' => 2500,
        'enterprise' => -1 // Unlimited
    );
    
    update_user_meta($user_id, 'vqr_monthly_quota', $plan_quotas[$new_plan]);
    
    return true;
}

/**
 * Get user's plan details
 */
function vqr_get_plan_details($plan) {
    $plans = array(
        'free' => array(
            'name' => 'Free',
            'price' => 0,
            'quota' => 50,
            'features' => array(
                '50 QR codes per month',
                'Scan tracking',
                'Email support',
                'Verify 420 branding only'
            ),
            'locked_features' => array(
                'Analytics page access',
                'Advanced analytics',
                'QR code resetting',
                'Bulk ZIP downloads',
                'Custom logo uploads'
            )
        ),
        'starter' => array(
            'name' => 'Starter',
            'price' => 49,
            'quota' => 300,
            'features' => array(
                '300 QR codes per month',
                'Scan tracking',
                'Basic analytics',
                'Custom branding',
                'Bulk QR code downloads'
            ),
            'locked_features' => array(
                'Advanced analytics',
                'QR code resetting'
            )
        ),
        'pro' => array(
            'name' => 'Pro',
            'price' => 99,
            'quota' => 2500,
            'features' => array(
                '2,500 QR codes per month',
                'Advanced analytics',
                'Reset QR codes',
                'Priority email support',
                'All Starter features'
            ),
            'locked_features' => array()
        ),
        'enterprise' => array(
            'name' => 'Enterprise',
            'price' => 399,
            'quota' => -1,
            'features' => array(
                'Unlimited QR codes',
                'Custom website integration',
                'Dedicated account manager',
                'All Pro features',
                'Priority support'
            ),
            'locked_features' => array(),
            'requires_sales_contact' => true
        )
    );
    
    return $plans[$plan] ?? $plans['free'];
}

/**
 * Feature access control functions
 */

/**
 * Check if user can access analytics page
 */
function vqr_user_can_access_analytics($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return $plan !== 'free';
}

/**
 * Check if user can access advanced analytics
 */
function vqr_user_can_access_advanced_analytics($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return in_array($plan, ['pro', 'enterprise']);
}

/**
 * Check if user can access security analytics
 */
function vqr_user_can_access_security_analytics($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return in_array($plan, ['pro', 'enterprise']);
}

/**
 * Check if user can access geographic analytics
 */
function vqr_user_can_access_geographic_analytics($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return in_array($plan, ['pro', 'enterprise']);
}

/**
 * Check if user can reset QR codes
 */
function vqr_user_can_reset_qr_codes($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return in_array($plan, ['pro', 'enterprise']);
}

/**
 * Check if user can delete QR codes
 */
function vqr_user_can_delete_qr_codes($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return in_array($plan, ['pro', 'enterprise']);
}

/**
 * Check if user can download bulk ZIP files
 */
function vqr_user_can_download_bulk_zip($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return $plan !== 'free';
}

/**
 * Check if user can upload custom logos
 */
function vqr_user_can_upload_custom_logo($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    return $plan !== 'free';
}

/**
 * Check if plan requires sales contact (Enterprise)
 */
function vqr_plan_requires_sales_contact($plan) {
    $plan_details = vqr_get_plan_details($plan);
    return isset($plan_details['requires_sales_contact']) && $plan_details['requires_sales_contact'];
}

/**
 * Get upgrade URL or sales contact info for locked features
 */
function vqr_get_upgrade_info($feature_name, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $plan = vqr_get_user_plan($user_id);
    
    $upgrade_messages = array(
        'analytics' => array(
            'title' => 'Analytics Locked',
            'message' => 'Upgrade to Starter plan or higher to access analytics.',
            'upgrade_plan' => 'starter'
        ),
        'security_analytics' => array(
            'title' => 'Security Analytics Locked',
            'message' => 'Upgrade to Pro plan to access advanced security analytics.',
            'upgrade_plan' => 'pro'
        ),
        'geographic_analytics' => array(
            'title' => 'Geographic Analytics Locked',
            'message' => 'Upgrade to Pro plan to access geographic analytics.',
            'upgrade_plan' => 'pro'
        ),
        'advanced_analytics' => array(
            'title' => 'Advanced Analytics Locked',
            'message' => 'Upgrade to Pro plan to access advanced analytics features.',
            'upgrade_plan' => 'pro'
        ),
        'reset_qr_codes' => array(
            'title' => 'QR Reset Locked',
            'message' => 'Upgrade to Pro plan to reset QR codes.',
            'upgrade_plan' => 'pro'
        ),
        'delete_qr_codes' => array(
            'title' => 'QR Delete Locked',
            'message' => 'Upgrade to Pro plan to delete individual or bulk QR codes.',
            'upgrade_plan' => 'pro'
        ),
        'bulk_zip' => array(
            'title' => 'Bulk Download Locked',
            'message' => 'Upgrade to Starter plan or higher for bulk ZIP downloads.',
            'upgrade_plan' => 'starter'
        ),
        'custom_logo' => array(
            'title' => 'Custom Logo Locked',
            'message' => 'Upgrade to Starter plan or higher to upload custom logos.',
            'upgrade_plan' => 'starter'
        )
    );
    
    return $upgrade_messages[$feature_name] ?? array(
        'title' => 'Feature Locked',
        'message' => 'Upgrade your plan to access this feature.',
        'upgrade_plan' => 'starter'
    );
}

/**
 * Synchronize user roles with their subscription plan meta
 * Useful for fixing users who have the wrong role
 */
function vqr_sync_user_role_with_plan($user_id) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    // Get plan from meta field (fallback method)
    $plan_from_meta = get_user_meta($user_id, 'vqr_subscription_plan', true) ?: 'free';
    
    // Map plan to role
    $role_map = array(
        'free' => 'qr_customer_free',
        'starter' => 'qr_customer_starter',
        'pro' => 'qr_customer_pro',
        'enterprise' => 'qr_customer_enterprise'
    );
    
    $expected_role = $role_map[$plan_from_meta];
    
    // Check if user already has the correct role
    if (in_array($expected_role, $user->roles)) {
        return true; // Already synchronized
    }
    
    // Update the user's role
    $user->set_role($expected_role);
    
    // Update quota to match plan
    $plan_quotas = array(
        'free' => 50,
        'starter' => 300,
        'pro' => 2500,
        'enterprise' => -1
    );
    
    update_user_meta($user_id, 'vqr_monthly_quota', $plan_quotas[$plan_from_meta]);
    
    return true;
}

/**
 * Force setup of user roles (admin utility function)
 */
function vqr_force_setup_roles() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Recreate all QR customer roles
    vqr_create_custom_roles();
    
    // Sync all QR customers with their correct roles
    $qr_customers = get_users(array(
        'meta_key' => 'vqr_subscription_plan',
        'meta_compare' => 'EXISTS'
    ));
    
    $synced_count = 0;
    foreach ($qr_customers as $user) {
        if (vqr_sync_user_role_with_plan($user->ID)) {
            $synced_count++;
        }
    }
    
    return $synced_count;
}

/**
 * Simple function to sync a user's meta based on their WordPress role
 */
function vqr_sync_user_meta_from_role($user_id) {
    $user = get_user_by('ID', $user_id);
    if (!$user || empty($user->roles)) {
        return false;
    }
    
    $role_to_plan = array(
        'qr_customer_enterprise' => 'enterprise',
        'qr_customer_pro' => 'pro', 
        'qr_customer_starter' => 'starter',
        'qr_customer_free' => 'free'
    );
    
    foreach ($user->roles as $role) {
        if (isset($role_to_plan[$role])) {
            update_user_meta($user_id, 'vqr_subscription_plan', $role_to_plan[$role]);
            return true;
        }
    }
    
    return false;
}

/**
 * Sync all users (simplified version)
 */
function vqr_sync_all_users() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    $qr_customers = get_users(array(
        'role__in' => array('qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'),
    ));
    
    $synced_count = 0;
    foreach ($qr_customers as $user) {
        if (vqr_sync_user_meta_from_role($user->ID)) {
            $synced_count++;
        }
    }
    
    return $synced_count;
}

/**
 * Admin function to update user plan (includes role and meta sync)
 */
function vqr_admin_update_user_plan($user_id, $new_plan) {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    return vqr_upgrade_user_plan($user_id, $new_plan);
}

/**
 * Remove QR customer roles on plugin deactivation
 */
function vqr_remove_custom_roles() {
    remove_role('qr_customer_free');
    remove_role('qr_customer_starter');
    remove_role('qr_customer_pro');
    remove_role('qr_customer_enterprise');
}

/**
 * Admin functions for managing user quotas
 */

/**
 * Set user's monthly quota (admin function)
 */
function vqr_admin_set_user_quota($user_id, $quota) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('no_permission', 'You do not have permission to manage user quotas.');
    }
    
    $quota = intval($quota);
    if ($quota < -1) {
        return new WP_Error('invalid_quota', 'Quota must be -1 (unlimited) or a positive number.');
    }
    
    update_user_meta($user_id, 'vqr_monthly_quota', $quota);
    update_user_meta($user_id, 'vqr_quota_updated_by', get_current_user_id());
    update_user_meta($user_id, 'vqr_quota_updated_date', current_time('mysql'));
    
    return $quota;
}

/**
 * Set user's current usage (admin function)
 */
function vqr_admin_set_user_usage($user_id, $usage) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('no_permission', 'You do not have permission to manage user usage.');
    }
    
    $usage = max(0, intval($usage));
    update_user_meta($user_id, 'vqr_current_usage', $usage);
    update_user_meta($user_id, 'vqr_usage_updated_by', get_current_user_id());
    update_user_meta($user_id, 'vqr_usage_updated_date', current_time('mysql'));
    
    return $usage;
}

/**
 * Reset user's monthly usage to 0 (admin function)
 */
function vqr_admin_reset_user_usage($user_id) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('no_permission', 'You do not have permission to reset user usage.');
    }
    
    update_user_meta($user_id, 'vqr_current_usage', 0);
    update_user_meta($user_id, 'vqr_last_quota_reset', current_time('Y-m-d'));
    update_user_meta($user_id, 'vqr_usage_reset_by', get_current_user_id());
    update_user_meta($user_id, 'vqr_usage_reset_date', current_time('mysql'));
    
    return true;
}

/**
 * Add quota tokens to user's current quota (admin function)
 */
function vqr_admin_add_quota_tokens($user_id, $tokens) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('no_permission', 'You do not have permission to manage user quotas.');
    }
    
    $tokens = intval($tokens);
    $current_quota = vqr_get_user_quota($user_id);
    
    // Handle unlimited quota
    if ($current_quota === -1) {
        return -1; // Already unlimited
    }
    
    $new_quota = $current_quota + $tokens;
    if ($new_quota < 0) {
        $new_quota = 0;
    }
    
    update_user_meta($user_id, 'vqr_monthly_quota', $new_quota);
    update_user_meta($user_id, 'vqr_quota_updated_by', get_current_user_id());
    update_user_meta($user_id, 'vqr_quota_updated_date', current_time('mysql'));
    
    return $new_quota;
}

/**
 * Get comprehensive user quota information for admin
 */
function vqr_admin_get_user_quota_info($user_id) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('no_permission', 'You do not have permission to view user quota information.');
    }
    
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.');
    }
    
    return array(
        'user_id' => $user_id,
        'username' => $user->user_login,
        'email' => $user->user_email,
        'display_name' => $user->display_name,
        'subscription_plan' => get_user_meta($user_id, 'vqr_subscription_plan', true) ?: 'free',
        'monthly_quota' => vqr_get_user_quota($user_id),
        'current_usage' => vqr_get_user_usage($user_id),
        'remaining_quota' => vqr_get_user_quota($user_id) === -1 ? 'Unlimited' : (vqr_get_user_quota($user_id) - vqr_get_user_usage($user_id)),
        'last_quota_reset' => get_user_meta($user_id, 'vqr_last_quota_reset', true),
        'quota_updated_by' => get_user_meta($user_id, 'vqr_quota_updated_by', true),
        'quota_updated_date' => get_user_meta($user_id, 'vqr_quota_updated_date', true),
        'usage_updated_by' => get_user_meta($user_id, 'vqr_usage_updated_by', true),
        'usage_updated_date' => get_user_meta($user_id, 'vqr_usage_updated_date', true),
    );
}

// Hook for plugin activation
add_action('vqr_plugin_activated', 'vqr_create_custom_roles');

// Hook for plugin deactivation
register_deactivation_hook(VQR_PLUGIN_DIR . 'verification-qr-manager.php', 'vqr_remove_custom_roles');