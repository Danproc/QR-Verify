<?php
/**
 * Email Verification System for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

/**
 * Generate a secure verification token
 */
function vqr_generate_verification_token() {
    return wp_generate_password(64, false, false);
}

/**
 * Create email verification record for user
 */
function vqr_create_verification_token($user_id, $email, $type = 'signup') {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    $token = vqr_generate_verification_token();
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Delete any existing tokens for this user and type
    $wpdb->delete(
        $table_name,
        array('user_id' => $user_id, 'verification_type' => $type),
        array('%d', '%s')
    );
    
    // Create new verification record
    $result = $wpdb->insert(
        $table_name,
        array(
            'user_id' => $user_id,
            'token' => $token,
            'email' => $email,
            'verification_type' => $type,
            'expires_at' => $expires_at
        ),
        array('%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to create verification token.');
    }
    
    return $token;
}

/**
 * Verify email token and mark as verified
 */
function vqr_verify_email_token($token) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    
    // Get verification record
    $verification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE token = %s AND verified_at IS NULL",
        $token
    ));
    
    if (!$verification) {
        return new WP_Error('invalid_token', 'Invalid or already used verification token.');
    }
    
    // Check if token has expired
    if (strtotime($verification->expires_at) < time()) {
        return new WP_Error('expired_token', 'Verification token has expired.');
    }
    
    // Mark as verified
    $result = $wpdb->update(
        $table_name,
        array('verified_at' => current_time('mysql')),
        array('id' => $verification->id),
        array('%s'),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error('db_error', 'Failed to verify email.');
    }
    
    // Handle different verification types
    if ($verification->verification_type === 'email_change') {
        // Update user's email address
        $user_data = array(
            'ID' => $verification->user_id,
            'user_email' => $verification->email
        );
        $update_result = wp_update_user($user_data);
        
        if (is_wp_error($update_result)) {
            return new WP_Error('email_update_failed', 'Failed to update email address.');
        }
        
        // Send confirmation email to new address
        vqr_send_email_change_confirmation($verification->user_id, $verification->email);
        
    } else {
        // Regular signup verification
        update_user_meta($verification->user_id, 'vqr_email_verified', true);
        update_user_meta($verification->user_id, 'vqr_email_verified_date', current_time('mysql'));
        
        // Send welcome email after successful verification
        vqr_send_welcome_email($verification->user_id);
    }
    
    return array(
        'user_id' => $verification->user_id,
        'email' => $verification->email,
        'verification_type' => $verification->verification_type,
        'verified_at' => current_time('mysql')
    );
}

/**
 * Check if user's email is verified
 */
function vqr_is_email_verified($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    return (bool) get_user_meta($user_id, 'vqr_email_verified', true);
}

/**
 * Get verification status for user
 */
function vqr_get_verification_status($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    
    $verification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    $is_verified = vqr_is_email_verified($user_id);
    
    return array(
        'is_verified' => $is_verified,
        'has_pending_verification' => $verification && !$verification->verified_at,
        'verification_sent_at' => $verification ? $verification->created_at : null,
        'verification_expires_at' => $verification ? $verification->expires_at : null,
        'resent_count' => $verification ? $verification->resent_count : 0,
        'can_resend' => $verification ? (strtotime($verification->created_at) < strtotime('-5 minutes')) : true
    );
}

/**
 * Send verification email to user
 */
function vqr_send_verification_email($user_id, $email, $token, $type = 'signup') {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.');
    }
    
    $verification_url = home_url('/app/verify-email?token=' . $token);
    $site_name = get_bloginfo('name');
    
    if ($type === 'email_change') {
        $subject = sprintf('[%s] Confirm your email address change', $site_name);
        $message = vqr_get_email_change_template($user, $verification_url, $email);
    } else {
        $subject = sprintf('[%s] Please verify your email address', $site_name);
        $message = vqr_get_verification_email_template($user, $verification_url);
    }
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    $sent = wp_mail($email, $subject, $message, $headers);
    
    if (!$sent) {
        return new WP_Error('email_failed', 'Failed to send verification email.');
    }
    
    return true;
}

