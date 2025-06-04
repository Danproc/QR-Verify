<?php
/**
 * Download handlers for QR codes
 */

defined('ABSPATH') || exit;

/**
 * Handle downloading of QR codes as ZIP
 */
function vqr_download_qr_codes() {
    if (isset($_POST['download_qr_codes']) && !empty($_POST['qr_ids'])) {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Verify nonce for security
        if (!check_admin_referer('vqr_bulk_action', 'vqr_bulk_action_nonce')) {
            wp_die('Security check failed. Please try again.');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vqr_codes';

        // Start output buffering
        ob_start();

        $qr_ids = array_map('intval', $_POST['qr_ids']);
        $ids_placeholder = implode(',', array_fill(0, count($qr_ids), '%d'));
        $qr_codes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE id IN ($ids_placeholder)", ...$qr_ids));

        if (!empty($qr_codes)) {
            $zip = new ZipArchive();
            $zip_filename = tempnam(sys_get_temp_dir(), 'qrcodes') . '.zip';
            if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
                foreach ($qr_codes as $code) {
                    $file_path = str_replace(home_url('/'), ABSPATH, $code->qr_code);
                    if (file_exists($file_path)) {
                        $zip->addFile($file_path, basename($file_path));
                    }
                }
                $zip->close();

                // Clear output buffering
                ob_clean();
                flush();

                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="qr_codes.zip"');
                header('Content-Length: ' . filesize($zip_filename));

                readfile($zip_filename);
                unlink($zip_filename);
                exit;
            }
        }
    }
}
add_action('admin_init', 'vqr_download_qr_codes');
