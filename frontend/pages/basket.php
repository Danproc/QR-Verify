<?php
/**
 * Pending Orders Basket Page
 */

defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/app/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = get_current_user_id();

// Get user's sticker orders by status
global $wpdb;
$orders_table = $wpdb->prefix . 'vqr_sticker_orders';
$order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
$qr_table = $wpdb->prefix . 'vqr_codes';

// Get current tab (default to processing since most orders will be completed)
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'processing';
$valid_tabs = ['pending', 'processing', 'shipped'];
if (!in_array($current_tab, $valid_tabs)) {
    $current_tab = 'processing';
}

// Get orders for all tabs
$all_orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$orders_table} 
     WHERE user_id = %d 
     ORDER BY created_at DESC",
    $user_id
));

// Group orders by status
$orders_by_status = [
    'pending' => [],
    'processing' => [],
    'shipped' => []
];

foreach ($all_orders as $order) {
    if (isset($orders_by_status[$order->status])) {
        $orders_by_status[$order->status][] = $order;
    }
}

// Get orders for current tab
$current_orders = $orders_by_status[$current_tab];

// Get counts for each tab
$tab_counts = [
    'pending' => count($orders_by_status['pending']),
    'processing' => count($orders_by_status['processing']), 
    'shipped' => count($orders_by_status['shipped'])
];

$total_orders = count($current_orders);

ob_start();
?>

