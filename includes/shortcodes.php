<?php
/**
 * Shortcode functionality
 */

defined('ABSPATH') || exit;

/**
 * Shortcode to display up-to-date scan data by querying the DB on each hit.
 * Usage: [qr_scan_data]
 */
function vqr_display_scan_data() {
    // Require a qr_id in the URL
    if ( empty( $_GET['qr_id'] ) ) {
        return '<p>No QR Scan data available.</p>';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    $qr_id      = sanitize_text_field( $_GET['qr_id'] );

    // Fetch the latest scan_count and first_scanned_at
    $qr_code = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT scan_count, first_scanned_at
            FROM $table_name
            WHERE qr_key = %s",
            $qr_id
        )
    );

    if ( ! $qr_code ) {
        return '<p>Invalid QR code.</p>';
    }

    // Render the results
    ob_start();
    ?>
    <div class="qr-scan-info">
        <p><strong>Scan Count:</strong> <?php echo intval( $qr_code->scan_count ); ?></p>
        <p><strong>First Scanned At:</strong>
            <?php echo $qr_code->first_scanned_at
                ? esc_html( $qr_code->first_scanned_at )
                : 'Not scanned yet'; ?>
        </p>
    </div>
    <?php
    return ob_get_clean();
}
remove_shortcode( 'qr_scan_data' );
add_shortcode( 'qr_scan_data', 'vqr_display_scan_data' );

/**
 * Shortcode: [qr_batch_code]
 * Outputs the 8-char batch code for the current qr_id, or "UNKNOWN BATCH".
 */
function vqr_show_batch_code_shortcode() {
    if ( empty( $_GET['qr_id'] ) ) {
        return 'UNKNOWN BATCH';
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    $qr_key     = sanitize_text_field( $_GET['qr_id'] );

    $batch = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT batch_code 
               FROM $table_name 
              WHERE qr_key = %s",
            $qr_key
        )
    );

    if ( ! $batch ) {
        return 'UNKNOWN BATCH';
    }

    return esc_html( $batch );
}
add_shortcode( 'qr_batch_code', 'vqr_show_batch_code_shortcode' );
