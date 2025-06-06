<?php
/**
 * Account Management page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user subscription info
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();
$remaining_quota = $monthly_quota === -1 ? 'Unlimited' : ($monthly_quota - $current_usage);

// Get user metadata
$registration_date = get_user_meta($user_id, 'vqr_registration_date', true);
$last_quota_reset = get_user_meta($user_id, 'vqr_last_quota_reset', true);

// Get QR code statistics
global $wpdb;
$table_name = $wpdb->prefix . 'vqr_codes';
$total_codes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", 
    $user_id
));
$total_scans = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(scan_count) FROM {$table_name} WHERE user_id = %d", 
    $user_id
)) ?: 0;

// Handle form submissions
$update_result = [];

// Handle profile update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_profile' && wp_verify_nonce($_POST['profile_nonce'], 'update_profile')) {
    $display_name = sanitize_text_field($_POST['display_name']);
    $email = sanitize_email($_POST['email']);
    
    if (empty($display_name)) {
        $update_result['error'] = 'Display name cannot be empty.';
    } elseif (!is_email($email)) {
        $update_result['error'] = 'Please enter a valid email address.';
    } else {
        // Handle profile picture changes
        $profile_picture_updated = false;
        
        // Check if user wants to remove profile picture
        if (isset($_POST['remove_profile_picture']) && $_POST['remove_profile_picture'] === '1') {
            $remove_result = vqr_remove_user_profile_picture($user_id);
            if ($remove_result) {
                $profile_picture_updated = true;
            }
        }
        // Handle profile picture upload
        elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $upload_result = vqr_handle_profile_picture_upload($user_id, $_FILES['profile_picture']);
            if (is_wp_error($upload_result)) {
                $update_result['error'] = $upload_result->get_error_message();
                return; // Don't continue with other updates if image upload fails
            } else {
                $profile_picture_updated = true;
            }
        }
        
        // Update display name immediately
        $user_data = [
            'ID' => $user_id,
            'display_name' => $display_name
        ];
        
        $display_result = wp_update_user($user_data);
        if (is_wp_error($display_result)) {
            $update_result['error'] = $display_result->get_error_message();
        } else {
            // Handle email change if different
            if ($email !== $current_user->user_email) {
                $email_change_result = vqr_request_email_change($user_id, $email);
                if (is_wp_error($email_change_result)) {
                    $update_result['error'] = $email_change_result->get_error_message();
                } else {
                    $success_message = 'Display name updated! ' . $email_change_result['message'];
                    if ($profile_picture_updated) {
                        $success_message = 'Profile picture and display name updated! ' . $email_change_result['message'];
                    }
                    $update_result['success'] = $success_message;
                }
            } else {
                if ($profile_picture_updated) {
                    $update_result['success'] = 'Profile updated successfully including new profile picture!';
                } else {
                    $update_result['success'] = 'Profile updated successfully!';
                }
            }
            $current_user = wp_get_current_user(); // Refresh user data
        }
    }
}

// Handle password change
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'change_password' && wp_verify_nonce($_POST['password_nonce'], 'change_password')) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $update_result['error'] = 'All password fields are required.';
    } elseif (!wp_check_password($current_password, $current_user->user_pass, $user_id)) {
        $update_result['error'] = 'Current password is incorrect.';
    } elseif ($new_password !== $confirm_password) {
        $update_result['error'] = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $update_result['error'] = 'New password must be at least 8 characters long.';
    } else {
        wp_set_password($new_password, $user_id);
        $update_result['success'] = 'Password changed successfully! A confirmation email has been sent to your email address.';
    }
}

// Prepare page content
ob_start();
?>

<div class="vqr-account-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">Account Settings</h1>
        <p class="vqr-page-description">Manage your profile, subscription, and account preferences.</p>
    </div>
    
    <!-- Success/Error Messages -->
    <?php if (isset($update_result['success'])): ?>
        <div class="vqr-alert vqr-alert-success">
            <svg class="vqr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <?php echo esc_html($update_result['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($update_result['error'])): ?>
        <div class="vqr-alert vqr-alert-error">
            <svg class="vqr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <?php echo esc_html($update_result['error']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Email Verification Notice for Unverified Users -->
    <?php if (!vqr_is_email_verified($user_id)): ?>
        <div class="vqr-alert vqr-alert-warning vqr-verification-notice">
            <svg class="vqr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="vqr-verification-notice-content">
                <strong>Please verify your email address</strong>
                <p>To ensure account security and enable all features, please verify your email address. We've sent a verification email to <strong><?php echo esc_html($current_user->user_email); ?></strong>.</p>
                <div class="vqr-verification-notice-actions">
                    <a href="<?php echo home_url('/app/verify-email'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                        Check Verification Status
                    </a>
                    <button id="resendVerificationBtn" class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                        Resend Email
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="vqr-account-grid">
        <!-- Left Column: Account Info & Profile -->
        <div class="vqr-account-main">
            
            <!-- Account Overview -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Account Overview</h3>
                </div>
                <div class="vqr-card-content">
                    <div class="vqr-account-overview">
                        <div class="vqr-overview-item">
                            <div class="vqr-overview-avatar">
                                <?php echo get_avatar($user_id, 64, '', '', ['class' => 'vqr-avatar']); ?>
                            </div>
                            <div class="vqr-overview-info">
                                <h4 class="vqr-overview-name"><?php echo esc_html($current_user->display_name); ?></h4>
                                <p class="vqr-overview-email">
                                    <?php echo esc_html($current_user->user_email); ?>
                                    <?php if (vqr_is_email_verified($user_id)): ?>
                                        <span class="vqr-verification-badge verified">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="vqr-verification-badge unverified">
                                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Unverified
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <span class="vqr-badge vqr-badge-primary">
                                    <?php echo esc_html($plan_details['name']); ?> Plan
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vqr-overview-stats">
                        <div class="vqr-stat-item">
                            <span class="vqr-stat-value"><?php echo number_format($total_codes); ?></span>
                            <span class="vqr-stat-label">QR Codes Created</span>
                        </div>
                        <div class="vqr-stat-item">
                            <span class="vqr-stat-value"><?php echo number_format($total_scans); ?></span>
                            <span class="vqr-stat-label">Total Scans</span>
                        </div>
                        <div class="vqr-stat-item">
                            <span class="vqr-stat-value">
                                <?php echo $registration_date ? esc_html(date('M Y', strtotime($registration_date))) : 'N/A'; ?>
                            </span>
                            <span class="vqr-stat-label">Member Since</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Settings -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Profile Information</h3>
                </div>
                <div class="vqr-card-content">
                    <form method="post" class="vqr-form vqr-account-form" enctype="multipart/form-data">
                        <?php wp_nonce_field('update_profile', 'profile_nonce'); ?>
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="remove_profile_picture" id="removeProfilePictureFlag" value="0">
                        
                        <!-- Profile Picture Upload -->
                        <div class="vqr-form-group vqr-form-full vqr-profile-picture-section">
                            <label class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Profile Picture
                            </label>
                            
                            <div class="vqr-profile-picture-upload">
                                <div class="vqr-current-picture" onclick="document.getElementById('profile_picture').click()">
                                    <?php echo get_avatar($user_id, 80, '', '', ['class' => 'vqr-profile-preview', 'id' => 'profilePreview']); ?>
                                    <div class="vqr-picture-overlay">
                                        <svg class="vqr-camera-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                    </div>
                                </div>
                                
                                <div class="vqr-upload-controls">
                                    <input type="file" 
                                           id="profile_picture" 
                                           name="profile_picture" 
                                           accept="image/*" 
                                           class="vqr-file-input">
                                    <label for="profile_picture" class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                                        Choose Photo
                                    </label>
                                    <button type="button" id="removeProfilePicture" class="vqr-btn vqr-btn-outline vqr-btn-sm vqr-remove-picture" style="display: <?php echo get_user_meta($user_id, 'vqr_profile_picture_id', true) ? 'inline-block' : 'none'; ?>;">
                                        Remove
                                    </button>
                                </div>
                                
                                <div class="vqr-field-help">
                                    Recommended: Square image, at least 200x200 pixels. Max file size: 2MB.
                                </div>
                            </div>
                        </div>
                        
                        <div class="vqr-form-grid">
                            <div class="vqr-form-group">
                                <label for="display_name" class="vqr-label">
                                    <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    Display Name
                                </label>
                                <input type="text" 
                                       id="display_name" 
                                       name="display_name" 
                                       class="vqr-input" 
                                       value="<?php echo esc_attr($current_user->display_name); ?>" 
                                       required>
                            </div>
                            
                            <div class="vqr-form-group">
                                <label for="email" class="vqr-label">
                                    <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 7.89a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="vqr-input" 
                                       value="<?php echo esc_attr($current_user->user_email); ?>" 
                                       required>
                            </div>
                            
                            <div class="vqr-form-group">
                                <label class="vqr-label">Username</label>
                                <input type="text" 
                                       class="vqr-input" 
                                       value="<?php echo esc_attr($current_user->user_login); ?>" 
                                       disabled>
                                <div class="vqr-field-help">Username cannot be changed</div>
                            </div>
                        </div>
                        
                        <div class="vqr-form-actions">
                            <button type="submit" class="vqr-btn vqr-btn-primary">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Password Change -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Change Password</h3>
                </div>
                <div class="vqr-card-content">
                    <form method="post" class="vqr-form vqr-account-form">
                        <?php wp_nonce_field('change_password', 'password_nonce'); ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="vqr-form-grid">
                            <div class="vqr-form-group vqr-form-full">
                                <label for="current_password" class="vqr-label">
                                    <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Current Password
                                </label>
                                <input type="password" 
                                       id="current_password" 
                                       name="current_password" 
                                       class="vqr-input" 
                                       required>
                            </div>
                            
                            <div class="vqr-form-group">
                                <label for="new_password" class="vqr-label">
                                    <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                    </svg>
                                    New Password
                                </label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       class="vqr-input" 
                                       minlength="8"
                                       required>
                                <div class="vqr-field-help">Must be at least 8 characters long</div>
                            </div>
                            
                            <div class="vqr-form-group">
                                <label for="confirm_password" class="vqr-label">
                                    <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Confirm New Password
                                </label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       class="vqr-input" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="vqr-form-actions">
                            <button type="submit" class="vqr-btn vqr-btn-primary">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
        
        <!-- Right Column: Subscription & Account Management -->
        <div class="vqr-account-sidebar">
            
            <!-- Current Subscription -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Current Plan</h3>
                </div>
                <div class="vqr-card-content">
                    <div class="vqr-subscription-info">
                        <div class="vqr-plan-badge">
                            <span class="vqr-plan-name"><?php echo esc_html($plan_details['name']); ?></span>
                            <span class="vqr-plan-price">
                                <?php if ($plan_details['price'] > 0): ?>
                                    $<?php echo esc_html($plan_details['price']); ?>/month
                                <?php else: ?>
                                    Free
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="vqr-plan-features">
                            <h4>Plan Features:</h4>
                            <ul>
                                <?php foreach ($plan_details['features'] as $feature): ?>
                                    <li>
                                        <svg class="vqr-feature-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <?php echo esc_html($feature); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        
                        <div class="vqr-plan-actions">
                            <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-full">
                                Manage Subscription
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage Statistics -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Usage This Month</h3>
                </div>
                <div class="vqr-card-content">
                    <div class="vqr-usage-display">
                        <div class="vqr-usage-numbers">
                            <span class="vqr-usage-current"><?php echo number_format($current_usage); ?></span>
                            <span class="vqr-usage-separator">/</span>
                            <span class="vqr-usage-limit">
                                <?php echo $monthly_quota === -1 ? '∞' : number_format($monthly_quota); ?>
                            </span>
                        </div>
                        <div class="vqr-usage-label">QR Codes Generated</div>
                        
                        <?php if ($monthly_quota !== -1): ?>
                            <div class="vqr-usage-bar">
                                <div class="vqr-usage-progress" 
                                     style="width: <?php echo min(100, ($current_usage / $monthly_quota) * 100); ?>%"></div>
                            </div>
                            <div class="vqr-usage-remaining">
                                <?php echo is_numeric($remaining_quota) ? number_format($remaining_quota) : $remaining_quota; ?> remaining
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($last_quota_reset): ?>
                            <div class="vqr-usage-reset">
                                Last reset: <?php echo esc_html(date('M j, Y', strtotime($last_quota_reset))); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="vqr-card vqr-mb-lg">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Quick Actions</h3>
                </div>
                <div class="vqr-card-content">
                    <div class="vqr-quick-actions">
                        <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-quick-action">
                            <svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Generate QR Codes
                        </a>
                        
                        <a href="<?php echo home_url('/app/strains'); ?>" class="vqr-quick-action">
                            <svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Manage Strains
                        </a>
                        
                        <a href="<?php echo home_url('/app/codes'); ?>" class="vqr-quick-action">
                            <svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                            </svg>
                            View All Codes
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Account Management -->
            <div class="vqr-card">
                <div class="vqr-card-header">
                    <h3 class="vqr-card-title">Account Management</h3>
                </div>
                <div class="vqr-card-content">
                    <div class="vqr-account-actions">
                        <button onclick="VQR.deleteAccount()" class="vqr-account-action vqr-account-action-danger">
                            <svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete Account
                        </button>
                    </div>
                    
                    <div class="vqr-account-help">
                        <p class="vqr-text-muted">Need help? <a href="mailto:support@verify420.com">Contact Support</a></p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
/* Account Page Styles */
.vqr-account-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 16px;
}

