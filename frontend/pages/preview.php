<?php
/**
 * Strain Preview page for Verify 420 SaaS - Standalone Display
 */

defined('ABSPATH') || exit;

// Get strain ID from URL
$strain_id = intval(get_query_var('strain_id'));
$user_id = get_current_user_id();

// Security check - verify user owns the strain
if (!vqr_user_can_manage_strain($strain_id, $user_id)) {
    wp_die('You do not have permission to preview this strain.');
}

$strain = get_post($strain_id);
if (!$strain || $strain->post_type !== 'strain') {
    wp_die('Strain not found.');
}

// Create fake QR data for preview
$qr_code = (object) [
    'post_id' => $strain_id,
    'scan_count' => 1,
    'first_scanned_at' => current_time('mysql'),
    'qr_key' => 'preview-mode'
];

// Get strain data using the existing function
$strain_data = vqr_get_strain_data($strain_id, $qr_code, true);

// Set preview mode
$is_preview = true;

// Enqueue the original frontend styles
wp_enqueue_style('vqr-frontend', VQR_PLUGIN_URL . 'assets/frontend-style.css', array(), '1.0.0');

// Load the original strain display template directly (standalone)
include VQR_PLUGIN_DIR . 'templates/strain-display.php';
?>