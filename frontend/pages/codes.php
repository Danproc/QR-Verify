<?php
/**
 * QR Codes Management page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get filter parameters
$batch_code_search = isset($_GET['batch_code_search']) ? sanitize_text_field($_GET['batch_code_search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$scanned_status = isset($_GET['scanned_status']) ? sanitize_text_field($_GET['scanned_status']) : '';
$order_scan = isset($_GET['order_scan']) ? sanitize_text_field($_GET['order_scan']) : '';

// Get user's QR code data with filters
global $wpdb;
$table_name = $wpdb->prefix . 'vqr_codes';

// Build WHERE clause for filters
$where_conditions = ['user_id = %d'];
$query_params = [$user_id];

if (!empty($batch_code_search)) {
    $where_conditions[] = 'batch_code LIKE %s';
    $query_params[] = '%' . $wpdb->esc_like($batch_code_search) . '%';
}

if (!empty($category_filter)) {
    $where_conditions[] = 'category = %s';
    $query_params[] = $category_filter;
}

if ($scanned_status === 'scanned') {
    $where_conditions[] = 'scan_count > 0';
} elseif ($scanned_status === 'never_scanned') {
    $where_conditions[] = 'scan_count = 0';
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Build ORDER BY clause
$order_clause = 'ORDER BY ';
if ($order_scan === 'scan_asc') {
    $order_clause .= 'qr.scan_count ASC, qr.created_at DESC';
} elseif ($order_scan === 'scan_desc') {
    $order_clause .= 'qr.scan_count DESC, qr.created_at DESC';
} elseif ($order_scan === 'unique_asc') {
    $order_clause .= 'COALESCE(ss.unique_scanners, 0) ASC, qr.created_at DESC';
} elseif ($order_scan === 'unique_desc') {
    $order_clause .= 'COALESCE(ss.unique_scanners, 0) DESC, qr.created_at DESC';
} elseif ($order_scan === 'avg_asc') {
    $order_clause .= 'avg_scans_per_user ASC, qr.created_at DESC';
} elseif ($order_scan === 'avg_desc') {
    $order_clause .= 'avg_scans_per_user DESC, qr.created_at DESC';
} else {
    $order_clause .= 'qr.created_at DESC';
}

// Get pagination
$per_page = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

// Count total filtered results
$count_query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
$total_codes = $wpdb->get_var($wpdb->prepare($count_query, $query_params));

// Get filtered results with unique scanner data
$security_scans_table = $wpdb->prefix . 'vqr_security_scans';
$main_query = "
    SELECT 
        qr.*,
        COALESCE(ss.unique_scanners, 0) as unique_scanners,
        CASE 
            WHEN COALESCE(ss.unique_scanners, 0) > 0 
            THEN ROUND(qr.scan_count / ss.unique_scanners, 1)
            ELSE 0 
        END as avg_scans_per_user
    FROM {$table_name} qr
    LEFT JOIN (
        SELECT 
            qr_key,
            COUNT(DISTINCT ip_address) as unique_scanners
        FROM {$security_scans_table}
        GROUP BY qr_key
    ) ss ON qr.qr_key = ss.qr_key
    {$where_clause} {$order_clause} 
    LIMIT %d OFFSET %d
";
$query_params[] = $per_page;
$query_params[] = $offset;
$qr_codes = $wpdb->get_results($wpdb->prepare($main_query, $query_params));

$total_pages = ceil($total_codes / $per_page);

// Get available categories for filter dropdown
$categories = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT category FROM {$table_name} WHERE user_id = %d AND category IS NOT NULL AND category != '' ORDER BY category",
    $user_id
));

// Get user subscription info for stats
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();

// Check if any filters are active
$has_active_filters = !empty($batch_code_search) || !empty($category_filter) || !empty($scanned_status) || !empty($order_scan);

// Prepare page content
ob_start();
?>

<div class="vqr-codes-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">Your QR Codes</h1>
        <p class="vqr-page-description">Manage and monitor your QR code performance.</p>
    </div>
    
    <!-- Stats Overview -->
    <div class="vqr-grid vqr-grid-cols-4 vqr-mb-lg">
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value"><?php echo number_format($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", $user_id))); ?></span>
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
                        $total_unique_users = $wpdb->get_var($wpdb->prepare("
                            SELECT COUNT(DISTINCT s.ip_address) 
                            FROM {$security_scans_table} s
                            INNER JOIN {$table_name} qr ON s.qr_key = qr.qr_key
                            WHERE qr.user_id = %d
                        ", $user_id)) ?: 0;
                        echo number_format($total_unique_users); 
                        ?>
                    </span>
                    <div class="vqr-stat-label">Total Unique Users</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters Card -->
    <div class="vqr-card vqr-mb-lg">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Filter & Search QR Codes</h3>
            <?php if ($has_active_filters): ?>
                <a href="<?php echo esc_url(remove_query_arg(['batch_code_search', 'category', 'scanned_status', 'order_scan', 'paged'])); ?>" 
                   class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                    Reset Filters
                </a>
            <?php endif; ?>
        </div>
        <div class="vqr-card-content">
            <form method="get" class="vqr-filters-form">
                <div class="vqr-filters-grid">
                    <!-- Batch Code Search -->
                    <div class="vqr-form-group">
                        <label for="batch_code_search" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Search Batch Code
                        </label>
                        <input type="text" 
                               id="batch_code_search" 
                               name="batch_code_search" 
                               class="vqr-input" 
                               value="<?php echo esc_attr($batch_code_search); ?>"
                               placeholder="Enter batch code...">
                    </div>
                    
                    <!-- Category Filter -->
                    <div class="vqr-form-group">
                        <label for="category" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            Category
                        </label>
                        <select id="category" name="category" class="vqr-input vqr-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                                    <?php echo esc_html($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Scan Status Filter -->
                    <div class="vqr-form-group">
                        <label for="scanned_status" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Scan Status
                        </label>
                        <select id="scanned_status" name="scanned_status" class="vqr-input vqr-select">
                            <option value="">All</option>
                            <option value="scanned" <?php selected($scanned_status, 'scanned'); ?>>Scanned</option>
                            <option value="never_scanned" <?php selected($scanned_status, 'never_scanned'); ?>>Never Scanned</option>
                        </select>
                    </div>
                    
                    <!-- Sort by Scan Count -->
                    <div class="vqr-form-group">
                        <label for="order_scan" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/>
                            </svg>
                            Sort by
                        </label>
                        <select id="order_scan" name="order_scan" class="vqr-input vqr-select">
                            <option value="">Default</option>
                            <option value="scan_asc" <?php selected($order_scan, 'scan_asc'); ?>>Total scans ↑</option>
                            <option value="scan_desc" <?php selected($order_scan, 'scan_desc'); ?>>Total scans ↓</option>
                            <option value="unique_asc" <?php selected($order_scan, 'unique_asc'); ?>>Unique users ↑</option>
                            <option value="unique_desc" <?php selected($order_scan, 'unique_desc'); ?>>Unique users ↓</option>
                            <option value="avg_asc" <?php selected($order_scan, 'avg_asc'); ?>>Avg/user ↑</option>
                            <option value="avg_desc" <?php selected($order_scan, 'avg_desc'); ?>>Avg/user ↓</option>
                        </select>
                    </div>
                </div>
                
                <div class="vqr-filters-actions">
                    <button type="submit" class="vqr-btn vqr-btn-primary">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                        </svg>
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- QR Codes Table -->
    <div class="vqr-card">
        <div class="vqr-card-header">
            <div class="vqr-card-header-content">
                <h3 class="vqr-card-title">
                    QR Codes
                    <?php if ($has_active_filters): ?>
                        <span class="vqr-filter-count">(<?php echo number_format($total_codes); ?> filtered)</span>
                    <?php endif; ?>
                </h3>
                <div class="vqr-card-actions">
                    <!-- Bulk Download Actions (conditionally shown) -->
                    <div id="vqr-bulk-actions" class="vqr-bulk-actions" style="display: none;">
                        <span id="vqr-selection-count" class="vqr-selection-count">0 selected</span>
                        <?php if (vqr_user_can_download_bulk_zip()): ?>
                            <button id="vqr-bulk-download-btn" class="vqr-btn vqr-btn-secondary" onclick="VQR.bulkDownload()">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Download ZIP
                            </button>
                        <?php else: ?>
                            <button class="vqr-btn vqr-btn-locked vqr-btn-sm" onclick="VQR.showBulkDownloadLocked()">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Bulk Download
                                <span class="vqr-locked-feature-badge">Starter+</span>
                            </button>
                        <?php endif; ?>
                        
                        <?php if (vqr_user_can_delete_qr_codes()): ?>
                            <button id="vqr-bulk-delete-btn" class="vqr-btn vqr-btn-danger" onclick="VQR.bulkDelete()">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                                Delete Selected
                            </button>
                        <?php else: ?>
                            <button class="vqr-btn vqr-btn-locked vqr-btn-sm" onclick="VQR.showBulkDeleteLocked()">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Delete Selected
                                <span class="vqr-locked-feature-badge">Pro+</span>
                            </button>
                        <?php endif; ?>
                        
                        <button id="vqr-bulk-sticker-btn" class="vqr-btn vqr-btn-primary vqr-btn-sm" onclick="VQR.bulkOrderStickers()">
                            <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            Order Stickers
                        </button>
                        <button class="vqr-btn vqr-btn-outline vqr-btn-sm" onclick="VQR.clearSelection()">
                            Clear Selection
                        </button>
                    </div>
                    
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
                                <th width="40">
                                    <input type="checkbox" id="vqr-select-all" class="vqr-checkbox" onchange="VQR.toggleSelectAll()">
                                </th>
                                <th>QR Code</th>
                                <th>Batch Code</th>
                                <th>Category</th>
                                <th>Strain</th>
                                <th>Total Scans</th>
                                <th title="Number of unique IP addresses that scanned this code">Unique Users</th>
                                <th title="Average scans per unique user">Avg/User</th>
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
                                        <input type="checkbox" 
                                               class="vqr-qr-checkbox" 
                                               data-qr-id="<?php echo esc_attr($code->id); ?>"
                                               data-qr-url="<?php echo esc_attr($code->qr_code); ?>"
                                               data-batch-code="<?php echo esc_attr($code->batch_code); ?>"
                                               onchange="VQR.updateSelection()">
                                    </td>
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
                                            <a href="<?php echo home_url('/app/preview/' . $strain->ID); ?>" target="_blank" class="vqr-strain-link">
                                                <?php echo esc_html($strain_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="vqr-text-muted"><?php echo esc_html($strain_name); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo esc_html($code->scan_count); ?></strong></td>
                                    <td>
                                        <?php if ($code->unique_scanners > 0): ?>
                                            <strong style="color: #059669;"><?php echo esc_html($code->unique_scanners); ?></strong>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($code->avg_scans_per_user > 0): ?>
                                            <?php
                                            // Color-code based on engagement level
                                            $avg = floatval($code->avg_scans_per_user);
                                            if ($avg >= 5) {
                                                $color = '#dc2626'; // Red for high engagement
                                                $badge = '🔥';
                                            } elseif ($avg >= 3) {
                                                $color = '#d97706'; // Orange for good engagement
                                                $badge = '⭐';
                                            } elseif ($avg >= 2) {
                                                $color = '#059669'; // Green for moderate engagement
                                                $badge = '';
                                            } else {
                                                $color = '#7c3aed'; // Purple for low engagement
                                                $badge = '';
                                            }
                                            ?>
                                            <span style="color: <?php echo $color; ?>; font-weight: 500;" title="<?php echo $avg >= 5 ? 'High repeat engagement!' : ($avg >= 3 ? 'Good repeat engagement' : 'Low repeat rate'); ?>">
                                                <?php echo $badge; ?><?php echo esc_html($code->avg_scans_per_user); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #6b7280;">-</span>
                                        <?php endif; ?>
                                    </td>
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
                                    <td>
                                        <span class="vqr-date" title="<?php echo esc_attr($code->created_at); ?>">
                                            <?php echo esc_html(date('M j, Y', strtotime($code->created_at))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="vqr-table-actions">
                                            <button class="vqr-btn vqr-btn-secondary vqr-btn-sm" 
                                                    onclick="VQR.copyToClipboard('<?php echo esc_js($code->url); ?>', <?php echo intval($code->post_id); ?>)">
                                                Copy URL
                                            </button>
                                            
                                            <button class="vqr-btn vqr-btn-outline vqr-btn-sm" 
                                                    onclick="VQR.downloadSingle('<?php echo esc_js($code->qr_code); ?>', '<?php echo esc_js($code->batch_code); ?>')"
                                                    title="Download QR code image">
                                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M7 13h10"/>
                                                </svg>
                                                Download
                                            </button>
                                            
                                            <button class="vqr-btn vqr-btn-primary vqr-btn-sm" 
                                                    onclick="VQR.orderSingleSticker(<?php echo esc_js($code->id); ?>, '<?php echo esc_js($code->batch_code); ?>')"
                                                    title="Order stickers for this QR code">
                                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                                                </svg>
                                                Stickers
                                            </button>
                                            
                                            <?php if (vqr_user_can_reset_qr_codes()): ?>
                                                <?php if ($code->scan_count > 0): ?>
                                                    <button class="vqr-btn vqr-btn-warning vqr-btn-sm" 
                                                            onclick="VQR.confirmResetQR(<?php echo esc_js($code->id); ?>, '<?php echo esc_js($code->batch_code); ?>', <?php echo esc_js($code->scan_count); ?>)"
                                                            title="Reset scan count to 0">
                                                        Reset Scans
                                                    </button>
                                                <?php else: ?>
                                                    <button class="vqr-btn vqr-btn-outline vqr-btn-sm" 
                                                            disabled
                                                            title="No scans to reset">
                                                        Reset Scans
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="vqr-btn vqr-btn-locked vqr-btn-sm" 
                                                        onclick="VQR.showResetLocked()"
                                                        title="Upgrade to Pro to reset scan counts">
                                                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                    </svg>
                                                    Reset
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if (vqr_user_can_delete_qr_codes()): ?>
                                                <button class="vqr-btn vqr-btn-danger vqr-btn-sm" 
                                                        onclick="VQR.confirmDeleteQR(<?php echo esc_js($code->id); ?>, '<?php echo esc_js($code->batch_code); ?>')"
                                                        title="Delete this QR code permanently">
                                                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            <?php else: ?>
                                                <button class="vqr-btn vqr-btn-locked vqr-btn-sm" 
                                                        onclick="VQR.showDeleteLocked()"
                                                        title="Upgrade to Pro to delete QR codes">
                                                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Mobile scroll hint -->
                <div class="vqr-scroll-hint">
                    <svg style="width: 16px; height: 16px; display: inline-block; vertical-align: middle; margin-right: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"/>
                    </svg>
                    Swipe left or right to view all columns
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="vqr-pagination">
                        <?php 
                        $pagination_args = array_filter([
                            'batch_code_search' => $batch_code_search,
                            'category' => $category_filter,
                            'scanned_status' => $scanned_status,
                            'order_scan' => $order_scan
                        ]);
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['paged' => $page - 1]))); ?>" 
                               class="vqr-btn vqr-btn-secondary">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <span class="vqr-pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> 
                            (<?php echo number_format($total_codes); ?> total)
                        </span>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['paged' => $page + 1]))); ?>" 
                               class="vqr-btn vqr-btn-secondary">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="vqr-empty-state">
                    <?php if ($has_active_filters): ?>
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-lg);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.207A1 1 0 013 6.5V4z"/>
                        </svg>
                        <h3>No QR codes match your filters</h3>
                        <p class="vqr-text-muted">Try adjusting your search criteria to find what you're looking for.</p>
                        <a href="<?php echo esc_url(remove_query_arg(['batch_code_search', 'category', 'scanned_status', 'order_scan', 'paged'])); ?>" 
                           class="vqr-btn vqr-btn-secondary" style="margin-top: var(--space-lg);">
                            Clear All Filters
                        </a>
                    <?php else: ?>
                        <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-lg);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M3 9h2m14 0h2M3 15h2m14 0h2M7 7h10v10H7z"/>
                        </svg>
                        <h3>No QR codes yet</h3>
                        <p class="vqr-text-muted">Generate your first batch of QR codes to start managing your codes.</p>
                        <a href="<?php echo home_url('/app/generate'); ?>" class="vqr-btn vqr-btn-primary" style="margin-top: var(--space-lg);">
                            <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Generate QR Codes
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

<!-- Copy URL Warning Modal -->
<div id="vqr-copy-warning-modal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-overlay" onclick="VQR.closeCopyWarningModal()"></div>
    <div class="vqr-modal-content vqr-modal-warning">
        <div class="vqr-modal-header">
            <h3 class="vqr-modal-warning-title">
                <svg class="vqr-warning-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                Copy URL Warning
            </h3>
            <button class="vqr-modal-close" onclick="VQR.closeCopyWarningModal()">×</button>
        </div>
        <div class="vqr-modal-body">
            <div class="vqr-warning-content">
                <p class="vqr-warning-message">
                    <strong>⚠️ Important:</strong> Visiting this URL will count as a scan and will increment your analytics.
                </p>
                <p class="vqr-warning-suggestion">
                    If you want to preview the verification page without affecting your scan counts, 
                    please use the <strong>Preview</strong> button instead.
                </p>
            </div>
        </div>
        <div class="vqr-modal-footer">
            <button class="vqr-btn vqr-btn-secondary" onclick="VQR.previewFromWarning()">
                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview Instead
            </button>
            <button class="vqr-btn vqr-btn-primary" onclick="VQR.proceedWithCopy()">
                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Copy URL Anyway
            </button>
        </div>
    </div>
</div>

<!-- Sticker order functionality moved to dedicated /app/order page -->

<style>
/* Prevent page-level horizontal scroll but allow controlled table scrolling */
body.vqr-app {
    overflow-x: hidden;
}

