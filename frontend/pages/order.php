<?php
/**
 * Sticker Order Page
 */

defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/app/login'));
    exit;
}

$current_user = wp_get_current_user();

// Get selected QR codes from session or URL parameter
$selected_qr_ids = [];
if (isset($_GET['qr_ids'])) {
    $selected_qr_ids = array_map('intval', explode(',', sanitize_text_field($_GET['qr_ids'])));
} elseif (isset($_SESSION['selected_qr_ids'])) {
    $selected_qr_ids = $_SESSION['selected_qr_ids'];
}

if (empty($selected_qr_ids)) {
    wp_redirect(home_url('/app/codes'));
    exit;
}

// Get QR codes data
global $wpdb;
$qr_table = $wpdb->prefix . 'vqr_codes';
$user_id = get_current_user_id();

$placeholders = implode(',', array_fill(0, count($selected_qr_ids), '%d'));
$query = $wpdb->prepare(
    "SELECT * FROM {$qr_table} WHERE id IN ({$placeholders}) AND user_id = %d ORDER BY batch_code ASC",
    array_merge($selected_qr_ids, [$user_id])
);

$qr_codes = $wpdb->get_results($query);

if (empty($qr_codes)) {
    wp_redirect(home_url('/app/codes'));
    exit;
}

// Sticker types and pricing (in GBP base) with stock checking
$all_sticker_types = [
    'standard' => [
        'name' => 'Standard Gloss Stickers',
        'price_gbp' => 0.20,
        'description' => 'High-quality glossy vinyl stickers, weather resistant',
        'features' => ['Weatherproof', 'UV resistant', 'Glossy finish', 'Durable adhesive']
    ],
    'iridescent' => [
        'name' => 'Iridescent Holographic Stickers',
        'price_gbp' => 0.50,
        'description' => 'Premium holographic stickers with rainbow shimmer effect',
        'features' => ['Holographic finish', 'Color-changing shimmer', 'Premium quality', 'Eye-catching design']
    ]
];

// Filter available sticker types based on stock
$sticker_types = [];
$out_of_stock_types = [];

foreach ($all_sticker_types as $type => $data) {
    if (vqr_is_sticker_in_stock($type)) {
        $sticker_types[$type] = $data;
    } else {
        $out_of_stock_types[$type] = $data;
    }
}

// Check if any stickers are available
$has_available_stickers = !empty($sticker_types);

// Currency exchange rates (base: GBP)
$currency_rates = [
    'GBP' => ['rate' => 1.00, 'symbol' => '£', 'name' => 'British Pound'],
    'EUR' => ['rate' => 1.19, 'symbol' => '€', 'name' => 'Euro'],
    'USD' => ['rate' => 1.27, 'symbol' => '$', 'name' => 'US Dollar']
];

// Get selected currency (default to GBP)
$selected_currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'GBP';
if (!isset($currency_rates[$selected_currency])) {
    $selected_currency = 'GBP';
}

$current_rate = $currency_rates[$selected_currency]['rate'];
$currency_symbol = $currency_rates[$selected_currency]['symbol'];

ob_start();
?>