<div class="vqr-basket-page">
    <div class="vqr-page-header">
        <div class="vqr-breadcrumb">
            <a href="<?php echo home_url('/app/dashboard'); ?>" class="vqr-breadcrumb-link">Dashboard</a>
            <span class="vqr-breadcrumb-separator">→</span>
            <span class="vqr-breadcrumb-current">Orders</span>
        </div>
        
        <h1 class="vqr-page-title">
            <svg class="vqr-title-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
            </svg>
            Sticker Orders
        </h1>
        <p class="vqr-page-description">Manage your sticker orders and track their progress</p>
        
        <!-- Order Status Tabs -->
        <div class="vqr-order-tabs">
            <a href="<?php echo home_url('/app/basket?tab=pending'); ?>" 
               class="vqr-tab <?php echo $current_tab === 'pending' ? 'vqr-tab-active' : ''; ?>"
               data-tab-short="Pending">
                <span class="vqr-tab-label">Pending Orders</span>
                <?php if ($tab_counts['pending'] > 0): ?>
                    <span class="vqr-tab-count"><?php echo $tab_counts['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo home_url('/app/basket?tab=processing'); ?>" 
               class="vqr-tab <?php echo $current_tab === 'processing' ? 'vqr-tab-active' : ''; ?>"
               data-tab-short="Processing">
                <span class="vqr-tab-label">Processing Orders</span>
                <?php if ($tab_counts['processing'] > 0): ?>
                    <span class="vqr-tab-count"><?php echo $tab_counts['processing']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo home_url('/app/basket?tab=shipped'); ?>" 
               class="vqr-tab <?php echo $current_tab === 'shipped' ? 'vqr-tab-active' : ''; ?>"
               data-tab-short="Shipped">
                <span class="vqr-tab-label">Shipped Orders</span>
                <?php if ($tab_counts['shipped'] > 0): ?>
                    <span class="vqr-tab-count"><?php echo $tab_counts['shipped']; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="vqr-basket-container">
        <?php if (empty($current_orders)): ?>
            <!-- Empty State -->
            <div class="vqr-empty-state">
                <div class="vqr-empty-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <h3>No <?php echo ucfirst($current_tab); ?> Orders</h3>
                <p>You don't have any <?php echo $current_tab; ?> sticker orders at the moment.</p>
                <?php if ($current_tab === 'pending'): ?>
                    <a href="<?php echo home_url('/app/codes'); ?>" class="vqr-btn vqr-btn-primary">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Order Stickers
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Orders Summary -->
            <div class="vqr-orders-summary">
                <div class="vqr-summary-card">
                    <div class="vqr-summary-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <div class="vqr-summary-content">
                        <div class="vqr-summary-number"><?php echo $total_orders; ?></div>
                        <div class="vqr-summary-label"><?php echo ucfirst($current_tab); ?> Order<?php echo $total_orders > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
                
                <?php
                $total_qr_count = 0;
                $total_amount = 0;
                foreach ($current_orders as $order) {
                    $total_qr_count += $order->qr_count;
                    $total_amount += $order->total_amount;
                }
                ?>
                
                <div class="vqr-summary-card">
                    <div class="vqr-summary-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div class="vqr-summary-content">
                        <div class="vqr-summary-number"><?php echo $total_qr_count; ?></div>
                        <div class="vqr-summary-label">Total Stickers</div>
                    </div>
                </div>
                
                <div class="vqr-summary-card">
                    <div class="vqr-summary-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                        </svg>
                    </div>
                    <div class="vqr-summary-content">
                        <div class="vqr-summary-number">£<?php echo number_format($total_amount, 2); ?></div>
                        <div class="vqr-summary-label">Total Value</div>
                    </div>
                </div>
            </div>

            <!-- Orders List -->
            <div class="vqr-orders-list">
                <?php foreach ($current_orders as $order): ?>
                    <?php
                    // Get order items for this order
                    $order_items = $wpdb->get_results($wpdb->prepare(
                        "SELECT oi.*, qr.qr_code, qr.batch_code as qr_batch_code, qr.url, qr.scan_count
                         FROM {$order_items_table} oi 
                         LEFT JOIN {$qr_table} qr ON oi.qr_code_id = qr.id 
                         WHERE oi.order_id = %d 
                         ORDER BY oi.created_at ASC",
                        $order->id
                    ));
                    
                    $status_colors = [
                        'pending' => 'orange',
                        'processing' => 'blue', 
                        'shipped' => 'green',
                        'delivered' => 'green',
                        'cancelled' => 'red'
                    ];
                    
                    $status_color = $status_colors[$order->status] ?? 'gray';
                    ?>
                    
                    <div class="vqr-order-card" data-order-id="<?php echo $order->id; ?>">
                        <div class="vqr-order-header">
                            <div class="vqr-order-info">
                                <div class="vqr-order-number">
                                    <strong>Order #<?php echo esc_html($order->order_number); ?></strong>
                                    <span class="vqr-order-status vqr-status-<?php echo $status_color; ?>">
                                        <?php echo ucfirst($order->status); ?>
                                    </span>
                                </div>
                                <div class="vqr-order-meta">
                                    <span class="vqr-order-date">
                                        <svg class="vqr-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <?php echo date('M j, Y', strtotime($order->created_at)); ?>
                                    </span>
                                    <span class="vqr-order-count">
                                        <svg class="vqr-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                        </svg>
                                        <?php echo $order->qr_count; ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?>
                                    </span>
                                    <span class="vqr-order-amount">
                                        <svg class="vqr-meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                                        </svg>
                                        £<?php echo number_format($order->total_amount, 2); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="vqr-order-actions">
                                <button type="button" class="vqr-btn vqr-btn-secondary vqr-btn-sm vqr-toggle-details" data-order-id="<?php echo $order->id; ?>">
                                    <svg class="vqr-btn-icon vqr-expand-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    Details
                                </button>
                                
                                <?php if (in_array($order->status, ['pending', 'processing']) && in_array($current_tab, ['pending', 'processing'])): ?>
                                    <button type="button" class="vqr-btn vqr-btn-danger vqr-btn-sm vqr-cancel-order" data-order-id="<?php echo $order->id; ?>" data-order-number="<?php echo esc_attr($order->order_number); ?>">
                                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Order Details (Initially Hidden) -->
                        <div class="vqr-order-details" id="order-details-<?php echo $order->id; ?>" style="display: none;">
                            <div class="vqr-order-details-grid">
                                <!-- Shipping Information -->
                                <div class="vqr-detail-section">
                                    <h4>Shipping Information</h4>
                                    <div class="vqr-shipping-info">
                                        <div class="vqr-shipping-name"><?php echo esc_html($order->shipping_name); ?></div>
                                        <div class="vqr-shipping-address">
                                            <?php echo esc_html($order->shipping_address); ?><br>
                                            <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                                            <?php echo esc_html($order->shipping_country); ?>
                                        </div>
                                        <div class="vqr-shipping-email"><?php echo esc_html($order->shipping_email); ?></div>
                                    </div>
                                    
                                    <?php if (!empty($order->tracking_number)): ?>
                                        <div class="vqr-tracking-info">
                                            <strong>Tracking Number:</strong>
                                            <span class="vqr-tracking-number"><?php echo esc_html($order->tracking_number); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order->notes)): ?>
                                        <div class="vqr-order-notes">
                                            <strong>Notes:</strong>
                                            <p><?php echo esc_html($order->notes); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Order Items -->
                                <div class="vqr-detail-section">
                                    <h4>Sticker Details</h4>
                                    <div class="vqr-order-items">
                                        <?php foreach (array_slice($order_items, 0, 10) as $item): ?>
                                            <div class="vqr-order-item">
                                                <?php if (!empty($item->qr_code)): ?>
                                                    <img src="<?php echo esc_url($item->qr_code); ?>" alt="QR Code" class="vqr-item-qr">
                                                <?php else: ?>
                                                    <div class="vqr-item-qr vqr-qr-missing">
                                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="vqr-item-details">
                                                    <div class="vqr-item-batch">#<?php echo esc_html($item->batch_code); ?></div>
                                                    <div class="vqr-item-type"><?php echo ucfirst($item->sticker_type); ?> sticker</div>
                                                    <div class="vqr-item-price">£<?php echo number_format($item->unit_price, 2); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if (count($order_items) > 10): ?>
                                            <div class="vqr-order-item vqr-more-items">
                                                <div class="vqr-more-text">
                                                    + <?php echo count($order_items) - 10; ?> more stickers
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Bulk Actions -->
            <div class="vqr-basket-actions">
                <div class="vqr-action-buttons">
                    <a href="<?php echo home_url('/app/codes'); ?>" class="vqr-btn vqr-btn-primary">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Order More Stickers
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.vqr-basket-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
}

