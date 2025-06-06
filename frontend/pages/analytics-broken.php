<?php
/**
 * Analytics page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Check if user can access analytics
if (!vqr_user_can_access_analytics()) {
    // Show locked page for Free plan users
    $upgrade_info = vqr_get_upgrade_info('analytics');
    ob_start();
    ?>
    
    <div class="vqr-feature-locked">
        <div class="vqr-locked-content">
            <div class="vqr-locked-icon">
                <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            
            <h2 class="vqr-locked-title"><?php echo esc_html($upgrade_info['title']); ?></h2>
            <p class="vqr-locked-message"><?php echo esc_html($upgrade_info['message']); ?></p>
            
            <div class="vqr-locked-features">
                <h4>Analytics features include:</h4>
                <ul>
                    <li>Detailed scan tracking</li>
                    <li>Performance metrics</li>
                    <li>QR code management</li>
                    <li>Usage analytics</li>
                    <li>Download capabilities</li>
                </ul>
            </div>
            
            <div class="vqr-locked-actions">
                <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary">
                    Upgrade to <?php echo ucfirst($upgrade_info['upgrade_plan']); ?> Plan
                </a>
                <a href="<?php echo home_url('/app/dashboard'); ?>" class="vqr-btn vqr-btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <style>
    .vqr-feature-locked {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 60vh;
        padding: var(--space-xl);
    }
    
    .vqr-locked-content {
        text-align: center;
        max-width: 500px;
        background: var(--surface);
        padding: var(--space-2xl);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
    }
    
    .vqr-locked-icon {
        margin-bottom: var(--space-lg);
        color: var(--warning);
    }
    
    .vqr-locked-title {
        font-size: var(--font-size-2xl);
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 var(--space-md) 0;
    }
    
    .vqr-locked-message {
        color: var(--text-muted);
        margin-bottom: var(--space-xl);
        font-size: var(--font-size-lg);
    }
    
    .vqr-locked-features {
        text-align: left;
        background: var(--background);
        padding: var(--space-lg);
        border-radius: var(--radius-md);
        margin-bottom: var(--space-xl);
    }
    
    .vqr-locked-features h4 {
        margin: 0 0 var(--space-md) 0;
        color: var(--text-primary);
        font-size: var(--font-size-md);
    }
    
    .vqr-locked-features ul {
        margin: 0;
        padding-left: var(--space-lg);
        color: var(--text-muted);
    }
    
    .vqr-locked-features li {
        margin-bottom: var(--space-sm);
    }
    
    .vqr-locked-actions {
        display: flex;
        gap: var(--space-md);
        justify-content: center;
        flex-wrap: wrap;
    }
    
    @media (max-width: 640px) {
        .vqr-locked-actions {
            flex-direction: column;
        }
        
        .vqr-locked-actions .vqr-btn {
            width: 100%;
        }
    }
    </style>
    
    <?php
    $page_content = ob_get_clean();
    $page_title = 'Analytics - Upgrade Required';
    include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
    return;
}

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's QR code data
global $wpdb;
$table_name = $wpdb->prefix . 'vqr_codes';

// Get all user's QR codes with pagination
$per_page = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

$total_codes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", 
    $user_id
));

$qr_codes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", 
    $user_id, $per_page, $offset
));

$total_pages = ceil($total_codes / $per_page);

// Get user subscription info
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();

// Get security analytics data if user has access
$security_data = null;
if (vqr_user_can_access_security_analytics()) {
    $security_data = vqr_get_security_dashboard_data($user_id, 30);
}

// Get geographic analytics data if user has access
$geographic_data = null;
$user_plan = vqr_get_user_plan($user_id);
$can_access_geographic = vqr_user_can_access_geographic_analytics();

// Debug user access
error_log("VQR Analytics Debug: User plan: {$user_plan}");
error_log("VQR Analytics Debug: Can access geographic analytics: " . ($can_access_geographic ? 'Yes' : 'No'));

if ($can_access_geographic) {
    $geographic_data = vqr_get_geographic_analytics_data($user_id, 30);
    
    // Debug logging for geographic data
    error_log("VQR Analytics Debug: Geographic data retrieved");
    error_log("VQR Analytics Debug: Geographic data is " . (is_array($geographic_data) ? 'array' : gettype($geographic_data)));
    if (is_array($geographic_data)) {
        error_log("VQR Analytics Debug: Geographic data keys: " . implode(', ', array_keys($geographic_data)));
        if (isset($geographic_data['heat_map_data'])) {
            error_log("VQR Analytics Debug: Heat map data count: " . count($geographic_data['heat_map_data']));
            error_log("VQR Analytics Debug: Heat map data empty check: " . (empty($geographic_data['heat_map_data']) ? 'EMPTY' : 'NOT EMPTY'));
            error_log("VQR Analytics Debug: Heat map data type: " . gettype($geographic_data['heat_map_data']));
            if (is_array($geographic_data['heat_map_data']) && count($geographic_data['heat_map_data']) > 0) {
                error_log("VQR Analytics Debug: First heat map item: " . json_encode($geographic_data['heat_map_data'][0]));
            }
        }
        if (isset($geographic_data['summary_stats'])) {
            error_log("VQR Analytics Debug: Summary stats: " . json_encode($geographic_data['summary_stats']));
        }
    }
} else {
    error_log("VQR Analytics Debug: User does not have access to geographic analytics");
}

// Prepare page content
ob_start();
?>

<div class="vqr-analytics-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">QR Code Analytics</h1>
        <p class="vqr-page-description">Monitor your QR code performance and usage.</p>
    </div>
    
    <!-- Analytics Tabs -->
    <div style="background: yellow; padding: 10px; margin: 10px; font-weight: bold;">
        🔥 ANALYTICS PAGE IS LOADING - You should see this before any tab content!
    </div>
    <div class="vqr-analytics-tabs">
        <div class="vqr-tab-nav">
            <button class="vqr-tab-btn" data-tab="overview">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Overview
            </button>
            
            <?php if (vqr_user_can_access_security_analytics()): ?>
            <button class="vqr-tab-btn" data-tab="security">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Security
            </button>
            <?php endif; ?>
            
            <button class="vqr-tab-btn active" data-tab="geographic">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Geographic
            </button>
            
            <button class="vqr-tab-btn" data-tab="codes">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                </svg>
                QR Codes
            </button>
        </div>
        
        <!-- Overview Tab Content -->
        <div class="vqr-tab-content" id="overview-tab">
            <div class="vqr-overview-stats">
                <div class="vqr-grid vqr-grid-cols-4 vqr-mb-lg">
                    <div class="vqr-card">
                        <div class="vqr-card-content">
                            <div class="vqr-stat">
                                <span class="vqr-stat-value"><?php echo number_format($total_codes); ?></span>
                                <div class="vqr-stat-label">Total QR Codes</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vqr-card">
                        <div class="vqr-card-content">
                            <div class="vqr-stat">
                                <span class="vqr-stat-value"><?php echo number_format($current_usage); ?></span>
                                <div class="vqr-stat-label">This Month</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vqr-card">
                        <div class="vqr-card-content">
                            <div class="vqr-stat">
                                <span class="vqr-stat-value">
                                    <?php 
                                    $total_scans = $wpdb->get_var($wpdb->prepare(
                                        "SELECT SUM(scan_count) FROM {$table_name} WHERE user_id = %d", 
                                        $user_id
                                    )) ?: 0;
                                    echo number_format($total_scans); 
                                    ?>
                                </span>
                                <div class="vqr-stat-label">Total Scans</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vqr-card">
                        <div class="vqr-card-content">
                            <div class="vqr-stat">
                                <span class="vqr-stat-value">
                                    <?php 
                                    $scanned_codes = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND scan_count > 0", 
                                        $user_id
                                    )) ?: 0;
                                    echo number_format($scanned_codes); 
                                    ?>
                                </span>
                                <div class="vqr-stat-label">Active Codes</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Summary -->
                <div class="vqr-overview-summary">
                    <div class="vqr-card">
                        <div class="vqr-card-header">
                            <h3 class="vqr-card-title">📊 Analytics Summary</h3>
                        </div>
                        <div class="vqr-card-content">
                            <div class="vqr-summary-grid">
                                <div class="vqr-summary-item">
                                    <div class="vqr-summary-metric">
                                        <?php 
                                        $scan_rate = $total_codes > 0 ? round(($scanned_codes / $total_codes) * 100, 1) : 0;
                                        echo $scan_rate . '%';
                                        ?>
                                    </div>
                                    <div class="vqr-summary-label">Activation Rate</div>
                                </div>
                                
                                <div class="vqr-summary-item">
                                    <div class="vqr-summary-metric">
                                        <?php 
                                        $avg_scans = $scanned_codes > 0 ? round($total_scans / $scanned_codes, 1) : 0;
                                        echo $avg_scans;
                                        ?>
                                    </div>
                                    <div class="vqr-summary-label">Avg Scans per Code</div>
                                </div>
                                
                                <div class="vqr-summary-item">
                                    <div class="vqr-summary-metric">
                                        <?php 
                                        $quota_used = $monthly_quota > 0 ? round(($current_usage / $monthly_quota) * 100, 1) : 0;
                                        echo $quota_used . '%';
                                        ?>
                                    </div>
                                    <div class="vqr-summary-label">Monthly Quota Used</div>
                                </div>
                                
                                <div class="vqr-summary-item">
                                    <div class="vqr-summary-metric"><?php echo ucfirst($user_plan); ?></div>
                                    <div class="vqr-summary-label">Current Plan</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <!-- Security Tab Content -->
        <?php if (vqr_user_can_access_security_analytics()): ?>
        <div class="vqr-tab-content" id="security-tab">
            <div class="vqr-security-analytics">
            <div class="vqr-card">
                <div class="vqr-card-header">
                    <div class="vqr-card-header-content">
                        <h3 class="vqr-card-title">🔒 Security & Anti-Counterfeiting Dashboard</h3>
                        <span class="vqr-security-badge">Security Analytics</span>
                    </div>
                </div>
                <div class="vqr-card-content">
                    <?php if ($security_data && !empty($security_data['recent_alerts'])): ?>
                        <!-- Security Alerts Summary -->
                        <div class="vqr-security-summary-minimal vqr-mb-md">
                            <?php
                            $alert_counts = array('critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0);
                            foreach ($security_data['alert_summary'] as $alert) {
                                $alert_counts[$alert->severity] += $alert->count;
                            }
                            ?>
                            <div class="vqr-stat-minimal vqr-stat-critical">
                                <span class="vqr-stat-number"><?php echo $alert_counts['critical']; ?></span>
                                <span class="vqr-stat-label">Critical</span>
                            </div>
                            
                            <div class="vqr-stat-minimal vqr-stat-high">
                                <span class="vqr-stat-number"><?php echo $alert_counts['high']; ?></span>
                                <span class="vqr-stat-label">High</span>
                            </div>
                            
                            <div class="vqr-stat-minimal vqr-stat-medium">
                                <span class="vqr-stat-number"><?php echo $alert_counts['medium']; ?></span>
                                <span class="vqr-stat-label">Medium</span>
                            </div>
                            
                            <div class="vqr-stat-minimal vqr-stat-low">
                                <span class="vqr-stat-number"><?php echo $alert_counts['low']; ?></span>
                                <span class="vqr-stat-label">Low</span>
                            </div>
                        </div>
                        
                        <!-- Recent Security Alerts -->
                        <div class="vqr-recent-alerts vqr-mb-lg">
                            <h4 class="vqr-section-title">🚨 Recent Security Alerts</h4>
                            
                            <?php 
                            // Pagination for alerts (10 per page)
                            $alerts_per_page = 10;
                            $alerts_page = isset($_GET['alerts_page']) ? max(1, intval($_GET['alerts_page'])) : 1;
                            $alerts_offset = ($alerts_page - 1) * $alerts_per_page;
                            
                            // Get total alerts count for pagination
                            $total_alerts = count($security_data['recent_alerts']);
                            $alerts_total_pages = ceil($total_alerts / $alerts_per_page);
                            
                            // Get alerts for current page
                            $current_alerts = array_slice($security_data['recent_alerts'], $alerts_offset, $alerts_per_page);
                            ?>
                            
                            <?php if (!empty($current_alerts)): ?>
                                <div class="vqr-table-container">
                                    <table class="vqr-table vqr-alerts-table">
                                        <thead>
                                            <tr>
                                                <th>Risk</th>
                                                <th>Alert Type</th>
                                                <th>Product</th>
                                                <th>Batch Code</th>
                                                <th>Location</th>
                                                <th>Score</th>
                                                <th>Time</th>
                                                <th>Details</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($current_alerts as $alert): ?>
                                                <?php 
                                                $flags = json_decode($alert->security_flags, true) ?: array();
                                                $severity_icon = array(
                                                    'critical' => '🔥',
                                                    'high' => '⚠️',
                                                    'medium' => '⚡',
                                                    'low' => 'ℹ️'
                                                )[$alert->severity] ?? 'ℹ️';
                                                
                                                // Get QR code details to show batch code
                                                $qr_code_details = $wpdb->get_row($wpdb->prepare(
                                                    "SELECT batch_code, category FROM {$wpdb->prefix}vqr_codes WHERE qr_key = %s",
                                                    $alert->qr_key
                                                ));
                                                
                                                // Get strain name if available
                                                $strain_name = 'Unknown Product';
                                                if ($alert->strain_id) {
                                                    $strain = get_post($alert->strain_id);
                                                    if ($strain) {
                                                        $strain_name = $strain->post_title;
                                                    }
                                                }
                                                
                                                // Create alert row class
                                                $row_class = 'vqr-alert-row-' . $alert->severity;
                                                ?>
                                                <tr class="<?php echo $row_class; ?>">
                                                    <td>
                                                        <span class="vqr-severity-badge vqr-severity-<?php echo $alert->severity; ?>">
                                                            <?php echo $severity_icon; ?> <?php echo strtoupper($alert->severity); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo ucwords(str_replace('_', ' ', $alert->alert_type)); ?></td>
                                                    <td class="vqr-product-name"><?php echo esc_html($strain_name); ?></td>
                                                    <td>
                                                        <?php if ($qr_code_details): ?>
                                                            <code class="vqr-batch-code-small"><?php echo esc_html($qr_code_details->batch_code); ?></code>
                                                        <?php else: ?>
                                                            <span class="vqr-text-muted">N/A</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="vqr-location-compact"><?php echo esc_html($alert->location); ?></td>
                                                    <td>
                                                        <span class="vqr-score-badge vqr-score-<?php echo $alert->severity; ?>">
                                                            <?php echo $alert->security_score; ?>
                                                        </span>
                                                    </td>
                                                    <td class="vqr-time-compact">
                                                        <?php 
                                                        // Convert GMT timestamp to local time for display
                                                        $local_time = get_date_from_gmt($alert->created_at);
                                                        ?>
                                                        <span title="<?php echo esc_attr($local_time); ?>">
                                                            <?php echo human_time_diff(strtotime($local_time)); ?> ago
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($flags)): ?>
                                                            <?php
                                                            $alert_details = array(
                                                                'id' => $alert->id,
                                                                'type' => $alert->alert_type,
                                                                'severity' => $alert->severity,
                                                                'score' => $alert->security_score,
                                                                'product' => $strain_name,
                                                                'batch' => $qr_code_details ? $qr_code_details->batch_code : 'N/A',
                                                                'location' => $alert->location,
                                                                'time' => $alert->created_at,
                                                                'flags' => $flags
                                                            );
                                                            ?>
                                                            <button class="vqr-btn vqr-btn-secondary vqr-btn-xs" 
                                                                    onclick="VQR.showAlertDetails(<?php echo esc_attr(json_encode($alert_details)); ?>)">
                                                                Details
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="vqr-text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Alerts Pagination -->
                                <?php if ($alerts_total_pages > 1): ?>
                                    <div class="vqr-pagination vqr-alerts-pagination">
                                        <?php if ($alerts_page > 1): ?>
                                            <a href="<?php echo esc_url(add_query_arg('alerts_page', $alerts_page - 1)); ?>" 
                                               class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                                                ← Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <span class="vqr-pagination-info">
                                            Page <?php echo $alerts_page; ?> of <?php echo $alerts_total_pages; ?> 
                                            (<?php echo number_format($total_alerts); ?> alerts)
                                        </span>
                                        
                                        <?php if ($alerts_page < $alerts_total_pages): ?>
                                            <a href="<?php echo esc_url(add_query_arg('alerts_page', $alerts_page + 1)); ?>" 
                                               class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                                                Next →
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="vqr-empty-alerts">
                                    <p>No security alerts yet. Your QR codes are being monitored.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Geographic Risk Analysis - Compact -->
                        <?php if (!empty($security_data['geographic_data'])): ?>
                            <div class="vqr-geographic-security-compact vqr-mb-lg">
                                <h4 class="vqr-section-title">🌍 Geographic Distribution</h4>
                                <div class="vqr-geo-table-container">
                                    <table class="vqr-table vqr-geo-table">
                                        <thead>
                                            <tr>
                                                <th>Location</th>
                                                <th>Scans</th>
                                                <th>Suspicious</th>
                                                <th>Risk</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($security_data['geographic_data'], 0, 6) as $location): ?>
                                                <?php 
                                                $risk_level = 'low';
                                                $risk_ratio = 0;
                                                if ($location->suspicious_scans > 0) {
                                                    $risk_ratio = $location->suspicious_scans / $location->scan_count;
                                                    if ($risk_ratio > 0.3) $risk_level = 'high';
                                                    elseif ($risk_ratio > 0.1) $risk_level = 'medium';
                                                }
                                                ?>
                                                <tr class="vqr-geo-row-<?php echo $risk_level; ?>">
                                                    <td class="vqr-geo-location-compact">
                                                        <?php echo esc_html($location->city . ', ' . $location->region); ?>
                                                    </td>
                                                    <td><?php echo $location->scan_count; ?></td>
                                                    <td>
                                                        <?php if ($location->suspicious_scans > 0): ?>
                                                            <span class="vqr-suspicious-count"><?php echo $location->suspicious_scans; ?></span>
                                                        <?php else: ?>
                                                            <span class="vqr-text-muted">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="vqr-risk-badge vqr-risk-<?php echo $risk_level; ?>">
                                                            <?php echo strtoupper($risk_level); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <!-- No Security Data Yet -->
                        <div class="vqr-security-empty">
                            <div class="vqr-security-empty-icon">🛡️</div>
                            <h4>Security Monitoring Active</h4>
                            <p>Your QR codes are being monitored for suspicious activity. Security data will appear here once your codes are scanned.</p>
                            <div class="vqr-security-features">
                                <div class="vqr-security-feature">
                                    <span class="vqr-feature-icon">🚨</span>
                                    <span>Real-time counterfeit detection</span>
                                </div>
                                <div class="vqr-security-feature">
                                    <span class="vqr-feature-icon">🌍</span>
                                    <span>Geographic anomaly alerts</span>
                                </div>
                                <div class="vqr-security-feature">
                                    <span class="vqr-feature-icon">📊</span>
                                    <span>Scanning pattern analysis</span>
                                </div>
                                <div class="vqr-security-feature">
                                    <span class="vqr-feature-icon">📧</span>
                                    <span>Email alerts for high-risk events</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Geographic Tab Content -->
        <div class="vqr-tab-content" id="geographic-tab">
            <h2>Geographic Analytics</h2>
            
            <?php if (vqr_user_can_access_geographic_analytics()): ?>
                <?php if ($geographic_data && !empty($geographic_data['heat_map_data'])): ?>
                    
                    <div style="background: lightgreen; padding: 20px; margin: 15px 0; border-radius: 8px;">
                        <h3>✅ Geographic Summary</h3>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold; color: #2563eb;"><?php echo $geographic_data['summary_stats']['countries_reached']; ?></div>
                                <div style="color: #666;">Countries Reached</div>
                            </div>
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold; color: #2563eb;"><?php echo $geographic_data['summary_stats']['total_locations']; ?></div>
                                <div style="color: #666;">Unique Locations</div>
                            </div>
                            <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold; color: #2563eb;"><?php echo $geographic_data['summary_stats']['total_scans']; ?></div>
                                <div style="color: #666;">Geographic Scans</div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: white; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h4 style="margin-top: 0;">🗺️ Scan Heat Map Locations</h4>
                        <?php foreach ($geographic_data['heat_map_data'] as $location): ?>
                            <div style="background: #f8fafc; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #3b82f6;">
                                <div style="font-weight: bold; color: #1f2937;">
                                    📍 <?php echo esc_html($location->city . ', ' . $location->region . ', ' . $location->country); ?>
                                </div>
                                <div style="margin-top: 8px; color: #6b7280;">
                                    <span style="margin-right: 15px;">🔢 <strong><?php echo $location->scan_count; ?></strong> scans</span>
                                    <span style="margin-right: 15px;">📱 <strong><?php echo $location->unique_codes; ?></strong> unique codes</span>
                                    <span>🕒 Last scan: <?php echo date('M j, Y g:i A', strtotime($location->last_scan)); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($geographic_data['country_distribution'])): ?>
                    <div style="background: white; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #e5e7eb;">
                        <h4 style="margin-top: 0;">🌍 Country Distribution</h4>
                        <?php foreach ($geographic_data['country_distribution'] as $country): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f3f4f6;">
                                <div style="font-weight: 500;">🏳️ <?php echo esc_html($country->country); ?></div>
                                <div style="color: #6b7280;">
                                    <span style="margin-right: 15px;"><?php echo $country->scan_count; ?> scans</span>
                                    <span><?php echo $country->unique_codes; ?> codes</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div style="background: #fff3cd; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #ffeaa7;">
                        <h3>🌍 Geographic Analytics Ready</h3>
                        <p>Your QR codes are being tracked for geographic distribution. Data will appear here once your codes are scanned in different locations.</p>
                        <ul style="margin: 15px 0; padding-left: 20px;">
                            <li>🔥 Scan heat maps showing hotspot locations</li>
                            <li>📦 Distribution tracking for product travel</li>
                            <li>📊 Market penetration analysis</li>
                            <li>🏳️ Territory coverage and expansion opportunities</li>
                        </ul>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="background: #fee2e2; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #fecaca;">
                    <h3>🔒 Geographic Analytics Locked</h3>
                    <p>Upgrade to Pro plan to access geographic analytics features.</p>
                    <a href="/app/billing" style="color: #2563eb; text-decoration: underline;">View Upgrade Options</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QR Codes Tab Content -->
        <div class="vqr-tab-content" id="codes-tab">
            <h2>QR Codes</h2>
            <div style="background: #f0f0f0; padding: 20px; margin: 10px 0; border-radius: 5px;">
                <h3>QR Codes Tab Working!</h3>
                <p>Total QR codes: <?php echo number_format($total_codes); ?></p>
                <p>This tab content will be completed once the Geographic tab is working properly.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabBtns = document.querySelectorAll('.vqr-tab-btn');
    const tabContents = document.querySelectorAll('.vqr-tab-content');
    
    function switchTab(targetTab) {
        console.log('Switching to tab:', targetTab);
        
        // Remove active class from all tabs and content
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Add active class to target button and content
        const targetButton = document.querySelector(`[data-tab="${targetTab}"]`);
        const targetContent = document.getElementById(`${targetTab}-tab`);
        
        if (targetButton && targetContent) {
            targetButton.classList.add('active');
            targetContent.classList.add('active');
            
            // Store in localStorage
            localStorage.setItem('vqr_active_tab', targetTab);
        }
    }
    
    // Add click handlers
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            switchTab(targetTab);
        });
    });
    
    // Restore last active tab or default to geographic
    const lastActiveTab = localStorage.getItem('vqr_active_tab') || 'geographic';
    switchTab(lastActiveTab);
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Analytics';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>
            <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;">
                <strong>Debug Info:</strong><br>
                Total QR codes: <?php echo number_format($total_codes); ?><br>
                QR codes array count: <?php echo count($qr_codes); ?><br>
                Current page: <?php echo $page; ?> of <?php echo $total_pages; ?><br>
                <strong>This is the QR Codes tab - if you can see this, the tab is working!</strong>
            </div>
                                </div>
                            </div>
                            
                            <div class="vqr-card">
                                <div class="vqr-card-content">
                                    <div class="vqr-stat">
                                        <span class="vqr-stat-value"><?php echo number_format($geographic_data['summary_stats']['total_locations']); ?></span>
                                        <div class="vqr-stat-label">Unique Locations</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vqr-card">
                                <div class="vqr-card-content">
                                    <div class="vqr-stat">
                                        <span class="vqr-stat-value"><?php echo number_format($geographic_data['summary_stats']['total_scans']); ?></span>
                                        <div class="vqr-stat-label">Geographic Scans</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="vqr-card">
                                <div class="vqr-card-content">
                                    <div class="vqr-stat">
                                        <span class="vqr-stat-value">
                                            <?php 
                                            $avg_locations = count($geographic_data['distribution_tracking']) > 0 ? 
                                                round(array_sum(array_column($geographic_data['distribution_tracking'], 'unique_locations')) / count($geographic_data['distribution_tracking']), 1) : 0;
                                            echo $avg_locations;
                                            ?>
                                        </span>
                                        <div class="vqr-stat-label">Avg Spread per Code</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scan Heat Map -->
                    <div class="vqr-heat-map-section vqr-mb-lg">
                        <div class="vqr-card">
                            <div class="vqr-card-header">
                                <h3 class="vqr-card-title">🌍 Scan Heat Map</h3>
                                <span class="vqr-pro-badge">Pro</span>
                            </div>
                            <div class="vqr-card-content">
                                <div class="vqr-heat-map-container">
                                    <!-- Heat Map Visualization -->
                                    <div class="vqr-heat-map-visual">
                                        <div class="vqr-world-map-placeholder">
                                            <svg viewBox="0 0 800 400" class="vqr-world-svg">
                                                <!-- Simplified world map with heat points -->
                                                <rect width="800" height="400" fill="#f8fafc" stroke="#e5e7eb" stroke-width="1"/>
                                                <text x="400" y="200" text-anchor="middle" class="vqr-map-placeholder-text">
                                                    🗺️ Interactive Heat Map
                                                </text>
                                                <text x="400" y="220" text-anchor="middle" class="vqr-map-subtitle">
                                                    Showing scan density across <?php echo $geographic_data['summary_stats']['countries_reached']; ?> countries
                                                </text>
                                                
                                                <!-- Sample heat points -->
                                                <?php foreach (array_slice($geographic_data['heat_map_data'], 0, 10) as $index => $location): ?>
                                                    <?php 
                                                    $intensity = min(100, ($location->scan_count / max(array_column($geographic_data['heat_map_data'], 'scan_count'))) * 100);
                                                    $x = 100 + ($index * 60) % 600;
                                                    $y = 150 + (($index * 30) % 100);
                                                    ?>
                                                    <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" 
                                                            r="<?php echo 3 + ($intensity / 20); ?>" 
                                                            fill="#dc2626" 
                                                            opacity="<?php echo 0.3 + ($intensity / 200); ?>" 
                                                            class="vqr-heat-point">
                                                        <title><?php echo esc_attr($location->city . ', ' . $location->region . ': ' . $location->scan_count . ' scans'); ?></title>
                                                    </circle>
                                                <?php endforeach; ?>
                                            </svg>
                                        </div>
                                    </div>
                                    
                                    <!-- Top Locations Table -->
                                    <div class="vqr-top-locations">
                                        <h4 class="vqr-section-title">🔥 Hotspot Locations</h4>
                                        <div class="vqr-table-container">
                                            <table class="vqr-table vqr-table-compact">
                                                <thead>
                                                    <tr>
                                                        <th>Location</th>
                                                        <th>Scans</th>
                                                        <th>Codes</th>
                                                        <th>Intensity</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (array_slice($geographic_data['heat_map_data'], 0, 10) as $location): ?>
                                                        <?php 
                                                        $max_scans = max(array_column($geographic_data['heat_map_data'], 'scan_count'));
                                                        $intensity = round(($location->scan_count / $max_scans) * 100);
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <div class="vqr-location-info">
                                                                    <div class="vqr-location-name"><?php echo esc_html($location->city); ?></div>
                                                                    <div class="vqr-location-region"><?php echo esc_html($location->region . ', ' . $location->country); ?></div>
                                                                </div>
                                                            </td>
                                                            <td><strong><?php echo number_format($location->scan_count); ?></strong></td>
                                                            <td><?php echo $location->unique_codes; ?></td>
                                                            <td>
                                                                <div class="vqr-intensity-bar">
                                                                    <div class="vqr-intensity-fill" style="width: <?php echo $intensity; ?>%"></div>
                                                                    <span class="vqr-intensity-text"><?php echo $intensity; ?>%</span>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Distribution Tracking -->
                    <div class="vqr-distribution-section vqr-mb-lg">
                        <div class="vqr-grid vqr-grid-cols-2 vqr-mb-md">
                            <!-- Product Travel Distance -->
                            <div class="vqr-card">
                                <div class="vqr-card-header">
                                    <h3 class="vqr-card-title">📦 Distribution Tracking</h3>
                                </div>
                                <div class="vqr-card-content">
                                    <div class="vqr-distribution-stats">
                                        <?php foreach (array_slice($geographic_data['distribution_tracking'], 0, 5) as $product): ?>
                                            <div class="vqr-distribution-item">
                                                <div class="vqr-distribution-header">
                                                    <span class="vqr-batch-code-small"><?php echo esc_html($product->batch_code); ?></span>
                                                    <span class="vqr-spread-count"><?php echo $product->unique_locations; ?> locations</span>
                                                </div>
                                                <div class="vqr-distribution-details">
                                                    <span class="vqr-scan-count"><?php echo $product->total_scans; ?> scans</span>
                                                    <span class="vqr-time-range">
                                                        <?php 
                                                        if ($product->first_scan && $product->last_scan) {
                                                            $days = round((strtotime($product->last_scan) - strtotime($product->first_scan)) / 86400);
                                                            echo $days > 0 ? $days . ' days spread' : 'Same day';
                                                        } else {
                                                            echo 'Single scan';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Market Penetration -->
                            <div class="vqr-card">
                                <div class="vqr-card-header">
                                    <h3 class="vqr-card-title">📈 Market Penetration</h3>
                                </div>
                                <div class="vqr-card-content">
                                    <div class="vqr-penetration-stats">
                                        <?php foreach (array_slice($geographic_data['market_penetration'], 0, 8) as $market): ?>
                                            <div class="vqr-penetration-item">
                                                <div class="vqr-penetration-location">
                                                    <strong><?php echo esc_html($market->region); ?></strong>
                                                    <span class="vqr-penetration-country"><?php echo esc_html($market->country); ?></span>
                                                </div>
                                                <div class="vqr-penetration-metrics">
                                                    <div class="vqr-engagement-ratio">
                                                        <span class="vqr-ratio-value"><?php echo round($market->engagement_ratio, 1); ?>x</span>
                                                        <span class="vqr-ratio-label">engagement</span>
                                                    </div>
                                                    <div class="vqr-scan-info">
                                                        <span><?php echo $market->scan_count; ?> scans</span>
                                                        <span><?php echo $market->unique_codes; ?> codes</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Country Distribution -->
                    <div class="vqr-country-section vqr-mb-lg">
                        <div class="vqr-card">
                            <div class="vqr-card-header">
                                <h3 class="vqr-card-title">🏳️ Territory Analysis</h3>
                            </div>
                            <div class="vqr-card-content">
                                <div class="vqr-country-grid">
                                    <?php foreach ($geographic_data['country_distribution'] as $country): ?>
                                        <?php 
                                        $max_country_scans = max(array_column($geographic_data['country_distribution'], 'scan_count'));
                                        $country_intensity = round(($country->scan_count / $max_country_scans) * 100);
                                        ?>
                                        <div class="vqr-country-card">
                                            <div class="vqr-country-header">
                                                <h4 class="vqr-country-name"><?php echo esc_html($country->country); ?></h4>
                                                <div class="vqr-country-intensity"><?php echo $country_intensity; ?>%</div>
                                            </div>
                                            <div class="vqr-country-stats">
                                                <div class="vqr-country-stat">
                                                    <span class="vqr-stat-number"><?php echo number_format($country->scan_count); ?></span>
                                                    <span class="vqr-stat-label">Scans</span>
                                                </div>
                                                <div class="vqr-country-stat">
                                                    <span class="vqr-stat-number"><?php echo $country->unique_codes; ?></span>
                                                    <span class="vqr-stat-label">Codes</span>
                                                </div>
                                                <div class="vqr-country-stat">
                                                    <span class="vqr-stat-number"><?php echo $country->active_days; ?></span>
                                                    <span class="vqr-stat-label">Days</span>
                                                </div>
                                            </div>
                                            <div class="vqr-country-bar">
                                                <div class="vqr-country-progress" style="width: <?php echo $country_intensity; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div style="background: #fee2e2; padding: 20px; margin: 15px 0; border-radius: 8px; border: 1px solid #fecaca;">
                    <h3>🔒 Geographic Analytics Locked</h3>
                    <p>Upgrade to Pro plan to access geographic analytics features.</p>
                    <a href="/app/billing" style="color: #2563eb; text-decoration: underline;">View Upgrade Options</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QR Codes Tab Content -->
                            <div class="vqr-geographic-feature">
                                <span class="vqr-feature-icon">🔥</span>
                                <span>Scan heat maps showing hotspot locations</span>
                            </div>
                            <div class="vqr-geographic-feature">
                                <span class="vqr-feature-icon">📦</span>
                                <span>Distribution tracking for product travel</span>
                            </div>
                            <div class="vqr-geographic-feature">
                                <span class="vqr-feature-icon">📈</span>
                                <span>Market penetration and engagement analysis</span>
                            </div>
                            <div class="vqr-geographic-feature">
                                <span class="vqr-feature-icon">🏳️</span>
                                <span>Territory coverage and expansion opportunities</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Geographic Analytics Locked -->
                <div class="vqr-geographic-locked">
                    <div class="vqr-card">
                        <div class="vqr-card-content">
                            <div class="vqr-locked-preview">
                                <div class="vqr-locked-header">
                                    <h3>🌍 Geographic Analytics</h3>
                                    <span class="vqr-pro-badge">Pro Required</span>
                                </div>
                                
                                <div class="vqr-locked-description">
                                    <p>Unlock powerful geographic insights about your QR code distribution:</p>
                                    <div class="vqr-geographic-features-grid">
                                        <div class="vqr-geographic-feature-locked">
                                            <span class="vqr-feature-icon">🔥</span>
                                            <div class="vqr-feature-content">
                                                <strong>Scan Heat Maps</strong>
                                                <span>Visualize where your products are being verified most</span>
                                            </div>
                                        </div>
                                        <div class="vqr-geographic-feature-locked">
                                            <span class="vqr-feature-icon">📦</span>
                                            <div class="vqr-feature-content">
                                                <strong>Distribution Tracking</strong>
                                                <span>Track how far your products travel from source</span>
                                            </div>
                                        </div>
                                        <div class="vqr-geographic-feature-locked">
                                            <span class="vqr-feature-icon">📈</span>
                                            <div class="vqr-feature-content">
                                                <strong>Market Penetration</strong>
                                                <span>Identify areas with highest customer engagement</span>
                                            </div>
                                        </div>
                                        <div class="vqr-geographic-feature-locked">
                                            <span class="vqr-feature-icon">🗺️</span>
                                            <div class="vqr-feature-content">
                                                <strong>Territory Analysis</strong>
                                                <span>Find coverage gaps and expansion opportunities</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="vqr-locked-preview-charts">
                                    <div class="vqr-geographic-preview-blur">
                                        <div class="vqr-mock-map">🗺️ Interactive Heat Map</div>
                                        <div class="vqr-mock-stats">
                                            <span class="vqr-mock-stat">15 Countries</span>
                                            <span class="vqr-mock-stat">142 Cities</span>
                                            <span class="vqr-mock-stat">2.3x Avg Spread</span>
                                        </div>
                                    </div>
                                    <div class="vqr-upgrade-overlay">
                                        <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary">
                                            Upgrade to Pro Plan
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QR Codes Tab Content -->
        <div class="vqr-tab-content" id="codes-tab">
            <h2>QR Codes</h2>
            <div style="background: #f0f0f0; padding: 20px; margin: 10px 0; border-radius: 5px;">
                <h3>QR Codes Tab Working!</h3>
                <p>Total QR codes: <?php echo number_format($total_codes); ?></p>
                <p>This tab content will be completed once the Geographic tab is working properly.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabBtns = document.querySelectorAll('.vqr-tab-btn');
    const tabContents = document.querySelectorAll('.vqr-tab-content');
    
    function switchTab(targetTab) {
        console.log('Switching to tab:', targetTab);
        
        // Remove active class from all tabs and content
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Add active class to target button and content
        const targetButton = document.querySelector(`[data-tab="${targetTab}"]`);
        const targetContent = document.getElementById(`${targetTab}-tab`);
        
        if (targetButton && targetContent) {
            targetButton.classList.add('active');
            targetContent.classList.add('active');
            
            // Store in localStorage
            localStorage.setItem('vqr_active_tab', targetTab);
        }
    }
    
    // Add click handlers
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            switchTab(targetTab);
        });
    });
    
    // Restore last active tab or default to geographic
    const lastActiveTab = localStorage.getItem('vqr_active_tab') || 'geographic';
    switchTab(lastActiveTab);
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Analytics';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>
            <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px;">
                <strong>Debug Info:</strong><br>
                Total QR codes: <?php echo number_format($total_codes); ?><br>
                QR codes array count: <?php echo count($qr_codes); ?><br>
                Current page: <?php echo $page; ?> of <?php echo $total_pages; ?><br>
                <strong>This is the QR Codes tab - if you can see this, the tab is working!</strong>
            </div>
            
            <!-- QR Codes Table -->
            <div class="vqr-card">
        <div class="vqr-card-header">
            <div class="vqr-card-header-content">
                <h3 class="vqr-card-title">Your QR Codes</h3>
                <div class="vqr-card-actions">
                    <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-primary">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Generate More
                    </a>
                </div>
            </div>
        </div>
        <div class="vqr-card-content">
            <?php if ($qr_codes): ?>
                <div class="vqr-table-container">
                    <table class="vqr-table">
                        <thead>
                            <tr>
                                <th>QR Code</th>
                                <th>Batch Code</th>
                                <th>Category</th>
                                <th>Strain</th>
                                <th>Scans</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qr_codes as $code): ?>
                                <?php 
                                $strain = get_post($code->post_id);
                                $strain_name = $strain ? $strain->post_title : 'Unknown';
                                ?>
                                <tr>
                                    <td>
                                        <div class="vqr-qr-preview">
                                            <img src="<?php echo esc_url($code->qr_code); ?>" 
                                                 alt="QR Code" 
                                                 class="vqr-qr-thumb"
                                                 onclick="VQR.showQRModal('<?php echo esc_url($code->qr_code); ?>', '<?php echo esc_attr($code->batch_code); ?>')">
                                        </div>
                                    </td>
                                    <td>
                                        <code class="vqr-batch-code"><?php echo esc_html($code->batch_code); ?></code>
                                    </td>
                                    <td><?php echo esc_html($code->category); ?></td>
                                    <td>
                                        <?php if ($strain): ?>
                                            <a href="<?php echo get_permalink($strain->ID); ?>" target="_blank" class="vqr-strain-link">
                                                <?php echo esc_html($strain_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="vqr-text-muted"><?php echo esc_html($strain_name); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($code->scan_count); ?></strong></td>
                                    <td>
                                        <?php if ($code->scan_count > 0): ?>
                                            <span class="vqr-badge vqr-badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="vqr-badge vqr-badge-warning">Unused</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="vqr-date" title="<?php echo esc_attr($code->created_at); ?>">
                                            <?php echo esc_html(date('M j, Y', strtotime($code->created_at))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="vqr-table-actions">
                                            <button class="vqr-btn vqr-btn-secondary vqr-btn-sm" 
                                                    onclick="VQR.copyToClipboard('<?php echo esc_js($code->url); ?>')">
                                                Copy URL
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="vqr-pagination">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $page - 1)); ?>" class="vqr-btn vqr-btn-secondary">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="vqr-pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_codes); ?> total)
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg('paged', $page + 1)); ?>" class="vqr-btn vqr-btn-secondary">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="vqr-empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-lg);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                    </svg>
                    <h3>No QR codes yet</h3>
                    <p class="vqr-text-muted">Generate your first batch of QR codes to start tracking analytics.</p>
                    <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-primary" style="margin-top: var(--space-lg);">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Generate QR Codes
                    </a>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div id="vqr-qr-modal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-overlay" onclick="VQR.closeQRModal()"></div>
    <div class="vqr-modal-content">
        <div class="vqr-modal-header">
            <h3 id="vqr-modal-title">QR Code</h3>
            <button class="vqr-modal-close" onclick="VQR.closeQRModal()">×</button>
        </div>
        <div class="vqr-modal-body">
            <img id="vqr-modal-image" src="" alt="QR Code" style="width: 100%; max-width: 300px;">
        </div>
        <div class="vqr-modal-footer">
            <button class="vqr-btn vqr-btn-secondary" onclick="VQR.downloadQR()">Download</button>
            <button class="vqr-btn vqr-btn-primary" onclick="VQR.closeQRModal()">Close</button>
        </div>
    </div>
</div>

<!-- Alert Details Modal -->
<div id="vqr-alert-modal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-overlay" onclick="VQR.closeAlertModal()"></div>
    <div class="vqr-modal-content vqr-alert-modal-content">
        <div class="vqr-modal-header">
            <h3 id="vqr-alert-modal-title">Security Alert Details</h3>
            <button class="vqr-modal-close" onclick="VQR.closeAlertModal()">×</button>
        </div>
        <div class="vqr-modal-body">
            <div id="vqr-alert-modal-body">
                <!-- Alert details will be populated here -->
            </div>
        </div>
        <div class="vqr-modal-footer">
            <button class="vqr-btn vqr-btn-primary" onclick="VQR.closeAlertModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* Analytics Page Styles */