/* Fix vqr-app-main container overflow specifically for codes page */
.vqr-app-main {
    overflow-x: hidden !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Enhanced table container with scroll indicators */
.vqr-table-container {
    position: relative;
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.05);
}


/* Mobile scroll hint */
.vqr-scroll-hint {
    display: none;
    text-align: center;
    padding: 8px;
    background: #f8fafc;
    border-top: 1px solid #e5e7eb;
    font-size: 12px;
    color: #6b7280;
}

@media (max-width: 768px) {
    .vqr-scroll-hint {
        display: block;
    }
}

/* Codes Page Styles */
.vqr-codes-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 16px;
    box-sizing: border-box;
    width: 100%;
    overflow-x: hidden;
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

/* Grid System */
.vqr-grid {
    display: grid;
    gap: 16px;
    width: 100%;
    box-sizing: border-box;
    overflow-x: hidden;
}

.vqr-grid-cols-4 {
    grid-template-columns: repeat(4, 1fr);
}

/* Margin Utilities */
.vqr-mb-lg {
    margin-bottom: 24px;
}

/* Card Styles */
.vqr-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    width: 100%;
    box-sizing: border-box;
}

.vqr-card-header {
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vqr-card-title {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    color: #1f2937;
}

.vqr-card-content {
    padding: 16px;
}

/* Stats */
.vqr-stat {
    text-align: center;
}

.vqr-stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    color: #059669;
    margin-bottom: 4px;
}