/**
 * Resend verification email
 */
function vqr_resend_verification_email($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    $user = get_user_by('ID', $user_id);
    
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.');
    }
    
    // Check if already verified
    if (vqr_is_email_verified($user_id)) {
        return new WP_Error('already_verified', 'Email is already verified.');
    }
    
    // Get current verification record
    $verification = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d AND verified_at IS NULL ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    if (!$verification) {
        // Create new token if none exists
        $token = vqr_create_verification_token($user_id, $user->user_email);
        if (is_wp_error($token)) {
            return $token;
        }
    } else {
        // Check rate limiting (5 minutes between resends)
        if (strtotime($verification->created_at) > strtotime('-5 minutes')) {
            return new WP_Error('rate_limited', 'Please wait before requesting another verification email.');
        }
        
        // Check resend limit (max 5 resends per day)
        if ($verification->resent_count >= 5) {
            return new WP_Error('resend_limit', 'Maximum resend limit reached. Please contact support.');
        }
        
        $token = $verification->token;
        
        // Update resend count and timestamp
        $wpdb->update(
            $table_name,
            array(
                'resent_count' => $verification->resent_count + 1,
                'created_at' => current_time('mysql')
            ),
            array('id' => $verification->id),
            array('%d', '%s'),
            array('%d')
        );
    }
    
    // Send the email
    $result = vqr_send_verification_email($user_id, $user->user_email, $token);
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    return array(
        'sent' => true,
        'email' => $user->user_email,
        'resent_count' => $verification ? $verification->resent_count + 1 : 1
    );
}

/**
 * Get verification email template
 */