.vqr-analytics-page {
    max-width: 1200px;
}

.vqr-card-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vqr-card-actions {
    display: flex;
    gap: var(--space-sm);
}

.vqr-qr-thumb {
    width: 40px;
    height: 40px;
    cursor: pointer;
    border-radius: var(--radius-sm);
    transition: transform 0.2s ease;
}

.vqr-qr-thumb:hover {
    transform: scale(1.1);
}

.vqr-batch-code {
    background: var(--surface);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-family: monospace;
    font-size: var(--font-size-sm);
}

.vqr-strain-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.vqr-strain-link:hover {
    text-decoration: underline;
}

.vqr-table-actions {
    display: flex;
    gap: var(--space-xs);
}

.vqr-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: var(--space-lg);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

.vqr-pagination-info {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

/* Modal Styles */
.vqr-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-lg);
}

.vqr-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.vqr-modal-content {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-lg);
    position: relative;
    max-width: 400px;
    width: 100%;
}

.vqr-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-lg);
    border-bottom: 1px solid var(--border);
}

.vqr-modal-header h3 {
    margin: 0;
}

.vqr-modal-close {
    background: none;
    border: none;
    font-size: var(--font-size-xl);
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vqr-modal-body {
    padding: var(--space-lg);
    text-align: center;
}

.vqr-modal-footer {
    display: flex;
    gap: var(--space-sm);
    padding: var(--space-lg);
    border-top: 1px solid var(--border);
    justify-content: flex-end;
}

/* Advanced Analytics Styles */
.vqr-pro-badge {
    background: linear-gradient(135deg, var(--primary), #8B5CF6);
    color: white;
    font-size: var(--font-size-xs);
    font-weight: 600;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-full);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vqr-chart-placeholder {
    text-align: center;
}

.vqr-chart-label {
    margin-top: var(--space-sm);
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-geo-stats {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-geo-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm);
    background: var(--background);
    border-radius: var(--radius-sm);
}

.vqr-geo-location {
    font-weight: 500;
    color: var(--text-primary);
}

.vqr-geo-count {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-device-stats {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.vqr-device-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.vqr-device-type {
    min-width: 60px;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
}

.vqr-device-bar {
    flex: 1;
    height: 6px;
    background: var(--border-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.vqr-device-progress {
    height: 100%;
    background: var(--primary);
}

.vqr-device-percent {
    min-width: 35px;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    text-align: right;
}

.vqr-time-stats {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
}

.vqr-time-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm);
    background: var(--background);
    border-radius: var(--radius-sm);
}

.vqr-time-hour {
    font-weight: 500;
    color: var(--text-primary);
}

.vqr-time-scans {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-conversion-stats {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.vqr-conversion-metric {
    text-align: center;
    padding: var(--space-md);
    background: var(--background);
    border-radius: var(--radius-md);
}

.vqr-conversion-value {
    display: block;
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--primary);
    margin-bottom: var(--space-xs);
}

.vqr-conversion-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

/* Security Analytics Styles */
.vqr-security-badge {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: white;
    font-size: var(--font-size-xs);
    font-weight: 600;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-full);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vqr-starter-badge {
    background: linear-gradient(135deg, #10B981, #059669);
    color: white;
    font-size: var(--font-size-xs);
    font-weight: 600;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-full);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Minimal Security Summary */
.vqr-security-summary-minimal {
    display: flex;
    gap: var(--space-md);
    padding: var(--space-sm) var(--space-md);
    background: var(--surface);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    font-size: var(--font-size-sm);
}

.vqr-stat-minimal {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-xs);
}

.vqr-stat-critical {
    color: #DC2626;
    background: rgba(220, 38, 38, 0.08);
}

.vqr-stat-high {
    color: #EA580C;
    background: rgba(234, 88, 12, 0.08);
}

.vqr-stat-medium {
    color: #D97706;
    background: rgba(217, 119, 6, 0.08);
}

.vqr-stat-low {
    color: #059669;
    background: rgba(5, 150, 105, 0.08);
}

.vqr-stat-number {
    font-weight: 700;
    font-size: var(--font-size-md);
    min-width: 16px;
    text-align: center;
}

.vqr-stat-label {
    font-size: var(--font-size-xs);
    font-weight: 500;
    opacity: 0.9;
}

.vqr-section-title {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 var(--space-md) 0;
}

/* Alerts Table Styles */
.vqr-alerts-table {
    font-size: var(--font-size-sm);
}

.vqr-alerts-table th {
    font-weight: 600;
    color: var(--text-primary);
    font-size: var(--font-size-xs);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vqr-alerts-table td {
    vertical-align: middle;
    padding: var(--space-sm);
}

.vqr-alert-row-critical {
    border-left: 3px solid #DC2626;
    background: rgba(220, 38, 38, 0.02);
}

.vqr-alert-row-high {
    border-left: 3px solid #EA580C;
    background: rgba(234, 88, 12, 0.02);
}

.vqr-alert-row-medium {
    border-left: 3px solid #D97706;
    background: rgba(217, 119, 6, 0.02);
}

.vqr-alert-row-low {
    border-left: 3px solid #059669;
    background: rgba(5, 150, 105, 0.02);
}

.vqr-severity-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.vqr-severity-critical {
    background: rgba(220, 38, 38, 0.1);
    color: #DC2626;
}

.vqr-severity-high {
    background: rgba(234, 88, 12, 0.1);
    color: #EA580C;
}

.vqr-severity-medium {
    background: rgba(217, 119, 6, 0.1);
    color: #D97706;
}

.vqr-severity-low {
    background: rgba(5, 150, 105, 0.1);
    color: #059669;
}

.vqr-batch-code-small {
    background: var(--background);
    padding: var(--space-xs);
    border-radius: var(--radius-sm);
    font-family: monospace;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--primary);
}

.vqr-product-name {
    font-weight: 500;
    color: var(--text-primary);
    max-width: 150px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vqr-location-compact {
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-score-badge {
    display: inline-block;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: var(--font-size-xs);
    text-align: center;
    min-width: 40px;
}

.vqr-score-critical {
    background: #DC2626;
    color: white;
}

.vqr-score-high {
    background: #EA580C;
    color: white;
}

.vqr-score-medium {
    background: #D97706;
    color: white;
}

.vqr-score-low {
    background: #059669;
    color: white;
}

.vqr-time-compact {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    white-space: nowrap;
}

.vqr-btn-xs {
    padding: var(--space-xs) var(--space-sm);
    font-size: var(--font-size-xs);
    border-radius: var(--radius-sm);
}

.vqr-alerts-pagination {
    margin-top: var(--space-md);
    padding-top: var(--space-md);
    border-top: 1px solid var(--border);
}

.vqr-empty-alerts {
    text-align: center;
    padding: var(--space-xl);
    color: var(--text-muted);
    font-style: italic;
}

/* Compact Geographic Table */
.vqr-geo-table {
    font-size: var(--font-size-sm);
}

.vqr-geo-table th {
    font-weight: 600;
    color: var(--text-primary);
    font-size: var(--font-size-xs);
    text-transform: uppercase;
}

.vqr-geo-row-low {
    border-left: 3px solid #059669;
    background: rgba(5, 150, 105, 0.02);
}

.vqr-geo-row-medium {
    border-left: 3px solid #D97706;
    background: rgba(217, 119, 6, 0.02);
}

.vqr-geo-row-high {
    border-left: 3px solid #DC2626;
    background: rgba(220, 38, 38, 0.02);
}

.vqr-geo-location-compact {
    font-weight: 500;
    color: var(--text-primary);
    max-width: 180px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vqr-suspicious-count {
    color: var(--error);
    font-weight: 600;
}

.vqr-risk-badge {
    display: inline-block;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-weight: 600;
    font-size: var(--font-size-xs);
    text-align: center;
}

.vqr-risk-badge.vqr-risk-low {
    background: rgba(5, 150, 105, 0.1);
    color: #059669;
}

.vqr-risk-badge.vqr-risk-medium {
    background: rgba(217, 119, 6, 0.1);
    color: #D97706;
}

.vqr-risk-badge.vqr-risk-high {
    background: rgba(220, 38, 38, 0.1);
    color: #DC2626;
}

.vqr-security-empty {
    text-align: center;
    padding: var(--space-2xl);
}

.vqr-security-empty-icon {
    font-size: 64px;
    margin-bottom: var(--space-lg);
}

.vqr-security-empty h4 {
    font-size: var(--font-size-xl);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 var(--space-md) 0;
}

.vqr-security-empty p {
    color: var(--text-muted);
    margin-bottom: var(--space-xl);
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.vqr-security-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-md);
    max-width: 600px;
    margin: 0 auto;
}

.vqr-security-feature {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.vqr-feature-icon {
    font-size: var(--font-size-lg);
}

.vqr-security-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-xl);
}

.vqr-security-feature-locked {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.vqr-feature-content {
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.vqr-feature-content strong {
    color: var(--text-primary);
    font-weight: 600;
}

.vqr-feature-content span {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-security-preview-blur {
    background: var(--background);
    padding: var(--space-lg);
    border-radius: var(--radius-md);
    filter: blur(1px);
    opacity: 0.6;
    margin-bottom: var(--space-lg);
}

.vqr-mock-alert {
    background: rgba(220, 38, 38, 0.1);
    color: #DC2626;
    padding: var(--space-md);
    border-radius: var(--radius-sm);
    margin-bottom: var(--space-md);
    font-weight: 500;
}

.vqr-mock-stats {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
}

.vqr-mock-stat {
    background: var(--surface);
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--radius-sm);
    font-weight: 500;
    color: var(--text-primary);
}

/* Advanced Analytics Locked Styles */
.vqr-advanced-locked {
    position: relative;
}

.vqr-locked-preview {
    padding: var(--space-xl);
}

.vqr-locked-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-lg);
}

.vqr-locked-header h3 {
    margin: 0;
    font-size: var(--font-size-xl);
    color: var(--text-primary);
}

.vqr-locked-description {
    margin-bottom: var(--space-xl);
}

.vqr-locked-description p {
    color: var(--text-muted);
    margin-bottom: var(--space-md);
}

.vqr-locked-description ul {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-sm);
}

.vqr-locked-description li {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
}

.vqr-locked-preview-charts {
    position: relative;
    background: var(--background);
    border-radius: var(--radius-md);
    padding: var(--space-lg);
    text-align: center;
}

.vqr-chart-blur {
    margin-bottom: var(--space-lg);
}

.vqr-upgrade-overlay {
    position: relative;
}

/* Alert Details Modal Styles - Minimal */
.vqr-alert-modal-content {
    max-width: 420px;
    width: 95%;
}

.vqr-alert-summary-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--border);
    font-size: var(--font-size-sm);
}

.vqr-alert-summary-line:last-child {
    border-bottom: none;
}

.vqr-alert-summary-label {
    color: var(--text-muted);
    font-weight: 500;
}

.vqr-alert-summary-value {
    color: var(--text-primary);
    font-weight: 600;
}

.vqr-alert-description-minimal {
    background: var(--background);
    padding: var(--space-md);
    border-radius: var(--radius-sm);
    margin: var(--space-md) 0;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    line-height: 1.4;
}

.vqr-alert-flags-minimal {
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
}

.vqr-alert-flags-minimal h5 {
    margin: 0 0 var(--space-sm) 0;
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--text-primary);
}

.vqr-alert-flag-minimal {
    padding: var(--space-xs) 0;
    border-bottom: 1px solid var(--border-light);
    font-size: var(--font-size-xs);
}

.vqr-alert-flag-minimal:last-child {
    border-bottom: none;
}

.vqr-alert-flag-minimal strong {
    color: var(--error);
    font-weight: 600;
}

@media (max-width: 640px) {
    .vqr-security-summary-minimal {
        flex-wrap: wrap;
        gap: var(--space-sm);
        justify-content: center;
    }
    
    .vqr-stat-minimal {
        flex: 1;
        min-width: 80px;
        justify-content: center;
    }
}

@media (max-width: 768px) {
    .vqr-table-container {
        overflow-x: auto;
    }
    
    .vqr-table th,
    .vqr-table td {
        min-width: 120px;
    }
    
    .vqr-card-header-content {
        flex-direction: column;
        gap: var(--space-md);
        align-items: flex-start;
    }
    
    .vqr-locked-description ul {
        grid-template-columns: 1fr;
    }
}

/* Tab System Styles */
.vqr-analytics-tabs {
    width: 100%;
}

.vqr-tab-nav {
    display: flex;
    background: var(--surface);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    border: 1px solid var(--border);
    border-bottom: none;
    overflow-x: auto;
    overflow-y: hidden;
}

.vqr-tab-btn {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-md) var(--space-lg);
    background: none;
    border: none;
    color: var(--text-muted);
    font-weight: 500;
    font-size: var(--font-size-sm);
    cursor: pointer;
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.vqr-tab-btn:hover {
    color: var(--text-primary);
    background: var(--background);
}

.vqr-tab-btn.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: var(--white);
}

.vqr-tab-icon {
    width: 16px;
    height: 16px;
}

.vqr-tab-content {
    display: none;
    background: var(--white);
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 var(--radius-lg) var(--radius-lg);
    padding: var(--space-xl);
    min-height: 400px;
}

.vqr-tab-content.active {
    display: block;
}

/* Overview Tab Styles */
.vqr-overview-summary {
    margin-top: var(--space-lg);
}

.vqr-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: var(--space-lg);
}

