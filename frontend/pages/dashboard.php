<?php
/**
 * Dashboard page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's QR code data
global $wpdb;
$table_name = $wpdb->prefix . 'vqr_codes';

// Get user-specific QR codes
$total_qr_codes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", 
    $user_id
));
$total_scans = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(scan_count) FROM {$table_name} WHERE user_id = %d", 
    $user_id
)) ?: 0;
$recent_codes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 5", 
    $user_id
));

// Get real user subscription data
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();

// Get recent active sticker orders (pending and processing)
$sticker_orders_table = $wpdb->prefix . 'vqr_sticker_orders';
$pending_orders = $wpdb->get_results($wpdb->prepare(
    "SELECT id, order_number, qr_count, total_amount, created_at, status
     FROM {$sticker_orders_table} 
     WHERE user_id = %d AND status IN ('pending', 'processing') 
     ORDER BY created_at DESC 
     LIMIT 5", 
    $user_id
));
$pending_orders_count = count($pending_orders);

// Prepare page content
ob_start();
?>

<div class="vqr-dashboard-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">Dashboard</h1>
        <p class="vqr-page-description">Welcome back, <?php echo esc_html($current_user->display_name); ?>! Here's your QR code overview.</p>
        
        <?php if ($pending_orders_count > 0): ?>
        <!-- Pending Orders Notification -->
        <div class="vqr-pending-orders-alert">
            <div class="vqr-alert-content">
                <div class="vqr-alert-icon">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <div class="vqr-alert-text">
                    <strong>Sticker Orders Pending</strong>
                    <span>You have <?php echo $pending_orders_count; ?> pending sticker order<?php echo $pending_orders_count > 1 ? 's' : ''; ?> awaiting processing.</span>
                </div>
                <div class="vqr-alert-actions">
                    <a href="<?php echo home_url('/app/basket'); ?>" class="vqr-alert-link">View Orders</a>
                </div>
            </div>
            
            <?php if ($pending_orders_count <= 3): ?>
            <!-- Show order details for small numbers -->
            <div class="vqr-alert-details">
                <?php foreach ($pending_orders as $order): ?>
                <div class="vqr-order-summary-item">
                    <span class="vqr-order-number">#<?php echo esc_html($order->order_number); ?></span>
                    <span class="vqr-order-info"><?php echo $order->qr_count; ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?> • <?php echo date('M j', strtotime($order->created_at)); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Stats Grid -->
    <div class="vqr-grid vqr-grid-cols-4 vqr-mb-lg">
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value"><?php echo number_format($total_qr_codes); ?></span>
                    <div class="vqr-stat-label">Total QR Codes</div>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value"><?php echo number_format($total_scans); ?></span>
                    <div class="vqr-stat-label">Total Scans</div>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value"><?php echo $current_usage; ?>/<?php echo $monthly_quota; ?></span>
                    <div class="vqr-stat-label">Monthly Usage</div>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value vqr-text-success"><?php echo esc_html($plan_details['name']); ?></span>
                    <div class="vqr-stat-label">Current Plan</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="vqr-grid vqr-grid-cols-2 vqr-mb-lg">
        <div class="vqr-card">
            <div class="vqr-card-header">
                <h3 class="vqr-card-title">Quick Actions</h3>
            </div>
            <div class="vqr-card-content">
                <div class="vqr-quick-actions">
                    <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-primary" style="width: 100%; margin-bottom: var(--space-md);">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Generate New QR Codes
                    </a>
                    
                    <a href="<?php echo home_url('/app/analytics'); ?>" class="vqr-btn vqr-btn-secondary" style="width: 100%;">
                        <svg class="vqr-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        View Analytics
                    </a>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-header">
                <h3 class="vqr-card-title">Usage Overview</h3>
            </div>
            <div class="vqr-card-content">
                <div class="vqr-usage-bar-container">
                    <div class="vqr-usage-bar">
                        <div class="vqr-usage-progress" style="width: <?php echo ($current_usage / $monthly_quota) * 100; ?>%;"></div>
                    </div>
                    <div class="vqr-usage-text">
                        <span><?php echo $current_usage; ?> of <?php echo $monthly_quota; ?> QR codes used this month</span>
                        <?php if ($current_usage / $monthly_quota > 0.8): ?>
                            <span class="vqr-text-warning">⚠️ Approaching limit</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($user_plan === 'free'): ?>
                <div style="margin-top: var(--space-md);">
                    <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                        Upgrade Plan
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent QR Codes -->
    <div class="vqr-card">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Recent QR Codes</h3>
        </div>
        <div class="vqr-card-content">
            <?php if ($recent_codes): ?>
                <div class="vqr-table-container">
                    <table class="vqr-table">
                        <thead>
                            <tr>
                                <th>QR Code</th>
                                <th>Batch Code</th>
                                <th>Category</th>
                                <th>Scans</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_codes as $code): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo esc_url($code->qr_code); ?>" 
                                             alt="QR Code" 
                                             style="width: 40px; height: 40px;">
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($code->batch_code); ?></code>
                                    </td>
                                    <td><?php echo esc_html($code->category); ?></td>
                                    <td><strong><?php echo esc_html($code->scan_count); ?></strong></td>
                                    <td>
                                        <div class="vqr-status-group">
                                            <?php if ($code->scan_count > 0): ?>
                                                <span class="vqr-badge vqr-badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="vqr-badge vqr-badge-warning">Unused</span>
                                            <?php endif; ?>
                                            <?php echo vqr_render_print_status_badge($code->id); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: var(--space-md); text-align: center;">
                    <a href="<?php echo home_url('/app/codes'); ?>" class="vqr-btn vqr-btn-secondary">
                        View All QR Codes
                    </a>
                </div>
            <?php else: ?>
                <div class="vqr-empty-state">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-md);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                    </svg>
                    <h4>No QR codes yet</h4>
                    <p class="vqr-text-muted">Generate your first batch of QR codes to get started.</p>
                    <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-primary" style="margin-top: var(--space-md);">
                        Generate QR Codes
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Dashboard Page Styles */
.vqr-dashboard-page {
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

/* Pending Orders Alert */
.vqr-pending-orders-alert {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 1px solid #f59e0b;
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    margin-top: var(--space-lg);
    box-shadow: 0 4px 6px rgba(245, 158, 11, 0.1);
}

.vqr-alert-content {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
}

.vqr-alert-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: rgba(245, 158, 11, 0.2);
    border-radius: var(--radius-md);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #92400e;
}