function vqr_get_verification_email_template($user, $verification_url) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Verify Your Email - <?php echo esc_html($site_name); ?></title>
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
            .verify-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .verify-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
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
            .alternative-link {
                background: #f3f4f6;
                padding: 16px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #6b7280;
                word-break: break-all;
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
                <h2>Welcome, <?php echo esc_html($user->display_name); ?>!</h2>
                
                <p>Thank you for signing up for <?php echo esc_html($site_name); ?>. To complete your registration and start securing your cannabis products, please verify your email address.</p>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($verification_url); ?>" class="verify-button">
                        Verify My Email Address
                    </a>
                </div>
                
                <p>This verification link will expire in 24 hours for security reasons.</p>
                
                <p><strong>Can't click the button?</strong> Copy and paste this link into your browser:</p>
                <div class="alternative-link">
                    <?php echo esc_url($verification_url); ?>
                </div>
                
                <p>If you didn't create an account with us, please ignore this email.</p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Get email change confirmation template
 */
function vqr_get_email_change_template($user, $verification_url, $new_email) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $old_email = $user->user_email;
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Email Change - <?php echo esc_html($site_name); ?></title>
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
            .email-change-info {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .email-change-info h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .email-item {
                margin: 8px 0;
                font-family: 'Monaco', 'Menlo', monospace;
                font-size: 14px;
            }
            .email-old {
                color: #dc2626;
                text-decoration: line-through;
            }
            .email-new {
                color: #059669;
                font-weight: 600;
            }
            .verify-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .verify-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
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
            .alternative-link {
                background: #f3f4f6;
                padding: 16px;
                border-radius: 6px;
                margin: 20px 0;
                font-size: 14px;
                color: #6b7280;
                word-break: break-all;
            }
            .security-notice {
                background: #fef2f2;
                border: 1px solid #fecaca;
                padding: 16px;
                border-radius: 6px;
                margin: 20px 0;
            }
            .security-notice h4 {
                margin: 0 0 8px 0;
                color: #dc2626;
                font-size: 14px;
                font-weight: 600;
            }
            .security-notice p {
                margin: 0;
                font-size: 14px;
                color: #b91c1c;
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
                <h2>Confirm Email Address Change</h2>
                
                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                
                <p>You have requested to change the email address associated with your <?php echo esc_html($site_name); ?> account. To complete this change, please confirm your new email address.</p>
                
                <div class="email-change-info">
                    <h3>Email Address Change:</h3>
                    <div class="email-item email-old">From: <?php echo esc_html($old_email); ?></div>
                    <div class="email-item email-new">To: <?php echo esc_html($new_email); ?></div>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($verification_url); ?>" class="verify-button">
                        Confirm Email Change
                    </a>
                </div>
                
                <p>This confirmation link will expire in 24 hours for security reasons.</p>
                
                <div class="security-notice">
                    <h4>🔒 Security Notice</h4>
                    <p>If you did not request this email change, please ignore this email and contact our support team immediately. Your account security is important to us.</p>
                </div>
                
                <p><strong>Can't click the button?</strong> Copy and paste this link into your browser:</p>
                <div class="alternative-link">
                    <?php echo esc_url($verification_url); ?>
                </div>
                
                <p>Once confirmed, you will receive all future communications at your new email address: <strong><?php echo esc_html($new_email); ?></strong></p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Send email change confirmation to new address
 */
function vqr_send_email_change_confirmation($user_id, $new_email) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    $site_name = get_bloginfo('name');
    $subject = sprintf('[%s] Email address successfully changed', $site_name);
    
    $message = vqr_get_email_change_success_template($user, $new_email);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    return wp_mail($new_email, $subject, $message, $headers);
}

/**
 * Get email change success confirmation template
 */
function vqr_get_email_change_success_template($user, $new_email) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $login_url = home_url('/app/login');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Email Successfully Changed - <?php echo esc_html($site_name); ?></title>
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
            .success-icon {
                font-size: 48px;
                margin-bottom: 16px;
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
            .success-info {
                background: #dcfdf7;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
                text-align: center;
            }
            .success-info h3 {
                margin: 0 0 12px 0;
                color: #065f46;
                font-size: 18px;
            }
            .new-email {
                font-family: 'Monaco', 'Menlo', monospace;
                font-size: 16px;
                color: #059669;
                font-weight: 600;
                background: white;
                padding: 8px 12px;
                border-radius: 4px;
                display: inline-block;
                margin: 8px 0;
            }
            .login-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .login-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
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
            .security-tips {
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                padding: 16px;
                border-radius: 6px;
                margin: 20px 0;
            }
            .security-tips h4 {
                margin: 0 0 8px 0;
                color: #0c4a6e;
                font-size: 14px;
                font-weight: 600;
            }
            .security-tips ul {
                margin: 8px 0 0 0;
                padding-left: 20px;
                color: #0c4a6e;
                font-size: 14px;
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
                         style="max-height: 60px; max-width: 200px; margin-bottom: 8px; object-fit: contain;">
                <?php endif; ?>
                <div class="success-icon">✅</div>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <h2>Email Address Successfully Changed!</h2>
                
                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                
                <p>Great news! Your email address has been successfully updated. All future communications will now be sent to your new email address.</p>
                
                <div class="success-info">
                    <h3>Your New Email Address:</h3>
                    <div class="new-email"><?php echo esc_html($new_email); ?></div>
                </div>
                
                <p>You can continue using your account as normal. Your username and password remain the same.</p>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($login_url); ?>" class="login-button">
                        Access Your Account
                    </a>
                </div>
                
                <div class="security-tips">
                    <h4>🛡️ Keep Your Account Secure:</h4>
                    <ul>
                        <li>Use a strong, unique password for your account</li>
                        <li>Never share your login credentials with anyone</li>
                        <li>Log out from shared computers and devices</li>
                        <li>Contact us immediately if you notice any suspicious activity</li>
                    </ul>
                </div>
                
                <p>If you have any questions or need assistance, our support team is here to help.</p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Create email change verification request
 */
function vqr_request_email_change($user_id, $new_email) {
    // Validate new email
    if (!is_email($new_email)) {
        return new WP_Error('invalid_email', 'Please enter a valid email address.');
    }
    
    // Check if email already exists
    if (email_exists($new_email)) {
        return new WP_Error('email_exists', 'This email address is already in use.');
    }
    
    // Get current user
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'User not found.');
    }
    
    // Check if trying to change to same email
    if ($user->user_email === $new_email) {
        return new WP_Error('same_email', 'This is already your current email address.');
    }
    
    // Create verification token
    $token = vqr_create_verification_token($user_id, $new_email, 'email_change');
    if (is_wp_error($token)) {
        return $token;
    }
    
    // Send verification email to new address
    $result = vqr_send_verification_email($user_id, $new_email, $token, 'email_change');
    if (is_wp_error($result)) {
        return $result;
    }
    
    return array(
        'success' => true,
        'message' => 'A verification email has been sent to your new email address. Please check your inbox and click the confirmation link to complete the change.',
        'new_email' => $new_email
    );
}

/**
 * Clean up expired verification tokens (run daily)
 */
function vqr_cleanup_expired_tokens() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_email_verification';
    
    // Delete tokens older than 24 hours that are unverified
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name} WHERE expires_at < %s AND verified_at IS NULL",
        current_time('mysql')
    ));
    
    // Delete verified tokens older than 30 days (keep for records)
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table_name} WHERE verified_at IS NOT NULL AND verified_at < %s",
        date('Y-m-d H:i:s', strtotime('-30 days'))
    ));
    
    return $deleted;
}