.vqr-stat-label {
    font-size: 14px;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Form Styles */
.vqr-filters-form {
    width: 100%;
}

.vqr-filters-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    width: 100%;
    box-sizing: border-box;
}

.vqr-form-group {
    width: 100%;
    box-sizing: border-box;
}

.vqr-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    margin-bottom: 8px;
    color: #374151;
    font-size: 14px;
}

.vqr-label-icon {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}

.vqr-input,
.vqr-select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
    background: white;
    box-sizing: border-box;
}

.vqr-input:focus,
.vqr-select:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}

.vqr-filters-actions {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
}

/* Button Styles */
.vqr-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    font-size: 14px;
    font-weight: 500;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    box-sizing: border-box;
}

.vqr-btn-primary {
    background: #059669;
    color: white;
    border-color: #059669;
}

.vqr-btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.vqr-btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.vqr-btn-icon {
    width: 16px;
    height: 16px;
}

/* Table Container - Global Styles */
.vqr-table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 8px;
    position: relative;
    border: 1px solid #e5e7eb;
}

.vqr-table-container::-webkit-scrollbar {
    height: 6px;
}

.vqr-table-container::-webkit-scrollbar-track {
    background: #f8fafc;
    border-radius: 3px;
}

.vqr-table-container::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 3px;
}