.vqr-page-header {
    margin-bottom: var(--space-xl);
}

.vqr-page-title {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 var(--space-xs) 0;
}

.vqr-page-description {
    color: var(--text-muted);
    margin: 0;
}

.vqr-account-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: var(--space-xl);
    margin-top: var(--space-lg);
}

/* Alerts */
.vqr-alert {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-lg);
    font-weight: 500;
}

.vqr-alert-success {
    background: #dcfdf7;
    color: #065f46;
    border: 1px solid #10b981;
}

.vqr-alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #ef4444;
}

.vqr-alert-warning {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.vqr-alert-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* Account Overview */
.vqr-account-overview {
    margin-bottom: var(--space-lg);
}

.vqr-overview-item {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
}

.vqr-overview-avatar {
    flex-shrink: 0;
}

.vqr-avatar {
    border-radius: 50%;
    border: 3px solid var(--border);
}

.vqr-overview-info h4 {
    margin: 0 0 var(--space-xs) 0;
    font-size: var(--font-size-xl);
    font-weight: 600;
}

.vqr-overview-email {
    margin: 0 0 var(--space-sm) 0;
    color: var(--text-muted);
}

.vqr-overview-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-lg);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

.vqr-stat-item {
    text-align: center;
}

.vqr-stat-value {
    display: block;
    font-size: var(--font-size-2xl);
    font-weight: 700;
    color: var(--primary);
}