<div class="vqr-order-page">
    <div class="vqr-page-header">
        <div class="vqr-header-top">
            <div class="vqr-breadcrumb">
                <a href="<?php echo home_url('/app/codes'); ?>" class="vqr-breadcrumb-link">QR Codes</a>
                <span class="vqr-breadcrumb-separator">→</span>
                <span class="vqr-breadcrumb-current">Order Stickers</span>
            </div>
            
            <div class="vqr-currency-selector">
                <label for="currency-select">Currency:</label>
                <select id="currency-select" onchange="VQR.changeCurrency(this.value)">
                    <?php foreach ($currency_rates as $code => $data): ?>
                        <option value="<?php echo esc_attr($code); ?>" <?php selected($selected_currency, $code); ?>>
                            <?php echo esc_html($data['symbol'] . ' ' . $data['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <h1 class="vqr-page-title">Order QR Code Stickers</h1>
        <p class="vqr-page-description">Choose your sticker type and complete your order</p>
    </div>

    <div class="vqr-order-container <?php echo !$has_available_stickers ? 'vqr-no-stock' : ''; ?>">
        <form id="vqr-sticker-order-form" class="vqr-order-form">
            <!-- Sticker Type Selection -->
            <div class="vqr-order-section">
                <h2>Choose Sticker Type</h2>
                <p class="vqr-section-description">Select the type of stickers you'd like for your QR codes</p>
                
                <?php if (!$has_available_stickers): ?>
                    <div class="vqr-out-of-stock-notice">
                        <div class="vqr-alert vqr-alert-warning">
                            <svg class="vqr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            <div class="vqr-alert-content">
                                <h3>All Stickers Currently Out of Stock</h3>
                                <p>We're sorry, but all sticker types are currently out of stock. Please check back later or contact support for more information.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="vqr-sticker-types">
                    <?php 
                    $first_available = true;
                    foreach ($sticker_types as $type_key => $type_data): 
                        $converted_price = $type_data['price_gbp'] * $current_rate;
                    ?>
                        <div class="vqr-sticker-option" data-type="<?php echo esc_attr($type_key); ?>" data-price-gbp="<?php echo esc_attr($type_data['price_gbp']); ?>">
                            <input type="radio" id="sticker_<?php echo esc_attr($type_key); ?>" name="sticker_type" value="<?php echo esc_attr($type_key); ?>" <?php echo $first_available ? 'checked' : ''; ?>>
                            <label for="sticker_<?php echo esc_attr($type_key); ?>" class="vqr-sticker-card">
                                <div class="vqr-sticker-header">
                                    <h3><?php echo esc_html($type_data['name']); ?></h3>
                                    <div class="vqr-sticker-price">
                                        <span class="vqr-price-amount"><?php echo $currency_symbol . number_format($converted_price, 2); ?></span>
                                        <span class="vqr-price-unit">each</span>
                                    </div>
                                </div>
                                <p class="vqr-sticker-description"><?php echo esc_html($type_data['description']); ?></p>
                                <ul class="vqr-sticker-features">
                                    <?php foreach ($type_data['features'] as $feature): ?>
                                        <li>
                                            <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <?php echo esc_html($feature); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </label>
                        </div>
                    <?php 
                        $first_available = false;
                    endforeach; 
                    ?>
                    
                    <?php foreach ($out_of_stock_types as $type_key => $type_data): ?>
                        <?php 
                        $converted_price = $type_data['price_gbp'] * $current_rate;
                        ?>
                        <div class="vqr-sticker-option vqr-out-of-stock" data-type="<?php echo esc_attr($type_key); ?>" data-price-gbp="<?php echo esc_attr($type_data['price_gbp']); ?>">
                            <input type="radio" id="sticker_<?php echo esc_attr($type_key); ?>" name="sticker_type" value="<?php echo esc_attr($type_key); ?>" disabled>
                            <label for="sticker_<?php echo esc_attr($type_key); ?>" class="vqr-sticker-card vqr-disabled">
                                <div class="vqr-sticker-header">
                                    <h3><?php echo esc_html($type_data['name']); ?></h3>
                                    <div class="vqr-sticker-price">
                                        <span class="vqr-price-amount"><?php echo $currency_symbol . number_format($converted_price, 2); ?></span>
                                        <span class="vqr-price-unit">each</span>
                                    </div>
                                    <div class="vqr-stock-badge vqr-out-of-stock">
                                        <svg class="vqr-stock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Out of Stock
                                    </div>
                                </div>
                                <p class="vqr-sticker-description"><?php echo esc_html($type_data['description']); ?></p>
                                <ul class="vqr-sticker-features">
                                    <?php foreach ($type_data['features'] as $feature): ?>
                                        <li>
                                            <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            <?php echo esc_html($feature); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                
                                <div class="vqr-out-of-stock-overlay">
                                    <div class="vqr-out-of-stock-message">
                                        <svg class="vqr-stock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        Out of Stock
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="vqr-order-section">
                <h2>Order Summary</h2>
                <p class="vqr-section-description">Review your selected QR codes and pricing</p>
                
                <div class="vqr-order-summary-content">
                    <div class="vqr-summary-grid">
                        <div class="vqr-selected-qrs">
                            <h4><?php echo count($qr_codes); ?> QR Code<?php echo count($qr_codes) > 1 ? 's' : ''; ?> Selected</h4>
                            <div class="vqr-qr-list">
                                <?php foreach (array_slice($qr_codes, 0, 5) as $qr_code): ?>
                                    <div class="vqr-qr-item">
                                        <img src="<?php echo esc_url($qr_code->qr_code); ?>" alt="QR Code" class="vqr-qr-thumb">
                                        <span class="vqr-batch-code"><?php echo esc_html($qr_code->batch_code); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($qr_codes) > 5): ?>
                                    <div class="vqr-qr-item vqr-qr-more">
                                        <span class="vqr-more-text">+ <?php echo count($qr_codes) - 5; ?> more QR codes</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="vqr-pricing-summary">
                            <div class="vqr-price-row">
                                <span>Quantity:</span>
                                <span id="vqr-quantity"><?php echo count($qr_codes); ?></span>
                            </div>
                            <div class="vqr-price-row">
                                <span>Unit Price:</span>
                                <span id="vqr-unit-price"><?php echo $currency_symbol; ?>0.00</span>
                            </div>
                            <div class="vqr-price-row vqr-total-row">
                                <span><strong>Total:</strong></span>
                                <span id="vqr-total-price"><strong><?php echo $currency_symbol; ?>0.00</strong></span>
                            </div>
                            
                            <div class="vqr-checkout-info">
                                <p class="vqr-secure-notice">
                                    <svg class="vqr-lock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    Secure checkout with Stripe
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="vqr-order-section">
                <h2>Shipping Information</h2>
                <p class="vqr-section-description">Where should we send your stickers?</p>
                
                <div class="vqr-shipping-form">
                    <div class="vqr-form-row">
                        <div class="vqr-form-group">
                            <label for="shipping_name">Full Name *</label>
                            <input type="text" id="shipping_name" name="shipping_name" value="<?php echo esc_attr($current_user->display_name); ?>" required>
                        </div>
                        <div class="vqr-form-group">
                            <label for="shipping_email">Email Address *</label>
                            <input type="email" id="shipping_email" name="shipping_email" value="<?php echo esc_attr($current_user->user_email); ?>" required>
                        </div>
                    </div>
                    
                    <div class="vqr-form-group">
                        <label for="shipping_address">Street Address *</label>
                        <input type="text" id="shipping_address" name="shipping_address" placeholder="123 Main Street" required>
                    </div>
                    
                    <div class="vqr-form-row">
                        <div class="vqr-form-group">
                            <label for="shipping_city">City *</label>
                            <input type="text" id="shipping_city" name="shipping_city" required>
                        </div>
                        <div class="vqr-form-group">
                            <label for="shipping_state">State/County *</label>
                            <input type="text" id="shipping_state" name="shipping_state" required>
                        </div>
                        <div class="vqr-form-group">
                            <label for="shipping_zip">Postal Code *</label>
                            <input type="text" id="shipping_zip" name="shipping_zip" required>
                        </div>
                    </div>
                    
                    <div class="vqr-form-group">
                        <label for="shipping_country">Country *</label>
                        <select id="shipping_country" name="shipping_country" required>
                            <option value="">Select Country</option>
                            <option value="GB" selected>United Kingdom</option>
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="AU">Australia</option>
                            <option value="DE">Germany</option>
                            <option value="FR">France</option>
                            <option value="IT">Italy</option>
                            <option value="ES">Spain</option>
                            <option value="NL">Netherlands</option>
                        </select>
                    </div>
                    
                    <div class="vqr-form-group">
                        <label for="order_notes">Special Instructions (Optional)</label>
                        <textarea id="order_notes" name="order_notes" rows="3" placeholder="Any special delivery instructions..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Order Actions -->
            <div class="vqr-order-actions">
                <button type="button" class="vqr-btn vqr-btn-secondary" onclick="window.history.back()">
                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to QR Codes
                </button>
                
                <button type="submit" class="vqr-btn vqr-btn-primary vqr-btn-large" id="vqr-checkout-btn" <?php echo !$has_available_stickers ? 'disabled' : ''; ?>>
                    <?php if ($has_available_stickers): ?>
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Proceed to Checkout
                        <span class="vqr-checkout-total"><?php echo $currency_symbol; ?>0.00</span>
                    <?php else: ?>
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Out of Stock - Cannot Order
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- Hidden fields -->
            <input type="hidden" name="action" value="vqr_create_sticker_order">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vqr_frontend_nonce'); ?>">
            <input type="hidden" name="currency" value="<?php echo esc_attr($selected_currency); ?>">
            <input type="hidden" name="currency_rate" value="<?php echo esc_attr($current_rate); ?>">
            <?php foreach ($selected_qr_ids as $qr_id): ?>
                <input type="hidden" name="qr_ids[]" value="<?php echo esc_attr($qr_id); ?>">
            <?php endforeach; ?>
        </form>
    </div>
</div>

<style>
.vqr-order-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
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
    font-size: 32px;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 8px 0;
}