.vqr-table-container::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

.vqr-table {
    width: 100%;
    min-width: 1200px;
    border-collapse: collapse;
    background: white;
    table-layout: fixed;
}

/* Fixed column widths for better control */
.vqr-table th:nth-child(1) { width: 40px; }    /* Checkbox */
.vqr-table th:nth-child(2) { width: 60px; }    /* QR Code */
.vqr-table th:nth-child(3) { width: 120px; }   /* Batch Code */
.vqr-table th:nth-child(4) { width: 100px; }   /* Category */
.vqr-table th:nth-child(5) { width: 150px; }   /* Strain */
.vqr-table th:nth-child(6) { width: 80px; }    /* Total Scans */
.vqr-table th:nth-child(7) { width: 90px; }    /* Unique Users */
.vqr-table th:nth-child(8) { width: 80px; }    /* Avg/User */
.vqr-table th:nth-child(9) { width: 80px; }    /* Status */
.vqr-table th:nth-child(10) { width: 100px; }  /* Created */
.vqr-table th:nth-child(11) { width: 320px; }  /* Actions */

.vqr-table th {
    background: #f9fafb;
    padding: 12px 8px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    font-size: 12px;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 5;
}

.vqr-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    color: #374151;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Allow text wrapping in strain and category columns */
.vqr-table td:nth-child(4),
.vqr-table td:nth-child(5) {
    white-space: normal;
    word-wrap: break-word;
    line-height: 1.3;
}

/* Keep other columns as nowrap */
.vqr-table td:nth-child(3),
.vqr-table td:nth-child(6),
.vqr-table td:nth-child(7),
.vqr-table td:nth-child(8),
.vqr-table td:nth-child(9),
.vqr-table td:nth-child(10) {
    white-space: nowrap;
}

.vqr-table tbody tr:hover {
    background: #f9fafb;
}

/* Table Elements */
.vqr-qr-thumb {
    width: 40px;
    height: 40px;
    cursor: pointer;
    border-radius: 4px;
    transition: transform 0.2s ease;
}

.vqr-qr-thumb:hover {
    transform: scale(1.1);
}

.vqr-batch-code {
    background: #f8fafc;
    padding: 4px 8px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
    color: #059669;
    font-weight: 600;
}

.vqr-strain-link {
    color: #059669;
    text-decoration: none;
    font-weight: 500;
}

.vqr-strain-link:hover {
    text-decoration: underline;
}

.vqr-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.vqr-badge-success {
    background: rgba(5, 150, 105, 0.1);
    color: #059669;
}

.vqr-badge-warning {
    background: rgba(245, 158, 11, 0.1);
    color: #d97706;
}

.vqr-text-muted {
    color: #6b7280;
}

.vqr-date {
    color: #6b7280;
    font-size: 12px;
}

/* Table Actions - optimized for fixed width */
.vqr-table-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    width: 300px;
    justify-content: flex-start;
    align-items: center;
}

.vqr-btn-outline {
    background: white;
    border-color: #d1d5db;
    color: #374151;
}

.vqr-btn-warning {
    background: #f59e0b;
    color: white;
    border-color: #f59e0b;
}

.vqr-btn-danger {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
}

.vqr-btn-locked {
    background: #64748b;
    color: white;
    border-color: #64748b;
    cursor: not-allowed;
}

/* Card Header */
.vqr-card-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}

.vqr-card-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* Bulk Actions */
.vqr-bulk-actions {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-right: 12px;
}

.vqr-selection-count {
    font-size: 14px;
    color: #6b7280;
    font-weight: 500;
}

.vqr-checkbox,
.vqr-qr-checkbox {
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid #d1d5db;
    cursor: pointer;
    accent-color: #059669;
}

/* Pagination */
.vqr-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e5e7eb;
}

.vqr-pagination-info {
    font-size: 14px;
    color: #6b7280;
}

/* Empty State */
.vqr-empty-state {
    text-align: center;
    padding: 48px 24px;
    color: #6b7280;
}

.vqr-empty-state h3 {
    color: #374151;
    margin: 0 0 8px 0;
    font-size: 18px;
}

.vqr-empty-state p {
    margin: 0 0 24px 0;
    font-size: 14px;
}

.vqr-filter-count {
    font-size: 14px;
    font-weight: 400;
    color: #6b7280;
}

/* Modal Styles */
.vqr-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.vqr-modal-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
}

.vqr-modal-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    position: relative;
    max-width: 400px;
    width: 100%;
}

.vqr-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.vqr-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1f2937;
}

.vqr-modal-close {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
}

.vqr-modal-body {
    padding: 16px;
    text-align: center;
}

.vqr-modal-footer {
    display: flex;
    gap: 8px;
    padding: 16px;
    border-top: 1px solid #e5e7eb;
    justify-content: flex-end;
}

/* Copy Warning Modal Styles */
.vqr-modal-warning {
    max-width: 500px;
}

.vqr-modal-warning-title {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    color: var(--warning);
    margin: 0;
}

.vqr-warning-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

.vqr-warning-content {
    padding: var(--space-md) 0;
}

.vqr-warning-message {
    color: var(--text-primary);
    margin: 0 0 var(--space-md) 0;
    font-size: var(--font-size-base);
    line-height: 1.5;
}

.vqr-warning-suggestion {
    color: var(--text-secondary);
    margin: 0;
    font-size: var(--font-size-sm);
    line-height: 1.5;
    background: var(--surface);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border-left: 3px solid var(--primary);
}

.vqr-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-sm);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

/* Upgrade Modal Styles */
.vqr-upgrade-features h4 {
    color: #1f2937;
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 16px;
}