.vqr-stat-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    margin-top: var(--space-xs);
}

/* Form Styles */
.vqr-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-lg);
}

.vqr-form-full {
    grid-column: 1 / -1;
}

.vqr-label {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    font-weight: 500;
    margin-bottom: var(--space-sm);
}

.vqr-label-icon {
    opacity: 0.7;
}

.vqr-field-help {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: var(--space-xs);
}

.vqr-form-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: var(--space-lg);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

/* Subscription Info */
.vqr-subscription-info {
    text-align: center;
}

.vqr-plan-badge {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: var(--space-lg);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-lg);
}

.vqr-plan-name {
    display: block;
    font-size: var(--font-size-xl);
    font-weight: 700;
    margin-bottom: var(--space-xs);
}

.vqr-plan-price {
    font-size: var(--font-size-lg);
    opacity: 0.9;
}

.vqr-plan-features h4 {
    margin: 0 0 var(--space-md) 0;
    text-align: left;
}

.vqr-plan-features ul {
    list-style: none;
    padding: 0;
    margin: 0 0 var(--space-lg) 0;
}

.vqr-plan-features li {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-xs) 0;
    text-align: left;
}

.vqr-feature-icon {
    color: var(--primary);
}

.vqr-btn-full {
    width: 100%;
    justify-content: center;
}