.vqr-page-description {
    color: #6b7280;
    margin: 0 0 32px 0;
    font-size: 16px;
}

.vqr-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.vqr-currency-selector {
    display: flex;
    align-items: center;
    gap: 8px;
}

.vqr-currency-selector label {
    font-size: 14px;
    font-weight: 500;
    color: #374151;
}

.vqr-currency-selector select {
    padding: 6px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    cursor: pointer;
    min-width: 140px;
}

.vqr-currency-selector select:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.1);
}

.vqr-order-container {
    max-width: 800px;
    margin: 0 auto;
}

.vqr-order-form {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

/* Order Summary Content */
.vqr-order-summary-content {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 24px;
}

.vqr-summary-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 32px;
    align-items: start;
}

.vqr-selected-qrs h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
    font-weight: 600;
    color: #1f2937;
}

.vqr-qr-list {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
}

.vqr-qr-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    min-width: 120px;
}

.vqr-qr-item:last-child {
    border-bottom: 1px solid #e5e7eb;
}

.vqr-qr-thumb {
    width: 24px;
    height: 24px;
    border-radius: 3px;
    flex-shrink: 0;
}

.vqr-batch-code {
    font-family: monospace;
    font-size: 11px;
    font-weight: 500;
    color: #374151;
}

.vqr-qr-more {
    justify-content: center;
    background: #f3f4f6;
    border: 1px dashed #d1d5db;
}

