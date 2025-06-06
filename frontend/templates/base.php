<?php
/**
 * Base template for Verify 420 SaaS Dashboard
 */

defined('ABSPATH') || exit;

// Get current user info
$current_user = wp_get_current_user();
$current_page = get_query_var('vqr_app_page', 'dashboard');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title ?? 'Dashboard'); ?> - Verify 420</title>
    
    <?php wp_head(); ?>
</head>
<body class="vqr-app">
    <div class="vqr-app-container">
        <!-- Header -->
        <header class="vqr-app-header">
            <div class="vqr-header-left">
                <button class="vqr-mobile-menu-btn" aria-label="Toggle menu">
                    <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
<?php 
                $global_logo = vqr_get_global_logo();
                ?>
                <a href="<?php echo home_url('/app/'); ?>" class="vqr-logo">
                    <?php if ($global_logo): ?>
                        <img src="<?php echo esc_url($global_logo['url']); ?>" 
                             alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                             class="vqr-logo-img">
                    <?php else: ?>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                        </svg>
                        <span class="vqr-logo-text">Verify 420</span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="vqr-header-actions">
                <div class="vqr-user-menu">
                    <button class="vqr-btn vqr-btn-secondary vqr-btn-sm vqr-user-menu-trigger" id="userMenuToggle">
                        <?php echo get_avatar($current_user->ID, 20, '', '', ['class' => 'vqr-user-avatar']); ?>
                        <?php echo esc_html($current_user->display_name); ?>
                        <svg class="vqr-nav-icon vqr-dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    
                    <div class="vqr-user-dropdown" id="userDropdown">
                        <div class="vqr-dropdown-header">
                            <div class="vqr-dropdown-user-info">
                                <?php echo get_avatar($current_user->ID, 32, '', '', ['class' => 'vqr-dropdown-avatar']); ?>
                                <div class="vqr-dropdown-user-details">
                                    <div class="vqr-dropdown-name"><?php echo esc_html($current_user->display_name); ?></div>
                                    <div class="vqr-dropdown-email"><?php echo esc_html($current_user->user_email); ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="vqr-dropdown-content">
                            <a href="<?php echo home_url('/app/account'); ?>" class="vqr-dropdown-item">
                                <svg class="vqr-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Account Settings
                            </a>
                            
                            <a href="mailto:support@verify420.com" class="vqr-dropdown-item">
                                <svg class="vqr-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Help & Support
                            </a>
                            
                            <div class="vqr-dropdown-divider"></div>
                            
                            <a href="<?php echo wp_logout_url(home_url('/app/login')); ?>" class="vqr-dropdown-item vqr-dropdown-logout">
                                <svg class="vqr-dropdown-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Sign Out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <div class="vqr-app-content">
            <!-- Sidebar -->
            <aside class="vqr-app-sidebar">
                <nav class="vqr-nav">
                    <a href="<?php echo home_url('/app/'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5a2 2 0 012-2h2a2 2 0 012 2v0H8v0z"/>
                        </svg>
                        Dashboard
                    </a>
                    
                    <a href="<?php echo home_url('/app/strains'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'strains' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        Strains
                    </a>
                    
                    <a href="<?php echo home_url('/app/generate'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'generate' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Generate QR Codes
                    </a>
                    
                    <a href="<?php echo home_url('/app/analytics'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'analytics' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Analytics
                    </a>
                    
                    <a href="<?php echo home_url('/app/codes'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'codes' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                        </svg>
                        Codes
                    </a>
                    
                    <?php
                    // Get pending orders count for basket badge (only show badge for pending/processing)
                    global $wpdb;
                    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
                    $pending_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$orders_table} WHERE user_id = %d AND status IN ('pending', 'processing')",
                        get_current_user_id()
                    ));
                    ?>
                    <a href="<?php echo home_url('/app/basket'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'basket' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Orders
                        <?php if ($pending_count > 0): ?>
                            <span class="vqr-nav-badge"><?php echo $pending_count; ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <a href="<?php echo home_url('/app/billing'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'billing' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Billing
                    </a>
                    
                    <a href="<?php echo home_url('/app/account'); ?>" 
                       class="vqr-nav-item <?php echo $current_page === 'account' ? 'active' : ''; ?>">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Account
                    </a>
                </nav>
                
                <div style="margin-top: auto; padding-top: var(--space-lg);">
                    <a href="<?php echo wp_logout_url(home_url('/app/login')); ?>" class="vqr-nav-item">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign Out
                    </a>
                </div>
            </aside>
            
            <!-- Main Content -->
            <main class="vqr-app-main">
                <?php if (isset($page_content)) echo $page_content; ?>
            </main>
        </div>
    </div>
    
    <!-- Mobile Overlay -->
    <div class="vqr-mobile-overlay"></div>
    
    <?php wp_footer(); ?>
</body>
</html>