.vqr-alert-text {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.vqr-alert-text strong {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: #92400e;
}

.vqr-alert-text span {
    font-size: var(--font-size-sm);
    color: #78350f;
    line-height: 1.4;
}

.vqr-alert-actions {
    flex-shrink: 0;
}

.vqr-alert-link {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    background: #f59e0b;
    color: white;
    text-decoration: none;
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    font-weight: 500;
    transition: all 0.2s ease;
}

.vqr-alert-link:hover {
    background: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
}

.vqr-alert-details {
    margin-top: var(--space-md);
    padding-top: var(--space-md);
    border-top: 1px solid rgba(245, 158, 11, 0.3);
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-order-summary-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: rgba(255, 255, 255, 0.7);
    border-radius: var(--radius-sm);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.vqr-order-number {
    font-family: monospace;
    font-weight: 600;
    color: #92400e;
    font-size: var(--font-size-sm);
}

.vqr-order-info {
    font-size: var(--font-size-xs);
    color: #78350f;
    opacity: 0.8;
}

.vqr-quick-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-usage-bar-container {
    margin-bottom: var(--space-md);
}

.vqr-usage-bar {
    width: 100%;
    height: 8px;
    background: var(--border-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: var(--space-sm);
}

.vqr-usage-progress {
    height: 100%;
    background: var(--primary);
    transition: width 0.3s ease;
}

.vqr-usage-text {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vqr-empty-state {
    text-align: center;
    padding: var(--space-xl);
}

.vqr-empty-state h4 {
    margin: 0 0 var(--space-sm) 0;
    color: var(--text-primary);
}

@media (max-width: 768px) {
    .vqr-dashboard-page {
        padding: var(--space-md);
    }
    
    .vqr-page-title {
        font-size: 24px;
    }
    
    .vqr-grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-sm);
    }
    
    .vqr-quick-actions .vqr-btn {
        width: 100%;
    }
    
    .vqr-pending-orders-alert {
        padding: var(--space-md);
        margin-top: var(--space-md);
    }
    
    .vqr-alert-content {
        flex-direction: column;
        gap: var(--space-sm);
    }
    
    .vqr-alert-icon {
        align-self: flex-start;
        width: 32px;
        height: 32px;
    }
    
    .vqr-alert-actions {
        align-self: stretch;
    }
    
    .vqr-alert-link {
        width: 100%;
        justify-content: center;
        padding: 12px 16px;
    }
    
    .vqr-order-summary-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-xs);
    }
}
</style>

<?php
$page_content = ob_get_clean();
$page_title = 'Dashboard';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>