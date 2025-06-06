<?php
/**
 * Billing & Subscription Management page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's subscription data
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();

// Currency exchange rates (base: USD for billing)
$currency_rates = [
    'USD' => ['rate' => 1.00, 'symbol' => '$', 'name' => 'US Dollar'],
    'GBP' => ['rate' => 0.79, 'symbol' => '£', 'name' => 'British Pound'],
    'EUR' => ['rate' => 0.84, 'symbol' => '€', 'name' => 'Euro']
];

// Get selected currency (default to USD)
$selected_currency = isset($_GET['currency']) ? sanitize_text_field($_GET['currency']) : 'USD';
if (!isset($currency_rates[$selected_currency])) {
    $selected_currency = 'USD';
}

$current_rate = $currency_rates[$selected_currency]['rate'];
$currency_symbol = $currency_rates[$selected_currency]['symbol'];

// Get all plan details for comparison
$all_plans = array(
    'free' => vqr_get_plan_details('free'),
    'starter' => vqr_get_plan_details('starter'),
    'pro' => vqr_get_plan_details('pro'),
    'enterprise' => vqr_get_plan_details('enterprise')
);

// Mock billing history (placeholder for future payment integration)
$billing_history = array(
    array(
        'date' => '2024-01-01',
        'description' => 'Starter Plan - Monthly',
        'amount' => 29.00,
        'status' => 'paid',
        'invoice_url' => '#'
    ),
    array(
        'date' => '2023-12-01',
        'description' => 'Starter Plan - Monthly',
        'amount' => 29.00,
        'status' => 'paid',
        'invoice_url' => '#'
    ),
    array(
        'date' => '2023-11-01',
        'description' => 'Free Plan Upgrade to Starter',
        'amount' => 29.00,
        'status' => 'paid',
        'invoice_url' => '#'
    )
);

// Prepare page content
ob_start();
?>

<div class="vqr-billing">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <div class="vqr-header-top">
            <div class="vqr-breadcrumb">
                <span class="vqr-breadcrumb-current">Billing & Subscription</span>
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
        
        <h1 class="vqr-page-title">Billing & Subscription</h1>
        <p class="vqr-page-description">Manage your subscription, view usage, and upgrade your plan.</p>
    </div>
    
    <!-- Current Subscription Overview -->
    <div class="vqr-grid vqr-grid-cols-2 vqr-mb-lg">
        <div class="vqr-card">
            <div class="vqr-card-header">
                <h3 class="vqr-card-title">Current Subscription</h3>
            </div>
            <div class="vqr-card-content">
                <div class="vqr-current-plan">
                    <div class="vqr-plan-info">
                        <h4 class="vqr-plan-name"><?php echo esc_html($plan_details['name']); ?> Plan</h4>
                        <div class="vqr-plan-price">
                            <?php if ($plan_details['price'] == 0): ?>
                                <span class="vqr-price-amount">Free</span>
                            <?php else: ?>
                                <?php $converted_price = $plan_details['price'] * $current_rate; ?>
                                <span class="vqr-price-amount" data-price-usd="<?php echo esc_attr($plan_details['price']); ?>"><?php echo $currency_symbol . number_format($converted_price, 0); ?></span>
                                <span class="vqr-price-period">/month</span>
                            <?php endif; ?>
                        </div>
                        <div class="vqr-plan-quota">
                            <?php if ($monthly_quota === -1): ?>
                                <strong>Unlimited</strong> QR codes per month
                            <?php else: ?>
                                <strong><?php echo number_format($monthly_quota); ?></strong> QR codes per month
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($user_plan !== 'enterprise'): ?>
                    <div class="vqr-plan-actions">
                        <button class="vqr-btn vqr-btn-primary" onclick="vqrShowUpgradeModal()">
                            Upgrade Plan
                        </button>
                        <?php if ($user_plan !== 'free'): ?>
                        <button class="vqr-btn vqr-btn-secondary vqr-btn-sm" onclick="vqrShowDowngradeModal()">
                            Downgrade
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-header">
                <h3 class="vqr-card-title">Usage This Month</h3>
            </div>
            <div class="vqr-card-content">
                <div class="vqr-usage-display">
                    <div class="vqr-usage-numbers">
                        <span class="vqr-usage-current"><?php echo number_format($current_usage); ?></span>
                        <span class="vqr-usage-separator">/</span>
                        <span class="vqr-usage-total">
                            <?php echo $monthly_quota === -1 ? '∞' : number_format($monthly_quota); ?>
                        </span>
                    </div>
                    <div class="vqr-usage-label">QR codes generated</div>
                    
                    <?php if ($monthly_quota !== -1): ?>
                    <div class="vqr-usage-bar-container">
                        <div class="vqr-usage-bar">
                            <div class="vqr-usage-progress" style="width: <?php echo min(100, ($current_usage / $monthly_quota) * 100); ?>%;"></div>
                        </div>
                        <div class="vqr-usage-percentage">
                            <?php echo round(($current_usage / $monthly_quota) * 100, 1); ?>% used
                        </div>
                    </div>
                    
                    <?php if ($current_usage / $monthly_quota > 0.8): ?>
                    <div class="vqr-usage-warning">
                        <svg class="vqr-warning-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        You're approaching your monthly limit. Consider upgrading your plan.
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Plan Comparison -->
    <div class="vqr-card vqr-mb-lg">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Choose Your Plan</h3>
            <p class="vqr-card-description">Select the plan that best fits your needs. You can upgrade or downgrade at any time.</p>
        </div>
        <div class="vqr-card-content">
            <div class="vqr-plans-grid">
                <?php foreach ($all_plans as $plan_key => $plan): ?>
                <div class="vqr-plan-card <?php echo $plan_key === $user_plan ? 'vqr-plan-current' : ''; ?> <?php echo $plan_key === 'pro' ? 'vqr-plan-featured' : ''; ?>">
                    <?php if ($plan_key === 'pro'): ?>
                    <div class="vqr-plan-badge">Most Popular</div>
                    <?php endif; ?>
                    
                    <?php if ($plan_key === $user_plan): ?>
                    <div class="vqr-plan-current-badge">Current Plan</div>
                    <?php endif; ?>
                    
                    <div class="vqr-plan-header">
                        <h4 class="vqr-plan-title"><?php echo esc_html($plan['name']); ?></h4>
                        <div class="vqr-plan-pricing">
                            <?php if ($plan['price'] == 0): ?>
                                <span class="vqr-plan-price">Free</span>
                            <?php else: ?>
                                <?php $converted_plan_price = $plan['price'] * $current_rate; ?>
                                <span class="vqr-plan-price" data-price-usd="<?php echo esc_attr($plan['price']); ?>"><?php echo $currency_symbol . number_format($converted_plan_price, 0); ?></span>
                                <span class="vqr-plan-period">/month</span>
                            <?php endif; ?>
                        </div>
                        <div class="vqr-plan-quota">
                            <?php if ($plan['quota'] === -1): ?>
                                Unlimited QR codes
                            <?php else: ?>
                                <?php echo number_format($plan['quota']); ?> QR codes/month
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="vqr-plan-features">
                        <ul>
                            <?php foreach ($plan['features'] as $feature): ?>
                            <li>
                                <svg class="vqr-feature-check" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <?php echo esc_html($feature); ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="vqr-plan-action">
                        <?php if ($plan_key === $user_plan): ?>
                            <button class="vqr-btn vqr-btn-outline" disabled>Current Plan</button>
                        <?php elseif ($plan_key === 'enterprise'): ?>
                            <button class="vqr-btn vqr-btn-secondary" onclick="vqrContactSales()">
                                Contact Sales
                            </button>
                        <?php else: ?>
                            <button class="vqr-btn <?php echo $plan_key === 'pro' ? 'vqr-btn-primary' : 'vqr-btn-secondary'; ?>" 
                                    onclick="vqrSelectPlan('<?php echo esc_js($plan_key); ?>')">
                                <?php 
                                // Determine if this is an upgrade or downgrade
                                $plan_order = array('free' => 0, 'starter' => 1, 'pro' => 2, 'enterprise' => 3);
                                $current_order = $plan_order[$user_plan];
                                $target_order = $plan_order[$plan_key];
                                
                                if ($target_order > $current_order):
                                    echo 'Upgrade to ' . $plan['name'];
                                else:
                                    echo 'Switch to ' . $plan['name'];
                                endif;
                                ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Billing History -->
    <div class="vqr-card">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Billing History</h3>
            <p class="vqr-card-description">View your past invoices and payment history.</p>
        </div>
        <div class="vqr-card-content">
            <?php if ($user_plan === 'free'): ?>
                <div class="vqr-empty-state">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-md);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h4>No billing history</h4>
                    <p class="vqr-text-muted">You're currently on the free plan. Upgrade to start generating invoices.</p>
                    <button class="vqr-btn vqr-btn-primary" style="margin-top: var(--space-md);" onclick="vqrShowUpgradeModal()">
                        Upgrade Now
                    </button>
                </div>
            <?php else: ?>
                <div class="vqr-table-container">
                    <table class="vqr-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billing_history as $invoice): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($invoice['date'])); ?></td>
                                <td><?php echo esc_html($invoice['description']); ?></td>
                                <td>
                                    <?php $converted_amount = $invoice['amount'] * $current_rate; ?>
                                    <span class="vqr-invoice-amount" data-amount-usd="<?php echo esc_attr($invoice['amount']); ?>"><?php echo $currency_symbol . number_format($converted_amount, 2); ?></span>
                                </td>
                                <td>
                                    <span class="vqr-badge vqr-badge-<?php echo $invoice['status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($invoice['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($invoice['invoice_url']); ?>" class="vqr-btn vqr-btn-sm vqr-btn-outline">
                                        Download
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Plan Selection Modal -->
<div id="vqrPlanModal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-backdrop" onclick="vqrClosePlanModal()"></div>
    <div class="vqr-modal-content">
        <div class="vqr-modal-header">
            <h3 class="vqr-modal-title" id="vqrModalTitle">Upgrade Your Plan</h3>
            <button class="vqr-modal-close" onclick="vqrClosePlanModal()">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="vqr-modal-body">
            <div id="vqrModalContent">
                <!-- Content will be dynamically populated -->
            </div>
        </div>
    </div>
</div>

<style>
.vqr-billing {
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

.vqr-current-plan {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-lg);
}

.vqr-plan-info {
    flex: 1;
}

.vqr-plan-name {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 var(--space-sm) 0;
}

.vqr-plan-price {
    margin-bottom: var(--space-sm);
}

.vqr-price-amount {
    font-size: var(--font-size-2xl);
    font-weight: 700;
    color: var(--primary);
}

.vqr-price-period {
    color: var(--text-muted);
    margin-left: var(--space-xs);
}

.vqr-plan-quota {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
}

.vqr-plan-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-usage-display {
    text-align: center;
}

.vqr-usage-numbers {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: var(--space-xs);
}

.vqr-usage-separator {
    color: var(--text-muted);
    margin: 0 var(--space-xs);
}

.vqr-usage-label {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
    margin-bottom: var(--space-md);
}

.vqr-usage-bar-container {
    margin-bottom: var(--space-sm);
}

.vqr-usage-bar {
    width: 100%;
    height: 8px;
    background: var(--border-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: var(--space-xs);
}

.vqr-usage-progress {
    height: 100%;
    background: var(--primary);
    transition: width 0.3s ease;
}

.vqr-usage-percentage {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-usage-warning {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm);
    background: var(--warning-bg);
    color: var(--warning-text);
    border-radius: var(--radius-md);
    font-size: var(--font-size-sm);
    margin-top: var(--space-md);
}

.vqr-warning-icon {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}

.vqr-plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-lg);
}

.vqr-plan-card {
    position: relative;
    border: 2px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    transition: all 0.3s ease;
    background: var(--surface);
}

.vqr-plan-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.vqr-plan-featured {
    border-color: var(--primary);
    background: linear-gradient(135deg, var(--surface), var(--primary-bg));
}

.vqr-plan-current {
    border-color: var(--success);
    background: var(--success-bg);
}

.vqr-plan-badge,
.vqr-plan-current-badge {
    position: absolute;
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-full);
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vqr-plan-badge {
    background: var(--primary);
    color: white;
}

.vqr-plan-current-badge {
    background: var(--success);
    color: white;
}

.vqr-plan-header {
    text-align: center;
    margin-bottom: var(--space-lg);
}

.vqr-plan-title {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 var(--space-sm) 0;
}

.vqr-plan-pricing {
    margin-bottom: var(--space-sm);
}

.vqr-plan-price {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    color: var(--primary);
}

.vqr-plan-period {
    color: var(--text-muted);
    margin-left: var(--space-xs);
}

.vqr-plan-quota {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
}

.vqr-plan-features {
    margin-bottom: var(--space-xl);
}

.vqr-plan-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.vqr-plan-features li {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm) 0;
    font-size: var(--font-size-sm);
}

.vqr-feature-check {
    width: 16px;
    height: 16px;
    color: var(--success);
    flex-shrink: 0;
}

.vqr-plan-action {
    margin-top: auto;
}

.vqr-plan-action .vqr-btn {
    width: 100%;
}

.vqr-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vqr-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.vqr-modal-content {
    position: relative;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
}

.vqr-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--space-lg);
    border-bottom: 1px solid var(--border-light);
}

.vqr-modal-title {
    margin: 0;
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
}

.vqr-modal-close {
    background: none;
    border: none;
    width: 24px;
    height: 24px;
    color: var(--text-muted);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vqr-modal-close:hover {
    color: var(--text-primary);
}

.vqr-modal-close svg {
    width: 20px;
    height: 20px;
}

.vqr-modal-body {
    padding: var(--space-lg);
    max-height: 60vh;
    overflow-y: auto;
}

@media (max-width: 768px) {
    .vqr-header-top {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .vqr-currency-selector {
        align-self: flex-end;
    }
    
    .vqr-current-plan {
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .vqr-plans-grid {
        grid-template-columns: 1fr;
    }
    
    .vqr-plan-actions {
        flex-direction: row;
    }
}

/* Payment Integration Styles */
.vqr-payment-integration {
    max-width: 500px;
    margin: 0 auto;
}

