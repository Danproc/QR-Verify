<?php
/**
 * Database management functions
 */

defined('ABSPATH') || exit;

/**
 * Create database table on plugin activation
 */
function vqr_create_tables() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'vqr_codes';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id               BIGINT(20) NOT NULL AUTO_INCREMENT,
        qr_key           VARCHAR(64)  NOT NULL,
        qr_code          VARCHAR(255) NOT NULL,
        url              VARCHAR(255) NOT NULL,
        batch_code       VARCHAR(8)   NOT NULL,
        category         VARCHAR(100) DEFAULT '',
        scan_count       INT          DEFAULT 0,
        created_at       DATETIME     DEFAULT CURRENT_TIMESTAMP,
        first_scanned_at DATETIME     DEFAULT NULL,
        post_id          BIGINT(20)   DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Update database version
    update_option( 'vqr_db_version', '1.6' );
}

/**
 * Check for database updates on plugins loaded
 */
function vqr_check_db_update() {
    if ( get_option( 'vqr_db_version' ) !== '1.6' ) {
        vqr_create_tables();
    }
}