.vqr-feature-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.vqr-feature-item {
    padding: 8px 0;
    color: #374151;
    font-size: 14px;
    line-height: 1.5;
    border-bottom: 1px solid #f3f4f6;
}

.vqr-feature-item:last-child {
    border-bottom: none;
}

/* Responsive Design */

/* Responsive grid adjustments (but keep all table data visible) */
@media (max-width: 1024px) {
    .vqr-grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .vqr-filters-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}

/* Bulk Download Styles */
.vqr-bulk-actions {
    display: none;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-sm) var(--space-md);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-right: var(--space-md);
}

.vqr-selection-count {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    font-weight: 500;
}

.vqr-checkbox,
.vqr-qr-checkbox {
    width: 16px;
    height: 16px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border);
    cursor: pointer;
    accent-color: var(--primary);
}

.vqr-checkbox:checked,
.vqr-qr-checkbox:checked {
    background-color: var(--primary);
    border-color: var(--primary);
}

.vqr-checkbox:indeterminate {
    background-color: var(--primary);
    border-color: var(--primary);
}

/* Locked button styles */
.vqr-btn-locked {
    background: var(--surface);
    color: var(--text-muted);
    border-color: var(--border);
    cursor: not-allowed;
    position: relative;
}

.vqr-btn-locked:hover {
    background: var(--surface-dark);
    border-color: var(--border);
    color: var(--text-muted);
}

.vqr-btn-locked .vqr-locked-feature-badge {
    margin-left: var(--space-xs);
}

/* Danger button styles */
.vqr-btn-danger {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    color: white;
    border: none;
}

.vqr-btn-danger:hover {
    background: linear-gradient(135deg, #b91c1c, #991b1b);
    transform: translateY(-1px);
}

.vqr-btn-danger .vqr-btn-icon {
    width: 14px;
    height: 14px;
    margin-right: var(--space-xs);
}

/* Compact Sticker Modal Styles */
.vqr-modal-compact {
    max-width: 600px;
    max-height: 85vh;
    width: 95%;
    overflow-y: auto;
}

.vqr-modal-compact .vqr-modal-body {
    padding: 12px 16px;
    max-height: 70vh;
    overflow-y: auto;
}

.vqr-modal-compact .vqr-modal-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
}

.vqr-modal-compact .vqr-modal-footer {
    padding: 10px 16px;
    border-top: 1px solid #e5e7eb;
}

.vqr-modal-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.vqr-modal-icon {
    width: 18px;
    height: 18px;
}

/* Compact Order Summary */
.vqr-order-summary-compact {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.vqr-summary-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.vqr-summary-header h4 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.vqr-total-display {
    text-align: right;
}

.vqr-total-display span {
    font-size: 16px;
    font-weight: 600;
    color: #059669;
}

.vqr-total-display small {
    display: block;
    font-size: 11px;
    color: #6b7280;
}

.vqr-qr-list-compact {
    max-height: 120px;
    overflow-y: auto;
    padding: 8px;
    background: #f9fafb;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
}

.vqr-qr-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid #e5e7eb;
}

.vqr-qr-item:last-child {
    border-bottom: none;
}

.vqr-qr-code-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.vqr-qr-mini-thumb {
    width: 24px;
    height: 24px;
    border-radius: 3px;
}

.vqr-qr-code-info span {
    font-size: 12px;
    font-weight: 500;
}

.vqr-qr-item .vqr-price {
    font-size: 12px;
    font-weight: 500;
    color: #059669;
}

/* Compact Shipping Form */
.vqr-shipping-info-compact {
    margin-bottom: 12px;
}

.vqr-shipping-info-compact h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.vqr-form-grid-compact {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}

.vqr-form-grid-compact .vqr-form-full {
    grid-column: 1 / -1;
}

.vqr-form-group {
    margin-bottom: 0;
}

.vqr-form-group label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 4px;
}

.vqr-form-group input,
.vqr-form-group select {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 13px;
    box-sizing: border-box;
}

.vqr-form-group input:focus,
.vqr-form-group select:focus {
    outline: none;
    border-color: #059669;
    box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.1);
}

/* Compact Notice */
.vqr-order-notice-compact {
    padding: 8px 10px;
    background: #fef3cd;
    border: 1px solid #fde047;
    border-radius: 4px;
    margin-top: 12px;
}

.vqr-order-notice-compact small {
    font-size: 11px;
    color: #92400e;
    line-height: 1.3;
}

/* Mobile adjustments for compact modal */
@media (max-width: 768px) {
    .vqr-modal-compact {
        max-width: 95%;
        max-height: 90vh;
        margin: 16px;
    }
    
    .vqr-form-grid-compact {
        grid-template-columns: 1fr;
        gap: 6px;
    }
    
    .vqr-modal-compact .vqr-modal-footer {
        flex-direction: column-reverse;
        gap: 8px;
    }
    
    .vqr-modal-compact .vqr-modal-footer .vqr-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Mobile responsive (but keep ALL data visible via horizontal scroll) */
@media (max-width: 768px) {
    .vqr-codes-page {
        padding: 12px;
    }
    
    .vqr-grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .vqr-filters-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .vqr-card-header-content {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
    }
    
    .vqr-bulk-actions {
        flex-direction: column;
        gap: 8px;
        width: 100%;
    }
    
    .vqr-bulk-actions .vqr-btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Optimize table for mobile but keep all columns */
    .vqr-table {
        min-width: 1200px; /* Keep full width, rely on scroll */
    }
    
    .vqr-table th,
    .vqr-table td {
        padding: 8px 6px;
        font-size: 12px;
    }
    
    .vqr-table-actions {
        width: 280px;
    }
    
    .vqr-table-actions .vqr-btn {
        padding: 4px 8px;
        font-size: 11px;
    }
    
    .vqr-modal-content {
        margin: 16px;
        max-width: calc(100vw - 32px);
    }
    
    .vqr-modal-footer {
        flex-direction: column-reverse;
        gap: 8px;
    }
    
    .vqr-modal-footer .vqr-btn {
        width: 100%;
        justify-content: center;
    }
}

/* Table Styles */
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
    flex-wrap: wrap;
    min-width: 300px; /* Ensure actions column has enough space */
    justify-content: flex-start;
}

