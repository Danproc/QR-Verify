<?php
/**
 * QR Code Plugin Settings Management
 */

defined('ABSPATH') || exit;

/**
 * Add settings menu to admin
 */
add_action('admin_menu', 'vqr_add_settings_menu', 15);

function vqr_add_settings_menu() {
    add_submenu_page(
        'verification_qr_manager',
        'QR Settings',
        'Settings',
        'manage_options',
        'vqr_settings',
        'vqr_settings_page'
    );
}

/**
 * Initialize settings
 */
add_action('admin_init', 'vqr_initialize_settings');

function vqr_initialize_settings() {
    // Register settings
    register_setting('vqr_settings_group', 'vqr_admin_notification_email');
    register_setting('vqr_settings_group', 'vqr_admin_cc_emails');
    register_setting('vqr_settings_group', 'vqr_admin_bcc_emails');
    register_setting('vqr_settings_group', 'vqr_global_logo_id');
    register_setting('vqr_settings_group', 'vqr_sticker_inventory');
    
    // Email Settings Section
    add_settings_section(
        'vqr_email_settings',
        'Email Notification Settings',
        'vqr_email_settings_section_callback',
        'vqr_settings'
    );
    
    add_settings_field(
        'vqr_admin_notification_email',
        'Admin Notification Email',
        'vqr_admin_notification_email_callback',
        'vqr_settings',
        'vqr_email_settings'
    );
    
    add_settings_field(
        'vqr_admin_cc_emails',
        'CC Email Addresses',
        'vqr_admin_cc_emails_callback',
        'vqr_settings',
        'vqr_email_settings'
    );
    
    add_settings_field(
        'vqr_admin_bcc_emails',
        'BCC Email Addresses',
        'vqr_admin_bcc_emails_callback',
        'vqr_settings',
        'vqr_email_settings'
    );
    
    // Logo Settings Section
    add_settings_section(
        'vqr_logo_settings',
        'Brand Logo Settings',
        'vqr_logo_settings_section_callback',
        'vqr_settings'
    );
    
    add_settings_field(
        'vqr_global_logo',
        'Global Logo',
        'vqr_global_logo_callback',
        'vqr_settings',
        'vqr_logo_settings'
    );
    
    // Inventory Settings Section
    add_settings_section(
        'vqr_inventory_settings',
        'Sticker Inventory Management',
        'vqr_inventory_settings_section_callback',
        'vqr_settings'
    );
    
    add_settings_field(
        'vqr_sticker_inventory',
        'Sticker Stock Status',
        'vqr_sticker_inventory_callback',
        'vqr_settings',
        'vqr_inventory_settings'
    );
}

/**
 * Settings page callback
 */