.vqr-plan-summary {
    margin-bottom: var(--space-xl);
}

.vqr-selected-plan {
    text-align: center;
    padding: var(--space-lg);
    background: var(--surface);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-lg);
}

.vqr-selected-plan h4 {
    margin: 0 0 var(--space-sm) 0;
    font-size: var(--font-size-lg);
    color: var(--text-primary);
}

.vqr-plan-price-large {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    color: var(--primary);
}

.vqr-plan-price-large span {
    font-size: var(--font-size-lg);
    color: var(--text-muted);
    font-weight: 400;
}

.vqr-stripe-placeholder {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    background: var(--white);
}

.vqr-stripe-card {
    margin-bottom: var(--space-lg);
}

.vqr-card-element-placeholder {
    border: 1px dashed var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-xl);
    text-align: center;
    background: var(--surface);
}

.vqr-billing-info {
    margin-bottom: var(--space-lg);
}

.vqr-billing-info h5 {
    margin: 0 0 var(--space-md) 0;
    font-size: var(--font-size-base);
    font-weight: 600;
    color: var(--text-primary);
}

.vqr-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-md);
}

.vqr-payment-total {
    border-top: 1px solid var(--border);
    padding-top: var(--space-md);
}

.vqr-total-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-xs) 0;
}

.vqr-total-final {
    border-top: 1px solid var(--border);
    padding-top: var(--space-sm);
    margin-top: var(--space-sm);
    font-size: var(--font-size-lg);
}