.vqr-summary-item {
    text-align: center;
    padding: var(--space-md);
    background: var(--background);
    border-radius: var(--radius-md);
}

.vqr-summary-metric {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--primary);
    margin-bottom: var(--space-xs);
}

.vqr-summary-label {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

/* Geographic Analytics Styles */
.vqr-world-map-placeholder {
    margin-bottom: var(--space-lg);
}

.vqr-world-svg {
    width: 100%;
    height: 300px;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}

.vqr-map-placeholder-text {
    font-size: 18px;
    font-weight: 600;
    fill: var(--text-primary);
}

.vqr-map-subtitle {
    font-size: 14px;
    fill: var(--text-muted);
}

.vqr-heat-point {
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.vqr-heat-point:hover {
    opacity: 1 !important;
}

.vqr-top-locations {
    margin-top: var(--space-lg);
}

.vqr-table-compact th,
.vqr-table-compact td {
    padding: var(--space-sm);
    font-size: var(--font-size-sm);
}

.vqr-location-info {
    text-align: left;
}

.vqr-location-name {
    font-weight: 500;
    color: var(--text-primary);
}

.vqr-location-region {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-intensity-bar {
    position: relative;
    background: var(--border-light);
    border-radius: var(--radius-sm);
    height: 20px;
    min-width: 80px;
    overflow: hidden;
}

.vqr-intensity-fill {
    background: linear-gradient(90deg, var(--primary), var(--success));
    height: 100%;
    transition: width 0.3s ease;
}

.vqr-intensity-text {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--white);
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
}

.vqr-distribution-item {
    margin-bottom: var(--space-md);
    padding: var(--space-md);
    background: var(--background);
    border-radius: var(--radius-md);
    border-left: 3px solid var(--primary);
}

.vqr-distribution-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xs);
}