.vqr-more-text {
    font-size: 12px;
    color: #6b7280;
    font-style: italic;
}

.vqr-pricing-summary {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 20px;
}

.vqr-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    font-size: 14px;
}

.vqr-total-row {
    border-top: 1px solid #e5e7eb;
    padding-top: 12px;
    margin-top: 12px;
    font-size: 16px;
}

.vqr-checkout-info {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.vqr-secure-notice {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #6b7280;
    margin: 0;
}

.vqr-lock-icon {
    width: 16px;
    height: 16px;
}

.vqr-order-section {
    padding: 32px;
    border-bottom: 1px solid #e5e7eb;
}

.vqr-order-section:last-of-type {
    border-bottom: none;
}

.vqr-order-section h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #1f2937;
}

.vqr-section-description {
    margin: 0 0 24px 0;
    color: #6b7280;
    font-size: 16px;
}

/* Sticker Types */
.vqr-sticker-types {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.vqr-sticker-option {
    position: relative;
}

.vqr-sticker-option input[type="radio"] {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.vqr-sticker-card {
    display: block;
    background: #f9fafb;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.2s ease;
    height: 100%;
    box-sizing: border-box;
}

.vqr-sticker-option input[type="radio"]:checked + .vqr-sticker-card {
    background: #ecfdf5;
    border-color: #059669;
    box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.1);
}

.vqr-sticker-card:hover {
    border-color: #d1d5db;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.vqr-sticker-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.vqr-sticker-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}

.vqr-sticker-price {
    text-align: right;
}

.vqr-price-amount {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: #059669;
}

.vqr-price-unit {
    font-size: 12px;
    color: #6b7280;
}

.vqr-sticker-description {
    margin: 0 0 16px 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.5;
}

.vqr-sticker-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.vqr-sticker-features li {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    font-size: 13px;
    color: #374151;
}

.vqr-check-icon {
    width: 16px;
    height: 16px;
    color: #059669;
    flex-shrink: 0;
}

/* Shipping Form */
.vqr-shipping-form {
    max-width: 600px;
}

.vqr-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.vqr-form-row.vqr-form-triple {
    grid-template-columns: 1fr 1fr 120px;
}

.vqr-form-group {
    margin-bottom: 20px;
}

.vqr-form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.vqr-form-group input,
.vqr-form-group select,
.vqr-form-group textarea {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box;
    transition: border-color 0.2s ease;
}

.vqr-form-group input:focus,
.vqr-form-group select:focus,
.vqr-form-group textarea:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.vqr-form-group textarea {
    resize: vertical;
    min-height: 80px;
}

/* Order Actions */
.vqr-order-actions {
    padding: 32px;
    background: #f9fafb;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.vqr-btn-large {
    padding: 16px 32px;
    font-size: 16px;
    font-weight: 600;
}

.vqr-checkout-total {
    margin-left: 12px;
    padding: 4px 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
    font-weight: 700;
}

/* Responsive Design */
@media (max-width: 768px) {
    .vqr-order-page {
        padding: 16px;
    }
    
    .vqr-header-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .vqr-currency-selector {
        align-self: flex-end;
    }
    
    .vqr-order-container {
        max-width: 100%;
    }
    
    .vqr-summary-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .vqr-sticker-types {
        grid-template-columns: 1fr;
    }
    
    .vqr-form-row {
        grid-template-columns: 1fr;
    }
    
    .vqr-order-section {
        padding: 24px 20px;
    }
    
    .vqr-order-actions {
        flex-direction: column-reverse;
        gap: 12px;
        padding: 24px 20px;
    }
    
    .vqr-order-actions .vqr-btn {
        width: 100%;
        justify-content: center;
    }
    
    .vqr-qr-list {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    
    .vqr-qr-item {
        min-width: auto;
        flex: 1;
    }
}

/* Stock Status Styling */
.vqr-stock-icon {
    width: 12px;
    height: 12px;
}

.vqr-sticker-option.vqr-out-of-stock {
    position: relative;
    opacity: 0.85;
}

.vqr-sticker-card.vqr-disabled {
    background: #f9fafb;
    border-color: #e5e7eb;
    cursor: not-allowed;
    pointer-events: none;
    filter: blur(1px);
    transition: filter 0.3s ease;
}

.vqr-out-of-stock-overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(220, 38, 38, 0.95);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(4px);
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.vqr-out-of-stock-message {
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
}

.vqr-out-of-stock-message svg {
    width: 14px;
    height: 14px;
}


.vqr-out-of-stock-notice {
    margin-bottom: 24px;
}

.vqr-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid;
}

.vqr-alert-warning {
    background: #fffbeb;
    border-color: #fed7aa;
    color: #92400e;
}

.vqr-alert-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.vqr-alert-content h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #92400e;
}

.vqr-alert-content p {
    margin: 0;
    color: #78350f;
    font-size: 14px;
}

/* Disable form when no stickers available */
.vqr-no-stock .vqr-order-form {
    pointer-events: none;
    opacity: 0.6;
}

.vqr-no-stock .vqr-btn-primary {
    background: #9ca3af;
    cursor: not-allowed;
}

.vqr-no-stock .vqr-btn-primary:hover {
    background: #9ca3af;
}
</style>

<script>
// Global currency data
window.VQR = window.VQR || {};
VQR.currencyRates = <?php echo json_encode($currency_rates); ?>;
VQR.currentCurrency = '<?php echo esc_js($selected_currency); ?>';

document.addEventListener('DOMContentLoaded', function() {
    const stickerOptions = document.querySelectorAll('input[name="sticker_type"]');
    const quantityEl = document.getElementById('vqr-quantity');
    const unitPriceEl = document.getElementById('vqr-unit-price');
    const totalPriceEl = document.getElementById('vqr-total-price');
    const checkoutBtn = document.getElementById('vqr-checkout-btn');
    const checkoutTotal = checkoutBtn.querySelector('.vqr-checkout-total');
    
    const quantity = parseInt(quantityEl.textContent);
    
    function updatePricing() {
        const selectedOption = document.querySelector('input[name="sticker_type"]:checked');
        if (selectedOption) {
            // Get price from the parent sticker option div
            const stickerOption = selectedOption.closest('.vqr-sticker-option');
            const priceGbp = parseFloat(stickerOption.dataset.priceGbp);
            const currentRate = VQR.currencyRates[VQR.currentCurrency].rate;
            const symbol = VQR.currencyRates[VQR.currentCurrency].symbol;
            
            console.log('Debug pricing:', {
                priceGbp: priceGbp,
                currentRate: currentRate,
                symbol: symbol,
                quantity: quantity
            });
            
            if (isNaN(priceGbp) || isNaN(currentRate)) {
                console.error('Invalid pricing data:', { priceGbp, currentRate });
                return;
            }
            
            const price = priceGbp * currentRate;
            const total = quantity * price;
            
            unitPriceEl.textContent = `${symbol}${price.toFixed(2)}`;
            totalPriceEl.innerHTML = `<strong>${symbol}${total.toFixed(2)}</strong>`;
            checkoutTotal.textContent = `${symbol}${total.toFixed(2)}`;
            
            // Update all sticker card prices
            document.querySelectorAll('.vqr-sticker-option').forEach(option => {
                const optionPriceGbp = parseFloat(option.dataset.priceGbp);
                const optionPrice = optionPriceGbp * currentRate;
                const priceAmount = option.querySelector('.vqr-price-amount');
                if (priceAmount && !isNaN(optionPrice)) {
                    priceAmount.textContent = `${symbol}${optionPrice.toFixed(2)}`;
                }
            });
        }
    }
    
    // Update pricing when sticker type changes
    stickerOptions.forEach(option => {
        option.addEventListener('change', updatePricing);
    });
    
    // Initial pricing update
    updatePricing();
    
    // Form submission
    document.getElementById('vqr-sticker-order-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Show loading state
        const originalText = checkoutBtn.innerHTML;
        checkoutBtn.innerHTML = '<span class="vqr-loading"></span> Processing...';
        checkoutBtn.disabled = true;
        
        // Get form data
        const formData = new FormData(this);
        
        // Submit to backend
        fetch(vqr_ajax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // For now, show success message and redirect
                alert(`Order created successfully! Order #${data.data.order_number}`);
                window.location.href = '<?php echo home_url('/app/codes'); ?>';
            } else {
                alert('Error: ' + (data.data || 'Failed to create order'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Network error occurred');
        })
        .finally(() => {
            checkoutBtn.innerHTML = originalText;
            checkoutBtn.disabled = false;
        });
    });
    
    // Currency change function
    VQR.changeCurrency = function(newCurrency) {
        // Update URL with new currency
        const url = new URL(window.location);
        url.searchParams.set('currency', newCurrency);
        window.location.href = url.toString();
    };
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Order Stickers';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>