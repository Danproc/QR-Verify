<?php
/**
 * Analytics page for Verify 420 SaaS - CLEAN VERSION
 */

defined('ABSPATH') || exit;

// Check if user can access analytics
if (!vqr_user_can_access_analytics()) {
    $upgrade_info = vqr_get_upgrade_info('analytics');
    ob_start();
    ?>
    <div style="display: flex; align-items: center; justify-content: center; min-height: 400px; padding: 20px;">
        <div style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; text-align: center; max-width: 400px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM9 6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9V6z"/>
                </svg>
            </div>
            <h3 style="color: #1f2937; margin: 0 0 12px 0; font-size: 20px; font-weight: 600;"><?php echo esc_html($upgrade_info['title']); ?></h3>
            <p style="color: #6b7280; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5;"><?php echo esc_html($upgrade_info['message']); ?></p>
            <a href="<?php echo home_url('/app/billing'); ?>" style="display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; transition: transform 0.2s;">
                Upgrade to <?php echo ucfirst($upgrade_info['upgrade_plan']); ?> Plan
            </a>
        </div>
    </div>
    <?php
    $page_content = ob_get_clean();
    $page_title = 'Analytics';
    include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
    return;
}

// Get current user ID
$user_id = get_current_user_id();

// Get strain filter
$selected_strain_id = intval($_GET['strain_filter'] ?? 0);

// Get user's strains for the filter dropdown
$user_strains = vqr_get_user_strains($user_id);

// Build strain filter condition for SQL queries
$strain_filter_sql = '';
$strain_filter_params = [$user_id];
if ($selected_strain_id > 0) {
    $strain_filter_sql = ' AND post_id = %d';
    $strain_filter_params[] = $selected_strain_id;
}

// Get basic QR code data for overview (with strain filtering)
global $wpdb;
$qr_table = $wpdb->prefix . 'vqr_codes';

// Build filtered queries
$base_where = "WHERE user_id = %d" . $strain_filter_sql;

$total_codes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$qr_table} {$base_where}", ...$strain_filter_params
));

$total_scans = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(scan_count) FROM {$qr_table} {$base_where}", ...$strain_filter_params
)) ?: 0;

// Get additional overview metrics
$scanned_codes = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$qr_table} {$base_where} AND scan_count > 0", ...$strain_filter_params
)) ?: 0;

$recent_scans = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(scan_count) FROM {$qr_table} 
     {$base_where} AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)", ...$strain_filter_params
)) ?: 0;

$avg_scans_per_code = $total_codes > 0 ? round($total_scans / $total_codes, 1) : 0;

$most_scanned = $wpdb->get_row($wpdb->prepare(
    "SELECT qr_key, batch_code, scan_count FROM {$qr_table} 
     {$base_where} AND scan_count > 0 
     ORDER BY scan_count DESC LIMIT 1", ...$strain_filter_params
));

// Get current month usage
$current_usage = vqr_get_user_usage($user_id);
$monthly_quota = vqr_get_user_quota($user_id);
$quota_percentage = $monthly_quota === -1 ? 0 : ($monthly_quota > 0 ? round(($current_usage / $monthly_quota) * 100, 1) : 0);

// Get security analytics data if user has access (with strain filtering)
$security_data = null;
if (vqr_user_can_access_security_analytics()) {
    $security_data = vqr_get_security_dashboard_data($user_id, 30, $selected_strain_id);
}

// Get geographic analytics data if user has access (with strain filtering)
$geographic_data = null;
if (vqr_user_can_access_geographic_analytics()) {
    $geographic_data = vqr_get_geographic_analytics_data($user_id, 30, $selected_strain_id);
    
    // Debug logging
    error_log("VQR Analytics Debug: Geographic data retrieved");
    error_log("VQR Analytics Debug: Geographic data is " . (is_array($geographic_data) ? 'array' : gettype($geographic_data)));
    if (is_array($geographic_data)) {
        error_log("VQR Analytics Debug: Geographic data keys: " . implode(', ', array_keys($geographic_data)));
        if (isset($geographic_data['heat_map_data'])) {
            error_log("VQR Analytics Debug: Heat map data count: " . count($geographic_data['heat_map_data']));
        }
    }
}

