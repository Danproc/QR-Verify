<?php
/**
 * Registration page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/app/'));
    exit;
}

// Handle registration form submission
if ($_POST && isset($_POST['vqr_register_nonce']) && wp_verify_nonce($_POST['vqr_register_nonce'], 'vqr_register')) {
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = array();
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (username_exists($username)) {
        $errors[] = 'Username already exists.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!is_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (email_exists($email)) {
        $errors[] = 'Email already registered.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    // Check TOS acceptance
    if (!isset($_POST['accept_tos']) || $_POST['accept_tos'] !== '1') {
        $errors[] = 'You must accept the Terms of Service to create an account.';
    }
    
    // Create user if no errors
    if (empty($errors)) {
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            $errors[] = $user_id->get_error_message();
        } else {
            // The user role and meta will be set automatically by the user_register hook
            
            // Record TOS acceptance
            vqr_record_tos_acceptance($user_id);
            
            // Create email verification token
            $token = vqr_create_verification_token($user_id, $email);
            if (is_wp_error($token)) {
                $errors[] = 'Account created but verification email failed to send. Please contact support.';
            } else {
                // Send verification email
                $email_result = vqr_send_verification_email($user_id, $email, $token);
                if (is_wp_error($email_result)) {
                    $errors[] = 'Account created but verification email failed to send. Please contact support.';
                } else {
                    // Auto-login the user and redirect to verification page
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                    
                    wp_redirect(home_url('/app/verify-email'));
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Verify 420</title>
    
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
        
        .vqr-register-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--space-2xl);
            width: 100%;
            max-width: 450px;
        }
        
        .vqr-register-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }
        
        .vqr-register-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: var(--space-md);
        }
        
        .vqr-register-logo img {
            transition: transform 0.2s ease;
        }
        
        .vqr-register-logo:hover img {
            transform: scale(1.05);
        }
        
        .vqr-register-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--space-xs) 0;
        }
        
        .vqr-register-subtitle {
            color: var(--text-muted);
            margin: 0;
        }
        
        .vqr-register-form {
            margin-bottom: var(--space-lg);
        }
        
        .vqr-register-errors {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            font-size: var(--font-size-sm);
        }
        
        .vqr-register-errors ul {
            margin: 0;
            padding-left: var(--space-lg);
        }
        
        .vqr-register-footer {
            text-align: center;
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border);
        }
        
        .vqr-register-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .vqr-register-footer a:hover {
            text-decoration: underline;
        }
        
        .vqr-features {
            background: var(--surface);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-bottom: var(--space-lg);
        }
        
        .vqr-features h4 {
            margin: 0 0 var(--space-sm) 0;
            font-size: var(--font-size-sm);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .vqr-features ul {
            margin: 0;
            padding-left: var(--space-lg);
            font-size: var(--font-size-sm);
            color: var(--text-muted);
        }
        
        .vqr-features li {
            margin-bottom: var(--space-xs);
        }
        
        /* TOS Acceptance Styles */
        .vqr-tos-acceptance {
            margin: var(--space-xl) 0;
        }
        
        .vqr-checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
            cursor: pointer;
            position: relative;
            padding-left: 28px;
        }
        
        .vqr-checkbox-container input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
            height: 0;
            width: 0;
        }
        
        .vqr-checkmark {
            position: absolute;
            left: 0;
            top: 2px;
            height: 20px;
            width: 20px;
            background-color: var(--white);
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }
        
        .vqr-checkbox-container:hover input ~ .vqr-checkmark {
            border-color: var(--primary);
        }
        
        .vqr-checkbox-container input:checked ~ .vqr-checkmark {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .vqr-checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        
        .vqr-checkbox-container input:checked ~ .vqr-checkmark:after {
            display: block;
        }
        
        .vqr-checkbox-text {
            flex: 1;
            font-size: var(--font-size-sm);
            color: var(--text-muted);
            line-height: 1.5;
        }
        
        .vqr-tos-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .vqr-tos-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="vqr-app">
    <div class="vqr-register-container">
<?php 
        $global_logo = vqr_get_global_logo();
        ?>
        <div class="vqr-register-header">
            <a href="<?php echo home_url(); ?>" class="vqr-register-logo">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="height: 48px; width: auto; max-width: 200px; object-fit: contain;">
                <?php else: ?>
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                    <span>Verify 420</span>
                <?php endif; ?>
            </a>
            
            <h1 class="vqr-register-title">Create your account</h1>
            <p class="vqr-register-subtitle">Start securing your cannabis products today</p>
        </div>
        
        <div class="vqr-features">
            <h4>Free Account Includes:</h4>
            <ul>
                <li>50 QR codes per month</li>
                <li>Basic scan analytics</li>
                <li>Product verification pages</li>
                <li>Email support</li>
            </ul>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="vqr-register-errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" class="vqr-register-form">
            <?php wp_nonce_field('vqr_register', 'vqr_register_nonce'); ?>
            
            <div class="vqr-form-group">
                <label for="username" class="vqr-label">Username</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="vqr-input" 
                       required 
                       value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>">
            </div>
            
            <div class="vqr-form-group">
                <label for="email" class="vqr-label">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="vqr-input" 
                       required 
                       value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
            </div>
            
            <div class="vqr-form-group">
                <label for="password" class="vqr-label">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="vqr-input" 
                       required 
                       minlength="6">
                <small class="vqr-text-muted">At least 6 characters</small>
            </div>
            
            <div class="vqr-form-group">
                <label for="confirm_password" class="vqr-label">Confirm Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="vqr-input" 
                       required>
            </div>
            
            <!-- Terms of Service Acceptance -->
            <div class="vqr-form-group vqr-tos-acceptance">
                <label class="vqr-checkbox-container">
                    <input type="checkbox" name="accept_tos" value="1" required id="acceptTosCheckbox">
                    <span class="vqr-checkmark"></span>
                    <div class="vqr-checkbox-text">
                        I agree to the 
                        <a href="<?php echo esc_url(vqr_get_tos_url()); ?>" target="_blank" class="vqr-tos-link">Terms of Service</a> 
                        and 
                        <a href="<?php echo esc_url(vqr_get_privacy_policy_url()); ?>" target="_blank" class="vqr-tos-link">Privacy Policy</a>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="vqr-btn vqr-btn-primary vqr-btn-lg" style="width: 100%;" id="createAccountBtn">
                Create Account
            </button>
        </form>
        
        <div class="vqr-register-footer">
            <p>
                Already have an account? 
                <a href="<?php echo home_url('/app/login'); ?>">Sign in</a>
            </p>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('.vqr-register-form');
        const tosCheckbox = document.getElementById('acceptTosCheckbox');
        const submitBtn = document.getElementById('createAccountBtn');
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('confirm_password');
        
        // Password confirmation validation
        function validatePasswordMatch() {
            const password = passwordField.value;
            const confirmPassword = confirmPasswordField.value;
            
            if (confirmPassword && password !== confirmPassword) {
                confirmPasswordField.setCustomValidity('Passwords do not match');
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        }
        
        if (passwordField && confirmPasswordField) {
            passwordField.addEventListener('input', validatePasswordMatch);
            confirmPasswordField.addEventListener('input', validatePasswordMatch);
        }
        
        // Form submission handling
        if (form) {
            form.addEventListener('submit', function(e) {
                // Check TOS acceptance
                if (!tosCheckbox.checked) {
                    e.preventDefault();
                    alert('Please accept the Terms of Service and Privacy Policy to create your account.');
                    tosCheckbox.focus();
                    return false;
                }
                
                // Check password match
                if (passwordField.value !== confirmPasswordField.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please check your passwords and try again.');
                    confirmPasswordField.focus();
                    return false;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating Account...';
                
                return true;
            });
        }
        
        // TOS link click tracking
        const tosLinks = document.querySelectorAll('.vqr-tos-link');
        tosLinks.forEach(link => {
            link.addEventListener('click', function() {
                // Track TOS/Privacy policy clicks for analytics
                console.log('Legal document opened:', this.href);
            });
        });
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>