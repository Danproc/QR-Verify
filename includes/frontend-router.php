<?php
/**
 * Frontend SaaS Router System
 * Handles /app/ routing for the Verify 420 dashboard
 */

defined('ABSPATH') || exit;

/**
 * Initialize frontend routing system
 */
function vqr_init_frontend_router() {
    // Add rewrite rules for /app/ URLs
    add_rewrite_rule('^app/?$', 'index.php?vqr_app_page=dashboard', 'top');
    add_rewrite_rule('^app/test-analytics/?$', 'index.php?vqr_app_page=test-analytics', 'top');
    add_rewrite_rule('^app/preview/([0-9]+)/?$', 'index.php?vqr_app_page=preview&strain_id=$matches[1]', 'top');
    add_rewrite_rule('^app/([^/]+)/?$', 'index.php?vqr_app_page=$matches[1]', 'top');
    add_rewrite_rule('^app/([^/]+)/([^/]+)/?$', 'index.php?vqr_app_page=$matches[1]&vqr_app_subpage=$matches[2]', 'top');
    
    // Add query vars
    add_filter('query_vars', function($vars) {
        $vars[] = 'vqr_app_page';
        $vars[] = 'vqr_app_subpage';
        $vars[] = 'strain_id';
        return $vars;
    });
}
add_action('init', 'vqr_init_frontend_router');

/**
 * Handle frontend template routing
 */
function vqr_handle_frontend_routing() {
    $app_page = get_query_var('vqr_app_page');
    
    if ($app_page) {
        // Block wp-admin access for regular users on app pages
        if (is_admin() && !current_user_can('manage_options') && !wp_doing_ajax()) {
            wp_redirect(home_url('/app/'));
            exit;
        }
        
        // Handle app page routing
        vqr_load_app_page($app_page);
        exit;
    }
}
add_action('template_redirect', 'vqr_handle_frontend_routing');

/**
 * Load the appropriate app page
 */
function vqr_load_app_page($page) {
    // Security check - ensure user is logged in for protected pages
    $public_pages = ['login', 'register', 'forgot-password', 'verify-email'];
    
    if (!in_array($page, $public_pages) && !is_user_logged_in()) {
        wp_redirect(home_url('/app/login'));
        exit;
    }
    
    // Redirect admin users back to wp-admin
    if (current_user_can('manage_options') && !in_array($page, ['logout'])) {
        wp_redirect(admin_url('admin.php?page=verification_qr_manager'));
        exit;
    }
    
    // Handle special test page
    if ($page === 'test-analytics') {
        include VQR_PLUGIN_DIR . 'test-analytics-simple.php';
        return;
    }
    
    // Load page template
    $template_file = VQR_PLUGIN_DIR . 'frontend/pages/' . sanitize_file_name($page) . '.php';
    
    if (file_exists($template_file)) {
        include $template_file;
    } else {
        // Default to dashboard
        include VQR_PLUGIN_DIR . 'frontend/pages/dashboard.php';
    }
}

/**
 * Enqueue frontend styles and scripts
 */
function vqr_enqueue_frontend_assets() {
    if (get_query_var('vqr_app_page')) {
        // Enqueue Verify 420 design system
        wp_enqueue_style('vqr-app-style', VQR_PLUGIN_URL . 'frontend/assets/app.css', array(), '1.0.0');
        wp_enqueue_script('vqr-app-script', VQR_PLUGIN_URL . 'frontend/assets/app.js', array('jquery'), '1.0.0', true);
        
        // Add AJAX URL for frontend
        wp_localize_script('vqr-app-script', 'vqr_ajax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vqr_frontend_nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'vqr_enqueue_frontend_assets');

// Admin access blocking is now handled in user-roles.php

/**
 * Redirect after login
 */
function vqr_redirect_after_login($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) {
            return admin_url('admin.php?page=verification_qr_manager');
        } else {
            return home_url('/app/');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'vqr_redirect_after_login', 10, 3);

/**
 * Flush rewrite rules on activation
 */
function vqr_flush_frontend_rules() {
    vqr_init_frontend_router();
    flush_rewrite_rules();
}
register_activation_hook(VQR_PLUGIN_DIR . 'verification-qr-manager.php', 'vqr_flush_frontend_rules');