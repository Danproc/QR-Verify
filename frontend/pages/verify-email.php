<?php
/**
 * Email Verification page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get token from URL
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
$verification_result = null;
$user_data = null;

// Process verification if token is provided
if ($token) {
    $verification_result = vqr_verify_email_token($token);
    
    if (!is_wp_error($verification_result)) {
        $user_data = get_user_by('ID', $verification_result['user_id']);
        
        // Auto-login the user after verification
        if ($user_data && !is_user_logged_in()) {
            wp_set_current_user($verification_result['user_id']);
            wp_set_auth_cookie($verification_result['user_id']);
        }
    }
}

// Handle resend request
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'resend_verification' && wp_verify_nonce($_POST['resend_nonce'], 'resend_verification')) {
    if (is_user_logged_in()) {
        $resend_result = vqr_resend_verification_email(get_current_user_id());
    }
}

// Get current user verification status
$current_user = wp_get_current_user();
$verification_status = null;
if ($current_user->ID) {
    $verification_status = vqr_get_verification_status($current_user->ID);
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Verify 420</title>
    
    <?php wp_head(); ?>
    
    <style>
        body.vqr-app {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-lg);
        }
        
        .vqr-verification-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--space-2xl);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        
        .vqr-verification-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: var(--space-lg);
        }
        
        .vqr-verification-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto var(--space-lg) auto;
            padding: var(--space-lg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .vqr-verification-icon.success {
            background: #dcfdf7;
            color: #065f46;
        }
        
        .vqr-verification-icon.error {
            background: #fef2f2;
            color: #991b1b;
        }
        
        .vqr-verification-icon.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .vqr-verification-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--space-md) 0;
        }
        
        .vqr-verification-message {
            color: var(--text-muted);
            margin-bottom: var(--space-xl);
            line-height: 1.6;
        }
        
        .vqr-verification-actions {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        
        .vqr-verification-info {
            background: var(--surface);
            padding: var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            text-align: left;
        }
        
        .vqr-verification-info h4 {
            margin: 0 0 var(--space-sm) 0;
            color: var(--text-primary);
            font-size: var(--font-size-md);
        }
        
        .vqr-verification-info p {
            margin: 0;
            color: var(--text-muted);
            font-size: var(--font-size-sm);
        }
        
        .vqr-verification-footer {
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border);
            font-size: var(--font-size-sm);
            color: var(--text-muted);
        }
        
        .vqr-verification-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .vqr-verification-footer a:hover {
            text-decoration: underline;
        }
        
        .vqr-alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            font-size: var(--font-size-sm);
        }
        
        .vqr-alert.success {
            background: #dcfdf7;
            color: #065f46;
            border: 1px solid #10b981;
        }
        
        .vqr-alert.error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        
        .vqr-resend-form {
            margin-top: var(--space-lg);
        }
        
        .vqr-countdown {
            color: var(--text-muted);
            font-size: var(--font-size-sm);
            margin-top: var(--space-sm);
        }
    </style>
</head>
<body class="vqr-app">
    <div class="vqr-verification-container">
        <a href="<?php echo home_url(); ?>" class="vqr-verification-logo">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
            </svg>
            Verify 420
        </a>
        
        <?php if ($token && $verification_result): ?>
            <!-- Token Verification Result -->
            <?php if (is_wp_error($verification_result)): ?>
                <!-- Verification Failed -->
                <div class="vqr-verification-icon error">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                
                <h1 class="vqr-verification-title">Verification Failed</h1>
                
                <div class="vqr-alert error">
                    <?php echo esc_html($verification_result->get_error_message()); ?>
                </div>
                
                <?php if ($verification_result->get_error_code() === 'expired_token'): ?>
                    <p class="vqr-verification-message">
                        Your verification link has expired. Click the button below to receive a new verification email.
                    </p>
                    
                    <?php if (is_user_logged_in()): ?>
                        <div class="vqr-verification-actions">
                            <form method="post" class="vqr-resend-form">
                                <?php wp_nonce_field('resend_verification', 'resend_nonce'); ?>
                                <input type="hidden" name="action" value="resend_verification">
                                <button type="submit" class="vqr-btn vqr-btn-primary">
                                    Send New Verification Email
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Verification Successful -->
                <div class="vqr-verification-icon success">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                
                <?php if ($verification_result['verification_type'] === 'email_change'): ?>
                    <h1 class="vqr-verification-title">Email Address Changed!</h1>
                    
                    <div class="vqr-alert success">
                        Your email address has been successfully updated to: <strong><?php echo esc_html($verification_result['email']); ?></strong>
                    </div>
                    
                    <p class="vqr-verification-message">
                        Great! Your email address has been changed successfully. You will now receive all communications at your new email address.
                    </p>
                <?php else: ?>
                    <h1 class="vqr-verification-title">Email Verified!</h1>
                    
                    <div class="vqr-alert success">
                        Your email address has been successfully verified.
                    </div>
                    
                    <p class="vqr-verification-message">
                        Welcome to Verify 420, <?php echo esc_html($user_data->display_name); ?>! Your account is now active and ready to use. Check your inbox for a welcome email with everything you need to get started!
                    </p>
                <?php endif; ?>
                
                <div class="vqr-verification-actions">
                    <a href="<?php echo home_url('/app/'); ?>" class="vqr-btn vqr-btn-primary">
                        Go to Dashboard
                    </a>
                    <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-secondary">
                        Generate Your First QR Codes
                    </a>
                </div>
            <?php endif; ?>
            
        <?php elseif (is_user_logged_in() && $verification_status): ?>
            <!-- Logged in user - show verification status -->
            <?php if ($verification_status['is_verified']): ?>
                <!-- Already Verified -->
                <div class="vqr-verification-icon success">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                
                <h1 class="vqr-verification-title">Email Already Verified</h1>
                <p class="vqr-verification-message">
                    Your email address is already verified. You have full access to your account.
                </p>
                
                <div class="vqr-verification-actions">
                    <a href="<?php echo home_url('/app/'); ?>" class="vqr-btn vqr-btn-primary">
                        Go to Dashboard
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Pending Verification -->
                <div class="vqr-verification-icon pending">
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                
                <h1 class="vqr-verification-title">Check Your Email</h1>
                <p class="vqr-verification-message">
                    We've sent a verification email to <strong><?php echo esc_html($current_user->user_email); ?></strong>. 
                    Please check your inbox and click the verification link to activate your account.
                </p>
                
                <?php if ($verification_status['has_pending_verification']): ?>
                    <div class="vqr-verification-info">
                        <h4>Verification Details</h4>
                        <p>Email sent: <?php echo esc_html(date('M j, Y g:i A', strtotime($verification_status['verification_sent_at']))); ?></p>
                        <p>Expires: <?php echo esc_html(date('M j, Y g:i A', strtotime($verification_status['verification_expires_at']))); ?></p>
                        <?php if ($verification_status['resent_count'] > 0): ?>
                            <p>Resent <?php echo esc_html($verification_status['resent_count']); ?> time(s)</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Show resend form or countdown -->
                <?php if ($verification_status['can_resend']): ?>
                    <div class="vqr-verification-actions">
                        <form method="post" class="vqr-resend-form">
                            <?php wp_nonce_field('resend_verification', 'resend_nonce'); ?>
                            <input type="hidden" name="action" value="resend_verification">
                            <button type="submit" class="vqr-btn vqr-btn-secondary">
                                Resend Verification Email
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="vqr-countdown">
                        Please wait a few minutes before requesting another verification email.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($resend_result)): ?>
                    <?php if (is_wp_error($resend_result)): ?>
                        <div class="vqr-alert error">
                            <?php echo esc_html($resend_result->get_error_message()); ?>
                        </div>
                    <?php else: ?>
                        <div class="vqr-alert success">
                            Verification email sent successfully! Please check your inbox.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            
        <?php else: ?>
            <!-- No token and not logged in -->
            <div class="vqr-verification-icon error">
                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            
            <h1 class="vqr-verification-title">Invalid Request</h1>
            <p class="vqr-verification-message">
                This page requires a valid verification token. Please check your email for the verification link.
            </p>
            
            <div class="vqr-verification-actions">
                <a href="<?php echo home_url('/app/login'); ?>" class="vqr-btn vqr-btn-primary">
                    Sign In
                </a>
                <a href="<?php echo home_url('/app/register'); ?>" class="vqr-btn vqr-btn-secondary">
                    Create Account
                </a>
            </div>
        <?php endif; ?>
        
        <div class="vqr-verification-footer">
            <p>
                Need help? <a href="mailto:support@verify420.com">Contact Support</a><br>
                <a href="<?php echo home_url(); ?>">Return to <?php echo get_bloginfo('name'); ?></a>
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>