.vqr-modal-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: flex-end;
    margin-top: var(--space-xl);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

.vqr-free-plan-info {
    margin-bottom: var(--space-lg);
}

.vqr-integration-note {
    text-align: center;
    padding: var(--space-md);
    background: rgba(16, 112, 70, 0.05);
    border-radius: var(--radius-md);
    border: 1px solid rgba(16, 112, 70, 0.1);
}

/* Mobile responsiveness for payment modal */
@media (max-width: 768px) {
    .vqr-payment-integration {
        max-width: 100%;
    }
    
    .vqr-modal-actions {
        flex-direction: column-reverse;
    }
    
    .vqr-modal-actions .vqr-btn {
        width: 100%;
        justify-content: center;
    }
    
    .vqr-form-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Plan selection and modal handling
function vqrShowUpgradeModal() {
    document.getElementById('vqrModalTitle').textContent = 'Upgrade Your Plan';
    document.getElementById('vqrModalContent').innerHTML = `
        <div class="vqr-upgrade-content">
            <p>Choose a plan that better fits your needs:</p>
            <div class="vqr-upgrade-options">
                <?php if ($user_plan === 'free'): ?>
                <button class="vqr-btn vqr-btn-primary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('starter')">
                    Upgrade to Starter - $49/month
                </button>
                <button class="vqr-btn vqr-btn-secondary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('pro')">
                    Upgrade to Pro - $99/month
                </button>
                <button class="vqr-btn vqr-btn-outline" style="width: 100%;" onclick="vqrContactSales()">
                    Enterprise - Contact Sales
                </button>
                <?php elseif ($user_plan === 'starter'): ?>
                <button class="vqr-btn vqr-btn-primary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('pro')">
                    Upgrade to Pro - $99/month
                </button>
                <button class="vqr-btn vqr-btn-outline" style="width: 100%;" onclick="vqrContactSales()">
                    Enterprise - Contact Sales
                </button>
                <?php elseif ($user_plan === 'pro'): ?>
                <button class="vqr-btn vqr-btn-primary" style="width: 100%;" onclick="vqrContactSales()">
                    Enterprise - Contact Sales
                </button>
                <?php endif; ?>
            </div>
        </div>
    `;
    document.getElementById('vqrPlanModal').style.display = 'flex';
}

function vqrShowDowngradeModal() {
    document.getElementById('vqrModalTitle').textContent = 'Downgrade Your Plan';
    document.getElementById('vqrModalContent').innerHTML = `
        <div class="vqr-downgrade-content">
            <div class="vqr-warning-message" style="background: var(--warning-bg); color: var(--warning-text); padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-md);">
                <strong>Important:</strong> Downgrading will reduce your monthly quota. Your generated QR codes will remain active.
            </div>
            <p>Select a plan to downgrade to:</p>
            <div class="vqr-downgrade-options">
                <?php if ($user_plan === 'enterprise'): ?>
                <button class="vqr-btn vqr-btn-secondary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('pro')">
                    Downgrade to Pro - $99/month
                </button>
                <button class="vqr-btn vqr-btn-secondary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('starter')">
                    Downgrade to Starter - $49/month
                </button>
                <button class="vqr-btn vqr-btn-outline" style="width: 100%;" onclick="vqrSelectPlan('free')">
                    Downgrade to Free - $0/month
                </button>
                <?php elseif ($user_plan === 'pro'): ?>
                <button class="vqr-btn vqr-btn-secondary" style="width: 100%; margin-bottom: var(--space-sm);" onclick="vqrSelectPlan('starter')">
                    Downgrade to Starter - $49/month
                </button>
                <button class="vqr-btn vqr-btn-outline" style="width: 100%;" onclick="vqrSelectPlan('free')">
                    Downgrade to Free - $0/month
                </button>
                <?php elseif ($user_plan === 'starter'): ?>
                <button class="vqr-btn vqr-btn-outline" style="width: 100%;" onclick="vqrSelectPlan('free')">
                    Downgrade to Free - $0/month
                </button>
                <?php endif; ?>
            </div>
        </div>
    `;
    document.getElementById('vqrPlanModal').style.display = 'flex';
}

function vqrSelectPlan(planKey) {
    // Show the modal first
    document.getElementById('vqrPlanModal').style.display = 'flex';
    
    // This is where payment integration would go
    // For now, just show a placeholder message ready for Stripe integration
    const planNames = {
        'free': 'Free',
        'starter': 'Starter',
        'pro': 'Pro',
        'enterprise': 'Enterprise'
    };
    
    const planPrices = {
        'free': 0,
        'starter': 49,
        'pro': 99,
        'enterprise': 399
    };
    
    const currentPlan = '<?php echo esc_js($user_plan); ?>';
    const isUpgrade = ['free', 'starter', 'pro', 'enterprise'].indexOf(planKey) > ['free', 'starter', 'pro', 'enterprise'].indexOf(currentPlan);
    const isDowngrade = ['free', 'starter', 'pro', 'enterprise'].indexOf(planKey) < ['free', 'starter', 'pro', 'enterprise'].indexOf(currentPlan);
    
    let actionText = 'Switch to';
    if (isUpgrade) actionText = 'Upgrade to';
    if (isDowngrade) actionText = 'Downgrade to';
    
    document.getElementById('vqrModalTitle').textContent = `${actionText} ${planNames[planKey]} Plan`;
    
    if (planKey === 'enterprise') {
        // Handle Enterprise plan differently
        vqrContactSales();
        return;
    }
    
    document.getElementById('vqrModalContent').innerHTML = `
        <div class="vqr-payment-integration">
            <div class="vqr-plan-summary">
                <div class="vqr-selected-plan">
                    <h4>${planNames[planKey]} Plan</h4>
                    <div class="vqr-plan-price-large">
                        ${planPrices[planKey] > 0 ? `$${planPrices[planKey]}<span>/month</span>` : 'Free'}
                    </div>
                </div>
                
                ${planPrices[planKey] > 0 ? `
                <div class="vqr-payment-form">
                    <div class="vqr-stripe-placeholder">
                        <div class="vqr-stripe-card">
                            <!-- Stripe Elements will be mounted here -->
                            <div class="vqr-card-element-placeholder">
                                <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--primary);">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                                <p style="margin: var(--space-sm) 0 0 0; color: var(--text-muted); font-size: var(--font-size-sm);">
                                    Stripe Card Element Placeholder
                                </p>
                            </div>
                        </div>
                        
                        <div class="vqr-billing-info">
                            <h5>Billing Information</h5>
                            <div class="vqr-form-grid">
                                <input type="email" placeholder="Email Address" class="vqr-input" value="<?php echo esc_attr($current_user->user_email); ?>" readonly>
                                <input type="text" placeholder="Full Name" class="vqr-input" value="<?php echo esc_attr($current_user->display_name); ?>">
                            </div>
                        </div>
                        
                        <div class="vqr-payment-total">
                            <div class="vqr-total-line">
                                <span>${planNames[planKey]} Plan (Monthly)</span>
                                <span>$${planPrices[planKey]}.00</span>
                            </div>
                            <div class="vqr-total-line vqr-total-final">
                                <span><strong>Total</strong></span>
                                <span><strong>$${planPrices[planKey]}.00/month</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                ` : `
                <div class="vqr-free-plan-info">
                    <div style="text-align: center; padding: var(--space-lg); background: var(--surface); border-radius: var(--radius-md);">
                        <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--success); margin-bottom: var(--space-md);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <h4 style="margin: 0 0 var(--space-sm) 0;">Switch to Free Plan</h4>
                        <p style="color: var(--text-muted); margin: 0;">
                            Your subscription will be cancelled and you'll be moved to the free plan.
                            You'll keep access to your current features until the end of your billing period.
                        </p>
                    </div>
                </div>
                `}
            </div>
            
            <div class="vqr-modal-actions">
                <button class="vqr-btn vqr-btn-secondary" onclick="vqrClosePlanModal()">
                    Cancel
                </button>
                <button class="vqr-btn vqr-btn-primary" onclick="vqrProcessPayment('${planKey}')">
                    ${planPrices[planKey] > 0 ? `${actionText} - $${planPrices[planKey]}/month` : actionText}
                </button>
            </div>
            
            <div class="vqr-integration-note">
                <p style="color: var(--text-muted); font-size: var(--font-size-xs); text-align: center; margin-top: var(--space-md);">
                    🔗 <strong>Integration Ready:</strong> This form is prepared for Stripe Elements integration
                </p>
            </div>
        </div>
    `;
}