/**
 * Schedule daily cleanup
 */
function vqr_schedule_token_cleanup() {
    if (!wp_next_scheduled('vqr_cleanup_tokens')) {
        wp_schedule_event(time(), 'daily', 'vqr_cleanup_tokens');
    }
}
add_action('wp', 'vqr_schedule_token_cleanup');
add_action('vqr_cleanup_tokens', 'vqr_cleanup_expired_tokens');

/**
 * Remove scheduled cleanup on plugin deactivation
 */
function vqr_unschedule_token_cleanup() {
    wp_clear_scheduled_hook('vqr_cleanup_tokens');
}
register_deactivation_hook(VQR_PLUGIN_DIR . 'verification-qr-manager.php', 'vqr_unschedule_token_cleanup');

/**
 * Send password change notification email
 */
function vqr_send_password_change_notification($user_id, $user_ip = null, $user_agent = null) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    // Get user's location info (if available)
    $location_info = vqr_get_location_info($user_ip);
    
    // Get device info
    $device_info = vqr_parse_user_agent($user_agent);
    
    $site_name = get_bloginfo('name');
    $subject = sprintf('[%s] Password changed for your account', $site_name);
    
    $message = vqr_get_password_change_email_template($user, $location_info, $device_info, $user_ip);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Get password change notification email template
 */