.vqr-page-header {
    margin-bottom: 32px;
}

.vqr-breadcrumb {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
    font-size: 14px;
}

.vqr-breadcrumb-link {
    color: #059669;
    text-decoration: none;
}

.vqr-breadcrumb-link:hover {
    text-decoration: underline;
}

.vqr-breadcrumb-separator {
    color: #6b7280;
}

.vqr-breadcrumb-current {
    color: #374151;
    font-weight: 500;
}

.vqr-page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 8px 0;
}

.vqr-title-icon {
    width: 32px;
    height: 32px;
    color: #059669;
}

.vqr-page-description {
    color: #6b7280;
    margin: 0 0 24px 0;
    font-size: 16px;
}

/* Order Tabs */
.vqr-order-tabs {
    display: flex;
    gap: 4px;
    background: #f3f4f6;
    border-radius: 12px;
    padding: 4px;
    margin-bottom: 32px;
    overflow-x: auto;
}

.vqr-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: transparent;
    color: #6b7280;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    white-space: nowrap;
    min-width: 0;
    flex: 1;
    justify-content: center;
}

.vqr-tab:hover {
    background: rgba(255, 255, 255, 0.5);
    color: #374151;
}

.vqr-tab-active {
    background: white;
    color: #059669;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.vqr-tab-active:hover {
    background: white;
    color: #059669;
}

.vqr-tab-label {
    flex-shrink: 0;
}

.vqr-tab-count {
    background: #059669;
    color: white;
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 20px;
    text-align: center;
    line-height: 1.4;
}

.vqr-tab-active .vqr-tab-count {
    background: #047857;
}

.vqr-tab:not(.vqr-tab-active) .vqr-tab-count {
    background: #9ca3af;
}

/* Empty State */
.vqr-empty-state {
    text-align: center;
    padding: 64px 32px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
}

.vqr-empty-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 24px;
    color: #d1d5db;
}

.vqr-empty-icon svg {
    width: 100%;
    height: 100%;
}

.vqr-empty-state h3 {
    font-size: 24px;
    font-weight: 600;
    color: #374151;
    margin: 0 0 8px 0;
}

.vqr-empty-state p {
    color: #6b7280;
    margin: 0 0 32px 0;
    font-size: 16px;
}

/* Orders Summary */
.vqr-orders-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.vqr-summary-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 16px;
}

.vqr-summary-icon {
    width: 48px;
    height: 48px;
    color: #059669;
    background: #f0fdf4;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.vqr-summary-icon svg {
    width: 24px;
    height: 24px;
}

.vqr-summary-number {
    font-size: 28px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1;
}

.vqr-summary-label {
    font-size: 14px;
    color: #6b7280;
    margin-top: 4px;
}

/* Orders List */
.vqr-orders-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 32px;
}

.vqr-order-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
}

