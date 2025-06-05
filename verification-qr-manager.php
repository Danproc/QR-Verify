<?php
/*
Plugin Name: Verification QR Manager
Description: Generate and manage unique QR codes in WordPress.
Version: 1.1.13
Author: Dan Proctor
Website: https://thenorthern-web.co.uk/
*/

defined('ABSPATH') || exit;

// Define paths
define('VQR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VQR_PLUGIN_URL', plugin_dir_url(__FILE__));

// Simple PSR-4 loader for tc-lib-pdf
spl_autoload_register( function( $class ) {
    $prefix   = 'Com\\Tecnick\\Pdf\\';
    $base_dir = VQR_PLUGIN_DIR . 'libs/tc-lib-pdf-main/src/';

    // Only handle classes in the Com\Tecnick\Pdf namespace
    if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    // Convert namespace to file path
    $relative = substr( $class, strlen( $prefix ) );
    $file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Start session on init if needed (only on frontend)
add_action('init', function() {
    if (!is_admin() && !wp_doing_ajax() && !session_id()) {
        session_start();
    }
});

// Include required files
require_once VQR_PLUGIN_DIR . 'includes/database.php';
require_once VQR_PLUGIN_DIR . 'includes/strain-post-type.php';
require_once VQR_PLUGIN_DIR . 'includes/strain-ownership.php';
require_once VQR_PLUGIN_DIR . 'includes/strain-templates.php';
require_once VQR_PLUGIN_DIR . 'includes/admin-page-modern.php';
require_once VQR_PLUGIN_DIR . 'includes/qr-generator.php';
require_once VQR_PLUGIN_DIR . 'includes/qr-scanner.php';
require_once VQR_PLUGIN_DIR . 'includes/shortcodes.php';
require_once VQR_PLUGIN_DIR . 'includes/download-handlers.php';
require_once VQR_PLUGIN_DIR . 'includes/pdf-generator.php';

// Include user roles and capabilities
require_once VQR_PLUGIN_DIR . 'includes/user-roles.php';
require_once VQR_PLUGIN_DIR . 'includes/activation-helper.php';
require_once VQR_PLUGIN_DIR . 'includes/email-verification.php';
require_once VQR_PLUGIN_DIR . 'includes/terms-of-service.php';

// Include frontend system
require_once VQR_PLUGIN_DIR . 'includes/frontend-router.php';
require_once VQR_PLUGIN_DIR . 'includes/frontend-ajax.php';
require_once VQR_PLUGIN_DIR . 'includes/strain-ajax.php';

// Include admin quota management
require_once VQR_PLUGIN_DIR . 'includes/admin-quota-ajax.php';
require_once VQR_PLUGIN_DIR . 'includes/user-profile-integration.php';
require_once VQR_PLUGIN_DIR . 'includes/user-profile-image.php';
require_once VQR_PLUGIN_DIR . 'includes/account-deletion.php';

// Enqueue admin styles
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style( 'dashicons' );
    
    // Enqueue modern admin styles on QR Codes page
    $screen = get_current_screen();
    if ($screen && $screen->id === 'toplevel_page_verification_qr_manager') {
        wp_enqueue_style( 'vqr-admin-style', VQR_PLUGIN_URL . 'assets/admin-style.css', array(), '1.0.0' );
    }
} );

// Plugin activation hook
register_activation_hook( __FILE__, 'vqr_create_tables' );

// Check for database updates on plugins loaded
add_action( 'plugins_loaded', 'vqr_check_db_update' );

// Update email verification table schema
add_action( 'admin_init', function() {
    if (current_user_can('manage_options')) {
        vqr_update_email_verification_table();
    }
});

// One-time setup for user roles (will only run once)
add_action('admin_init', function() {
    if (current_user_can('manage_options') && !get_option('vqr_roles_setup_complete')) {
        vqr_force_setup_roles();
        update_option('vqr_roles_setup_complete', true);
    }
});

// Flush rewrite rules on activation (for frontend routing)
register_activation_hook( __FILE__, function() {
    vqr_create_tables();
    vqr_create_custom_roles();
    
    // Trigger legal pages creation
    do_action('vqr_plugin_activated');
    
    flush_rewrite_rules();
} );
