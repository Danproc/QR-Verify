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

// Start session if needed
if (!session_id()) {
    session_start();
}

// Include required files
require_once VQR_PLUGIN_DIR . 'includes/database.php';
require_once VQR_PLUGIN_DIR . 'includes/admin-page.php';
require_once VQR_PLUGIN_DIR . 'includes/qr-generator.php';
require_once VQR_PLUGIN_DIR . 'includes/qr-scanner.php';
require_once VQR_PLUGIN_DIR . 'includes/shortcodes.php';
require_once VQR_PLUGIN_DIR . 'includes/download-handlers.php';
require_once VQR_PLUGIN_DIR . 'includes/pdf-generator.php';

// Enqueue admin styles
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style( 'dashicons' );
} );

// Plugin activation hook
register_activation_hook( __FILE__, 'vqr_create_tables' );

// Check for database updates on plugins loaded
add_action( 'plugins_loaded', 'vqr_check_db_update' );