function vqrProcessPayment(planKey) {
    // This function will handle the actual payment processing
    // Here you would integrate with Stripe, PayPal, or other payment processors
    
    const planNames = {
        'free': 'Free',
        'starter': 'Starter',
        'pro': 'Pro',
        'enterprise': 'Enterprise'
    };
    
    const planPrices = {
        'free': 0,
        'starter': 49,
        'pro': 99,
        'enterprise': 399
    };
    
    // Show processing state
    const processBtn = event.target;
    const originalText = processBtn.innerHTML;
    processBtn.innerHTML = '<span class="vqr-loading"></span> Processing...';
    processBtn.disabled = true;
    
    // Simulate payment processing (replace with actual Stripe integration)
    setTimeout(() => {
        if (planKey === 'free') {
            // Handle downgrade to free
            alert(`✅ Successfully switched to ${planNames[planKey]} plan!`);
        } else {
            // Handle paid plan upgrade
            alert(`✅ Payment successful! Welcome to the ${planNames[planKey]} plan!\n\n🔗 Ready for Stripe integration:\n• Customer creation\n• Subscription management\n• Webhook handling\n• Invoice generation`);
        }
        
        vqrClosePlanModal();
        
        // In a real implementation, you would:
        // 1. Create Stripe customer if not exists
        // 2. Create or update subscription
        // 3. Handle payment method
        // 4. Update user plan in database
        // 5. Send confirmation email
        // 6. Refresh page to show new plan
        
    }, 2000);
}

