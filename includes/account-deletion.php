<?php
/**
 * Account Deletion Functionality for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Delete user account and all associated data
 */
function vqr_delete_user_account($user_id, $confirmation_text = '') {
    // Verify user exists
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.');
    }
    
    // Verify confirmation text
    if ($confirmation_text !== 'DELETE MY ACCOUNT') {
        return new WP_Error('invalid_confirmation', 'Confirmation text does not match.');
    }
    
    // Check if user has QR customer role (additional safety check)
    $qr_roles = ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'];
    $user_roles = $user->roles;
    $is_qr_customer = array_intersect($qr_roles, $user_roles);
    
    if (!$is_qr_customer) {
        return new WP_Error('invalid_user', 'Only QR customer accounts can be deleted through this method.');
    }
    
    // Prevent administrators from being deleted
    if (user_can($user_id, 'manage_options')) {
        return new WP_Error('admin_protection', 'Administrator accounts cannot be deleted.');
    }
    
    global $wpdb;
    
    try {
        // Start transaction-like cleanup
        
        // 1. Delete all QR codes created by this user
        $qr_table = $wpdb->prefix . 'vqr_codes';
        $deleted_qr_codes = $wpdb->delete($qr_table, array('user_id' => $user_id), array('%d'));
        
        // 2. Delete all strains (custom posts) created by this user
        $strain_posts = get_posts(array(
            'post_type' => 'strain',
            'author' => $user_id,
            'posts_per_page' => -1,
            'post_status' => 'any'
        ));
        
        $deleted_strains = 0;
        foreach ($strain_posts as $strain) {
            // Delete associated media attachments
            $attachments = get_attached_media('', $strain->ID);
            foreach ($attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
            }
            
            // Delete the strain post
            if (wp_delete_post($strain->ID, true)) {
                $deleted_strains++;
            }
        }
        
        // 3. Delete profile picture attachment if exists
        $profile_picture_id = get_user_meta($user_id, 'vqr_profile_picture_id', true);
        if ($profile_picture_id) {
            wp_delete_attachment($profile_picture_id, true);
        }
        
        // 4. Delete email verification records
        $verification_table = $wpdb->prefix . 'vqr_email_verification';
        $deleted_verifications = $wpdb->delete($verification_table, array('user_id' => $user_id), array('%d'));
        
        // 5. Delete all user metadata
        $deleted_meta = $wpdb->delete($wpdb->usermeta, array('user_id' => $user_id), array('%d'));
        
        // 6. Send account deletion confirmation email
        vqr_send_account_deletion_email($user);
        
        // 7. Finally, delete the user account
        $user_deleted = wp_delete_user($user_id);
        
        if (!$user_deleted) {
            return new WP_Error('deletion_failed', 'Failed to delete user account.');
        }
        
        // Return summary of what was deleted
        return array(
            'success' => true,
            'message' => 'Account successfully deleted.',
            'deleted_data' => array(
                'qr_codes' => $deleted_qr_codes,
                'strains' => $deleted_strains,
                'verifications' => $deleted_verifications,
                'user_meta' => $deleted_meta,
                'profile_picture' => $profile_picture_id ? 1 : 0
            )
        );
        
    } catch (Exception $e) {
        return new WP_Error('deletion_error', 'An error occurred during account deletion: ' . $e->getMessage());
    }
}

/**
 * Send account deletion confirmation email
 */
function vqr_send_account_deletion_email($user) {
    $site_name = get_bloginfo('name');
    $subject = sprintf('[%s] Account deletion confirmation', $site_name);
    
    $message = vqr_get_account_deletion_email_template($user);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Get account deletion confirmation email template
 */
function vqr_get_account_deletion_email_template($user) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $current_time = current_time('M j, Y \a\t g:i A T');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Deleted - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px;
            }
            .content h2 {
                color: #1f2937;
                font-size: 20px;
                margin-bottom: 20px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .deletion-info {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .deletion-info h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <h2>Account Successfully Deleted</h2>
                
                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                
                <p>This email confirms that your <?php echo esc_html($site_name); ?> account has been permanently deleted on <?php echo esc_html($current_time); ?>.</p>
                
                <div class="deletion-info">
                    <h3>What was deleted:</h3>
                    <ul>
                        <li>Your user account and profile information</li>
                        <li>All cannabis strain data and images</li>
                        <li>All generated QR codes and analytics</li>
                        <li>Email verification records</li>
                        <li>Profile picture and preferences</li>
                    </ul>
                </div>
                
                <p><strong>This action is permanent and cannot be undone.</strong></p>
                
                <p>If you change your mind in the future, you're welcome to create a new account at any time. However, all your previous data cannot be recovered.</p>
                
                <p>Thank you for using <?php echo esc_html($site_name); ?>. We're sorry to see you go and hope you'll consider us again in the future.</p>
            </div>
            
            <div class="footer">
                <p>
                    This final email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    This email was sent to: <?php echo esc_html($user->user_email); ?>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler for account deletion
 */
function vqr_ajax_delete_account() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_frontend_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $confirmation_text = sanitize_text_field($_POST['confirmation'] ?? '');
    
    $result = vqr_delete_user_account($user_id, $confirmation_text);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        // Clear any user sessions
        wp_destroy_current_session();
        wp_clear_auth_cookie();
        
        wp_send_json_success(array(
            'message' => 'Your account has been successfully deleted. You will be redirected to the homepage.',
            'redirect' => home_url()
        ));
    }
}

add_action('wp_ajax_vqr_delete_account', 'vqr_ajax_delete_account');