// Get QR codes for table (with strain filtering)
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

$qr_codes = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$qr_table} 
     {$base_where} 
     ORDER BY created_at DESC 
     LIMIT %d OFFSET %d",
    ...array_merge($strain_filter_params, [$per_page, $offset])
));

$total_pages = ceil($total_codes / $per_page);

// Start page content
ob_start();
?>

<div class="vqr-analytics-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">QR Code Analytics</h1>
        <p class="vqr-page-description">Monitor your QR code performance and usage.</p>
    </div>
    
    <!-- Strain Filter -->
    <?php if (!empty($user_strains)): ?>
    <div style="background: white; border-radius: 6px; border: 1px solid #e5e7eb; padding: 16px; margin: 16px 0;">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <label for="strain-filter" style="font-weight: 500; color: #374151; font-size: 14px; white-space: nowrap;">
                Filter by Product:
            </label>
            <select id="strain-filter" name="strain_filter" style="border: 1px solid #d1d5db; border-radius: 6px; padding: 8px 12px; font-size: 14px; background: white; color: #374151; min-width: 200px;">
                <option value="0" <?php echo $selected_strain_id == 0 ? 'selected' : ''; ?>>All Products</option>
                <?php foreach ($user_strains as $strain): ?>
                    <option value="<?php echo $strain->ID; ?>" <?php echo $selected_strain_id == $strain->ID ? 'selected' : ''; ?>>
                        <?php echo esc_html($strain->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($selected_strain_id > 0): ?>
                <a href="<?php echo remove_query_arg('strain_filter'); ?>" style="color: #6b7280; text-decoration: none; font-size: 12px; padding: 6px 10px; background: #f3f4f6; border-radius: 4px; transition: all 0.2s;">
                    Clear Filter
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Analytics Tabs -->
    <div class="vqr-analytics-tabs">
        <div class="vqr-tab-nav">
            <button class="vqr-tab-btn active" data-tab="overview">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                Overview
            </button>
            
            <button class="vqr-tab-btn" data-tab="codes">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
                QR Codes
            </button>
            
            <button class="vqr-tab-btn" data-tab="security">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Security
            </button>
            
            <button class="vqr-tab-btn" data-tab="geographic">
                <svg class="vqr-tab-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Geographic
            </button>
        </div>
        
        <!-- Overview Tab Content -->
        <div class="vqr-tab-content active" id="overview-tab">
            
            <!-- Key Metrics Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin: 16px 0;">
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: #059669;"><?php echo number_format($total_codes); ?></div>
                    <div style="color: #6b7280; font-size: 12px;">Total QR Codes</div>
                </div>
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: #059669;"><?php echo number_format($total_scans); ?></div>
                    <div style="color: #6b7280; font-size: 12px;">Total Scans</div>
                </div>
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: #dc2626;"><?php echo number_format($scanned_codes); ?></div>
                    <div style="color: #6b7280; font-size: 12px;">Active Codes</div>
                </div>
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                    <div style="font-size: 24px; font-weight: 600; color: #7c3aed;"><?php echo $avg_scans_per_code; ?></div>
                    <div style="color: #6b7280; font-size: 12px;">Avg Scans/Code</div>
                </div>
            </div>

            <!-- Quota Status -->
            <div style="background: white; padding: 16px; margin: 16px 0; border-radius: 6px; border: 1px solid #e5e7eb;">
                <h3 style="margin: 0 0 12px 0; color: #1f2937; font-size: 16px;">Monthly Usage</h3>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-weight: 500; font-size: 14px;">Generation Quota</span>
                    <span style="color: #6b7280; font-size: 14px;">
                        <?php echo number_format($current_usage); ?> / 
                        <?php echo $monthly_quota === -1 ? 'Unlimited' : number_format($monthly_quota); ?>
                    </span>
                </div>
                <?php if ($monthly_quota !== -1): ?>
                <div style="background: #f3f4f6; height: 6px; border-radius: 3px; overflow: hidden;">
                    <div style="background: <?php echo $quota_percentage > 80 ? '#dc2626' : ($quota_percentage > 60 ? '#d97706' : '#059669'); ?>; height: 100%; width: <?php echo min($quota_percentage, 100); ?>%; transition: width 0.3s;"></div>
                </div>
                <div style="text-align: right; margin-top: 4px; font-size: 11px; color: #6b7280;">
                    <?php echo $quota_percentage; ?>% used
                </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity & Top Performer -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 16px 0;">
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <h3 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px;">Recent Activity</h3>
                    <div style="font-size: 20px; font-weight: 600; color: #059669; margin-bottom: 4px;">
                        <?php echo number_format($recent_scans); ?>
                    </div>
                    <div style="color: #6b7280; font-size: 12px;">Scans in last 30 days</div>
                </div>
                
                <div style="background: white; padding: 16px; border-radius: 6px; border: 1px solid #e5e7eb;">
                    <h3 style="margin: 0 0 8px 0; color: #1f2937; font-size: 14px;">Most Scanned</h3>
                    <?php if ($most_scanned): ?>
                        <div style="font-family: monospace; font-weight: 600; color: #059669; margin-bottom: 4px; font-size: 14px;">
                            <?php echo esc_html($most_scanned->batch_code); ?>
                        </div>
                        <div style="color: #6b7280; font-size: 12px;">
                            <?php echo number_format($most_scanned->scan_count); ?> scans
                        </div>
                    <?php else: ?>
                        <div style="color: #6b7280; font-style: italic; font-size: 12px;">No scans yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Security Tab Content -->
        <div class="vqr-tab-content" id="security-tab">
        <?php if (vqr_user_can_access_security_analytics()): ?>
            
            <?php if ($security_data && !empty($security_data['recent_alerts'])): ?>
                <!-- Alert Summary -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 16px 0;">
                    <?php
                    $alert_counts = array('critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0);
                    foreach ($security_data['recent_alerts'] as $alert) {
                        $alert_counts[$alert->severity]++;
                    }
                    ?>
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                        <div style="font-size: 20px; font-weight: 600; color: #dc2626;"><?php echo $alert_counts['critical'] + $alert_counts['high']; ?></div>
                        <div style="color: #6b7280; font-size: 11px;">High Risk</div>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                        <div style="font-size: 20px; font-weight: 600; color: #d97706;"><?php echo $alert_counts['medium']; ?></div>
                        <div style="color: #6b7280; font-size: 11px;">Medium Risk</div>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                        <div style="font-size: 20px; font-weight: 600; color: #059669;"><?php echo $alert_counts['low']; ?></div>
                        <div style="color: #6b7280; font-size: 11px;">Low Risk</div>
                    </div>
                    <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                        <div style="font-size: 20px; font-weight: 600; color: #2563eb;"><?php echo count($security_data['recent_alerts']); ?></div>
                        <div style="color: #6b7280; font-size: 11px;">Total Alerts</div>
                    </div>
                </div>

                <!-- Security Alerts Table -->
                <div style="background: white; border-radius: 6px; border: 1px solid #e5e7eb; overflow: hidden; margin: 16px 0;">
                    <div style="padding: 16px; border-bottom: 1px solid #e5e7eb;">
                        <h3 style="margin: 0; color: #1f2937; font-size: 16px;">Recent Security Alerts</h3>
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f9fafb;">
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Type</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Severity</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Batch Code</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Location</th>
                                    <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Time</th>
                                    <th style="padding: 10px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Get pagination for security alerts
                                $alerts_page = max(1, intval($_GET['alerts_page'] ?? 1));
                                $alerts_per_page = 10;
                                $alerts_offset = ($alerts_page - 1) * $alerts_per_page;
                                $total_alerts = count($security_data['recent_alerts']);
                                $alerts_total_pages = ceil($total_alerts / $alerts_per_page);
                                $paginated_alerts = array_slice($security_data['recent_alerts'], $alerts_offset, $alerts_per_page);
                                
                                foreach ($paginated_alerts as $index => $alert): 
                                    $actual_index = $alerts_offset + $index; // For JavaScript modal reference
                                    $severity_colors = array(
                                        'critical' => array('bg' => '#fef2f2', 'color' => '#dc2626'),
                                        'high' => array('bg' => '#fff7ed', 'color' => '#ea580c'),
                                        'medium' => array('bg' => '#fffbeb', 'color' => '#d97706'),
                                        'low' => array('bg' => '#ecfdf5', 'color' => '#059669')
                                    );
                                    $severity_style = $severity_colors[$alert->severity] ?? array('bg' => '#f9fafb', 'color' => '#6b7280');
                                    $alert_time = get_date_from_gmt($alert->created_at);
                                    ?>
                                    <tr style="border-bottom: 1px solid #f3f4f6; background: <?php echo $severity_style['bg']; ?>;">
                                        <td style="padding: 10px; color: #374151; font-size: 13px;">
                                            <?php echo ucwords(str_replace('_', ' ', $alert->alert_type)); ?>
                                        </td>
                                        <td style="padding: 10px;">
                                            <span style="background: <?php echo $severity_style['color']; ?>15; color: <?php echo $severity_style['color']; ?>; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 500; text-transform: uppercase;">
                                                <?php echo $alert->severity; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 10px; font-family: monospace; font-weight: 600; color: <?php echo $severity_style['color']; ?>; font-size: 13px;">
                                            <?php echo esc_html($alert->batch_code); ?>
                                        </td>
                                        <td style="padding: 10px; color: #6b7280; font-size: 13px;">
                                            <?php echo esc_html($alert->location); ?>
                                        </td>
                                        <td style="padding: 10px; color: #6b7280; font-size: 12px;">
                                            <?php echo date('M j, g:i A', strtotime($alert_time)); ?>
                                        </td>
                                        <td style="padding: 10px; text-align: center;">
                                            <button onclick="showAlertDetails(<?php echo $actual_index; ?>)" style="background: <?php echo $severity_style['color']; ?>; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px; cursor: pointer;">
                                                Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Security Alerts Pagination -->
                    <?php if ($alerts_total_pages > 1): ?>
                    <div style="padding: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; align-items: center; gap: 8px;">
                        <?php 
                        $alerts_base_url = remove_query_arg(['alerts_page']);
                        if ($selected_strain_id > 0) {
                            $alerts_base_url = add_query_arg('strain_filter', $selected_strain_id, $alerts_base_url);
                        }
                        ?>
                        <?php if ($alerts_page > 1): ?>
                            <a href="<?php echo add_query_arg('alerts_page', $alerts_page - 1, $alerts_base_url); ?>" style="padding: 6px 10px; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <span style="color: #6b7280; font-size: 12px;">
                            Page <?php echo $alerts_page; ?> of <?php echo $alerts_total_pages; ?>
                        </span>
                        
                        <?php if ($alerts_page < $alerts_total_pages): ?>
                            <a href="<?php echo add_query_arg('alerts_page', $alerts_page + 1, $alerts_base_url); ?>" style="padding: 6px 10px; background: #dc2626; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="background: #f9fafb; padding: 32px; margin: 16px 0; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                    <h3 style="color: #374151; margin: 0 0 8px 0; font-size: 16px;">All Clear</h3>
                    <p style="color: #6b7280; margin: 0; font-size: 14px;">No security alerts detected in the last 30 days.</p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Security Analytics Lockout Card -->
            <div style="display: flex; align-items: center; justify-content: center; min-height: 300px; padding: 20px;">
                <div style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; text-align: center; max-width: 400px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                            <path d="M12,1L3,5V11C3,16.55 6.84,21.74 12,23C17.16,21.74 21,16.55 21,11V5L12,1M12,7C13.4,7 14.8,8.6 14.8,10V11.5C15.4,11.5 16,12.4 16,13V16C16,17.4 15.4,18 14.8,18H9.2C8.6,18 8,17.4 8,16V13C8,12.4 8.6,11.5 9.2,11.5V10C9.2,8.6 10.6,7 12,7M12,8.2C11.2,8.2 10.5,8.7 10.5,10V11.5H13.5V10C13.5,8.7 12.8,8.2 12,8.2Z"/>
                        </svg>
                    </div>
                    <h3 style="color: #1f2937; margin: 0 0 12px 0; font-size: 20px; font-weight: 600;">Security Analytics Locked</h3>
                    <p style="color: #6b7280; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5;">Upgrade to Pro plan to access advanced security analytics and threat detection.</p>
                    <a href="<?php echo home_url('/app/billing'); ?>" style="display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; transition: transform 0.2s;">
                        Upgrade to Pro Plan
                    </a>
                </div>
            </div>
        <?php endif; ?>
        </div>
        
        <!-- Geographic Tab Content -->
        <div class="vqr-tab-content" id="geographic-tab">
            
            <?php if (vqr_user_can_access_geographic_analytics()): ?>
                <?php if ($geographic_data && !empty($geographic_data['heat_map_data'])): ?>
                    
                    <!-- Summary Stats -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 16px 0;">
                        <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #059669;"><?php echo $geographic_data['summary_stats']['countries_reached']; ?></div>
                            <div style="color: #6b7280; font-size: 11px;">Countries</div>
                        </div>
                        <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #059669;"><?php echo $geographic_data['summary_stats']['total_locations']; ?></div>
                            <div style="color: #6b7280; font-size: 11px;">Locations</div>
                        </div>
                        <div style="background: white; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                            <div style="font-size: 20px; font-weight: 600; color: #dc2626;"><?php echo $geographic_data['summary_stats']['total_scans']; ?></div>
                            <div style="color: #6b7280; font-size: 11px;">Scans</div>
                        </div>
                    </div>
                    
                    <!-- Heat Map Locations -->
                    <div style="background: white; padding: 16px; margin: 16px 0; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <h3 style="margin: 0 0 12px 0; color: #1f2937; font-size: 16px;">Top Scan Locations</h3>
                        <div>
                            <?php foreach (array_slice($geographic_data['heat_map_data'], 0, 10) as $i => $location): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; <?php echo $i < 9 ? 'border-bottom: 1px solid #f3f4f6;' : ''; ?>">
                                    <div>
                                        <div style="font-weight: 500; color: #1f2937; font-size: 13px;">
                                            <?php echo esc_html($location->city . ', ' . $location->region); ?>
                                        </div>
                                        <div style="color: #6b7280; font-size: 11px; margin-top: 1px;">
                                            <?php echo esc_html($location->country); ?>
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: #059669; font-size: 13px;">
                                            <?php echo $location->scan_count; ?>
                                        </div>
                                        <div style="color: #6b7280; font-size: 10px;">
                                            <?php echo date('M j', strtotime($location->last_scan)); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div style="background: #f9fafb; padding: 32px; margin: 16px 0; border-radius: 6px; border: 1px solid #e5e7eb; text-align: center;">
                        <h3 style="color: #374151; margin: 0 0 8px 0; font-size: 16px;">Geographic Tracking Ready</h3>
                        <p style="color: #6b7280; margin: 0; font-size: 14px;">Location data will appear here once your QR codes are scanned.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Geographic Analytics Lockout Card -->
                <div style="display: flex; align-items: center; justify-content: center; min-height: 300px; padding: 20px;">
                    <div style="background: white; border-radius: 12px; border: 1px solid #e5e7eb; padding: 32px; text-align: center; max-width: 400px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);">
                        <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #059669 0%, #047857 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                            <svg width="24" height="24" fill="white" viewBox="0 0 24 24">
                                <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8C14.21,8 16,9.79 16,12C16,14.21 14.21,16 12,16C9.79,16 8,14.21 8,12C8,9.79 9.79,8 12,8Z"/>
                            </svg>
                        </div>
                        <h3 style="color: #1f2937; margin: 0 0 12px 0; font-size: 20px; font-weight: 600;">Geographic Analytics Locked</h3>
                        <p style="color: #6b7280; margin: 0 0 24px 0; font-size: 14px; line-height: 1.5;">Upgrade to Pro plan to access geographic analytics and location tracking.</p>
                        <a href="<?php echo home_url('/app/billing'); ?>" style="display: inline-block; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500; font-size: 14px; transition: transform 0.2s;">
                            Upgrade to Pro Plan
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- QR Codes Tab Content -->
        <div class="vqr-tab-content" id="codes-tab">

            <!-- QR Codes Table -->
            <div style="background: white; border-radius: 6px; border: 1px solid #e5e7eb; overflow: hidden; margin: 16px 0;">
                <div style="padding: 16px; border-bottom: 1px solid #e5e7eb;">
                    <h3 style="margin: 0; color: #1f2937; font-size: 16px;">Recent QR Codes</h3>
                </div>
                
                <?php if (!empty($qr_codes)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f9fafb;">
                                <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Batch Code</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Product</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Scans</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Created</th>
                                <th style="padding: 10px; text-align: left; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Last Scan</th>
                                <th style="padding: 10px; text-align: center; font-weight: 600; color: #374151; border-bottom: 1px solid #e5e7eb; font-size: 12px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qr_codes as $qr_code): ?>
                                <?php
                                $strain_title = 'Unknown Product';
                                if ($qr_code->post_id) {
                                    $strain = get_post($qr_code->post_id);
                                    $strain_title = $strain ? $strain->post_title : 'Deleted Product';
                                }
                                
                                $created_date = date('M j, Y', strtotime($qr_code->created_at));
                                $last_scan = (isset($qr_code->last_scanned) && $qr_code->last_scanned) ? date('M j, g:i A', strtotime($qr_code->last_scanned)) : 'Never';
                                $status = $qr_code->scan_count > 0 ? 'Active' : 'Unused';
                                $status_color = $qr_code->scan_count > 0 ? '#059669' : '#6b7280';
                                ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 10px; font-family: monospace; font-weight: 600; color: #059669; font-size: 13px;">
                                        <?php echo esc_html($qr_code->batch_code); ?>
                                    </td>
                                    <td style="padding: 10px; color: #374151; font-size: 13px;">
                                        <?php echo esc_html($strain_title); ?>
                                    </td>
                                    <td style="padding: 10px; text-align: center; font-weight: 600; color: #374151; font-size: 13px;">
                                        <?php echo number_format($qr_code->scan_count); ?>
                                    </td>
                                    <td style="padding: 10px; color: #6b7280; font-size: 12px;">
                                        <?php echo esc_html($created_date); ?>
                                    </td>
                                    <td style="padding: 10px; color: #6b7280; font-size: 12px;">
                                        <?php echo esc_html($last_scan); ?>
                                    </td>
                                    <td style="padding: 10px; text-align: center;">
                                        <span style="background: <?php echo $status_color; ?>15; color: <?php echo $status_color; ?>; padding: 2px 6px; border-radius: 10px; font-size: 11px; font-weight: 500;">
                                            <?php echo esc_html($status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="padding: 16px; border-top: 1px solid #e5e7eb; display: flex; justify-content: center; align-items: center; gap: 8px;">
                    <?php 
                    $base_url = remove_query_arg(['page']);
                    if ($selected_strain_id > 0) {
                        $base_url = add_query_arg('strain_filter', $selected_strain_id, $base_url);
                    }
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="<?php echo add_query_arg('page', $page - 1, $base_url); ?>" style="padding: 6px 10px; background: #f3f4f6; color: #374151; text-decoration: none; border-radius: 4px; font-size: 12px;">
                            ← Previous
                        </a>
                    <?php endif; ?>
                    
                    <span style="color: #6b7280; font-size: 12px;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="<?php echo add_query_arg('page', $page + 1, $base_url); ?>" style="padding: 6px 10px; background: #059669; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                            Next →
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php else: ?>
                <div style="padding: 32px; text-align: center; color: #6b7280;">
                    <h3 style="color: #374151; margin: 0 0 8px 0; font-size: 16px;">No QR Codes</h3>
                    <p style="margin: 0 0 16px 0; font-size: 14px;">Generate your first QR codes to start tracking scans and analytics.</p>
                    <a href="<?php echo home_url('/app/generate'); ?>" style="background: #059669; color: white; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; font-size: 14px;">
                        Generate QR Codes
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Security Alert Details Modal -->
<div id="alert-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="padding: 20px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: #1f2937; font-size: 18px;">Security Alert Details</h3>
            <button onclick="closeAlertDetails()" style="background: none; border: none; color: #6b7280; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">×</button>
        </div>
        <div id="alert-details-content" style="padding: 20px;">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<style>
.vqr-tab-nav {
    display: flex;
    border-bottom: 1px solid #e5e7eb;
    background: white;
    border-radius: 8px 8px 0 0;
}

.vqr-tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: none;
    border: none;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}

.vqr-tab-btn:hover {
    color: #374151;
    background: #f9fafb;
}

.vqr-tab-btn.active {
    color: #2563eb;
    border-bottom-color: #2563eb;
    background: #eff6ff;
}

.vqr-tab-icon {
    width: 16px;
    height: 16px;
}

.vqr-tab-content {
    display: none;
    background: white;
    border: 1px solid #e5e7eb;
    border-top: none;
    border-radius: 0 0 8px 8px;
    padding: 20px;
    min-height: 400px;
}

.vqr-tab-content.active {
    display: block;
}

.vqr-analytics-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 16px;
}

.vqr-page-header {
    margin-bottom: 20px;
}

.vqr-page-title {
    font-size: var(--font-size-3xl);
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 var(--space-xs) 0;
}

.vqr-page-description {
    color: #6b7280;
    font-size: 14px;
    margin: 0;
}
</style>

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
    
    // Restore last active tab or default to overview
    const lastActiveTab = localStorage.getItem('vqr_active_tab') || 'overview';
    switchTab(lastActiveTab);
    
    // Strain filter functionality
    const strainFilter = document.getElementById('strain-filter');
    if (strainFilter) {
        strainFilter.addEventListener('change', function() {
            const selectedValue = this.value;
            const currentUrl = new URL(window.location);
            
            if (selectedValue && selectedValue !== '0') {
                currentUrl.searchParams.set('strain_filter', selectedValue);
            } else {
                currentUrl.searchParams.delete('strain_filter');
            }
            
            // Remove page parameter to reset pagination
            currentUrl.searchParams.delete('page');
            currentUrl.searchParams.delete('alerts_page');
            
            window.location.href = currentUrl.toString();
        });
    }
});