function vqrClosePlanModal() {
    document.getElementById('vqrPlanModal').style.display = 'none';
}

function vqrContactSales() {
    document.getElementById('vqrModalTitle').textContent = 'Enterprise Plan';
    document.getElementById('vqrModalContent').innerHTML = `
        <div class="vqr-enterprise-contact">
            <div style="text-align: center; padding: var(--space-xl);">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--primary); margin-bottom: var(--space-md);">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <h4 style="margin: 0 0 var(--space-sm) 0;">Contact Our Sales Team</h4>
                <p style="color: var(--text-muted); margin-bottom: var(--space-lg);">
                    Enterprise plans include unlimited QR codes, custom integrations, and dedicated support. 
                    Our sales team will work with you to create a custom solution.
                </p>
                <div style="display: flex; flex-direction: column; gap: var(--space-md); max-width: 300px; margin: 0 auto;">
                    <a href="mailto:sales@verify420.com?subject=Enterprise Plan Inquiry" 
                       class="vqr-btn vqr-btn-primary" 
                       style="text-decoration: none;">
                        Email Sales Team
                    </a>
                    <a href="tel:+1-555-VERIFY" 
                       class="vqr-btn vqr-btn-secondary" 
                       style="text-decoration: none;">
                        Call: (555) VERIFY-0
                    </a>
                    <button class="vqr-btn vqr-btn-outline" onclick="vqrClosePlanModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    `;
    document.getElementById('vqrPlanModal').style.display = 'flex';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('vqrPlanModal');
    if (event.target === modal) {
        vqrClosePlanModal();
    }
});