.vqr-spread-count {
    font-weight: 600;
    color: var(--primary);
    font-size: var(--font-size-sm);
}

.vqr-distribution-details {
    display: flex;
    gap: var(--space-md);
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-penetration-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm) 0;
    border-bottom: 1px solid var(--border-light);
}

.vqr-penetration-item:last-child {
    border-bottom: none;
}

.vqr-penetration-location strong {
    color: var(--text-primary);
    display: block;
}

.vqr-penetration-country {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-penetration-metrics {
    text-align: right;
}

.vqr-engagement-ratio {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    margin-bottom: var(--space-xs);
}

.vqr-ratio-value {
    font-weight: 700;
    color: var(--primary);
}

.vqr-ratio-label {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-scan-info {
    display: flex;
    gap: var(--space-sm);
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-country-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md);
}

.vqr-country-card {
    background: var(--background);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.vqr-country-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-md);
}

.vqr-country-name {
    margin: 0;
    font-size: var(--font-size-md);
    font-weight: 600;
    color: var(--text-primary);
}

.vqr-country-intensity {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--primary);
}

.vqr-country-stats {
    display: flex;
    justify-content: space-between;
    margin-bottom: var(--space-md);
}

.vqr-country-stat {
    text-align: center;
}

.vqr-country-stat .vqr-stat-number {
    display: block;
    font-weight: 700;
    color: var(--text-primary);
}