/* Usage Display */
.vqr-usage-display {
    text-align: center;
}

.vqr-usage-numbers {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    margin-bottom: var(--space-sm);
}

.vqr-usage-current {
    color: var(--primary);
}

.vqr-usage-separator {
    color: var(--text-muted);
    margin: 0 var(--space-xs);
}

.vqr-usage-limit {
    color: var(--text-muted);
}

.vqr-usage-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    margin-bottom: var(--space-md);
}

.vqr-usage-bar {
    background: var(--surface);
    height: 8px;
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: var(--space-sm);
}

.vqr-usage-progress {
    background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    height: 100%;
    transition: width 0.3s ease;
}

.vqr-usage-remaining {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-usage-reset {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: var(--space-sm);
}

/* Quick Actions */
.vqr-quick-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-quick-action {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-md);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-primary);
    font-weight: 500;
    transition: all 0.2s ease;
}

.vqr-quick-action:hover {
    background: var(--white);
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.vqr-action-icon {
    color: var(--primary);
}

/* Account Actions */
.vqr-account-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    margin-bottom: var(--space-lg);
}

.vqr-account-action {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-md);
    background: none;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: left;
    width: 100%;
}

.vqr-account-action:hover {
    background: var(--surface);
    border-color: var(--primary);
}

.vqr-account-action-danger {
    color: var(--danger);
    border-color: var(--danger);
}