.vqr-order-header {
    padding: 24px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.vqr-order-info {
    flex: 1;
}

.vqr-order-number {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.vqr-order-number strong {
    font-size: 18px;
    color: #1f2937;
}

.vqr-order-status {
    font-size: 12px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.vqr-status-orange {
    background: #fef3c7;
    color: #d97706;
}

.vqr-status-blue {
    background: #dbeafe;
    color: #2563eb;
}

.vqr-status-green {
    background: #d1fae5;
    color: #059669;
}

.vqr-status-red {
    background: #fee2e2;
    color: #dc2626;
}

.vqr-order-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    font-size: 14px;
    color: #6b7280;
}

.vqr-order-meta span {
    display: flex;
    align-items: center;
    gap: 4px;
}

.vqr-meta-icon {
    width: 16px;
    height: 16px;
}

.vqr-order-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

/* Order Details */
.vqr-order-details {
    border-top: 1px solid #e5e7eb;
    padding: 24px;
    background: #f9fafb;
}

.vqr-order-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}

.vqr-detail-section h4 {
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 16px 0;
}

.vqr-shipping-info {
    font-size: 14px;
    line-height: 1.6;
}

.vqr-shipping-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 8px;
}

.vqr-shipping-address {
    color: #6b7280;
    margin-bottom: 8px;
}

.vqr-shipping-email {
    color: #059669;
}

.vqr-tracking-info, .vqr-order-notes {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.vqr-tracking-number {
    font-family: monospace;
    background: #f3f4f6;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 13px;
}

.vqr-order-notes p {
    margin: 8px 0 0 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}

/* Order Items */
.vqr-order-items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.vqr-order-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.vqr-item-qr {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    flex-shrink: 0;
    object-fit: cover;
}

.vqr-qr-missing {
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #9ca3af;
}

.vqr-qr-missing svg {
    width: 20px;
    height: 20px;
}

.vqr-item-details {
    flex: 1;
    min-width: 0;
}

.vqr-item-batch {
    font-family: monospace;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.vqr-item-type {
    font-size: 12px;
    color: #6b7280;
    margin-top: 2px;
}

.vqr-item-price {
    font-size: 14px;
    font-weight: 600;
    color: #059669;
    margin-top: 4px;
}

.vqr-more-items {
    justify-content: center;
    background: #f9fafb;
    border: 1px dashed #d1d5db;
}

.vqr-more-text {
    font-size: 14px;
    color: #6b7280;
    font-style: italic;
}

/* Basket Actions */
.vqr-basket-actions {
    display: flex;
    justify-content: center;
    padding: 32px 0;
}

/* Toggle Details Animation */
.vqr-expand-icon {
    transition: transform 0.2s ease;
}

.vqr-expand-icon.expanded {
    transform: rotate(180deg);
}

/* Responsive Design */
@media (max-width: 768px) {
    .vqr-basket-page {
        padding: 16px;
    }
    
    .vqr-order-tabs {
        margin-bottom: 24px;
    }
    
    .vqr-tab {
        padding: 10px 16px;
        font-size: 13px;
    }
    
    .vqr-tab-label {
        display: none;
    }
    
    .vqr-tab:before {
        content: attr(data-tab-short);
        display: block;
    }
    
    .vqr-orders-summary {
        grid-template-columns: 1fr;
    }
    
    .vqr-order-header {
        flex-direction: column;
        align-items: stretch;
        gap: 16px;
    }
    
    .vqr-order-actions {
        justify-content: flex-end;
    }
    
    .vqr-order-details-grid {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .vqr-order-meta {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle order details
    document.querySelectorAll('.vqr-toggle-details').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const details = document.getElementById('order-details-' + orderId);
            const icon = this.querySelector('.vqr-expand-icon');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                icon.classList.add('expanded');
                this.innerHTML = this.innerHTML.replace('Details', 'Hide');
            } else {
                details.style.display = 'none';
                icon.classList.remove('expanded');
                this.innerHTML = this.innerHTML.replace('Hide', 'Details');
            }
        });
    });
    
    // Cancel order functionality
    document.querySelectorAll('.vqr-cancel-order').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            const orderNumber = this.dataset.orderNumber;
            
            if (confirm(`Are you sure you want to cancel order #${orderNumber}? This action cannot be undone.`)) {
                // Show loading state
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="vqr-loading"></span> Cancelling...';
                this.disabled = true;
                
                // Submit cancel request
                const formData = new FormData();
                formData.append('action', 'vqr_cancel_sticker_order');
                formData.append('nonce', vqr_ajax.nonce);
                formData.append('order_id', orderId);
                
                fetch(vqr_ajax.url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the order card or refresh page
                        location.reload();
                    } else {
                        alert('Error: ' + (data.data || 'Failed to cancel order'));
                        this.innerHTML = originalText;
                        this.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred');
                    this.innerHTML = originalText;
                    this.disabled = false;
                });
            }
        });
    });
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = ucfirst($current_tab) . ' Orders';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>