function vqr_settings_page() {
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-admin-settings"></span> QR Code Plugin Settings</h1>
        
        <div class="vqr-settings-container">
            <form method="post" action="options.php">
                <?php
                settings_fields('vqr_settings_group');
                do_settings_sections('vqr_settings');
                submit_button('Save Settings', 'primary', 'submit', true, array('class' => 'button-primary button-large'));
                ?>
            </form>
        </div>
    </div>
    
    <style>
    .vqr-settings-container {
        max-width: 800px;
        margin: 20px 0;
    }
    
    .vqr-settings-container .form-table {
        background: white;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .vqr-settings-container .form-table th {
        background: #f8fafc;
        font-weight: 600;
        color: #1f2937;
        border-bottom: 1px solid #e5e7eb;
        padding: 20px;
    }
    
    .vqr-settings-container .form-table td {
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .vqr-settings-container .form-table tr:last-child td,
    .vqr-settings-container .form-table tr:last-child th {
        border-bottom: none;
    }
    
    .vqr-settings-container h2 {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        margin: 0;
        padding: 15px 20px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px 8px 0 0;
    }
    
    .vqr-stock-controls {
        display: flex;
        gap: 20px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .vqr-stock-item {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        min-width: 200px;
        text-align: center;
    }
    
    .vqr-stock-item h4 {
        margin: 0 0 12px 0;
        color: #1f2937;
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .vqr-stock-toggle {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
    }
    
    .vqr-stock-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .vqr-stock-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #dc2626;
        transition: .4s;
        border-radius: 30px;
    }
    
    .vqr-stock-slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .vqr-stock-slider {
        background-color: #10b981;
    }
    
    input:checked + .vqr-stock-slider:before {
        transform: translateX(30px);
    }
    
    .vqr-stock-status {
        margin-top: 8px;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
    }
    
    .vqr-stock-status.in-stock {
        color: #10b981;
    }
    
    .vqr-stock-status.out-of-stock {
        color: #dc2626;
    }
    
    .vqr-logo-preview {
        margin-top: 10px;
        padding: 10px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        text-align: center;
    }
    
    .vqr-logo-preview img {
        max-width: 200px;
        max-height: 80px;
        object-fit: contain;
    }
    
    .vqr-logo-preview .no-logo {
        color: #6b7280;
        font-style: italic;
    }
    
    .vqr-upload-button {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        margin-right: 10px;
    }
    
    .vqr-upload-button:hover {
        background: #1d4ed8;
    }
    
    .vqr-remove-button {
        background: #dc2626;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }
    
    .vqr-remove-button:hover {
        background: #b91c1c;
    }
    
    .vqr-help-text {
        font-size: 13px;
        color: #6b7280;
        margin-top: 8px;
        font-style: italic;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Logo upload functionality
        var mediaUploader;
        
        $('#vqr_upload_logo').click(function(e) {
            e.preventDefault();
            
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Logo',
                button: {
                    text: 'Use as Logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });
            
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#vqr_global_logo_id').val(attachment.id);
                
                var preview = '<img src="' + attachment.url + '" alt="' + attachment.alt + '">';
                $('.vqr-logo-preview').html(preview);
            });
            
            mediaUploader.open();
        });
        
        $('#vqr_remove_logo').click(function(e) {
            e.preventDefault();
            $('#vqr_global_logo_id').val('');
            $('.vqr-logo-preview').html('<div class="no-logo">No logo selected</div>');
        });
        
        // Stock toggle functionality
        $('.vqr-stock-toggle input').change(function() {
            var $status = $(this).closest('.vqr-stock-item').find('.vqr-stock-status');
            if ($(this).is(':checked')) {
                $status.text('In Stock').removeClass('out-of-stock').addClass('in-stock');
            } else {
                $status.text('Out of Stock').removeClass('in-stock').addClass('out-of-stock');
            }
        });
        
        // Email validation for CC and BCC fields
        function validateEmailList(emails) {
            if (!emails.trim()) return true;
            
            var emailArray = emails.split(',');
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            for (var i = 0; i < emailArray.length; i++) {
                var email = emailArray[i].trim();
                if (email && !emailRegex.test(email)) {
                    return false;
                }
            }
            return true;
        }
        
        $('#vqr_admin_cc_emails, #vqr_admin_bcc_emails').on('blur', function() {
            var $field = $(this);
            var emails = $field.val();
            
            if (!validateEmailList(emails)) {
                $field.css('border-color', '#dc2626');
                $field.next('.vqr-help-text').css('color', '#dc2626').append(' <strong>Please check email format.</strong>');
            } else {
                $field.css('border-color', '');
                $field.next('.vqr-help-text').css('color', '').find('strong').remove();
            }
        });
    });
    </script>
    <?php
}

/**
 * Section callbacks
 */
function vqr_email_settings_section_callback() {
    echo '<p>Configure email notification settings for order alerts and admin notifications. You can set up the main recipient, as well as additional CC and BCC recipients for new order notifications.</p>';
}

function vqr_logo_settings_section_callback() {
    echo '<p>Upload and manage your brand logo that appears in email templates and QR code interfaces.</p>';
}

function vqr_inventory_settings_section_callback() {
    echo '<p>Manage sticker inventory status to control order availability and prevent orders when out of stock.</p>';
}

/**
 * Field callbacks
 */
function vqr_admin_notification_email_callback() {
    $email = get_option('vqr_admin_notification_email', get_option('admin_email'));
    echo '<input type="email" id="vqr_admin_notification_email" name="vqr_admin_notification_email" value="' . esc_attr($email) . '" class="regular-text" />';
    echo '<p class="vqr-help-text">Email address where new order notifications will be sent. Defaults to site admin email.</p>';
}

function vqr_admin_cc_emails_callback() {
    $cc_emails = get_option('vqr_admin_cc_emails', '');
    echo '<input type="text" id="vqr_admin_cc_emails" name="vqr_admin_cc_emails" value="' . esc_attr($cc_emails) . '" class="regular-text" placeholder="email1@example.com, email2@example.com" />';
    echo '<p class="vqr-help-text">Additional email addresses to CC on order notifications. Separate multiple emails with commas.</p>';
}

function vqr_admin_bcc_emails_callback() {
    $bcc_emails = get_option('vqr_admin_bcc_emails', '');
    echo '<input type="text" id="vqr_admin_bcc_emails" name="vqr_admin_bcc_emails" value="' . esc_attr($bcc_emails) . '" class="regular-text" placeholder="email1@example.com, email2@example.com" />';
    echo '<p class="vqr-help-text">Additional email addresses to BCC on order notifications. Separate multiple emails with commas. BCC recipients are hidden from other recipients.</p>';
}

function vqr_global_logo_callback() {
    $logo_id = get_option('vqr_global_logo_id', '');
    $logo_url = '';
    $logo_alt = '';
    
    if ($logo_id) {
        $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
        $logo_alt = get_post_meta($logo_id, '_wp_attachment_image_alt', true);
    }
    
    echo '<input type="hidden" id="vqr_global_logo_id" name="vqr_global_logo_id" value="' . esc_attr($logo_id) . '" />';
    echo '<button type="button" id="vqr_upload_logo" class="vqr-upload-button">Upload Logo</button>';
    echo '<button type="button" id="vqr_remove_logo" class="vqr-remove-button">Remove Logo</button>';
    
    echo '<div class="vqr-logo-preview">';
    if ($logo_url) {
        echo '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($logo_alt) . '">';
    } else {
        echo '<div class="no-logo">No logo selected</div>';
    }
    echo '</div>';
    
    echo '<p class="vqr-help-text">This logo will appear in email headers and QR code interfaces. Recommended size: 200x80px or similar aspect ratio.</p>';
}

function vqr_sticker_inventory_callback() {
    $inventory = get_option('vqr_sticker_inventory', array(
        'standard' => true,
        'iridescent' => true
    ));
    
    $standard_checked = isset($inventory['standard']) && $inventory['standard'] ? 'checked' : '';
    $iridescent_checked = isset($inventory['iridescent']) && $inventory['iridescent'] ? 'checked' : '';
    
    echo '<div class="vqr-stock-controls">';
    
    // Standard Gloss Stickers
    echo '<div class="vqr-stock-item">';
    echo '<h4>Standard Gloss</h4>';
    echo '<label class="vqr-stock-toggle">';
    echo '<input type="checkbox" name="vqr_sticker_inventory[standard]" value="1" ' . $standard_checked . '>';
    echo '<span class="vqr-stock-slider"></span>';
    echo '</label>';
    echo '<div class="vqr-stock-status ' . ($standard_checked ? 'in-stock' : 'out-of-stock') . '">';
    echo $standard_checked ? 'In Stock' : 'Out of Stock';
    echo '</div>';
    echo '</div>';
    
    // Iridescent Holographic Stickers
    echo '<div class="vqr-stock-item">';
    echo '<h4>Iridescent Holographic</h4>';
    echo '<label class="vqr-stock-toggle">';
    echo '<input type="checkbox" name="vqr_sticker_inventory[iridescent]" value="1" ' . $iridescent_checked . '>';
    echo '<span class="vqr-stock-slider"></span>';
    echo '</label>';
    echo '<div class="vqr-stock-status ' . ($iridescent_checked ? 'in-stock' : 'out-of-stock') . '">';
    echo $iridescent_checked ? 'In Stock' : 'Out of Stock';
    echo '</div>';
    echo '</div>';
    
    echo '</div>';
    
    echo '<p class="vqr-help-text">Toggle sticker types on/off to control order availability. When a sticker type is out of stock, customers cannot select it during order placement.</p>';
}


/**
 * Check if sticker type is in stock
 */
if (!function_exists('vqr_is_sticker_in_stock')) {
    function vqr_is_sticker_in_stock($sticker_type) {
        $inventory = get_option('vqr_sticker_inventory', array(
            'standard' => true,
            'iridescent' => true
        ));
        
        return isset($inventory[$sticker_type]) && $inventory[$sticker_type];
    }
}

/**
 * Get available sticker types
 */
if (!function_exists('vqr_get_available_sticker_types')) {
    function vqr_get_available_sticker_types() {
        $inventory = get_option('vqr_sticker_inventory', array(
            'standard' => true,
            'iridescent' => true
        ));
        
        $available = array();
        
        if (isset($inventory['standard']) && $inventory['standard']) {
            $available['standard'] = 'Standard Gloss';
        }
        
        if (isset($inventory['iridescent']) && $inventory['iridescent']) {
            $available['iridescent'] = 'Iridescent Holographic';
        }
        
        return $available;
    }
}

/**
 * Enqueue media uploader on settings page
 */
add_action('admin_enqueue_scripts', 'vqr_settings_scripts');

function vqr_settings_scripts($hook) {
    if ($hook === 'qr-codes_page_vqr_settings') {
        wp_enqueue_media();
        wp_enqueue_script('jquery');
    }
}

/**
 * Get CC emails for admin notifications
 */
if (!function_exists('vqr_get_admin_cc_emails')) {
    function vqr_get_admin_cc_emails() {
        $cc_emails = get_option('vqr_admin_cc_emails', '');
        if (empty($cc_emails)) {
            return array();
        }
        
        $emails = array_map('trim', explode(',', $cc_emails));
        return array_filter($emails, 'is_email');
    }
}

/**
 * Get BCC emails for admin notifications
 */
if (!function_exists('vqr_get_admin_bcc_emails')) {
    function vqr_get_admin_bcc_emails() {
        $bcc_emails = get_option('vqr_admin_bcc_emails', '');
        if (empty($bcc_emails)) {
            return array();
        }
        
        $emails = array_map('trim', explode(',', $bcc_emails));
        return array_filter($emails, 'is_email');
    }
}

/**
 * Check if a QR code has been ordered for printing
 */
if (!function_exists('vqr_qr_code_has_print_order')) {
    function vqr_qr_code_has_print_order($qr_code_id) {
        global $wpdb;
        $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
        $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT so.status, so.order_number, soi.sticker_type 
             FROM {$order_items_table} soi 
             INNER JOIN {$orders_table} so ON soi.order_id = so.id 
             WHERE soi.qr_code_id = %d 
             AND so.status IN ('processing', 'shipped', 'delivered')
             ORDER BY so.created_at DESC 
             LIMIT 1",
            $qr_code_id
        ));
        
        return $result ? $result : false;
    }
}