.vqr-country-stat .vqr-stat-label {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
}

.vqr-country-bar {
    height: 4px;
    background: var(--border-light);
    border-radius: var(--radius-sm);
    overflow: hidden;
}

.vqr-country-progress {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--success));
    transition: width 0.3s ease;
}

.vqr-geographic-empty {
    text-align: center;
    padding: var(--space-2xl);
}

.vqr-geographic-empty-icon {
    font-size: 64px;
    margin-bottom: var(--space-lg);
}

.vqr-geographic-empty h4 {
    font-size: var(--font-size-xl);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 var(--space-md) 0;
}

.vqr-geographic-empty p {
    color: var(--text-muted);
    margin-bottom: var(--space-xl);
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.vqr-geographic-features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-md);
    max-width: 600px;
    margin: 0 auto;
}

.vqr-geographic-feature {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.vqr-geographic-features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-md);
    margin-bottom: var(--space-xl);
}

.vqr-geographic-feature-locked {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}

.vqr-geographic-preview-blur {
    background: var(--background);
    padding: var(--space-lg);
    border-radius: var(--radius-md);
    filter: blur(1px);
    opacity: 0.6;
    margin-bottom: var(--space-lg);
    text-align: center;
}

.vqr-mock-map {
    background: rgba(34, 197, 94, 0.1);
    color: #059669;
    padding: var(--space-lg);
    border-radius: var(--radius-sm);
    margin-bottom: var(--space-md);
    font-weight: 500;
    font-size: var(--font-size-lg);
}