// Close modal with escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        vqrClosePlanModal();
    }
});

// Currency conversion functionality
window.VQR = window.VQR || {};
VQR.currencyRates = <?php echo json_encode($currency_rates); ?>;
VQR.currentCurrency = '<?php echo esc_js($selected_currency); ?>';

// Currency change function
VQR.changeCurrency = function(newCurrency) {
    // Update URL with new currency
    const url = new URL(window.location);
    url.searchParams.set('currency', newCurrency);
    window.location.href = url.toString();
};

// Update all pricing when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateAllPricing();
});

function updateAllPricing() {
    const currentRate = VQR.currencyRates[VQR.currentCurrency].rate;
    const symbol = VQR.currencyRates[VQR.currentCurrency].symbol;
    
    // Update current plan price
    const currentPlanPrice = document.querySelector('.vqr-price-amount[data-price-usd]');
    if (currentPlanPrice) {
        const priceUsd = parseFloat(currentPlanPrice.dataset.priceUsd);
        const convertedPrice = priceUsd * currentRate;
        currentPlanPrice.textContent = symbol + Math.round(convertedPrice);
    }
    
    // Update all plan prices in comparison
    document.querySelectorAll('.vqr-plan-price[data-price-usd]').forEach(priceEl => {
        const priceUsd = parseFloat(priceEl.dataset.priceUsd);
        const convertedPrice = priceUsd * currentRate;
        priceEl.textContent = symbol + Math.round(convertedPrice);
    });
    
    // Update billing history amounts
    document.querySelectorAll('.vqr-invoice-amount[data-amount-usd]').forEach(amountEl => {
        const amountUsd = parseFloat(amountEl.dataset.amountUsd);
        const convertedAmount = amountUsd * currentRate;
        amountEl.textContent = symbol + convertedAmount.toFixed(2);
    });
}
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Billing & Subscription';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>