.vqr-btn-locked {
    background: linear-gradient(135deg, #64748B, #94A3B8);
    color: white;
    border: none;
    position: relative;
    overflow: hidden;
}

.vqr-btn-locked:hover {
    background: linear-gradient(135deg, #475569, #64748B);
    transform: translateY(-1px);
}

.vqr-btn-locked .vqr-btn-icon {
    width: 14px;
    height: 14px;
    margin-right: var(--space-xs);
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

/* Sticker Order Modal Styles */
.vqr-modal-large {
    max-width: 600px;
    width: 90%;
}

.vqr-modal-title {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    margin: 0;
}

.vqr-modal-icon {
    width: 20px;
    height: 20px;
    color: var(--primary);
}

.vqr-order-summary {
    margin-bottom: var(--space-lg);
    padding: var(--space-md);
    background: var(--surface);
    border-radius: var(--radius-md);
}

.vqr-order-summary h4 {
    margin: 0 0 var(--space-md) 0;
    color: var(--text-primary);
}

.vqr-qr-list {
    max-height: 150px;
    overflow-y: auto;
    margin-bottom: var(--space-md);
}

.vqr-qr-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: var(--space-xs);
    background: white;
}

.vqr-qr-item:last-child {
    margin-bottom: 0;
}

.vqr-qr-code-info {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.vqr-qr-mini-thumb {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    object-fit: contain;
}

.vqr-pricing-info {
    border-top: 1px solid var(--border);
    padding-top: var(--space-md);
}

.vqr-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xs);
}

.vqr-price-row.vqr-total {
    font-weight: 600;
    font-size: var(--font-size-lg);
    border-top: 1px solid var(--border);
    padding-top: var(--space-sm);
    margin-top: var(--space-sm);
}

.vqr-price {
    color: var(--primary);
    font-weight: 500;
}

.vqr-shipping-info {
    margin-bottom: var(--space-lg);
}

.vqr-shipping-info h4 {
    margin: 0 0 var(--space-md) 0;
    color: var(--text-primary);
}

.vqr-form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-md);
}

.vqr-form-group {
    display: flex;
    flex-direction: column;
}

.vqr-form-full {
    grid-column: 1 / -1;
}

.vqr-form-group label {
    font-weight: 500;
    margin-bottom: var(--space-xs);
    color: var(--text-primary);
}

.vqr-form-group input,
.vqr-form-group select,
.vqr-form-group textarea {
    padding: var(--space-sm);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
}

.vqr-form-group input:focus,
.vqr-form-group select:focus,
.vqr-form-group textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.vqr-order-notice {
    padding: var(--space-md);
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: var(--radius-md);
    margin-bottom: var(--space-lg);
}

.vqr-notice-content {
    display: flex;
    gap: var(--space-sm);
    align-items: flex-start;
}

.vqr-notice-icon {
    width: 20px;
    height: 20px;
    color: #d97706;
    flex-shrink: 0;
    margin-top: 2px;
}

.vqr-notice-content h5 {
    margin: 0 0 var(--space-xs) 0;
    color: #92400e;
    font-size: var(--font-size-sm);
    font-weight: 600;
}

.vqr-notice-content p {
    margin: 0;
    color: #92400e;
    font-size: var(--font-size-sm);
    line-height: 1.4;
}