function vqr_get_password_change_email_template($user, $location_info, $device_info, $user_ip) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $login_url = home_url('/app/login');
    $account_url = home_url('/app/account');
    $current_time = current_time('M j, Y \a\t g:i A T');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Changed - <?php echo esc_html($site_name); ?></title>
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
                background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .security-icon {
                font-size: 48px;
                margin-bottom: 16px;
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
            .alert-info {
                background: #fef3c7;
                border: 1px solid #f59e0b;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .alert-info h3 {
                margin: 0 0 12px 0;
                color: #92400e;
                font-size: 16px;
                font-weight: 600;
            }
            .change-details {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .change-details h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .detail-item {
                margin: 8px 0;
                font-size: 14px;
                display: flex;
                justify-content: space-between;
            }
            .detail-label {
                color: #6b7280;
                font-weight: 500;
            }
            .detail-value {
                color: #1f2937;
                font-family: 'Monaco', 'Menlo', monospace;
                font-size: 13px;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .action-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .security-notice {
                background: #fef2f2;
                border: 1px solid #fecaca;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .security-notice h4 {
                margin: 0 0 12px 0;
                color: #dc2626;
                font-size: 16px;
                font-weight: 600;
            }
            .security-notice p {
                margin: 8px 0;
                color: #b91c1c;
                font-size: 14px;
            }
            .security-tips {
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .security-tips h4 {
                margin: 0 0 12px 0;
                color: #0c4a6e;
                font-size: 16px;
                font-weight: 600;
            }
            .security-tips ul {
                margin: 8px 0 0 0;
                padding-left: 20px;
                color: #0c4a6e;
                font-size: 14px;
            }
            .security-tips li {
                margin-bottom: 8px;
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
            .contact-support {
                background: #f3f4f6;
                padding: 16px;
                border-radius: 6px;
                margin: 20px 0;
                text-align: center;
            }
            .contact-support h4 {
                margin: 0 0 8px 0;
                color: #1f2937;
                font-size: 14px;
                font-weight: 600;
            }
            .contact-support p {
                margin: 0;
                font-size: 14px;
                color: #6b7280;
            }
            .contact-support a {
                color: #dc2626;
                font-weight: 600;
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
                         style="max-height: 60px; max-width: 200px; margin-bottom: 8px; object-fit: contain;">
                <?php endif; ?>
                <div class="security-icon">🔒</div>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <h2>Password Changed Successfully</h2>
                
                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                
                <p>This email confirms that your account password was successfully changed on <?php echo esc_html($current_time); ?>.</p>
                
                <div class="alert-info">
                    <h3>⚠️ If this was you</h3>
                    <p>Great! Your account is secure. No further action is needed.</p>
                </div>
                
                <div class="change-details">
                    <h3>Change Details:</h3>
                    <div class="detail-item">
                        <span class="detail-label">Date & Time:</span>
                        <span class="detail-value"><?php echo esc_html($current_time); ?></span>
                    </div>
                    <?php if ($user_ip): ?>
                    <div class="detail-item">
                        <span class="detail-label">IP Address:</span>
                        <span class="detail-value"><?php echo esc_html($user_ip); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($location_info): ?>
                    <div class="detail-item">
                        <span class="detail-label">Location:</span>
                        <span class="detail-value"><?php echo esc_html($location_info); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($device_info): ?>
                    <div class="detail-item">
                        <span class="detail-label">Device:</span>
                        <span class="detail-value"><?php echo esc_html($device_info); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($account_url); ?>" class="action-button">
                        Manage Account Security
                    </a>
                </div>
                
                <div class="security-notice">
                    <h4>🚨 If this wasn't you</h4>
                    <p><strong>Take immediate action:</strong></p>
                    <p>1. Log into your account immediately and change your password</p>
                    <p>2. Review your account for any unauthorized changes</p>
                    <p>3. Contact our support team immediately</p>
                    <p>4. Consider enabling two-factor authentication if available</p>
                </div>
                
                <div class="security-tips">
                    <h4>🛡️ Keep Your Account Secure:</h4>
                    <ul>
                        <li>Use a unique, strong password that you don't use anywhere else</li>
                        <li>Never share your login credentials with anyone</li>
                        <li>Log out from shared or public computers</li>
                        <li>Be cautious of phishing emails asking for your password</li>
                        <li>Regularly review your account activity</li>
                        <li>Keep your email account secure as it can be used to reset your password</li>
                    </ul>
                </div>
                
                <div class="contact-support">
                    <h4>Need Help?</h4>
                    <p>If you have any concerns about your account security, please contact us immediately at <a href="mailto:support@verify420.com">support@verify420.com</a></p>
                </div>
            </div>
            
            <div class="footer">
                <p>
                    This is an automated security notification from <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
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
 * Get basic location info from IP address
 */
function vqr_get_location_info($ip_address) {
    if (!$ip_address || $ip_address === '127.0.0.1' || $ip_address === '::1') {
        return 'Local/Development Environment';
    }
    
    // For privacy reasons, we'll just show general location
    // In production, you could integrate with a geolocation API
    return 'Location information unavailable';
}

/**
 * Parse user agent for device info
 */
function vqr_parse_user_agent($user_agent) {
    if (!$user_agent) {
        return 'Unknown device';
    }
    
    // Basic user agent parsing
    if (strpos($user_agent, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($user_agent, 'Mac') !== false) {
        $os = 'macOS';
    } elseif (strpos($user_agent, 'Linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($user_agent, 'iPhone') !== false) {
        $os = 'iOS (iPhone)';
    } elseif (strpos($user_agent, 'iPad') !== false) {
        $os = 'iOS (iPad)';
    } elseif (strpos($user_agent, 'Android') !== false) {
        $os = 'Android';
    } else {
        $os = 'Unknown OS';
    }
    
    // Browser detection
    if (strpos($user_agent, 'Chrome') !== false && strpos($user_agent, 'Edge') === false) {
        $browser = 'Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false && strpos($user_agent, 'Chrome') === false) {
        $browser = 'Safari';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        $browser = 'Edge';
    } else {
        $browser = 'Unknown browser';
    }
    
    return $browser . ' on ' . $os;
}

/**
 * Hook into password change events to send notifications
 */
function vqr_password_change_notification_hook($user_id, $new_pass = null) {
    // Only send notifications for QR customer users
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return;
    }
    
    // Check if user has a QR customer role
    $qr_roles = ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'];
    $user_roles = $user->roles;
    $is_qr_customer = array_intersect($qr_roles, $user_roles);
    
    if (!$is_qr_customer) {
        return; // Only send notifications to QR customers
    }
    
    // Get user IP and agent from current request
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Send notification email
    vqr_send_password_change_notification($user_id, $user_ip, $user_agent);
}

// Hook into WordPress password change events
add_action('password_reset', 'vqr_password_change_notification_hook', 10, 2);
add_action('profile_update', function($user_id, $old_user_data) {
    // Check if password was changed
    $new_user_data = get_userdata($user_id);
    if ($old_user_data->user_pass !== $new_user_data->user_pass) {
        vqr_password_change_notification_hook($user_id);
    }
}, 10, 2);

// Also hook into wp_set_password
add_action('wp_set_password', function($password, $user_id) {
    // Add a small delay to ensure the password is actually set
    wp_schedule_single_event(time() + 1, 'vqr_delayed_password_notification', array($user_id));
}, 10, 2);

// Handle delayed password change notification
add_action('vqr_delayed_password_notification', function($user_id) {
    vqr_password_change_notification_hook($user_id);
});

/**
 * Send welcome email after email verification
 */
function vqr_send_welcome_email($user_id) {
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }
    
    // Get user's plan information
    $user_plan = vqr_get_user_plan($user_id);
    $plan_details = vqr_get_plan_details($user_plan);
    $monthly_quota = vqr_get_user_quota($user_id);
    
    $site_name = get_bloginfo('name');
    $subject = sprintf('Welcome to %s! Let\'s get you started 🚀', $site_name);
    
    $message = vqr_get_welcome_email_template($user, $plan_details, $monthly_quota);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Get welcome email template
 */
function vqr_get_welcome_email_template($user, $plan_details, $monthly_quota) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $dashboard_url = home_url('/app/');
    $strains_url = home_url('/app/strains');
    $generate_url = home_url('/app/generate');
    $account_url = home_url('/app/account');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to <?php echo esc_html($site_name); ?>!</title>
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
                padding: 40px 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
            }
            .welcome-icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            .subtitle {
                font-size: 18px;
                margin: 16px 0 0 0;
                opacity: 0.9;
            }
            .content {
                padding: 40px 30px;
            }
            .content h2 {
                color: #1f2937;
                font-size: 24px;
                margin-bottom: 20px;
                text-align: center;
            }
            .content h3 {
                color: #1f2937;
                font-size: 18px;
                margin: 30px 0 15px 0;
                font-weight: 600;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
                font-size: 16px;
            }
            .plan-highlight {
                background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
                border: 2px solid #10b981;
                padding: 24px;
                border-radius: 12px;
                margin: 30px 0;
                text-align: center;
            }
            .plan-highlight h3 {
                margin: 0 0 12px 0;
                color: #065f46;
                font-size: 20px;
            }
            .plan-highlight p {
                margin: 0;
                color: #047857;
                font-weight: 500;
            }
            .quota-info {
                background: #f3f4f6;
                padding: 16px;
                border-radius: 8px;
                margin: 12px 0;
                font-family: 'Monaco', 'Menlo', monospace;
                font-size: 14px;
                text-align: center;
            }
            .steps-container {
                margin: 30px 0;
            }
            .step {
                display: flex;
                align-items: flex-start;
                margin-bottom: 24px;
                padding: 20px;
                background: #f9fafb;
                border-radius: 12px;
                border-left: 4px solid #10b981;
            }
            .step-number {
                background: #10b981;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                margin-right: 16px;
                flex-shrink: 0;
            }
            .step-content h4 {
                margin: 0 0 8px 0;
                color: #1f2937;
                font-size: 16px;
                font-weight: 600;
            }
            .step-content p {
                margin: 0;
                color: #6b7280;
                font-size: 14px;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 14px 28px;
                border-radius: 8px;
                font-weight: 600;
                margin: 8px 8px 8px 0;
                text-align: center;
                font-size: 16px;
            }
            .action-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .action-button.secondary {
                background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            }
            .action-button.secondary:hover {
                background: linear-gradient(135deg, #4b5563 0%, #374151 100%);
            }
            .features-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin: 30px 0;
            }
            .feature-card {
                background: #f8fafc;
                padding: 20px;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                text-align: center;
            }
            .feature-icon {
                font-size: 32px;
                margin-bottom: 12px;
            }
            .feature-card h4 {
                margin: 0 0 8px 0;
                color: #1f2937;
                font-size: 16px;
                font-weight: 600;
            }
            .feature-card p {
                margin: 0;
                color: #6b7280;
                font-size: 14px;
            }
            .tips-section {
                background: #f0f9ff;
                border: 1px solid #bae6fd;
                padding: 24px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .tips-section h3 {
                margin: 0 0 16px 0;
                color: #0c4a6e;
                font-size: 18px;
                font-weight: 600;
            }
            .tips-section ul {
                margin: 0;
                padding-left: 20px;
                color: #0c4a6e;
            }
            .tips-section li {
                margin-bottom: 8px;
                font-size: 14px;
            }
            .footer {
                background: #f9fafb;
                padding: 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
                font-weight: 500;
            }
            .social-links {
                margin: 20px 0;
            }
            .social-links a {
                margin: 0 8px;
                font-size: 24px;
                text-decoration: none;
            }
            .center {
                text-align: center;
            }
            
            @media (max-width: 600px) {
                .features-grid {
                    grid-template-columns: 1fr;
                }
                .action-button {
                    display: block;
                    margin: 8px 0;
                }
                .content {
                    padding: 30px 20px;
                }
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
                         style="max-height: 80px; max-width: 250px; margin-bottom: 12px; object-fit: contain;">
                <?php endif; ?>
                <div class="welcome-icon">🎉</div>
                <h1>Welcome to <?php echo esc_html($site_name); ?>!</h1>
                <p class="subtitle">Your cannabis product verification journey starts here</p>
            </div>
            
            <div class="content">
                <h2>Hi <?php echo esc_html($user->display_name); ?>, you're all set!</h2>
                
                <p>Congratulations on successfully verifying your email address! You now have full access to our cannabis product verification platform.</p>
                
                <div class="plan-highlight">
                    <h3>🌟 Your Current Plan: <?php echo esc_html($plan_details['name']); ?></h3>
                    <p>
                        <?php if ($monthly_quota === -1): ?>
                            Unlimited QR code generation per month
                        <?php else: ?>
                            Generate up to <?php echo number_format($monthly_quota); ?> QR codes per month
                        <?php endif; ?>
                    </p>
                    <?php if ($plan_details['price'] > 0): ?>
                        <div class="quota-info">Monthly subscription: $<?php echo esc_html($plan_details['price']); ?></div>
                    <?php else: ?>
                        <div class="quota-info">Free tier - Perfect for getting started!</div>
                    <?php endif; ?>
                </div>
                
                <h3>🚀 Let's Get You Started</h3>
                <p>Here's everything you can do with <?php echo esc_html($site_name); ?>:</p>
                
                <div class="steps-container">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Add Your Cannabis Strains</h4>
                            <p>Create detailed profiles for your cannabis products including strain information, effects, THC/CBD content, and product images.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Generate QR Codes</h4>
                            <p>Create unique QR codes for each product that customers can scan to verify authenticity and view detailed strain information.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Print & Apply</h4>
                            <p>Download your QR codes as individual images or print them on sticker sheets with cut guides for easy application to products.</p>
                        </div>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h4>Track & Analyze</h4>
                            <p>Monitor QR code scans, view analytics, and gain insights into customer engagement with your products.</p>
                        </div>
                    </div>
                </div>
                
                <div class="center">
                    <a href="<?php echo esc_url($dashboard_url); ?>" class="action-button">
                        Go to Dashboard
                    </a>
                    <a href="<?php echo esc_url($strains_url); ?>" class="action-button">
                        Add Your First Strain
                    </a>
                </div>
                
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🌿</div>
                        <h4>Strain Management</h4>
                        <p>Organize all your cannabis products in one place</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h4>QR Code Generation</h4>
                        <p>Create scannable codes for product verification</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">🖨️</div>
                        <h4>Print-Ready Formats</h4>
                        <p>Download individual images or sticker sheets</p>
                    </div>
                    
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h4>Analytics Dashboard</h4>
                        <p>Track scans and customer engagement</p>
                    </div>
                </div>
                
                <?php if ($plan_details['name'] === 'Free'): ?>
                <div class="tips-section">
                    <h3>💡 Ready to Grow? Consider Upgrading</h3>
                    <ul>
                        <li><strong>Starter Plan ($9/month):</strong> 100 QR codes, priority support</li>
                        <li><strong>Pro Plan ($29/month):</strong> 500 QR codes, advanced analytics</li>
                        <li><strong>Enterprise Plan ($99/month):</strong> Unlimited QR codes, white-label options</li>
                    </ul>
                    <div class="center" style="margin-top: 20px;">
                        <a href="<?php echo esc_url($account_url); ?>" class="action-button secondary">
                            View Plan Options
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="tips-section">
                    <h3>📋 Pro Tips for Success</h3>
                    <ul>
                        <li>Add high-quality images to make your strain profiles more engaging</li>
                        <li>Include detailed descriptions with effects, flavors, and growing information</li>
                        <li>Use batch codes to organize QR codes by production run or date</li>
                        <li>Monitor your analytics to see which products customers are most interested in</li>
                        <li>Test your QR codes after printing to ensure they scan properly</li>
                    </ul>
                </div>
                
                <h3>Need Help Getting Started?</h3>
                <p>We're here to help! If you have any questions or need assistance setting up your first strain or generating QR codes, don't hesitate to reach out.</p>
                
                <div class="center">
                    <a href="mailto:support@verify420.com" class="action-button secondary">
                        Contact Support
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <p>
                    <strong>Welcome to the <?php echo esc_html($site_name); ?> family!</strong><br>
                    We're excited to help you build trust and transparency with your customers.
                </p>
                
                <div class="social-links">
                    <a href="<?php echo esc_url($site_url); ?>">🌐</a>
                    <a href="mailto:support@verify420.com">📧</a>
                </div>
                
                <p>
                    This email was sent to: <?php echo esc_html($user->user_email); ?><br>
                    <a href="<?php echo esc_url($account_url); ?>">Manage your account settings</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}