/**
 * Get print status info for a QR code
 */
if (!function_exists('vqr_get_qr_print_status')) {
    function vqr_get_qr_print_status($qr_code_id) {
        $order_info = vqr_qr_code_has_print_order($qr_code_id);
        
        if (!$order_info) {
            return array(
                'has_order' => false,
                'status' => 'not_ordered',
                'badge_class' => 'vqr-print-status-none',
                'badge_text' => 'Not Ordered',
                'icon' => 'circle'
            );
        }
        
        switch ($order_info->status) {
            case 'processing':
                return array(
                    'has_order' => true,
                    'status' => 'processing',
                    'badge_class' => 'vqr-print-status-processing',
                    'badge_text' => 'Processing',
                    'icon' => 'clock',
                    'order_number' => $order_info->order_number,
                    'sticker_type' => $order_info->sticker_type
                );
            case 'shipped':
                return array(
                    'has_order' => true,
                    'status' => 'shipped',
                    'badge_class' => 'vqr-print-status-shipped',
                    'badge_text' => 'Shipped',
                    'icon' => 'truck',
                    'order_number' => $order_info->order_number,
                    'sticker_type' => $order_info->sticker_type
                );
            case 'delivered':
                return array(
                    'has_order' => true,
                    'status' => 'delivered',
                    'badge_class' => 'vqr-print-status-delivered',
                    'badge_text' => 'Delivered',
                    'icon' => 'check-circle',
                    'order_number' => $order_info->order_number,
                    'sticker_type' => $order_info->sticker_type
                );
            default:
                return array(
                    'has_order' => false,
                    'status' => 'not_ordered',
                    'badge_class' => 'vqr-print-status-none',
                    'badge_text' => 'Not Ordered',
                    'icon' => 'circle'
                );
        }
    }
}

