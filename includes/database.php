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
    
    // Create Security Scans table for enhanced analytics
    $security_scans_table = $wpdb->prefix . 'vqr_security_scans';
    $security_scans_sql = "CREATE TABLE $security_scans_table (
        id              BIGINT(20) NOT NULL AUTO_INCREMENT,
        qr_key          VARCHAR(64) NOT NULL,
        strain_id       BIGINT(20) DEFAULT NULL,
        ip_address      VARCHAR(45) NOT NULL,
        user_agent      TEXT,
        referer         TEXT,
        country         VARCHAR(100) DEFAULT '',
        region          VARCHAR(100) DEFAULT '',
        city            VARCHAR(100) DEFAULT '',
        latitude        DECIMAL(10, 8) DEFAULT NULL,
        longitude       DECIMAL(11, 8) DEFAULT NULL,
        timezone        VARCHAR(50) DEFAULT '',
        isp             VARCHAR(255) DEFAULT '',
        security_score  INT DEFAULT 0,
        security_flags  TEXT,
        is_suspicious   TINYINT(1) DEFAULT 0,
        scan_timestamp  DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY qr_key (qr_key),
        KEY strain_id (strain_id),
        KEY ip_address (ip_address),
        KEY country (country),
        KEY is_suspicious (is_suspicious),
        KEY scan_timestamp (scan_timestamp),
        KEY security_score (security_score)
    ) $charset_collate;";
    
    // Create Security Alerts table
    $security_alerts_table = $wpdb->prefix . 'vqr_security_alerts';
    $security_alerts_sql = "CREATE TABLE $security_alerts_table (
        id              BIGINT(20) NOT NULL AUTO_INCREMENT,
        qr_code_id      BIGINT(20) NOT NULL,
        qr_key          VARCHAR(64) NOT NULL,
        batch_code      VARCHAR(8) NOT NULL,
        user_id         BIGINT(20) NOT NULL,
        strain_id       BIGINT(20) DEFAULT NULL,
        alert_type      VARCHAR(50) NOT NULL,
        severity        ENUM('low', 'medium', 'high', 'critical') NOT NULL,
        security_score  INT NOT NULL,
        security_flags  TEXT,
        scan_data       TEXT,
        ip_address      VARCHAR(45) NOT NULL,
        location        VARCHAR(255) DEFAULT '',
        is_resolved     TINYINT(1) DEFAULT 0,
        resolved_at     DATETIME DEFAULT NULL,
        resolved_by     BIGINT(20) DEFAULT NULL,
        notes           TEXT,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY qr_code_id (qr_code_id),
        KEY qr_key (qr_key),
        KEY user_id (user_id),
        KEY strain_id (strain_id),
        KEY alert_type (alert_type),
        KEY severity (severity),
        KEY is_resolved (is_resolved),
        KEY created_at (created_at),
        KEY security_score (security_score)
    ) $charset_collate;";

    // Create Sticker Orders table
    $sticker_orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $sticker_orders_sql = "CREATE TABLE $sticker_orders_table (
        id              BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id         BIGINT(20) NOT NULL,
        order_number    VARCHAR(32) NOT NULL,
        status          ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        qr_count        INT NOT NULL,
        total_amount    DECIMAL(10, 2) DEFAULT 0.00,
        shipping_name   VARCHAR(255) NOT NULL,
        shipping_email  VARCHAR(255) NOT NULL,
        shipping_address TEXT NOT NULL,
        shipping_city   VARCHAR(100) NOT NULL,
        shipping_state  VARCHAR(100) NOT NULL,
        shipping_zip    VARCHAR(20) NOT NULL,
        shipping_country VARCHAR(100) NOT NULL,
        notes           TEXT,
        tracking_number VARCHAR(100) DEFAULT NULL,
        shipped_at      DATETIME DEFAULT NULL,
        delivered_at    DATETIME DEFAULT NULL,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY order_number (order_number),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";

    // Create Sticker Order Items table (individual QR codes in orders)
    $sticker_order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    $sticker_order_items_sql = "CREATE TABLE $sticker_order_items_table (
        id              BIGINT(20) NOT NULL AUTO_INCREMENT,
        order_id        BIGINT(20) NOT NULL,
        qr_code_id      BIGINT(20) NOT NULL,
        batch_code      VARCHAR(8) NOT NULL,
        sticker_type    VARCHAR(50) NOT NULL DEFAULT 'standard',
        quantity        INT DEFAULT 1,
        unit_price      DECIMAL(10, 2) DEFAULT 0.00,
        created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY qr_code_id (qr_code_id),
        KEY batch_code (batch_code),
        KEY sticker_type (sticker_type),
        FOREIGN KEY (order_id) REFERENCES $sticker_orders_table (id) ON DELETE CASCADE,
        FOREIGN KEY (qr_code_id) REFERENCES $qr_table_name (id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $qr_sql );
    dbDelta( $verification_sql );
    dbDelta( $tos_sql );
    dbDelta( $security_scans_sql );
    dbDelta( $security_alerts_sql );
    dbDelta( $sticker_orders_sql );
    dbDelta( $sticker_order_items_sql );

    // Update database version
    update_option( 'vqr_db_version', '2.3' );
}

/**
 * Check for database updates on plugins loaded
 */
function vqr_check_db_update() {
    if ( get_option( 'vqr_db_version' ) !== '2.3' ) {
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

/**
 * Update security alerts table to add batch_code column
 */
function vqr_update_security_alerts_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_security_alerts';
    
    // Check if batch_code column exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s 
         AND TABLE_NAME = %s 
         AND COLUMN_NAME = 'batch_code'",
        DB_NAME,
        $table_name
    ));
    
    // Add column if it doesn't exist
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN batch_code VARCHAR(8) NOT NULL DEFAULT '' AFTER qr_key");
        
        // Populate batch_code for existing records by joining with vqr_codes table
        $qr_codes_table = $wpdb->prefix . 'vqr_codes';
        $wpdb->query("
            UPDATE {$table_name} sa 
            INNER JOIN {$qr_codes_table} qr ON sa.qr_key = qr.qr_key 
            SET sa.batch_code = qr.batch_code 
            WHERE sa.batch_code = ''
        ");
        
        error_log("VQR Security: Updated security_alerts table with batch_code column");
    }
}

/**
 * Update sticker order items table to add sticker_type column
 */
function vqr_update_sticker_order_items_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Check if sticker_type column exists
    $column_exists = $wpdb->get_results($wpdb->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = %s 
         AND TABLE_NAME = %s 
         AND COLUMN_NAME = 'sticker_type'",
        DB_NAME,
        $table_name
    ));
    
    // Add column if it doesn't exist
    if (empty($column_exists)) {
        $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN sticker_type VARCHAR(50) NOT NULL DEFAULT 'standard' AFTER batch_code");
        $wpdb->query("ALTER TABLE {$table_name} ADD KEY sticker_type (sticker_type)");
        
        error_log("VQR Stickers: Updated sticker_order_items table with sticker_type column");
    }
}