@media (max-width: 640px) {
    .vqr-tab-nav {
        border-radius: 0;
    }
    
    .vqr-tab-btn {
        padding: var(--space-sm) var(--space-md);
        font-size: var(--font-size-xs);
    }
    
    .vqr-tab-content {
        border-radius: 0;
        padding: var(--space-lg);
    }
    
    .vqr-summary-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-md);
    }
    
    .vqr-country-grid {
        grid-template-columns: 1fr;
    }
    
    .vqr-world-svg {
        height: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Extend VQR object with modal functions
    window.VQR = window.VQR || {};
    
    // Tab switching functionality
    const tabBtns = document.querySelectorAll('.vqr-tab-btn');
    const tabContents = document.querySelectorAll('.vqr-tab-content');
    
    console.log('Found tab buttons:', tabBtns.length);
    console.log('Found tab contents:', tabContents.length);
    console.log('Tab button data-tab values:', Array.from(tabBtns).map(btn => btn.getAttribute('data-tab')));
    console.log('Tab content IDs:', Array.from(tabContents).map(content => content.id));
    
    function switchTab(targetTab) {
        console.log('Switching to tab:', targetTab);
        
        // Remove active class from all tabs and content
        tabBtns.forEach(btn => btn.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding content
        const targetBtn = document.querySelector(`[data-tab="${targetTab}"]`);
        const targetContent = document.getElementById(`${targetTab}-tab`);
        
        console.log('Target button found:', !!targetBtn);
        console.log('Target content found:', !!targetContent);
        
        if (targetBtn) targetBtn.classList.add('active');
        if (targetContent) {
            targetContent.classList.add('active');
            console.log('Activated tab content:', targetContent.id);
        }
        
        // Save tab preference
        localStorage.setItem('vqr_analytics_tab', targetTab);
    }
    
    // Add click event listeners to tabs
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            switchTab(targetTab);
        });
    });
    
    // Restore saved tab or default to overview
    const savedTab = localStorage.getItem('vqr_analytics_tab');
    const defaultTab = savedTab && document.getElementById(`${savedTab}-tab`) ? savedTab : 'overview';
    switchTab(defaultTab);
    
    // QR Modal functions
    VQR.showQRModal = function(imageSrc, batchCode) {
        const modal = document.getElementById('vqr-qr-modal');
        const title = document.getElementById('vqr-modal-title');
        const image = document.getElementById('vqr-modal-image');
        
        if (modal && title && image) {
            title.textContent = 'QR Code: ' + batchCode;
            image.src = imageSrc;
            modal.style.display = 'flex';
            
            // Store for download
            VQR.currentQRImage = imageSrc;
            VQR.currentBatchCode = batchCode;
        }
    };

    VQR.closeQRModal = function() {
        const modal = document.getElementById('vqr-qr-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    VQR.downloadQR = function() {
        if (VQR.currentQRImage && VQR.currentBatchCode) {
            const link = document.createElement('a');
            link.href = VQR.currentQRImage;
            link.download = 'qr-code-' + VQR.currentBatchCode + '.png';
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    };

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            VQR.closeQRModal();
            VQR.closeAlertModal();
        }
    });
    
    // Alert details modal functions - Minimal
    VQR.showAlertDetails = function(alertData) {
        const modal = document.getElementById('vqr-alert-modal');
        const modalBody = document.getElementById('vqr-alert-modal-body');
        
        if (!modal || !modalBody) return;
        
        // Generate alert type descriptions (shorter)
        const alertDescriptions = {
            'counterfeit_suspected': 'QR code scanned in multiple distant locations within impossible timeframe.',
            'bot_activity': 'Rapid automated scanning detected from same IP address.',
            'duplication_suspected': 'Repeated scanning in same location may indicate unauthorized copies.',
            'general_suspicious': 'Scanning patterns don\'t match normal user behavior.',
            'geographic_anomaly': 'QR code appeared in unexpected or distant locations.',
            'scanning_anomaly': 'Unusual scanning frequency or timing detected.'
        };
        
        const description = alertDescriptions[alertData.type] || 'Suspicious activity detected.';
        
        // Format the time (shorter) - convert from GMT to local
        const alertTime = new Date(alertData.time + ' UTC');
        const formattedTime = alertTime.toLocaleDateString() + ' ' + alertTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        
        // Build minimal modal content
        let modalContent = `
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Type:</span>
                <span class="vqr-alert-summary-value">${alertData.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Severity:</span>
                <span class="vqr-alert-summary-value">${alertData.severity.toUpperCase()}</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Risk Score:</span>
                <span class="vqr-alert-summary-value">${alertData.score}/100</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Product:</span>
                <span class="vqr-alert-summary-value">${alertData.product}</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Batch:</span>
                <span class="vqr-alert-summary-value">${alertData.batch}</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Location:</span>
                <span class="vqr-alert-summary-value">${alertData.location}</span>
            </div>
            <div class="vqr-alert-summary-line">
                <span class="vqr-alert-summary-label">Time:</span>
                <span class="vqr-alert-summary-value">${formattedTime}</span>
            </div>
            
            <div class="vqr-alert-description-minimal">
                ${description}
            </div>
        `;
        
        // Add minimal flags section if there are any
        if (alertData.flags && alertData.flags.length > 0) {
            modalContent += `
                <div class="vqr-alert-flags-minimal">
                    <h5>Security Details:</h5>
            `;
            
            alertData.flags.forEach(flag => {
                modalContent += `
                    <div class="vqr-alert-flag-minimal">
                        <strong>${flag.type ? flag.type.replace(/_/g, ' ') : 'Issue'}:</strong> ${flag.message || 'Security anomaly detected'}
                    </div>
                `;
            });
            
            modalContent += '</div>';
        }
        
        modalBody.innerHTML = modalContent;
        modal.style.display = 'flex';
    };

    VQR.closeAlertModal = function() {
        const modal = document.getElementById('vqr-alert-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    };
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Analytics';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>