/**
 * Check if a strain has any QR codes ordered for printing
 */
if (!function_exists('vqr_strain_has_print_orders')) {
    function vqr_strain_has_print_orders($strain_id) {
        global $wpdb;
        $qr_table = $wpdb->prefix . 'vqr_codes';
        $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
        $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT qr.id)
             FROM {$qr_table} qr
             INNER JOIN {$order_items_table} soi ON qr.id = soi.qr_code_id
             INNER JOIN {$orders_table} so ON soi.order_id = so.id
             WHERE qr.post_id = %d 
             AND so.status IN ('processing', 'shipped', 'delivered')",
            $strain_id
        ));
        
        return intval($count) > 0;
    }
}

/**
 * Get strain print order information with link details
 */
if (!function_exists('vqr_get_strain_print_order_info')) {
    function vqr_get_strain_print_order_info($strain_id) {
        global $wpdb;
        $qr_table = $wpdb->prefix . 'vqr_codes';
        $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
        $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
        
        $order_info = $wpdb->get_row($wpdb->prepare(
            "SELECT so.id as order_id, so.order_number, so.status, COUNT(DISTINCT qr.id) as qr_count
             FROM {$qr_table} qr
             INNER JOIN {$order_items_table} soi ON qr.id = soi.qr_code_id
             INNER JOIN {$orders_table} so ON soi.order_id = so.id
             WHERE qr.post_id = %d 
             AND so.status IN ('processing', 'shipped', 'delivered')
             GROUP BY so.id, so.order_number, so.status
             ORDER BY so.created_at DESC
             LIMIT 1",
            $strain_id
        ));
        
        if (!$order_info) {
            return false;
        }
        
        return array(
            'has_order' => true,
            'order_id' => $order_info->order_id,
            'order_number' => $order_info->order_number,
            'status' => $order_info->status,
            'qr_count' => $order_info->qr_count,
            'link_url' => home_url('/app/basket?tab=' . $order_info->status),
            'badge_class' => 'vqr-strain-print-overlay',
            'status_class' => 'vqr-print-status-' . $order_info->status
        );
    }
}

