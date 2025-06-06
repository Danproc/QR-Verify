<?php
/**
 * Login page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Redirect if already logged in
if (is_user_logged_in()) {
    wp_redirect(home_url('/app/'));
    exit;
}

// Handle login form submission
if ($_POST && isset($_POST['vqr_login_nonce']) && wp_verify_nonce($_POST['vqr_login_nonce'], 'vqr_login')) {
    $username = sanitize_user($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $creds = array(
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember
    );
    
    $user = wp_signon($creds, false);
    
    if (is_wp_error($user)) {
        $login_error = $user->get_error_message();
    } else {
        wp_redirect(home_url('/app/'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Verify 420</title>
    
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
        
        .vqr-login-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--space-2xl);
            width: 100%;
            max-width: 400px;
        }
        
        .vqr-login-header {
            text-align: center;
            margin-bottom: var(--space-xl);
        }
        
        .vqr-login-logo {
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
        
        .vqr-login-logo img {
            transition: transform 0.2s ease;
        }
        
        .vqr-login-logo:hover img {
            transform: scale(1.05);
        }
        
        .vqr-login-title {
            font-size: var(--font-size-2xl);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--space-xs) 0;
        }
        
        .vqr-login-subtitle {
            color: var(--text-muted);
            margin: 0;
        }
        
        .vqr-login-form {
            margin-bottom: var(--space-lg);
        }
        
        .vqr-login-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            font-size: var(--font-size-sm);
        }
        
        .vqr-checkbox-group {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        
        .vqr-checkbox {
            width: auto;
        }
        
        .vqr-login-footer {
            text-align: center;
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border);
        }
        
        .vqr-login-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .vqr-login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="vqr-app">
    <div class="vqr-login-container">
<?php 
        $global_logo = vqr_get_global_logo();
        ?>
        <div class="vqr-login-header">
            <a href="<?php echo home_url(); ?>" class="vqr-login-logo">
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
            
            <h1 class="vqr-login-title">Welcome back</h1>
            <p class="vqr-login-subtitle">Sign in to your account to continue</p>
        </div>
        
        <?php if (isset($login_error)): ?>
            <div class="vqr-login-error">
                <?php echo esc_html($login_error); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" class="vqr-login-form">
            <?php wp_nonce_field('vqr_login', 'vqr_login_nonce'); ?>
            
            <div class="vqr-form-group">
                <label for="username" class="vqr-label">Username or Email</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       class="vqr-input" 
                       required 
                       value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>">
            </div>
            
            <div class="vqr-form-group">
                <label for="password" class="vqr-label">Password</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       class="vqr-input" 
                       required>
            </div>
            
            <div class="vqr-form-group">
                <div class="vqr-checkbox-group">
                    <input type="checkbox" 
                           id="remember" 
                           name="remember" 
                           class="vqr-checkbox"
                           <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    <label for="remember" class="vqr-label" style="margin-bottom: 0;">Remember me</label>
                </div>
            </div>
            
            <button type="submit" class="vqr-btn vqr-btn-primary vqr-btn-lg" style="width: 100%;">
                Sign In
            </button>
        </form>
        
        <div class="vqr-login-footer">
            <p>
                <a href="<?php echo wp_lostpassword_url(home_url('/app/login')); ?>">
                    Forgot your password?
                </a>
            </p>
            <p style="margin-top: var(--space-sm);">
                Don't have an account? 
                <a href="<?php echo home_url('/app/register'); ?>">Sign up</a>
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>