/* Small Mobile - still show all data */
@media (max-width: 480px) {
    .vqr-codes-page {
        padding: 8px;
    }
    
    .vqr-grid-cols-4 {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    /* Maintain full table width with better mobile optimization */
    .vqr-table {
        min-width: 1100px;
    }
    
    .vqr-table th,
    .vqr-table td {
        padding: 6px 4px;
        font-size: 11px;
    }
    
    .vqr-table-actions {
        width: 250px;
    }
    
    .vqr-table-actions .vqr-btn {
        padding: 3px 6px;
        font-size: 10px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced table scrolling functionality
    function initTableScrolling() {
        const tableContainer = document.querySelector('.vqr-table-container');
        if (!tableContainer) return;
        
        
        // Smooth scrolling with mouse wheel (horizontal)
        tableContainer.addEventListener('wheel', function(e) {
            if (e.deltaX === 0 && e.deltaY !== 0) {
                // Convert vertical scroll to horizontal when hovering over table
                e.preventDefault();
                this.scrollLeft += e.deltaY;
            }
        });
        
        // Touch scrolling optimization for mobile
        let startX = 0;
        tableContainer.addEventListener('touchstart', function(e) {
            startX = e.touches[0].clientX;
        });
        
        tableContainer.addEventListener('touchmove', function(e) {
            if (Math.abs(e.touches[0].clientX - startX) > 10) {
                // Horizontal swipe detected, prevent page scroll
                e.preventDefault();
            }
        });
    }
    
    // Initialize table scrolling
    initTableScrolling();
    
    // Extend VQR object with modal and utility functions
    window.VQR = window.VQR || {};
    
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

    // Copy URL Warning Modal functions
    VQR.pendingCopyText = null;
    VQR.pendingStrainId = null;
    
    VQR.copyToClipboard = function(text, strainId) {
        // Store the URL and strain ID for later use
        VQR.pendingCopyText = text;
        VQR.pendingStrainId = strainId;
        
        // Show the warning modal
        VQR.showCopyWarningModal();
    };
    
    VQR.showCopyWarningModal = function() {
        const modal = document.getElementById('vqr-copy-warning-modal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };
    
    VQR.closeCopyWarningModal = function() {
        const modal = document.getElementById('vqr-copy-warning-modal');
        if (modal) {
            modal.style.display = 'none';
        }
        // Clear pending data
        VQR.pendingCopyText = null;
        VQR.pendingStrainId = null;
    };
    
    VQR.previewFromWarning = function() {
        if (VQR.pendingStrainId) {
            // Open preview in new tab
            const previewUrl = '<?php echo home_url('/app/preview/'); ?>' + VQR.pendingStrainId;
            window.open(previewUrl, '_blank');
        } else {
            // Fallback: show notification that preview is not available
            VQR.showNotification('Preview Unavailable', 'Unable to preview this QR code. The strain information may not be available.', 'warning');
        }
        // Close the modal
        VQR.closeCopyWarningModal();
    };
    
    VQR.proceedWithCopy = function() {
        if (VQR.pendingCopyText) {
            // Proceed with copying
            if (navigator.clipboard) {
                navigator.clipboard.writeText(VQR.pendingCopyText).then(function() {
                    VQR.showNotification('Success', 'URL copied to clipboard!', 'success');
                });
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = VQR.pendingCopyText;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                VQR.showNotification('Success', 'URL copied to clipboard!', 'success');
            }
        }
        // Close the modal
        VQR.closeCopyWarningModal();
    };

    // QR Reset functions
    VQR.confirmResetQR = function(qrId, batchCode, scanCount) {
        if (confirm(`Are you sure you want to reset the scan count for QR code ${batchCode}? This will reset ${scanCount} scans to 0 and cannot be undone.`)) {
            VQR.resetQRScans(qrId, batchCode);
        }
    };

    VQR.resetQRScans = function(qrId, batchCode) {
        // Show loading state
        const button = document.querySelector(`button[onclick*="${qrId}"]`);
        const originalText = button.innerHTML;
        button.innerHTML = 'Resetting...';
        button.disabled = true;

        // Make AJAX request to reset scans
        fetch(vqr_ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'vqr_reset_qr_scans',
                nonce: vqr_ajax.nonce,
                qr_id: qrId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                VQR.showNotification('Success', `Scan count reset for ${batchCode}`, 'success');
                // Reload the page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                VQR.showNotification('Error', data.data || 'Failed to reset scan count', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            VQR.showNotification('Error', 'Network error occurred', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    };

    VQR.showResetLocked = function() {
        // Create and show the upgrade modal
        VQR.showUpgradeModal('reset_qr_codes');
    };

    // Bulk Download Functions - Persistent Selection Across Pages
    VQR.selectedQRs = JSON.parse(sessionStorage.getItem('vqr_selected_qrs') || '[]');
    
    VQR.updateSelection = function() {
        const checkboxes = document.querySelectorAll('.vqr-qr-checkbox');
        const bulkActions = document.getElementById('vqr-bulk-actions');
        const selectionCount = document.getElementById('vqr-selection-count');
        const selectAllCheckbox = document.getElementById('vqr-select-all');
        
        // Update selected QRs array based on current page checkboxes
        Array.from(checkboxes).forEach(checkbox => {
            const qrData = {
                id: checkbox.dataset.qrId,
                url: checkbox.dataset.qrUrl,
                batchCode: checkbox.dataset.batchCode
            };
            
            const existingIndex = VQR.selectedQRs.findIndex(qr => qr.id === qrData.id);
            
            if (checkbox.checked && existingIndex === -1) {
                // Add to selection
                VQR.selectedQRs.push(qrData);
            } else if (!checkbox.checked && existingIndex !== -1) {
                // Remove from selection
                VQR.selectedQRs.splice(existingIndex, 1);
            }
        });
        
        // Persist to sessionStorage
        sessionStorage.setItem('vqr_selected_qrs', JSON.stringify(VQR.selectedQRs));
        
        // Show/hide bulk actions
        if (VQR.selectedQRs.length > 0) {
            bulkActions.style.display = 'flex';
            selectionCount.textContent = `${VQR.selectedQRs.length} selected`;
        } else {
            bulkActions.style.display = 'none';
        }
        
        // Update select all checkbox state (only for current page)
        const checkedBoxes = document.querySelectorAll('.vqr-qr-checkbox:checked');
        if (checkedBoxes.length === checkboxes.length && checkboxes.length > 0) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else if (checkedBoxes.length > 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        }
    };
    
    // Restore selection state on page load
    VQR.restoreSelection = function() {
        const checkboxes = document.querySelectorAll('.vqr-qr-checkbox');
        Array.from(checkboxes).forEach(checkbox => {
            const isSelected = VQR.selectedQRs.some(qr => qr.id === checkbox.dataset.qrId);
            checkbox.checked = isSelected;
        });
        VQR.updateSelection();
    };
    
    VQR.toggleSelectAll = function() {
        const selectAllCheckbox = document.getElementById('vqr-select-all');
        const qrCheckboxes = document.querySelectorAll('.vqr-qr-checkbox');
        
        qrCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        
        VQR.updateSelection();
    };
    
    VQR.clearSelection = function() {
        const checkboxes = document.querySelectorAll('.vqr-qr-checkbox');
        const selectAllCheckbox = document.getElementById('vqr-select-all');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
        
        // Clear persistent storage
        VQR.selectedQRs = [];
        sessionStorage.removeItem('vqr_selected_qrs');
        
        VQR.updateSelection();
    };
    
    VQR.downloadSingle = function(qrUrl, batchCode) {
        const link = document.createElement('a');
        link.href = qrUrl;
        link.download = 'qr-code-' + batchCode + '.png';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        VQR.showNotification('Success', 'QR code downloaded!', 'success');
    };
    
    VQR.bulkDownload = function() {
        if (VQR.selectedQRs.length === 0) {
            VQR.showNotification('Error', 'Please select QR codes to download.', 'error');
            return;
        }
        
        // Show loading state
        const downloadBtn = document.getElementById('vqr-bulk-download-btn');
        const originalText = downloadBtn.innerHTML;
        downloadBtn.innerHTML = '<span class="vqr-loading"></span> Preparing ZIP...';
        downloadBtn.disabled = true;
        
        // Prepare download data
        const qrIds = VQR.selectedQRs.map(qr => qr.id);
        console.log('Bulk download request:', {
            selected_count: VQR.selectedQRs.length,
            qr_ids: qrIds,
            selected_qrs: VQR.selectedQRs
        });
        
        // Use FormData for better array handling
        const formData = new FormData();
        formData.append('action', 'vqr_bulk_download_qr');
        formData.append('nonce', vqr_ajax.nonce);
        
        // Add each QR ID separately
        qrIds.forEach(id => {
            formData.append('qr_ids[]', id);
        });
        
        // Debug FormData contents
        console.log('FormData contents:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        // Send request to backend
        fetch(vqr_ajax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create download link for ZIP file
                const link = document.createElement('a');
                link.href = data.data.download_url;
                link.download = data.data.filename;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                VQR.showNotification('Success', `Downloaded ${VQR.selectedQRs.length} QR codes as ZIP file!`, 'success');
                VQR.clearSelection();
            } else {
                VQR.showNotification('Error', data.data || 'Failed to create ZIP file.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            VQR.showNotification('Error', 'Network error occurred.', 'error');
        })
        .finally(() => {
            // Restore button state
            downloadBtn.innerHTML = originalText;
            downloadBtn.disabled = false;
        });
    };
    
    VQR.showBulkDownloadLocked = function() {
        VQR.showUpgradeModal('bulk_download');
    };

    // QR Delete Functions
    VQR.confirmDeleteQR = function(qrId, batchCode) {
        if (confirm(`Are you sure you want to delete QR code ${batchCode}? This action cannot be undone and will permanently remove the QR code and all its scan data.`)) {
            VQR.deleteQR(qrId, batchCode);
        }
    };

    VQR.deleteQR = function(qrId, batchCode) {
        // Show loading state
        const button = document.querySelector(`button[onclick*="confirmDeleteQR(${qrId}"]`);
        const originalText = button.innerHTML;
        button.innerHTML = 'Deleting...';
        button.disabled = true;

        // Make AJAX request to delete QR
        fetch(vqr_ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'vqr_delete_qr_code',
                nonce: vqr_ajax.nonce,
                qr_id: qrId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                VQR.showNotification('Success', `QR code ${batchCode} deleted successfully`, 'success');
                // Remove the row from the table
                button.closest('tr').remove();
                
                // Update counts (optional - could reload page)
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                VQR.showNotification('Error', data.data || 'Failed to delete QR code', 'error');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            VQR.showNotification('Error', 'Network error occurred', 'error');
            button.innerHTML = originalText;
            button.disabled = false;
        });
    };

    VQR.bulkDelete = function() {
        if (VQR.selectedQRs.length === 0) {
            VQR.showNotification('Error', 'Please select QR codes to delete.', 'error');
            return;
        }
        
        const count = VQR.selectedQRs.length;
        const confirmMessage = `Are you sure you want to delete ${count} QR code${count > 1 ? 's' : ''}? This action cannot be undone and will permanently remove all selected QR codes and their scan data.`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Show loading state
        const deleteBtn = document.getElementById('vqr-bulk-delete-btn');
        const originalText = deleteBtn.innerHTML;
        deleteBtn.innerHTML = '<span class="vqr-loading"></span> Deleting...';
        deleteBtn.disabled = true;
        
        // Prepare delete data
        const qrIds = VQR.selectedQRs.map(qr => qr.id);
        
        // Use FormData for better array handling
        const formData = new FormData();
        formData.append('action', 'vqr_bulk_delete_qr');
        formData.append('nonce', vqr_ajax.nonce);
        
        // Add each QR ID separately
        qrIds.forEach(id => {
            formData.append('qr_ids[]', id);
        });
        
        // Send request to backend
        fetch(vqr_ajax.url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                VQR.showNotification('Success', `Deleted ${count} QR code${count > 1 ? 's' : ''} successfully!`, 'success');
                VQR.clearSelection();
                
                // Reload page to show updated data
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                VQR.showNotification('Error', data.data || 'Failed to delete QR codes.', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            VQR.showNotification('Error', 'Network error occurred.', 'error');
        })
        .finally(() => {
            // Restore button state
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        });
    };
    
    VQR.showBulkDeleteLocked = function() {
        VQR.showUpgradeModal('delete_qr_codes');
    };
    
    VQR.showDeleteLocked = function() {
        VQR.showUpgradeModal('delete_qr_codes');
    };

    VQR.showUpgradeModal = function(feature) {
        // Remove existing modal if any
        const existingModal = document.getElementById('vqr-upgrade-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const upgradeInfo = {
            'reset_qr_codes': {
                title: 'QR Reset Locked',
                message: 'Upgrade to Pro plan to reset QR code scan counts.',
                features: [
                    'Reset individual QR code scan counts',
                    'Track QR code performance over time',
                    'Advanced analytics and reporting'
                ],
                upgrade_plan: 'pro'
            },
            'bulk_download': {
                title: 'Bulk Download Locked',
                message: 'Upgrade to Starter plan or higher to download multiple QR codes as ZIP files.',
                features: [
                    'Download multiple QR codes at once',
                    'Organized ZIP file with proper naming',
                    'Save time with bulk operations',
                    'Professional workflow management'
                ],
                upgrade_plan: 'starter'
            },
            'delete_qr_codes': {
                title: 'QR Delete Locked',
                message: 'Upgrade to Pro plan to delete individual or bulk QR codes.',
                features: [
                    'Delete individual QR codes permanently',
                    'Bulk delete multiple QR codes at once',
                    'Clean up unused or outdated codes',
                    'Free up storage and organization',
                    'Advanced QR code management'
                ],
                upgrade_plan: 'pro'
            }
        };

        const info = upgradeInfo[feature] || upgradeInfo['reset_qr_codes'];

        const modal = document.createElement('div');
        modal.id = 'vqr-upgrade-modal';
        modal.className = 'vqr-modal';
        modal.style.display = 'flex';
        modal.innerHTML = `
            <div class="vqr-modal-overlay" onclick="VQR.closeUpgradeModal()"></div>
            <div class="vqr-modal-content">
                <div class="vqr-modal-header">
                    <h3>${info.title}</h3>
                    <button class="vqr-modal-close" onclick="VQR.closeUpgradeModal()">×</button>
                </div>
                <div class="vqr-modal-body">
                    <div class="vqr-upgrade-content">
                        <p style="margin-bottom: var(--space-lg); color: var(--text-muted);">${info.message}</p>
                        
                        <div class="vqr-upgrade-features">
                            <h4 style="margin-bottom: 16px;">Pro features include:</h4>
                            <div class="vqr-feature-list">
                                ${info.features.map(feature => `<div class="vqr-feature-item">${feature}</div>`).join('')}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="vqr-modal-footer">
                    <button class="vqr-btn vqr-btn-secondary" onclick="VQR.closeUpgradeModal()">Cancel</button>
                    <a href="/app/billing" class="vqr-btn vqr-btn-primary">Upgrade to ${info.upgrade_plan.charAt(0).toUpperCase() + info.upgrade_plan.slice(1)}</a>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
    };

    VQR.closeUpgradeModal = function() {
        const modal = document.getElementById('vqr-upgrade-modal');
        if (modal) {
            modal.remove();
        }
    };

    // Basic notification system (if not already defined)
    VQR.showNotification = VQR.showNotification || function(title, message, type) {
        // Simple alert fallback if no notification system exists
        alert(title + ': ' + message);
    };

    // Sticker Ordering Functions
    VQR.selectedStickers = [];
    VQR.stickerPrice = 0.50; // $0.50 per sticker

    VQR.bulkOrderStickers = function() {
        if (VQR.selectedQRs.length === 0) {
            VQR.showNotification('Error', 'Please select QR codes to order stickers for.', 'error');
            return;
        }
        
        // Navigate to order page with selected QR IDs
        const qrIds = VQR.selectedQRs.map(qr => qr.id);
        window.location.href = '<?php echo home_url('/app/order?qr_ids='); ?>' + qrIds.join(',');
    };

    VQR.orderSingleSticker = function(qrId, batchCode) {
        // Navigate to order page with single QR ID
        window.location.href = '<?php echo home_url('/app/order?qr_ids='); ?>' + qrId;
    };

    // Sticker modal functions removed - now using dedicated order page

    // Close modals on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            VQR.closeQRModal();
            VQR.closeCopyWarningModal();
            VQR.closeUpgradeModal();
        }
    });
    
    // Initialize selection restoration after page loads
    setTimeout(() => {
        VQR.restoreSelection();
    }, 100);
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'QR Codes';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>