.vqr-account-action-danger:hover {
    background: #fef2f2;
    border-color: var(--danger);
}

.vqr-account-help {
    text-align: center;
    padding-top: var(--space-md);
    border-top: 1px solid var(--border);
}

.vqr-account-help a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.vqr-account-help a:hover {
    text-decoration: underline;
}

/* Loading animation for delete button */
.animate-spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Email Verification Badges */
.vqr-verification-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 500;
    margin-left: var(--space-sm);
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.vqr-verification-badge.verified {
    background: #dcfdf7;
    color: #065f46;
    border: 1px solid #10b981;
}

.vqr-verification-badge.unverified {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.vqr-verification-badge svg {
    width: 12px;
    height: 12px;
}

/* Email Verification Notice */
.vqr-verification-notice {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
}

.vqr-verification-notice-content {
    flex: 1;
}

.vqr-verification-notice-content strong {
    display: block;
    margin-bottom: var(--space-xs);
}

.vqr-verification-notice-content p {
    margin: 0 0 var(--space-md) 0;
    font-size: var(--font-size-sm);
}

.vqr-verification-notice-actions {
    display: flex;
    gap: var(--space-sm);
    flex-wrap: wrap;
}

/* Profile Picture Upload */
.vqr-profile-picture-section {
    margin-bottom: var(--space-xl);
}

.vqr-profile-picture-upload {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
}

.vqr-current-picture {
    position: relative;
    display: inline-block;
}

.vqr-profile-preview {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid var(--border);
    transition: all 0.2s ease;
    object-fit: cover;
    object-position: center;
}

.vqr-picture-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.2s ease;
    cursor: pointer;
}

.vqr-current-picture:hover .vqr-picture-overlay {
    opacity: 1;
}

.vqr-camera-icon {
    color: white;
}

