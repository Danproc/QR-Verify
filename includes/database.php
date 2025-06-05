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
    $charset_collate = $wpdb->get_charset_collate();

    // Create QR codes table
    $qr_table_name = $wpdb->prefix . 'vqr_codes';
    $qr_sql = "CREATE TABLE $qr_table_name (
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
        user_id          BIGINT(20)   DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    // Create email verification table
    $verification_table_name = $wpdb->prefix . 'vqr_email_verification';
    $verification_sql = "CREATE TABLE $verification_table_name (
        id          BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) NOT NULL,
        token       VARCHAR(64) NOT NULL,
        email       VARCHAR(100) NOT NULL,
        verification_type VARCHAR(20) DEFAULT 'signup',
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at  DATETIME NOT NULL,
        verified_at DATETIME DEFAULT NULL,
        resent_count INT DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY user_id (user_id),
        KEY email (email),
        KEY verification_type (verification_type),
        KEY expires_at (expires_at)
    ) $charset_collate;";

    // Create Terms of Service acceptance table
    $tos_table_name = $wpdb->prefix . 'vqr_tos_acceptance';
    $tos_sql = "CREATE TABLE $tos_table_name (
        id          BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id     BIGINT(20) NOT NULL,
        tos_version VARCHAR(20) NOT NULL,
        ip_address  VARCHAR(45) NOT NULL,
        user_agent  TEXT,
        accepted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY tos_version (tos_version),
        KEY accepted_at (accepted_at)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $qr_sql );
    dbDelta( $verification_sql );
    dbDelta( $tos_sql );

    // Update database version
    update_option( 'vqr_db_version', '2.0' );
}

/**
 * Check for database updates on plugins loaded
 */
function vqr_check_db_update() {
    if ( get_option( 'vqr_db_version' ) !== '2.0' ) {
        vqr_create_tables();
    }
}

/**
 * Update existing email verification table to add verification_type column
 */
function vqr_update_email_verification_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    
    // Check if verification_type column exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s 
         AND TABLE_NAME = %s 
         AND COLUMN_NAME = 'verification_type'",
        DB_NAME,
        $table_name
    ));
    
    // Add column if it doesn't exist
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN verification_type VARCHAR(20) DEFAULT 'signup' AFTER email");
        $wpdb->query("ALTER TABLE {$table_name} ADD KEY verification_type (verification_type)");
    }
}