// Security alert modal functions
const alertsData = <?php echo json_encode($security_data['recent_alerts'] ?? []); ?>;

function showAlertDetails(index) {
    const alert = alertsData[index];
    if (!alert) return;
    
    const modal = document.getElementById('alert-details-modal');
    const content = document.getElementById('alert-details-content');
    
    const severityColors = {
        'critical': '#dc2626',
        'high': '#ea580c', 
        'medium': '#d97706',
        'low': '#059669'
    };
    
    const color = severityColors[alert.severity] || '#6b7280';
    const alertTime = new Date(alert.created_at).toLocaleString();
    
    content.innerHTML = `
        <div style="margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="background: ${color}15; color: ${color}; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase;">
                    ${alert.severity}
                </span>
                <h4 style="margin: 0; color: #1f2937; font-size: 16px;">
                    ${alert.alert_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}
                </h4>
            </div>
            
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                <div style="font-weight: 500; color: #374151;">Batch Code:</div>
                <div style="font-family: monospace; font-weight: 600; color: #2563eb;">${alert.batch_code || alert.qr_key}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                <div style="font-weight: 500; color: #374151;">Location:</div>
                <div style="color: #6b7280;">${alert.location}</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                <div style="font-weight: 500; color: #374151;">Risk Score:</div>
                <div style="color: #6b7280;">${alert.security_score}/100</div>
            </div>
            
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 8px; margin-bottom: 12px;">
                <div style="font-weight: 500; color: #374151;">Time:</div>
                <div style="color: #6b7280;">${alertTime}</div>
            </div>
        </div>
        
        <div style="background: #f9fafb; padding: 16px; border-radius: 6px; margin-top: 16px;">
            <h5 style="margin: 0 0 8px 0; color: #374151; font-size: 14px;">Additional Information</h5>
            <p style="margin: 0; color: #6b7280; font-size: 13px;">
                This alert was generated based on suspicious scanning patterns. 
                Review your distribution channels and verify if this activity is expected.
            </p>
        </div>
    `;
    
    modal.style.display = 'flex';
}

function closeAlertDetails() {
    document.getElementById('alert-details-modal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('alert-details-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAlertDetails();
    }
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Analytics';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>