/**
 * Render print status badge for QR code
 */
if (!function_exists('vqr_render_print_status_badge')) {
    function vqr_render_print_status_badge($qr_code_id, $show_tooltip = true) {
        $status = vqr_get_qr_print_status($qr_code_id);
        
        $tooltip_attr = '';
        if ($show_tooltip && $status['has_order']) {
            $tooltip_title = "Order #{$status['order_number']} - " . ucfirst($status['sticker_type']) . " stickers";
            $tooltip_attr = ' title="' . esc_attr($tooltip_title) . '"';
        }
        
        $icon_svg = vqr_get_status_icon_svg($status['icon']);
        
        return sprintf(
            '<span class="vqr-print-badge %s"%s>%s %s</span>',
            esc_attr($status['badge_class']),
            $tooltip_attr,
            $icon_svg,
            esc_html($status['badge_text'])
        );
    }
}

/**
 * Get SVG icon for status
 */
if (!function_exists('vqr_get_status_icon_svg')) {
    function vqr_get_status_icon_svg($icon_name) {
        $icons = array(
            'circle' => '<svg class="vqr-status-icon" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="6"></circle></svg>',
            'clock' => '<svg class="vqr-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12,6 12,12 16,14"></polyline></svg>',
            'truck' => '<svg class="vqr-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>',
            'check-circle' => '<svg class="vqr-status-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle></svg>'
        );
        
        return isset($icons[$icon_name]) ? $icons[$icon_name] : $icons['circle'];
    }
}