.vqr-upload-controls {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-file-input {
    display: none;
}

.vqr-remove-picture {
    color: var(--error);
    border-color: var(--error);
}

.vqr-remove-picture:hover {
    background: #fef2f2;
    border-color: var(--error);
    color: var(--error);
}

/* Responsive Design */
@media (max-width: 768px) {
    .vqr-account-grid {
        grid-template-columns: 1fr;
        gap: var(--space-lg);
    }
    
    .vqr-form-grid {
        grid-template-columns: 1fr;
    }
    
    .vqr-overview-item {
        flex-direction: column;
        text-align: center;
        gap: var(--space-md);
    }
    
    .vqr-overview-stats {
        grid-template-columns: 1fr;
        gap: var(--space-md);
    }
    
    .vqr-profile-picture-upload {
        flex-direction: column;
        text-align: center;
        gap: var(--space-md);
    }
    
    .vqr-upload-controls {
        align-items: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Extend VQR object with account functions
    window.VQR = window.VQR || {};
    
    // Delete account
    VQR.deleteAccount = function() {
        const confirmation = prompt('⚠️ DANGER: This action cannot be undone!\n\nAll your data will be permanently deleted including:\n• All cannabis strains\n• All QR codes\n• All analytics data\n• Your profile and settings\n\nType "DELETE MY ACCOUNT" to confirm:');
        
        if (confirmation === 'DELETE MY ACCOUNT') {
            if (confirm('Are you absolutely sure? This is your final chance to cancel.\n\nClick OK to permanently delete your account, or Cancel to keep it.')) {
                // Show loading state
                const deleteBtn = document.querySelector('button[onclick="VQR.deleteAccount()"]');
                if (deleteBtn) {
                    deleteBtn.disabled = true;
                    deleteBtn.innerHTML = '<svg class="vqr-action-icon animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" class="opacity-75"></path></svg> Deleting Account...';
                }
                
                // Make AJAX request to delete account
                fetch(vqr_ajax.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'vqr_delete_account',
                        nonce: vqr_ajax.nonce,
                        confirmation: confirmation
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message and redirect
                        alert('✅ ' + data.data.message);
                        window.location.href = data.data.redirect;
                    } else {
                        // Show error message
                        if (window.VQR && VQR.showNotification) {
                            VQR.showNotification('Error', data.data || 'Failed to delete account.', 'error');
                        } else {
                            alert('❌ Error: ' + (data.data || 'Failed to delete account.'));
                        }
                        
                        // Reset button
                        if (deleteBtn) {
                            deleteBtn.disabled = false;
                            deleteBtn.innerHTML = '<svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Delete Account';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    if (window.VQR && VQR.showNotification) {
                        VQR.showNotification('Error', 'Network error. Please try again.', 'error');
                    } else {
                        alert('❌ Network error. Please try again.');
                    }
                    
                    // Reset button
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = '<svg class="vqr-action-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Delete Account';
                    }
                });
            }
        } else if (confirmation !== null) {
            if (window.VQR && VQR.showNotification) {
                VQR.showNotification('Info', 'Account deletion cancelled - confirmation text did not match.', 'info');
            } else {
                alert('ℹ️ Account deletion cancelled - confirmation text did not match.');
            }
        }
    };
    
    // Resend verification email
    const resendBtn = document.getElementById('resendVerificationBtn');
    if (resendBtn) {
        resendBtn.addEventListener('click', function() {
            const button = this;
            const originalText = button.textContent;
            
            button.disabled = true;
            button.textContent = 'Sending...';
            
            // Make AJAX request
            fetch(vqr_ajax.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'vqr_resend_verification',
                    nonce: vqr_ajax.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    VQR.showNotification('Success', data.data.message, 'success');
                    // Disable button for 5 minutes
                    setTimeout(() => {
                        button.disabled = false;
                        button.textContent = originalText;
                    }, 300000); // 5 minutes
                    button.textContent = 'Email Sent';
                } else {
                    VQR.showNotification('Error', data.data || 'Failed to send verification email.', 'error');
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                VQR.showNotification('Error', 'Network error. Please try again.', 'error');
                button.disabled = false;
                button.textContent = originalText;
            });
        });
    }
    
    // Password confirmation validation
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    if (newPasswordField && confirmPasswordField) {
        function validatePasswordMatch() {
            const newPassword = newPasswordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmPasswordField.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        }
        
        newPasswordField.addEventListener('input', validatePasswordMatch);
        confirmPasswordField.addEventListener('input', validatePasswordMatch);
    }
    
    // Profile picture upload functionality
    const profilePictureInput = document.getElementById('profile_picture');
    const profilePreview = document.getElementById('profilePreview');
    const removeProfileButton = document.getElementById('removeProfilePicture');
    const pictureOverlay = document.querySelector('.vqr-picture-overlay');
    
    console.log('Profile elements found:', {
        input: !!profilePictureInput,
        preview: !!profilePreview,
        removeBtn: !!removeProfileButton,
        overlay: !!pictureOverlay
    });
    
    if (profilePictureInput && profilePreview) {
        // Store original avatar URL
        const originalAvatarSrc = profilePreview.src;
        
        // Handle file selection
        profilePictureInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    if (window.VQR && VQR.showNotification) {
                        VQR.showNotification('Error', 'Please select a valid image file.', 'error');
                    } else {
                        alert('Please select a valid image file.');
                    }
                    this.value = '';
                    return;
                }
                
                // Validate file size (2MB limit)
                if (file.size > 2 * 1024 * 1024) {
                    if (window.VQR && VQR.showNotification) {
                        VQR.showNotification('Error', 'Image file size must be less than 2MB.', 'error');
                    } else {
                        alert('Image file size must be less than 2MB.');
                    }
                    this.value = '';
                    return;
                }
                
                // Create preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('File loaded successfully');
                    profilePreview.src = e.target.result;
                    if (removeProfileButton) {
                        removeProfileButton.style.display = 'inline-block';
                    }
                };
                reader.onerror = function(e) {
                    console.error('Error reading file:', e);
                };
                console.log('Reading file:', file.name);
                reader.readAsDataURL(file);
            }
        });
        
        // Handle remove picture
        if (removeProfileButton) {
            removeProfileButton.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove your profile picture?')) {
                    // Set flag to remove profile picture on form submission
                    document.getElementById('removeProfilePictureFlag').value = '1';
                    profilePictureInput.value = '';
                    profilePreview.src = originalAvatarSrc;
                    this.style.display = 'none';
                    
                    // Show notification that picture will be removed on save
                    if (window.VQR && VQR.showNotification) {
                        VQR.showNotification('Info', 'Profile picture will be removed when you save your profile.', 'info');
                    }
                }
            });
        }
        
        // Clicking on the profile picture is handled by the onclick attribute
    }
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Account Settings';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>