<?php
/**
 * QR Code scanning functionality
 */

defined('ABSPATH') || exit;

/**
 * Handle QR code scans on every URL hit
 */
function vqr_handle_qr_scan() {
    if ( empty( $_GET['qr_id'] ) ) {
        return;
    }

    global $wpdb;
    $table_name   = $wpdb->prefix . 'vqr_codes';
    $qr_id        = sanitize_text_field( $_GET['qr_id'] );
    $current_time = current_time( 'mysql' );

    // Fetch exactly the row for this key
    $qr_code = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE qr_key = %s",
            $qr_id
        )
    );

    if ( ! $qr_code ) {
        return;
    }

    // Log security scan data BEFORE updating count
    if (function_exists('vqr_log_security_scan')) {
        $security_result = vqr_log_security_scan($qr_id, $qr_code->post_id);
        // Debug: Log if security logging failed
        if ($security_result === false) {
            error_log("VQR Security: Failed to log scan for QR key: " . $qr_id);
        }
    } else {
        error_log("VQR Security: vqr_log_security_scan function not found");
    }

    // Bump the count
    $new_count = $qr_code->scan_count + 1;
    $wpdb->update(
        $table_name,
        [
            'scan_count'       => $new_count,
            'first_scanned_at' => is_null( $qr_code->first_scanned_at )
                ? $current_time
                : $qr_code->first_scanned_at,
        ],
        [ 'id' => $qr_code->id ],
        [ '%d', '%s' ],
        [ '%d' ]
    );

    // Mirror into post-meta so Bricks (and your shortcode) can pull it
    if ( ! empty( $qr_code->post_id ) ) {
        update_post_meta( $qr_code->post_id, 'scan_count', $new_count );
        if ( is_null( $qr_code->first_scanned_at ) ) {
            update_post_meta( $qr_code->post_id, 'first_scanned_at', $current_time );
        }
    }
}
add_action( 'template_redirect', 'vqr_handle_qr_scan', 5 );
