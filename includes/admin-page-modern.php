<?php
/**
 * Modern Admin Page Functionality with WordPress Best Practices
 */

defined('ABSPATH') || exit;

/**
 * Add admin page to WordPress menu
 */
function vqr_add_admin_page() {
    add_menu_page(
        'Verification QR Manager',
        'QR Codes',
        'manage_options',
        'verification_qr_manager',
        'vqr_display_admin_page',
        'dashicons-qrcode',
        6
    );
    
    // Add submenu for user management
    add_submenu_page(
        'verification_qr_manager',
        'QR User Management',
        'User Management',
        'manage_options',
        'vqr_user_management',
        'vqr_display_user_management_page'
    );
    
    // Add separate menu for sticker orders
    add_menu_page(
        'Sticker Orders',
        'Sticker Orders',
        'manage_options',
        'vqr_sticker_orders',
        'vqr_display_sticker_orders_page',
        'dashicons-cart',
        7
    );
}
add_action('admin_menu', 'vqr_add_admin_page');

/**
 * Main modern admin page display function
 */
function vqr_display_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';

    // Handle all POST actions
    vqr_handle_admin_actions($wpdb, $table_name);

    // Get filter parameters
    $filters = vqr_get_filter_parameters();
    
    // Get filtered QR codes
    $qr_codes = vqr_get_filtered_qr_codes($wpdb, $table_name, $filters);
    
    // Get data for dropdowns
    $categories = vqr_get_categories($wpdb, $table_name);
    $users = vqr_get_qr_users($wpdb, $table_name);
    $strains = vqr_get_qr_strains($wpdb, $table_name);

    // Render the modern admin page
    vqr_render_modern_admin_page($qr_codes, $categories, $filters, $users, $strains);
}

/**
 * Handle all admin POST actions
 */
function vqr_handle_admin_actions($wpdb, $table_name) {
    // Generate new QR codes
    if (isset($_POST['generate_qr_codes'])) {
        vqr_handle_generate_qr_codes($wpdb, $table_name);
    }

    // Bulk actions via dropdown
    if (isset($_POST['vqr_bulk_action']) && !empty($_POST['qr_ids'])) {
        vqr_handle_bulk_actions($wpdb, $table_name);
    }

    // Individual reset (legacy support)
    if (isset($_POST['reset_scan_count']) && !empty($_POST['qr_id'])) {
        vqr_handle_individual_reset($wpdb, $table_name);
    }
}

/**
 * Handle QR code generation
 */
function vqr_handle_generate_qr_codes($wpdb, $table_name) {
    if (!check_admin_referer('vqr_generate_codes', 'vqr_generate_nonce')) {
        vqr_admin_notice('Security check failed.', 'error');
        return;
    }

    $count = intval($_POST['qr_count']);
    $base_url = esc_url_raw($_POST['base_url']);
    $gen_cat = sanitize_text_field($_POST['category']);
    $post_id = intval($_POST['post_id']);
    $prefix = sanitize_text_field($_POST['code_prefix']);

    // Handle logo upload
    $logo_path = '';
    if (!empty($_FILES['logo_file']['name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $upl = wp_handle_upload($_FILES['logo_file'], [
            'test_form' => false,
            'mimes' => ['png' => 'image/png', 'jpg|jpeg' => 'image/jpeg'],
        ]);
        if (empty($upl['error'])) {
            $logo_path = $upl['file'];
        }
    }

    if ($count > 0 && filter_var($base_url, FILTER_VALIDATE_URL)) {
        vqr_generate_codes($count, $base_url, $gen_cat, $post_id, $prefix, $logo_path);
        vqr_admin_notice("Generated {$count} QR codes successfully.", 'success');
    } else {
        vqr_admin_notice('Invalid input for generating QR codes.', 'error');
    }
}

/**
 * Handle bulk actions from dropdown
 */
function vqr_handle_bulk_actions($wpdb, $table_name) {
    if (!check_admin_referer('vqr_bulk_action', 'vqr_bulk_action_nonce')) {
        vqr_admin_notice('Security check failed.', 'error');
        return;
    }

    $action = sanitize_text_field($_POST['vqr_bulk_action']);
    $ids = array_map('intval', $_POST['qr_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));

    switch ($action) {
        case 'delete':
            $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids));
            vqr_admin_notice('Deleted selected QR codes.', 'success');
            break;

        case 'reset':
            // Clear CPT meta
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids));
            foreach ($rows as $r) {
                if ($r->post_id) {
                    delete_post_meta($r->post_id, 'times_scanned');
                    delete_post_meta($r->post_id, 'first_scanned_date');
                }
            }
            // Reset DB counts
            $wpdb->query($wpdb->prepare("UPDATE {$table_name} SET scan_count = 0, first_scanned_at = NULL WHERE id IN ({$placeholders})", ...$ids));
            vqr_admin_notice('Reset scan counts for selected QR codes.', 'success');
            break;

        case 'download':
            // Handle ZIP download directly to avoid conflicts
            if (!empty($ids)) {
                vqr_handle_zip_download($wpdb, $table_name, $ids);
            }
            break;

        case 'download_pdf':
            // Create JavaScript to submit form to new window for PDF download
            if (!empty($ids)) {
                $nonce = wp_create_nonce('vqr_bulk_action');
                $admin_post_url = admin_url('admin-post.php');
                $ids_json = json_encode($ids);
                
                echo "<script>
                (function() {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '" . esc_js($admin_post_url) . "';
                    form.target = '_blank';
                    form.style.display = 'none';
                    
                    var actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'download_qr_pdf';
                    form.appendChild(actionInput);
                    
                    var nonceInput = document.createElement('input');
                    nonceInput.type = 'hidden';
                    nonceInput.name = 'vqr_bulk_action_nonce';
                    nonceInput.value = '" . esc_js($nonce) . "';
                    form.appendChild(nonceInput);
                    
                    var idsData = " . $ids_json . ";
                    idsData.forEach(function(id) {
                        var idInput = document.createElement('input');
                        idInput.type = 'hidden';
                        idInput.name = 'qr_ids[]';
                        idInput.value = id;
                        form.appendChild(idInput);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                })();
                </script>";
                return; // Don't break, return to avoid further processing
            }
            break;
    }
}

/**
 * Get filter parameters from URL
 */
function vqr_get_filter_parameters() {
    return [
        'batch_code' => sanitize_text_field($_GET['batch_code_search'] ?? ''),
        'category' => sanitize_text_field($_GET['category'] ?? ''),
        'scanned' => sanitize_text_field($_GET['scanned_status'] ?? ''),
        'order' => in_array($_GET['order_scan'] ?? '', ['asc', 'desc'], true) ? $_GET['order_scan'] : '',
        'user_search' => sanitize_text_field($_GET['user_search'] ?? ''),
        'user_id' => intval($_GET['user_id'] ?? 0),
        'strain_id' => intval($_GET['strain_id'] ?? 0),
        'strain_search' => sanitize_text_field($_GET['strain_search'] ?? '')
    ];
}

/**
 * Get filtered QR codes from database
 */
function vqr_get_filtered_qr_codes($wpdb, $table_name, $filters) {
    $where = [];
    $vars = [];
    $joins = [];

    // Base QR code filters
    if ($filters['batch_code']) {
        $where[] = 'qr.batch_code LIKE %s';
        $vars[] = '%' . $wpdb->esc_like($filters['batch_code']) . '%';
    }
    if ($filters['category']) {
        $where[] = 'qr.category = %s';
        $vars[] = $filters['category'];
    }
    if ($filters['scanned'] === 'scanned') {
        $where[] = 'qr.scan_count > 0';
    } elseif ($filters['scanned'] === 'not_scanned') {
        $where[] = 'qr.scan_count = 0';
    }

    // User filters
    if ($filters['user_id']) {
        $where[] = 'qr.user_id = %d';
        $vars[] = $filters['user_id'];
    } elseif ($filters['user_search']) {
        $joins[] = "LEFT JOIN {$wpdb->users} u ON qr.user_id = u.ID";
        $where[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($filters['user_search']) . '%';
        $vars[] = $search_term;
        $vars[] = $search_term;
        $vars[] = $search_term;
    }

    // Strain filters
    if ($filters['strain_id']) {
        $where[] = 'qr.post_id = %d';
        $vars[] = $filters['strain_id'];
    } elseif ($filters['strain_search']) {
        $joins[] = "LEFT JOIN {$wpdb->posts} p ON qr.post_id = p.ID";
        $where[] = 'p.post_title LIKE %s';
        $vars[] = '%' . $wpdb->esc_like($filters['strain_search']) . '%';
    }

    // Build SQL
    $sql_joins = !empty($joins) ? implode(' ', array_unique($joins)) : '';
    $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql_order = '';
    if ($filters['order'] === 'asc') {
        $sql_order = 'ORDER BY qr.scan_count ASC';
    } elseif ($filters['order'] === 'desc') {
        $sql_order = 'ORDER BY qr.scan_count DESC';
    } else {
        $sql_order = 'ORDER BY qr.id DESC';
    }

    $sql = "SELECT qr.*, 
                   u.user_login, u.user_email, u.display_name as user_display_name,
                   p.post_title as strain_title
            FROM {$table_name} qr 
            LEFT JOIN {$wpdb->users} u ON qr.user_id = u.ID
            LEFT JOIN {$wpdb->posts} p ON qr.post_id = p.ID
            {$sql_joins} 
            {$sql_where} 
            {$sql_order}";

    if (!empty($vars)) {
        $sql = $wpdb->prepare($sql, ...$vars);
    }

    return $wpdb->get_results($sql);
}

/**
 * Get categories for dropdown
 */
function vqr_get_categories($wpdb, $table_name) {
    return $wpdb->get_col("SELECT DISTINCT category FROM {$table_name} WHERE category != ''");
}

/**
 * Get users who have QR codes for dropdown
 */
function vqr_get_qr_users($wpdb, $table_name) {
    return $wpdb->get_results("
        SELECT DISTINCT u.ID, u.user_login, u.user_email, u.display_name 
        FROM {$wpdb->users} u 
        INNER JOIN {$table_name} qr ON u.ID = qr.user_id 
        WHERE qr.user_id IS NOT NULL 
        ORDER BY u.display_name, u.user_login
    ");
}

/**
 * Get strains that have QR codes for dropdown
 */
function vqr_get_qr_strains($wpdb, $table_name) {
    return $wpdb->get_results("
        SELECT DISTINCT p.ID, p.post_title 
        FROM {$wpdb->posts} p 
        INNER JOIN {$table_name} qr ON p.ID = qr.post_id 
        WHERE qr.post_id IS NOT NULL AND p.post_type = 'strain' AND p.post_status = 'publish'
        ORDER BY p.post_title
    ");
}

/**
 * Render the modern admin page
 */
function vqr_render_modern_admin_page($qr_codes, $categories, $filters, $users = [], $strains = []) {
    $global_logo = vqr_get_global_logo();
    ?>
    <div class="vqr-admin-wrap wrap">
        <h1>
            <?php if ($global_logo): ?>
                <img src="<?php echo esc_url($global_logo['url']); ?>" 
                     alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                     style="height: 24px; width: auto; margin-right: 8px; vertical-align: middle;">
            <?php else: ?>
                <span class="dashicons dashicons-qrcode"></span> 
            <?php endif; ?>
            QR Code Manager
        </h1>
        
        <!-- Modern Admin Layout -->
        <div class="vqr-admin-layout">
            
            <!-- Full Width QR Generation Card -->
            <div class="vqr-admin-card vqr-full-width">
                <div class="inside">
                    <h3><span class="dashicons dashicons-plus-alt vqr-card-icon"></span>Generate QR Codes</h3>
                    <?php vqr_render_horizontal_generation_form(); ?>
                </div>
            </div>
            
            <!-- Combined QR Management Section -->
            <div class="vqr-qr-management">
                <!-- Filters Section -->
                <div class="vqr-filters-section">
                    <h3><span class="dashicons dashicons-filter vqr-card-icon"></span>Filter & Manage QR Codes</h3>
                    <?php vqr_render_filters_form($categories, $filters, $users, $strains); ?>
                </div>
                
                <!-- Data Table -->
                <?php vqr_render_enhanced_data_table($qr_codes); ?>
            </div>
            
        </div>
        
    </div>
    <?php
}

/**
 * Render horizontal QR generation form
 */
function vqr_render_horizontal_generation_form() {
    $strains = get_posts([
        'post_type' => 'strain',
        'numberposts' => -1,
        'post_status' => 'publish',
    ]);
    ?>
    <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('vqr_generate_codes', 'vqr_generate_nonce'); ?>
        
        <div class="vqr-horizontal-form">
            <div class="vqr-form-group">
                <label for="qr_count">Quantity:</label>
                <input type="number" id="qr_count" name="qr_count" min="1" max="1000" placeholder="e.g. 100" required>
            </div>
            
            <div class="vqr-form-group">
                <label for="post_id">Strain:</label>
                <select id="post_id" name="post_id" required>
                    <option value="">Select strain...</option>
                    <?php foreach ($strains as $strain): ?>
                        <option value="<?php echo esc_attr($strain->ID); ?>" data-url="<?php echo esc_attr(get_permalink($strain->ID)); ?>">
                            <?php echo esc_html($strain->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="vqr-form-group">
                <label for="strain_url">Strain URL:</label>
                <input type="url" id="strain_url" name="base_url" placeholder="Select a strain first..." readonly required>
            </div>
            
            <div class="vqr-form-group">
                <label for="category">Category:</label>
                <input type="text" id="category" name="category" placeholder="e.g. Batch A, Summer 2024" required>
            </div>
            
            <div class="vqr-form-group">
                <label for="code_prefix">Prefix:</label>
                <input type="text" id="code_prefix" name="code_prefix" maxlength="4" placeholder="e.g. AB12" required>
            </div>
            
            <div class="vqr-form-group">
                <label for="logo_file">Logo:</label>
                <input type="file" id="logo_file" name="logo_file" accept="image/png,image/jpeg">
            </div>
            
            <div class="vqr-form-group vqr-form-button">
                <label>&nbsp;</label>
                <input type="submit" name="generate_qr_codes" value="Generate QR Codes" class="button button-primary">
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const strainSelect = document.getElementById('post_id');
            const urlField = document.getElementById('strain_url');
            
            strainSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const strainUrl = selectedOption.getAttribute('data-url');
                    urlField.value = strainUrl;
                } else {
                    urlField.value = '';
                    urlField.placeholder = 'Select a strain first...';
                }
            });
        });
        </script>
    </form>
    <?php
}

/**
 * Render compact QR generation form (keeping for backward compatibility)
 */
function vqr_render_compact_generation_form() {
    $strains = get_posts([
        'post_type' => 'strain',
        'numberposts' => -1,
        'post_status' => 'publish',
    ]);
    ?>
    <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('vqr_generate_codes', 'vqr_generate_nonce'); ?>
        
        <table class="vqr-form-table">
            <tr>
                <th><label for="qr_count">Qty:</label></th>
                <td><input type="number" id="qr_count" name="qr_count" min="1" max="1000" required></td>
            </tr>
            <tr>
                <th><label for="base_url">URL:</label></th>
                <td><input type="url" id="base_url" name="base_url" required></td>
            </tr>
            <tr>
                <th><label for="category">Category:</label></th>
                <td><input type="text" id="category" name="category" required></td>
            </tr>
            <tr>
                <th><label for="code_prefix">Prefix:</label></th>
                <td><input type="text" id="code_prefix" name="code_prefix" maxlength="4" required></td>
            </tr>
            <tr>
                <th><label for="post_id">Strain:</label></th>
                <td>
                    <select id="post_id" name="post_id" required>
                        <option value="">Select...</option>
                        <?php foreach ($strains as $strain): ?>
                            <option value="<?php echo esc_attr($strain->ID); ?>">
                                <?php echo esc_html($strain->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="generate_qr_codes" value="Generate" class="button button-primary">
        </p>
    </form>
    <?php
}

/**
 * Render QR generation form (full version - keeping for backward compatibility)
 */
function vqr_render_generation_form() {
    $strains = get_posts([
        'post_type' => 'strain',
        'numberposts' => -1,
        'post_status' => 'publish',
    ]);
    ?>
    <form method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('vqr_generate_codes', 'vqr_generate_nonce'); ?>
        
        <table class="vqr-form-table">
            <tr>
                <th><label for="qr_count">Quantity:</label></th>
                <td>
                    <input type="number" id="qr_count" name="qr_count" min="1" max="1000" required>
                    <p class="vqr-form-description">Number of QR codes to generate (1-1000)</p>
                </td>
            </tr>
            <tr>
                <th><label for="base_url">Base URL:</label></th>
                <td>
                    <input type="url" id="base_url" name="base_url" required>
                    <p class="vqr-form-description">Base URL for QR code destinations</p>
                </td>
            </tr>
            <tr>
                <th><label for="category">Category:</label></th>
                <td>
                    <input type="text" id="category" name="category" required>
                    <p class="vqr-form-description">Category to organize QR codes</p>
                </td>
            </tr>
            <tr>
                <th><label for="code_prefix">Prefix:</label></th>
                <td>
                    <input type="text" id="code_prefix" name="code_prefix" maxlength="4" required>
                    <p class="vqr-form-description">4-character prefix for batch codes</p>
                </td>
            </tr>
            <tr>
                <th><label for="post_id">Strain:</label></th>
                <td>
                    <select id="post_id" name="post_id" required>
                        <option value="">Select a strain...</option>
                        <?php foreach ($strains as $strain): ?>
                            <option value="<?php echo esc_attr($strain->ID); ?>">
                                <?php echo esc_html($strain->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="vqr-form-description">Associate QR codes with a strain</p>
                </td>
            </tr>
            <tr>
                <th><label for="logo_file">Logo:</label></th>
                <td>
                    <input type="file" id="logo_file" name="logo_file" accept="image/png,image/jpeg">
                    <p class="vqr-form-description">Optional logo (PNG/JPEG)</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="generate_qr_codes" value="Generate QR Codes" class="button button-primary">
        </p>
    </form>
    <?php
}

/**
 * Render enhanced statistics with charts
 */
function vqr_render_enhanced_stats($qr_codes) {
    $total = count($qr_codes);
    $scanned = count(array_filter($qr_codes, function($code) { return $code->scan_count > 0; }));
    $never_scanned = $total - $scanned;
    
    // Calculate scan distribution
    $scan_stats = [];
    foreach ($qr_codes as $code) {
        $scan_stats[] = $code->scan_count;
    }
    
    ?>
    <div class="vqr-stats-grid">
        <div class="vqr-stat-item">
            <div class="vqr-stat-number"><?php echo $total; ?></div>
            <div class="vqr-stat-label">Total QR Codes</div>
        </div>
        <div class="vqr-stat-item">
            <div class="vqr-stat-number"><?php echo $scanned; ?></div>
            <div class="vqr-stat-label">Scanned</div>
        </div>
        <div class="vqr-stat-item">
            <div class="vqr-stat-number"><?php echo $never_scanned; ?></div>
            <div class="vqr-stat-label">Never Scanned</div>
        </div>
    </div>
    
    <!-- Mini Chart -->
    <div class="vqr-chart-container">
        <canvas id="qrStatsChart" width="400" height="120"></canvas>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('qrStatsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Scanned', 'Never Scanned'],
                datasets: [{
                    data: [<?php echo $scanned; ?>, <?php echo $never_scanned; ?>],
                    backgroundColor: ['#10b981', '#e2e8f0'],
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });
    });
    </script>
    <?php
}

/**
 * Render quick statistics (keeping for backward compatibility)
 */
function vqr_render_quick_stats($qr_codes) {
    return vqr_render_enhanced_stats($qr_codes);
}

/**
 * Render filters form
 */
function vqr_render_filters_form($categories, $filters, $users = [], $strains = []) {
    ?>
    <form method="get">
        <input type="hidden" name="page" value="verification_qr_manager">
        
        <div class="vqr-filters-grid">
            <div class="vqr-filter-group">
                <label for="batch_code_search">Batch Code:</label>
                <input type="text" id="batch_code_search" name="batch_code_search" 
                       placeholder="Search by batch code..."
                       value="<?php echo esc_attr($filters['batch_code']); ?>">
            </div>
            
            <div class="vqr-filter-group">
                <label for="category">Category:</label>
                <select id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>" <?php selected($filters['category'], $cat); ?>>
                            <?php echo esc_html($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="vqr-filter-group">
                <label for="scanned_status">Status:</label>
                <select id="scanned_status" name="scanned_status">
                    <option value="" <?php selected($filters['scanned'], ''); ?>>All</option>
                    <option value="scanned" <?php selected($filters['scanned'], 'scanned'); ?>>Scanned</option>
                    <option value="not_scanned" <?php selected($filters['scanned'], 'not_scanned'); ?>>Never Scanned</option>
                </select>
            </div>
            
            <div class="vqr-filter-group">
                <label for="order_scan">Sort by:</label>
                <select id="order_scan" name="order_scan">
                    <option value="" <?php selected($filters['order'], ''); ?>>Default</option>
                    <option value="asc" <?php selected($filters['order'], 'asc'); ?>>Scan count ↑</option>
                    <option value="desc" <?php selected($filters['order'], 'desc'); ?>>Scan count ↓</option>
                </select>
            </div>
            
            <div class="vqr-filter-group">
                <label for="user_id">User:</label>
                <select id="user_id" name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($filters['user_id'], $user->ID); ?>>
                            <?php echo esc_html(($user->display_name ?: $user->user_login) . ' (' . $user->user_email . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="vqr-filter-group">
                <label for="strain_id">Strain:</label>
                <select id="strain_id" name="strain_id">
                    <option value="">All Strains</option>
                    <?php foreach ($strains as $strain): ?>
                        <option value="<?php echo esc_attr($strain->ID); ?>" <?php selected($filters['strain_id'], $strain->ID); ?>>
                            <?php echo esc_html($strain->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="vqr-filter-group">
                <label for="user_search">User Search:</label>
                <input type="text" id="user_search" name="user_search" 
                       placeholder="Search by username, email..."
                       value="<?php echo esc_attr($filters['user_search']); ?>"
                       style="font-size: 12px;">
            </div>
            
            <div class="vqr-filter-group">
                <label for="strain_search">Strain Search:</label>
                <input type="text" id="strain_search" name="strain_search" 
                       placeholder="Search by strain name..."
                       value="<?php echo esc_attr($filters['strain_search']); ?>"
                       style="font-size: 12px;">
            </div>
            
            <div class="vqr-filter-group">
                <button type="submit" class="button button-primary">Apply Filters</button>
            </div>
            
            <?php 
            // Check if any filters are applied
            $has_filters = !empty($filters['batch_code']) || !empty($filters['category']) || !empty($filters['scanned']) || 
                          !empty($filters['order']) || !empty($filters['user_search']) || !empty($filters['strain_search']) ||
                          !empty($filters['user_id']) || !empty($filters['strain_id']);
            if ($has_filters): ?>
            <div class="vqr-filter-group">
                <a href="<?php echo admin_url('admin.php?page=verification_qr_manager'); ?>" class="button button-secondary">Reset Filters</a>
            </div>
            <?php endif; ?>
        </div>
    </form>
    <?php
}

/**
 * Render enhanced data table
 */
function vqr_render_enhanced_data_table($qr_codes) {
    ?>
    <div class="vqr-table-container">
        <div class="vqr-table-header">
            <h3><span class="dashicons dashicons-list-view"></span> QR Codes (<?php echo count($qr_codes); ?>)</h3>
            <div class="vqr-table-actions">
                <a href="#" class="button button-secondary">Export CSV</a>
            </div>
        </div>
        
        <form method="POST" id="qr-codes-form">
            <?php wp_nonce_field('vqr_bulk_action', 'vqr_bulk_action_nonce'); ?>
            
            <!-- Bulk Actions Dropdown -->
            <div class="vqr-bulk-actions">
                <select name="vqr_bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="delete">Delete</option>
                    <option value="reset">Reset Scan Count</option>
                    <option value="download">Download ZIP</option>
                    <option value="download_pdf">Download PDF</option>
                </select>
                <button type="submit" class="button">Apply</button>
                <span class="vqr-selected-count" style="margin-left: 10px; color: #646970;"></span>
            </div>
            
            <div class="vqr-table-scroll">
                <table class="vqr-enhanced-table wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                        <th>ID</th>
                        <th>User</th>
                        <th>Strain</th>
                        <th>QR Code</th>
                        <th>Batch Code</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Scan Count</th>
                        <th>First Scanned</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($qr_codes): ?>
                        <?php foreach ($qr_codes as $code): ?>
                            <tr>
                                <td><input type="checkbox" name="qr_ids[]" value="<?php echo esc_attr($code->id); ?>"></td>
                                <td><?php echo esc_html($code->id); ?></td>
                                <td>
                                    <?php if ($code->user_id): ?>
                                        <div class="vqr-user-info">
                                            <strong><?php echo esc_html($code->user_display_name ?: $code->user_login); ?></strong>
                                            <br><small><?php echo esc_html($code->user_email); ?></small>
                                            <br><small style="color: #666;">ID: <?php echo esc_html($code->user_id); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <em style="color: #999;">No user assigned</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($code->post_id && $code->strain_title): ?>
                                        <div class="vqr-strain-info">
                                            <strong><?php echo esc_html($code->strain_title); ?></strong>
                                            <br><small style="color: #666;">ID: <?php echo esc_html($code->post_id); ?></small>
                                        </div>
                                    <?php else: ?>
                                        <em style="color: #999;">No strain linked</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="vqr-qr-preview">
                                        <img src="<?php echo esc_url($code->qr_code); ?>" 
                                             class="vqr-qr-image" 
                                             alt="QR Code <?php echo esc_attr($code->id); ?>"
                                             title="Click to enlarge">
                                    </div>
                                </td>
                                <td><code><?php echo esc_html($code->batch_code); ?></code></td>
                                <td><?php echo esc_html($code->category); ?></td>
                                <td>
                                    <?php echo vqr_get_status_badge($code->scan_count); ?>
                                    <br>
                                    <?php echo vqr_render_print_status_badge($code->id, false); ?>
                                </td>
                                <td><strong><?php echo esc_html($code->scan_count); ?></strong></td>
                                <td><?php echo esc_html($code->first_scanned_at ?: 'Never'); ?></td>
                                <td>
                                    <div class="vqr-action-buttons">
                                        <?php if ($code->scan_count > 0): ?>
                                            <form method="POST" style="display: inline;">
                                                <?php wp_nonce_field('vqr_reset_scan_count', 'vqr_reset_scan_count_nonce'); ?>
                                                <input type="hidden" name="qr_id" value="<?php echo esc_attr($code->id); ?>">
                                                <button type="submit" name="reset_scan_count" class="vqr-action-button vqr-action-reset" 
                                                        onclick="return confirm('Reset scan count for this QR code?');">
                                                    Reset
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="11" style="text-align: center; padding: 40px;">No QR codes found.</td></tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </form>
    </div>
    
    <script>
    // Enhanced select all functionality
    document.getElementById('select-all').addEventListener('change', function(e) {
        const checkboxes = document.querySelectorAll("input[name='qr_ids[]']");
        checkboxes.forEach(cb => cb.checked = e.target.checked);
        updateSelectedCount();
    });
    
    // Update selected count display
    document.querySelectorAll("input[name='qr_ids[]']").forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    function updateSelectedCount() {
        const selected = document.querySelectorAll("input[name='qr_ids[]']:checked").length;
        const counter = document.querySelector('.vqr-selected-count');
        if (selected > 0) {
            counter.textContent = selected + ' item' + (selected !== 1 ? 's' : '') + ' selected';
        } else {
            counter.textContent = '';
        }
    }
    
    // Initialize count
    updateSelectedCount();
    
    </script>
    <?php
}

/**
 * Get status badge HTML
 */
function vqr_get_status_badge($scan_count) {
    if ($scan_count == 0) {
        return '<span class="vqr-status-badge vqr-status-never-scanned">Never Scanned</span>';
    } elseif ($scan_count <= 3) {
        return '<span class="vqr-status-badge vqr-status-low-usage">Low Usage</span>';
    } else {
        return '<span class="vqr-status-badge vqr-status-high-usage">High Usage</span>';
    }
}

/**
 * Display admin notice
 */
function vqr_admin_notice($message, $type = 'info') {
    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
}

/**
 * Show admin notices for legal page status
 */
function vqr_show_legal_page_notices() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Check if legal pages created notice
    if (isset($_GET['legal_pages_created'])) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>Legal pages created successfully!</strong> Terms of Service and Privacy Policy pages are now available.</p></div>';
    }
    
    // Note: Legal page existence checking removed - pages are created manually
    // Links point to /terms-of-service/ and /privacy-policy/ which should be created manually
}
add_action('admin_notices', 'vqr_show_legal_page_notices');

/**
 * Handle individual reset (legacy support)
 */
function vqr_handle_individual_reset($wpdb, $table_name) {
    if (!check_admin_referer('vqr_reset_scan_count', 'vqr_reset_scan_count_nonce')) {
        vqr_admin_notice('Security check failed.', 'error');
        return;
    }

    $qr_id = intval($_POST['qr_id']);
    $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $qr_id));

    $updated = $wpdb->update(
        $table_name,
        ['scan_count' => 0, 'first_scanned_at' => null],
        ['id' => $qr_id],
        ['%d', '%s'],
        ['%d']
    );

    if ($updated !== false) {
        if ($old && $old->post_id) {
            delete_post_meta($old->post_id, 'times_scanned');
            delete_post_meta($old->post_id, 'first_scanned_date');
        }
        vqr_admin_notice("Reset scan count for QR code #{$qr_id}.", 'success');
    } else {
        vqr_admin_notice("Failed to reset scan count for QR code #{$qr_id}.", 'error');
    }
}

/**
 * Handle ZIP download for bulk actions
 */
function vqr_handle_zip_download($wpdb, $table_name, $ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $qr_codes = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids));

    if (!empty($qr_codes)) {
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'qrcodes') . '.zip';
        
        if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
            foreach ($qr_codes as $code) {
                $file_path = str_replace(home_url('/'), ABSPATH, $code->qr_code);
                if (file_exists($file_path)) {
                    $zip->addFile($file_path, basename($file_path));
                }
            }
            $zip->close();

            // Clean any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="qr_codes.zip"');
            header('Content-Length: ' . filesize($zip_filename));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zip_filename);
            unlink($zip_filename);
            exit;
        }
    }
}

/**
 * Display QR scan data meta box content
 */
function vqr_display_scan_data($post) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    
    $qr_codes = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE post_id = %d",
            $post->ID
        )
    );
    
    if ($qr_codes) {
        echo '<h4>Associated QR Codes</h4>';
        foreach ($qr_codes as $code) {
            echo '<div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd;">';
            echo '<strong>Batch Code:</strong> ' . esc_html($code->batch_code) . '<br>';
            echo '<strong>Scan Count:</strong> ' . esc_html($code->scan_count) . '<br>';
            echo '<strong>First Scanned:</strong> ' . ($code->first_scanned_at ? esc_html($code->first_scanned_at) : 'Never') . '<br>';
            echo '<a href="' . esc_url($code->url) . '" target="_blank">View QR Code</a>';
            echo '</div>';
        }
    } else {
        echo '<p>No QR codes associated with this strain yet.</p>';
        echo '<p><a href="' . admin_url('admin.php?page=verification_qr_manager') . '">Generate QR Codes</a></p>';
    }
}

/**
 * Display User Management Page
 */
function vqr_display_user_management_page() {
    // Handle form submissions
    if (isset($_POST['action']) && wp_verify_nonce($_POST['vqr_user_nonce'], 'vqr_user_management')) {
        vqr_handle_user_management_actions();
    }
    
    // Handle sync all action
    if (isset($_POST['sync_all_users'])) {
        $synced = vqr_sync_all_users();
        echo '<div class="notice notice-success"><p>' . sprintf('Successfully synced %d users.', $synced) . '</p></div>';
    }
    
    ?>
    <div class="wrap">
        <h1>QR User Management</h1>
        
        <!-- Sync All Users -->
        <div class="card" style="margin-bottom: 20px;">
            <h2>Sync User Roles</h2>
            <p>Automatically sync all users' WordPress roles with their subscription plans.</p>
            <form method="post">
                <?php wp_nonce_field('vqr_sync_users', 'sync_users_nonce'); ?>
                <input type="submit" name="sync_all_users" class="button button-primary" value="Sync All Users" 
                       onclick="return confirm('This will update all QR customer roles and quotas. Continue?');">
            </form>
        </div>

        <!-- QR Customers Table -->
        <div class="card">
            <h2>QR Customers</h2>
            <?php vqr_display_qr_customers_table(); ?>
        </div>
    </div>
    <?php
}

/**
 * Handle user management form actions
 */
function vqr_handle_user_management_actions() {
    if ($_POST['action'] === 'update_user_plan') {
        $user_id = intval($_POST['user_id']);
        $new_plan = sanitize_text_field($_POST['new_plan']);
        
        if (vqr_admin_update_user_plan($user_id, $new_plan)) {
            echo '<div class="notice notice-success"><p>User plan updated successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update user plan.</p></div>';
        }
    }
    
    if ($_POST['action'] === 'update_user_quota') {
        $user_id = intval($_POST['user_id']);
        $new_quota = intval($_POST['new_quota']);
        
        if (vqr_admin_set_user_quota($user_id, $new_quota)) {
            echo '<div class="notice notice-success"><p>User quota updated successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to update user quota.</p></div>';
        }
    }
}

/**
 * Display QR customers table
 */
function vqr_display_qr_customers_table() {
    // Get all QR customers
    $qr_customers = get_users(array(
        'role__in' => array('qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'),
        'orderby' => 'registered',
        'order' => 'DESC'
    ));
    
    // If no role-based users, get users with QR meta
    if (empty($qr_customers)) {
        $qr_customers = get_users(array(
            'meta_key' => 'vqr_subscription_plan',
            'meta_compare' => 'EXISTS',
            'orderby' => 'registered',
            'order' => 'DESC'
        ));
    }
    
    if (empty($qr_customers)) {
        echo '<p>No QR customers found.</p>';
        return;
    }
    
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>User</th>
                <th>Email</th>
                <th>WordPress Role</th>
                <th>Current Plan</th>
                <th>Quota</th>
                <th>Usage</th>
                <th>QR Codes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($qr_customers as $user): 
                $user_plan = vqr_get_user_plan($user->ID);
                $quota = vqr_get_user_quota($user->ID);
                $usage = vqr_get_user_usage($user->ID);
                
                global $wpdb;
                $qr_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}vqr_codes WHERE user_id = %d",
                    $user->ID
                ));
                
                $role_names = array(
                    'qr_customer_free' => 'QR Customer (Free)',
                    'qr_customer_starter' => 'QR Customer (Starter)', 
                    'qr_customer_pro' => 'QR Customer (Pro)',
                    'qr_customer_enterprise' => 'QR Customer (Enterprise)'
                );
                
                $user_role = !empty($user->roles) ? $user->roles[0] : 'none';
                $role_display = isset($role_names[$user_role]) ? $role_names[$user_role] : ucfirst(str_replace('_', ' ', $user_role));
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($user->display_name); ?></strong><br>
                    <small>ID: <?php echo $user->ID; ?></small>
                </td>
                <td><?php echo esc_html($user->user_email); ?></td>
                <td>
                    <span class="role-badge role-<?php echo esc_attr($user_role); ?>">
                        <?php echo esc_html($role_display); ?>
                    </span>
                </td>
                <td>
                    <strong style="color: <?php echo $user_plan === 'enterprise' ? '#9333ea' : ($user_plan === 'pro' ? '#059669' : ($user_plan === 'starter' ? '#0891b2' : '#6b7280')); ?>">
                        <?php echo ucfirst($user_plan); ?>
                    </strong>
                </td>
                <td>
                    <?php echo $quota === -1 ? 'Unlimited' : number_format($quota); ?>
                </td>
                <td>
                    <span style="color: <?php echo ($quota !== -1 && $usage / $quota > 0.8) ? '#dc2626' : '#059669'; ?>">
                        <?php echo number_format($usage); ?>
                        <?php if ($quota !== -1): ?>
                            (<?php echo round(($usage / $quota) * 100, 1); ?>%)
                        <?php endif; ?>
                    </span>
                </td>
                <td><?php echo number_format($qr_count); ?></td>
                <td>
                    <button type="button" class="button button-small" onclick="vqrShowUserModal(<?php echo $user->ID; ?>)">
                        Manage
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <!-- User Management Modal -->
    <div id="vqr-user-modal" style="display: none;">
        <div class="vqr-modal-backdrop"></div>
        <div class="vqr-modal-content">
            <div class="vqr-modal-header">
                <h3>Manage User</h3>
                <button type="button" class="vqr-modal-close" onclick="vqrCloseUserModal()">&times;</button>
            </div>
            <div class="vqr-modal-body" id="vqr-modal-body">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
    
    <style>
    .role-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .role-qr_customer_free { background: #f3f4f6; color: #374151; }
    .role-qr_customer_starter { background: #dbeafe; color: #1e40af; }
    .role-qr_customer_pro { background: #d1fae5; color: #065f46; }
    .role-qr_customer_enterprise { background: #e9d5ff; color: #6b21a8; }
    
    #vqr-user-modal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 100000;
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
    }
    
    .vqr-modal-content {
        position: relative;
        background: white;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow: hidden;
    }
    
    .vqr-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #e5e7eb;
    }
    
    .vqr-modal-header h3 {
        margin: 0;
    }
    
    .vqr-modal-close {
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 30px;
        height: 30px;
    }
    
    .vqr-modal-body {
        padding: 20px;
        max-height: 60vh;
        overflow-y: auto;
    }
    </style>
    
    <script>
    function vqrShowUserModal(userId) {
        // You'll need to implement the AJAX call to get user details
        // For now, just show a placeholder
        document.getElementById('vqr-modal-body').innerHTML = `
            <form method="post">
                <?php wp_nonce_field('vqr_user_management', 'vqr_user_nonce'); ?>
                <input type="hidden" name="user_id" value="${userId}">
                
                <h4>Change Plan</h4>
                <p>
                    <label>New Plan:</label><br>
                    <select name="new_plan" required>
                        <option value="free">Free</option>
                        <option value="starter">Starter</option>
                        <option value="pro">Pro</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </p>
                <p>
                    <input type="submit" name="action" value="update_user_plan" class="button button-primary">
                </p>
                
                <hr>
                
                <h4>Set Custom Quota</h4>
                <p>
                    <label>Monthly Quota (-1 for unlimited):</label><br>
                    <input type="number" name="new_quota" min="-1" step="1">
                </p>
                <p>
                    <input type="submit" name="action" value="update_user_quota" class="button button-secondary">
                </p>
            </form>
        `;
        document.getElementById('vqr-user-modal').style.display = 'flex';
    }
    
    function vqrCloseUserModal() {
        document.getElementById('vqr-user-modal').style.display = 'none';
    }
    
    // Close modal when clicking backdrop
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('vqr-modal-backdrop')) {
            vqrCloseUserModal();
        }
    });
    </script>
    <?php
}

/**
 * Get the global logo URL for use across the application
 * Returns array with URL and metadata, or false if no logo available
 */
function vqr_get_global_logo() {
    // First try admin-uploaded global logo
    $global_logo_id = get_option('vqr_global_logo_id');
    if ($global_logo_id) {
        $logo_url = wp_get_attachment_image_url($global_logo_id, 'medium');
        if ($logo_url) {
            $logo_data = wp_get_attachment_metadata($global_logo_id);
            return array(
                'url' => $logo_url,
                'full_url' => wp_get_attachment_image_url($global_logo_id, 'full'),
                'alt' => get_post_meta($global_logo_id, '_wp_attachment_image_alt', true) ?: 'Verify 420',
                'width' => isset($logo_data['width']) ? $logo_data['width'] : 300,
                'height' => isset($logo_data['height']) ? $logo_data['height'] : 80,
                'is_global' => true,
                'is_default' => false
            );
        }
    }
    
    // Fallback to generated default logo
    $default_logo_path = vqr_create_default_logo();
    if ($default_logo_path) {
        $upload_dir = wp_upload_dir();
        $logo_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $default_logo_path);
        return array(
            'url' => $logo_url,
            'full_url' => $logo_url,
            'alt' => 'Verify 420',
            'width' => 300,
            'height' => 80,
            'is_global' => false,
            'is_default' => true
        );
    }
    
    return false;
}

/**
 * Get global logo URL (simple version for quick access)
 */
function vqr_get_global_logo_url() {
    $logo = vqr_get_global_logo();
    return $logo ? $logo['url'] : '';
}

/**
 * Display sticker orders management page
 */
function vqr_display_sticker_orders_page() {
    global $wpdb;
    
    // Handle POST actions for order management
    vqr_handle_sticker_order_actions();
    
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Build query with filters
    $where_conditions = ['1=1'];
    $query_params = [];
    
    if (!empty($status_filter)) {
        $where_conditions[] = 'o.status = %s';
        $query_params[] = $status_filter;
    }
    
    if (!empty($user_filter)) {
        $where_conditions[] = 'o.user_id = %d';
        $query_params[] = $user_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = '(o.order_number LIKE %s OR o.shipping_name LIKE %s OR o.shipping_email LIKE %s)';
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $query_params[] = $search_term;
        $query_params[] = $search_term;
        $query_params[] = $search_term;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get orders with user info
    $query = "SELECT o.*, u.display_name, u.user_email, 
                     COUNT(oi.id) as item_count
              FROM {$orders_table} o 
              LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
              LEFT JOIN {$order_items_table} oi ON o.id = oi.order_id 
              WHERE {$where_clause}
              GROUP BY o.id 
              ORDER BY o.created_at DESC";
    
    if (!empty($query_params)) {
        $orders = $wpdb->get_results($wpdb->prepare($query, $query_params));
    } else {
        $orders = $wpdb->get_results($query);
    }
    
    // Get status counts for filter tabs
    $status_counts = $wpdb->get_results(
        "SELECT status, COUNT(*) as count FROM {$orders_table} GROUP BY status"
    );
    
    $counts = ['all' => 0];
    foreach ($status_counts as $count) {
        $counts[$count->status] = $count->count;
        $counts['all'] += $count->count;
    }
    
    // Get users who have placed orders
    $order_users = $wpdb->get_results(
        "SELECT DISTINCT o.user_id, u.display_name 
         FROM {$orders_table} o 
         LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
         ORDER BY u.display_name"
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Sticker Orders</h1>
        <hr class="wp-header-end">
        
        <?php if (isset($_GET['message'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['message'])); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo esc_html(urldecode($_GET['error'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Status Filter Tabs -->
        <div class="subsubsub">
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders'); ?>" 
               class="<?php echo empty($status_filter) ? 'current' : ''; ?>">
                All <span class="count">(<?php echo $counts['all']; ?>)</span>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders&status=pending'); ?>" 
               class="<?php echo $status_filter === 'pending' ? 'current' : ''; ?>">
                Pending <span class="count">(<?php echo $counts['pending'] ?? 0; ?>)</span>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders&status=processing'); ?>" 
               class="<?php echo $status_filter === 'processing' ? 'current' : ''; ?>">
                Processing <span class="count">(<?php echo $counts['processing'] ?? 0; ?>)</span>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders&status=shipped'); ?>" 
               class="<?php echo $status_filter === 'shipped' ? 'current' : ''; ?>">
                Shipped <span class="count">(<?php echo $counts['shipped'] ?? 0; ?>)</span>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders&status=delivered'); ?>" 
               class="<?php echo $status_filter === 'delivered' ? 'current' : ''; ?>">
                Delivered <span class="count">(<?php echo $counts['delivered'] ?? 0; ?>)</span>
            </a> |
            <a href="<?php echo admin_url('admin.php?page=vqr_sticker_orders&status=cancelled'); ?>" 
               class="<?php echo $status_filter === 'cancelled' ? 'current' : ''; ?>">
                Cancelled <span class="count">(<?php echo $counts['cancelled'] ?? 0; ?>)</span>
            </a>
        </div>
        
        <!-- Search and Filters -->
        <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="search-form">
            <input type="hidden" name="page" value="vqr_sticker_orders">
            <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
            <?php endif; ?>
            
            <p class="search-box">
                <label class="screen-reader-text" for="order-search-input">Search Orders:</label>
                <input type="search" id="order-search-input" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search orders, names, emails...">
                
                <select name="user_id">
                    <option value="">All Users</option>
                    <?php foreach ($order_users as $user): ?>
                        <option value="<?php echo $user->user_id; ?>" <?php selected($user_filter, $user->user_id); ?>>
                            <?php echo esc_html($user->display_name ?: 'User #' . $user->user_id); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="submit" name="" id="search-submit" class="button" value="Search Orders">
            </p>
        </form>
        
        <!-- Orders Table -->
        <form method="post" action="">
            <?php wp_nonce_field('vqr_sticker_orders_bulk', 'vqr_orders_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1">Bulk Actions</option>
                        <option value="mark_processing">Mark as Processing</option>
                        <option value="mark_shipped">Mark as Shipped</option>
                        <option value="mark_delivered">Mark as Delivered</option>
                        <option value="mark_cancelled">Mark as Cancelled</option>
                        <option value="delete_orders">Delete Orders</option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="Apply">
                </div>
                
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo count($orders); ?> items</span>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped orders">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1">Select All</label>
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th scope="col" class="manage-column column-order-number">Order</th>
                        <th scope="col" class="manage-column column-customer">Customer</th>
                        <th scope="col" class="manage-column column-status">Status</th>
                        <th scope="col" class="manage-column column-items">Items</th>
                        <th scope="col" class="manage-column column-total">Total</th>
                        <th scope="col" class="manage-column column-date">Date</th>
                        <th scope="col" class="manage-column column-delivery">Delivery</th>
                        <th scope="col" class="manage-column column-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="the-list">
                    <?php if (empty($orders)): ?>
                        <tr class="no-items">
                            <td class="colspanchange" colspan="9">No orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr id="order-<?php echo $order->id; ?>">
                                <th scope="row" class="check-column">
                                    <input id="cb-select-<?php echo $order->id; ?>" type="checkbox" name="order_ids[]" value="<?php echo $order->id; ?>">
                                </th>
                                <td class="order-number column-order-number">
                                    <strong>
                                        <a href="#" onclick="viewOrderDetails(<?php echo $order->id; ?>); return false;">
                                            #<?php echo esc_html($order->order_number); ?>
                                        </a>
                                    </strong>
                                    <?php if (!empty($order->tracking_number)): ?>
                                        <br><small>Tracking: <?php echo esc_html($order->tracking_number); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="customer column-customer">
                                    <strong><?php echo esc_html($order->shipping_name); ?></strong>
                                    <br><a href="mailto:<?php echo esc_attr($order->shipping_email); ?>"><?php echo esc_html($order->shipping_email); ?></a>
                                    <?php if ($order->display_name): ?>
                                        <br><small>User: <?php echo esc_html($order->display_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="status column-status">
                                    <span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                                        <?php echo ucfirst($order->status); ?>
                                    </span>
                                </td>
                                <td class="items column-items">
                                    <?php echo $order->qr_count; ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?>
                                </td>
                                <td class="total column-total">
                                    £<?php echo number_format($order->total_amount, 2); ?>
                                </td>
                                <td class="date column-date">
                                    <?php echo date('M j, Y', strtotime($order->created_at)); ?>
                                    <br><small><?php echo date('g:i a', strtotime($order->created_at)); ?></small>
                                </td>
                                <td class="delivery column-delivery">
                                    <?php if ($order->status === 'delivered' && $order->delivered_at): ?>
                                        <span class="delivery-date">
                                            <strong>Delivered:</strong><br>
                                            <?php echo date('M j, Y', strtotime($order->delivered_at)); ?>
                                            <br><small><?php echo date('g:i a', strtotime($order->delivered_at)); ?></small>
                                        </span>
                                    <?php elseif ($order->status === 'shipped' && $order->shipped_at): ?>
                                        <span class="shipped-date">
                                            <strong>Shipped:</strong> <?php echo date('M j', strtotime($order->shipped_at)); ?>
                                            <?php if (!empty($order->tracking_number)): ?>
                                                <br><small>Track: <?php echo esc_html(substr($order->tracking_number, 0, 10)); ?><?php echo strlen($order->tracking_number) > 10 ? '...' : ''; ?></small>
                                            <?php endif; ?>
                                        </span>
                                    <?php elseif (in_array($order->status, ['processing', 'pending'])): ?>
                                        <div class="delivery-address">
                                            <strong><?php echo esc_html($order->shipping_name); ?></strong><br>
                                            <?php echo esc_html($order->shipping_address); ?><br>
                                            <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                                            <small><?php echo esc_html($order->shipping_country); ?></small>
                                        </div>
                                    <?php elseif ($order->status === 'cancelled'): ?>
                                        <span class="cancelled-status">Cancelled</span>
                                    <?php else: ?>
                                        <span class="unknown-status">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions column-actions">
                                    <div class="action-buttons">
                                        <a href="#" onclick="viewOrderDetails(<?php echo $order->id; ?>); return false;" class="button button-small">View</a>
                                        
                                        <div class="action-dropdown">
                                            <button type="button" class="button button-small dropdown-toggle" onclick="toggleActionDropdown(<?php echo $order->id; ?>); return false;">Downloads ▼</button>
                                            <div class="dropdown-menu" id="actions-<?php echo $order->id; ?>">
                                                <a href="#" onclick="downloadOrderZip(<?php echo $order->id; ?>); return false;" class="dropdown-item">
                                                    <span class="dashicons dashicons-download"></span> ZIP Download
                                                </a>
                                                <a href="#" onclick="downloadOrderPDF(<?php echo $order->id; ?>); return false;" class="dropdown-item">
                                                    <span class="dashicons dashicons-media-document"></span> PDF with Cutlines
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <?php if ($order->status === 'pending'): ?>
                                            <a href="#" onclick="updateOrderStatus(<?php echo $order->id; ?>, 'processing'); return false;" class="button button-small button-primary">Process</a>
                                        <?php elseif ($order->status === 'processing'): ?>
                                            <a href="#" onclick="shipOrder(<?php echo $order->id; ?>); return false;" class="button button-small button-primary">Ship</a>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($order->status, ['pending', 'cancelled'])): ?>
                                            <a href="#" onclick="deleteOrder(<?php echo $order->id; ?>, '<?php echo esc_js($order->order_number); ?>'); return false;" class="button button-small button-link-delete">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
    
    <!-- Order Details Modal -->
    <div id="order-details-modal" style="display: none;">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Order Details</h2>
                <span class="order-modal-close">&times;</span>
            </div>
            <div id="order-details-content">
                Loading...
            </div>
        </div>
    </div>
    
    <style>
    .status-badge {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-pending { background: #fef3c7; color: #d97706; }
    .status-processing { background: #dbeafe; color: #2563eb; }
    .status-shipped { background: #d1fae5; color: #059669; }
    .status-delivered { background: #d1fae5; color: #047857; }
    .status-cancelled { background: #fee2e2; color: #dc2626; }
    
    #order-details-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
    }
    
    .order-modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #ddd;
        border-radius: 6px;
        width: 80%;
        max-width: 800px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .order-modal-header {
        padding: 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f9f9f9;
    }
    
    .order-modal-close {
        color: #aaa;
        font-size: 24px;
        font-weight: bold;
        cursor: pointer;
        line-height: 1;
    }
    
    .order-modal-close:hover {
        color: #000;
    }
    
    #order-details-content {
        padding: 20px;
    }
    
    .order-detail-section {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background: #f9f9f9;
    }
    
    .order-detail-section h3 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 14px;
        text-transform: uppercase;
        font-weight: 600;
    }
    
    .order-items-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    
    .order-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .order-item img {
        width: 30px;
        height: 30px;
        border-radius: 3px;
    }
    
    .order-item-details {
        font-size: 12px;
    }
    
    .order-item-batch {
        font-family: monospace;
        font-weight: 600;
    }
    
    .order-item-type {
        color: #666;
    }
    
    /* Delivery column styles */
    .delivery-date {
        color: #047857;
        font-weight: 600;
    }
    
    .shipped-date {
        color: #059669;
    }
    
    .delivery-address {
        font-size: 12px;
        line-height: 1.4;
        color: #333;
        max-width: 200px;
    }
    
    .delivery-address strong {
        color: #000;
        font-weight: 600;
    }
    
    .processing-status {
        color: #2563eb;
        font-style: italic;
    }
    
    .pending-status {
        color: #d97706;
        font-style: italic;
    }
    
    .cancelled-status {
        color: #dc2626;
        font-style: italic;
    }
    
    .unknown-status {
        color: #6b7280;
    }
    
    /* Action buttons styles */
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        align-items: center;
    }
    
    .action-dropdown {
        position: relative;
        display: inline-block;
    }
    
    .dropdown-toggle {
        cursor: pointer;
    }
    
    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        z-index: 1000;
        min-width: 160px;
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-item {
        display: block;
        padding: 8px 12px;
        text-decoration: none;
        color: #333;
        white-space: nowrap;
        border-bottom: 1px solid #eee;
    }
    
    .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-item:hover {
        background: #f5f5f5;
        color: #333;
    }
    
    .dropdown-item .dashicons {
        margin-right: 6px;
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    </style>
    
    <script>
    function viewOrderDetails(orderId) {
        document.getElementById('order-details-modal').style.display = 'block';
        document.getElementById('order-details-content').innerHTML = 'Loading...';
        
        // AJAX call to get order details
        fetch(ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=vqr_get_order_details&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_admin_orders'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('order-details-content').innerHTML = data.data.html;
            } else {
                document.getElementById('order-details-content').innerHTML = 'Error loading order details.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('order-details-content').innerHTML = 'Error loading order details.';
        });
    }
    
    function updateOrderStatus(orderId, status) {
        if (confirm(`Are you sure you want to mark this order as ${status}?`)) {
            window.location.href = `admin.php?page=vqr_sticker_orders&action=update_status&order_id=${orderId}&status=${status}&nonce=<?php echo wp_create_nonce('vqr_update_order_status'); ?>`;
        }
    }
    
    function shipOrder(orderId) {
        const tracking = prompt('Enter tracking number (optional):');
        let url = `admin.php?page=vqr_sticker_orders&action=ship_order&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_ship_order'); ?>`;
        if (tracking) {
            url += `&tracking_number=${encodeURIComponent(tracking)}`;
        }
        window.location.href = url;
    }
    
    // Toggle action dropdown
    function toggleActionDropdown(orderId) {
        event.preventDefault();
        event.stopPropagation();
        
        const dropdown = document.getElementById('actions-' + orderId);
        const isVisible = dropdown.classList.contains('show');
        
        // Close all dropdowns
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
        
        // Toggle this dropdown
        if (!isVisible) {
            dropdown.classList.add('show');
        }
        
        return false;
    }
    
    // Download order as ZIP
    function downloadOrderZip(orderId) {
        window.location.href = `admin.php?page=vqr_sticker_orders&action=download_zip&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_download_order'); ?>`;
    }
    
    // Download order as PDF with cutlines
    function downloadOrderPDF(orderId) {
        window.location.href = `admin.php?page=vqr_sticker_orders&action=download_pdf&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_download_order'); ?>`;
    }
    
    // Delete order
    function deleteOrder(orderId, orderNumber) {
        if (confirm(`Are you sure you want to delete order #${orderNumber}? This action cannot be undone.`)) {
            window.location.href = `admin.php?page=vqr_sticker_orders&action=delete_order&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_delete_order'); ?>`;
        }
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
        const modal = document.getElementById('order-details-modal');
        if (event.target === modal || event.target.classList.contains('order-modal-close')) {
            modal.style.display = 'none';
        }
        
        // Close dropdowns when clicking outside
        if (!event.target.closest('.action-dropdown')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
            });
        }
    });
    </script>
    <?php
}

/**
 * Handle sticker order admin actions
 */
function vqr_handle_sticker_order_actions() {
    // Handle GET actions (from URL parameters)
    if (isset($_GET['action']) && !empty($_GET['order_id'])) {
        $action = sanitize_text_field($_GET['action']);
        $order_id = intval($_GET['order_id']);
        
        switch ($action) {
            case 'update_status':
                if (wp_verify_nonce($_GET['nonce'], 'vqr_update_order_status')) {
                    $status = sanitize_text_field($_GET['status']);
                    vqr_update_order_status($order_id, $status);
                }
                break;
                
            case 'ship_order':
                if (wp_verify_nonce($_GET['nonce'], 'vqr_ship_order')) {
                    $tracking_number = isset($_GET['tracking_number']) ? sanitize_text_field($_GET['tracking_number']) : '';
                    vqr_ship_order($order_id, $tracking_number);
                }
                break;
                
            case 'delete_order':
                if (wp_verify_nonce($_GET['nonce'], 'vqr_delete_order')) {
                    vqr_delete_order($order_id);
                }
                break;
                
            case 'download_zip':
                if (wp_verify_nonce($_GET['nonce'], 'vqr_download_order')) {
                    vqr_download_order_zip($order_id);
                }
                break;
                
            case 'download_pdf':
                if (wp_verify_nonce($_GET['nonce'], 'vqr_download_order')) {
                    vqr_download_order_pdf($order_id);
                }
                break;
        }
    }
    
    // Handle POST actions (bulk actions)
    if (isset($_POST['action']) && $_POST['action'] !== '-1' && !empty($_POST['order_ids'])) {
        if (wp_verify_nonce($_POST['vqr_orders_nonce'], 'vqr_sticker_orders_bulk')) {
            $action = sanitize_text_field($_POST['action']);
            $order_ids = array_map('intval', $_POST['order_ids']);
            
            switch ($action) {
                case 'mark_processing':
                    vqr_bulk_update_order_status($order_ids, 'processing');
                    break;
                case 'mark_shipped':
                    vqr_bulk_update_order_status($order_ids, 'shipped');
                    break;
                case 'mark_delivered':
                    vqr_bulk_update_order_status($order_ids, 'delivered');
                    break;
                case 'mark_cancelled':
                    vqr_bulk_update_order_status($order_ids, 'cancelled');
                    break;
                case 'delete_orders':
                    vqr_bulk_delete_orders($order_ids);
                    break;
            }
        }
    }
}

/**
 * Update single order status
 */
function vqr_update_order_status($order_id, $status) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Invalid status')));
        exit;
    }
    
    // Get current order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Order not found')));
        exit;
    }
    
    $update_data = [
        'status' => $status,
        'updated_at' => current_time('mysql')
    ];
    
    // Add timestamp for specific statuses
    if ($status === 'shipped' && empty($order->shipped_at)) {
        $update_data['shipped_at'] = current_time('mysql');
    } elseif ($status === 'delivered' && empty($order->delivered_at)) {
        $update_data['delivered_at'] = current_time('mysql');
    }
    
    $result = $wpdb->update(
        $orders_table,
        $update_data,
        ['id' => $order_id],
        ['%s', '%s', '%s'],
        ['%d']
    );
    
    if ($result !== false) {
        // Send email notification
        vqr_send_order_status_email($order_id, $status);
        
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&message=' . urlencode("Order #{$order->order_number} marked as {$status}")));
    } else {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Failed to update order status')));
    }
    exit;
}

/**
 * Ship an order with optional tracking number
 */
function vqr_ship_order($order_id, $tracking_number = '') {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    
    // Get current order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Order not found')));
        exit;
    }
    
    $update_data = [
        'status' => 'shipped',
        'shipped_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];
    
    if (!empty($tracking_number)) {
        $update_data['tracking_number'] = $tracking_number;
    }
    
    $format = ['%s', '%s', '%s'];
    if (!empty($tracking_number)) {
        $format[] = '%s';
    }
    
    $result = $wpdb->update(
        $orders_table,
        $update_data,
        ['id' => $order_id],
        $format,
        ['%d']
    );
    
    if ($result !== false) {
        // Send shipping notification email
        vqr_send_order_status_email($order_id, 'shipped', $tracking_number);
        
        $message = "Order #{$order->order_number} marked as shipped";
        if (!empty($tracking_number)) {
            $message .= " with tracking number: {$tracking_number}";
        }
        
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&message=' . urlencode($message)));
    } else {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Failed to ship order')));
    }
    exit;
}

/**
 * Bulk update order statuses
 */
function vqr_bulk_update_order_status($order_ids, $status) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $valid_statuses)) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Invalid status')));
        exit;
    }
    
    $updated_count = 0;
    $failed_orders = [];
    
    foreach ($order_ids as $order_id) {
        // Get current order details
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$orders_table} WHERE id = %d",
            $order_id
        ));
        
        if (!$order) {
            $failed_orders[] = $order_id;
            continue;
        }
        
        $update_data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];
        
        // Add timestamp for specific statuses
        if ($status === 'shipped' && empty($order->shipped_at)) {
            $update_data['shipped_at'] = current_time('mysql');
        } elseif ($status === 'delivered' && empty($order->delivered_at)) {
            $update_data['delivered_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update(
            $orders_table,
            $update_data,
            ['id' => $order_id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result !== false) {
            // Send email notification
            vqr_send_order_status_email($order_id, $status);
            $updated_count++;
        } else {
            $failed_orders[] = $order->order_number;
        }
    }
    
    $message = "Updated {$updated_count} orders to {$status}";
    if (!empty($failed_orders)) {
        $message .= ". Failed to update: " . implode(', ', $failed_orders);
    }
    
    wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&message=' . urlencode($message)));
    exit;
}

/**
 * Send order status update email
 */
function vqr_send_order_status_email($order_id, $status, $tracking_number = '') {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$order_items_table} WHERE order_id = %d",
        $order_id
    ));
    
    // Prepare email subject and content
    $subjects = [
        'processing' => "Order Processing - #{$order->order_number}",
        'shipped' => "Order Shipped - #{$order->order_number}",
        'delivered' => "Order Delivered - #{$order->order_number}",
        'cancelled' => "Order Cancelled - #{$order->order_number}"
    ];
    
    $subject = isset($subjects[$status]) ? $subjects[$status] : "Order Update - #{$order->order_number}";
    $to = $order->shipping_email;
    
    // Generate HTML email body
    $body = vqr_get_order_status_email_template($order, $items, $status, $tracking_number);
    
    // Send email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Verify 420 <noreply@verify420.com>',
        'Reply-To: support@verify420.com'
    ];
    
    $sent = wp_mail($to, $subject, $body, $headers);
    
    // Log the email attempt
    error_log(sprintf(
        'Order Status Email: Order #%s status changed to %s, email sent to %s: %s',
        $order->order_number,
        $status,
        $to,
        $sent ? 'SUCCESS' : 'FAILED'
    ));
    
    return $sent;
}

/**
 * Get order status email template
 */
function vqr_get_order_status_email_template($order, $items, $status, $tracking_number = '') {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $order_url = home_url('/app/basket');
    
    // Status-specific content
    $status_configs = [
        'processing' => [
            'title' => 'Order Processing',
            'message' => 'Great news! Your sticker order is now being processed and will be shipped soon.',
            'color' => '#3b82f6',
            'bg_color' => '#eff6ff',
            'icon' => '⚙️'
        ],
        'shipped' => [
            'title' => 'Order Shipped',
            'message' => 'Your stickers are on their way! Your order has been shipped and should arrive soon.',
            'color' => '#10b981',
            'bg_color' => '#ecfdf5',
            'icon' => '📦'
        ],
        'delivered' => [
            'title' => 'Order Delivered',
            'message' => 'Your sticker order has been delivered! We hope you love your new QR code stickers.',
            'color' => '#059669',
            'bg_color' => '#d1fae5',
            'icon' => '✅'
        ],
        'cancelled' => [
            'title' => 'Order Cancelled',
            'message' => 'Your sticker order has been cancelled. If you have any questions, please contact our support team.',
            'color' => '#dc2626',
            'bg_color' => '#fef2f2',
            'icon' => '❌'
        ]
    ];
    
    $config = isset($status_configs[$status]) ? $status_configs[$status] : $status_configs['processing'];
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($config['title']); ?> - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>CC 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px;
            }
            .status-banner {
                background: <?php echo $config['bg_color']; ?>;
                border-left: 4px solid <?php echo $config['color']; ?>;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: center;
            }
            .status-banner h2 {
                margin: 0 0 10px 0;
                color: <?php echo $config['color']; ?>;
                font-size: 24px;
                font-weight: 600;
            }
            .status-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .order-summary {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .order-summary h3 {
                margin: 0 0 16px 0;
                color: #1f2937;
                font-size: 18px;
            }
            .order-detail {
                display: flex;
                justify-content: space-between;
                margin: 8px 0;
                padding: 4px 0;
            }
            .order-detail strong {
                color: #1f2937;
            }
            .order-number {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #10b981;
            }
            .tracking-info {
                background: #eff6ff;
                border-left: 4px solid #3b82f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .tracking-info h3 {
                margin: 0 0 12px 0;
                color: #1e40af;
                font-size: 16px;
            }
            .tracking-number {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #1e40af;
                font-size: 18px;
                background: white;
                padding: 12px;
                border-radius: 6px;
                border: 2px solid #3b82f6;
                text-align: center;
                margin: 12px 0;
            }
            .shipping-info {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .shipping-info h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .shipping-address {
                color: #4b5563;
                line-height: 1.5;
            }
            .track-button {
                display: inline-block;
                background: linear-gradient(135deg, <?php echo $config['color']; ?> 0%, <?php echo $config['color']; ?>CC 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .track-button:hover {
                opacity: 0.9;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <div class="status-banner">
                    <div class="status-icon"><?php echo $config['icon']; ?></div>
                    <h2><?php echo esc_html($config['title']); ?></h2>
                    <p style="color: <?php echo $config['color']; ?>; font-weight: 500; margin: 0;">
                        <?php echo esc_html($config['message']); ?>
                    </p>
                </div>
                
                <p>Hello <strong><?php echo esc_html($order->shipping_name); ?></strong>,</p>
                
                <p>Your Verify 420 sticker order has been updated with a new status.</p>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="order-detail">
                        <span><strong>Order Number:</strong></span>
                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Status:</strong></span>
                        <span style="color: <?php echo $config['color']; ?>; font-weight: 600; text-transform: capitalize;">
                            <?php echo esc_html($status); ?>
                        </span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Items:</strong></span>
                        <span><?php echo esc_html($order->qr_count); ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Total:</strong></span>
                        <span>£<?php echo number_format($order->total_amount, 2); ?></span>
                    </div>
                    <?php if (!empty($order->tracking_number)): ?>
                    <div class="order-detail">
                        <span><strong>Tracking:</strong></span>
                        <span style="font-family: monospace; font-weight: 600;"><?php echo esc_html($order->tracking_number); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($tracking_number) && $status === 'shipped'): ?>
                <div class="tracking-info">
                    <h3>Tracking Information</h3>
                    <p>Your package is now in transit. You can track your shipment using the tracking number below:</p>
                    <div class="tracking-number"><?php echo esc_html($tracking_number); ?></div>
                    <p style="font-size: 14px; color: #6b7280; margin: 0;">
                        Please allow 24-48 hours for tracking information to become available.
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="shipping-info">
                    <h3>Shipping Address</h3>
                    <div class="shipping-address">
                        <?php echo esc_html($order->shipping_name); ?><br>
                        <?php echo esc_html($order->shipping_address); ?><br>
                        <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                        <?php echo esc_html($order->shipping_country); ?>
                    </div>
                </div>
                
                <?php if ($status !== 'cancelled'): ?>
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($order_url); ?>" class="track-button">
                        View Order Details
                    </a>
                </div>
                <?php endif; ?>
                
                <p>If you have any questions about your order, please don't hesitate to contact us.</p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Send payment confirmation email (for future Stripe integration)
 */
function vqr_send_payment_confirmation_email($order_id, $payment_data = []) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$order_items_table} WHERE order_id = %d",
        $order_id
    ));
    
    $subject = "Payment Confirmation - #{$order->order_number}";
    $to = $order->shipping_email;
    
    // Generate HTML email body
    $body = vqr_get_payment_confirmation_email_template($order, $items, $payment_data);
    
    // Send email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Verify 420 <noreply@verify420.com>',
        'Reply-To: support@verify420.com'
    ];
    
    $sent = wp_mail($to, $subject, $body, $headers);
    
    // Log the email attempt
    error_log(sprintf(
        'Payment Confirmation Email: Order #%s payment confirmation sent to %s: %s',
        $order->order_number,
        $to,
        $sent ? 'SUCCESS' : 'FAILED'
    ));
    
    return $sent;
}

/**
 * Get payment confirmation email template (for future Stripe integration)
 */
function vqr_get_payment_confirmation_email_template($order, $items, $payment_data = []) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $order_url = home_url('/app/basket');
    
    // Default payment data structure for when Stripe is integrated
    $payment_defaults = [
        'payment_method' => 'Credit Card',
        'payment_id' => 'pi_' . strtoupper(substr(md5($order->order_number), 0, 12)),
        'amount' => $order->total_amount,
        'currency' => 'GBP',
        'receipt_url' => '#',
        'card_last4' => '****'
    ];
    
    $payment = array_merge($payment_defaults, $payment_data);
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Confirmation - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px;
            }
            .success-banner {
                background: #ecfdf5;
                border-left: 4px solid #10b981;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: center;
            }
            .success-banner h2 {
                margin: 0 0 10px 0;
                color: #10b981;
                font-size: 24px;
                font-weight: 600;
            }
            .success-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .payment-summary {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .payment-summary h3 {
                margin: 0 0 16px 0;
                color: #1f2937;
                font-size: 18px;
            }
            .payment-detail {
                display: flex;
                justify-content: space-between;
                margin: 8px 0;
                padding: 4px 0;
            }
            .payment-detail strong {
                color: #1f2937;
            }
            .payment-id {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #6b7280;
                font-size: 14px;
            }
            .amount-highlight {
                background: #10b981;
                color: white;
                padding: 16px;
                border-radius: 6px;
                text-align: center;
                font-size: 24px;
                font-weight: 600;
                margin: 20px 0;
            }
            .order-summary {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .order-summary h3 {
                margin: 0 0 16px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .order-detail {
                display: flex;
                justify-content: space-between;
                margin: 6px 0;
                padding: 2px 0;
            }
            .order-number {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #10b981;
            }
            .receipt-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .receipt-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .security-note {
                background: #eff6ff;
                border-left: 4px solid #3b82f6;
                padding: 16px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .security-note h4 {
                margin: 0 0 8px 0;
                color: #1e40af;
                font-size: 14px;
            }
            .security-note p {
                margin: 0;
                color: #1e40af;
                font-size: 14px;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <div class="success-banner">
                    <div class="success-icon">💳</div>
                    <h2>Payment Successful</h2>
                    <p style="color: #10b981; font-weight: 500; margin: 0;">
                        Your payment has been processed successfully!
                    </p>
                </div>
                
                <p>Hello <strong><?php echo esc_html($order->shipping_name); ?></strong>,</p>
                
                <p>Thank you for your payment! Your order has been confirmed and will be processed shortly.</p>
                
                <div class="amount-highlight">
                    Payment: £<?php echo number_format($payment['amount'], 2); ?>
                </div>
                
                <div class="payment-summary">
                    <h3>Payment Details</h3>
                    <div class="payment-detail">
                        <span><strong>Payment Method:</strong></span>
                        <span><?php echo esc_html($payment['payment_method']); ?> ending in <?php echo esc_html($payment['card_last4']); ?></span>
                    </div>
                    <div class="payment-detail">
                        <span><strong>Payment ID:</strong></span>
                        <span class="payment-id"><?php echo esc_html($payment['payment_id']); ?></span>
                    </div>
                    <div class="payment-detail">
                        <span><strong>Amount:</strong></span>
                        <span>£<?php echo number_format($payment['amount'], 2); ?> <?php echo strtoupper($payment['currency']); ?></span>
                    </div>
                    <div class="payment-detail">
                        <span><strong>Date:</strong></span>
                        <span><?php echo date('F j, Y g:i a'); ?></span>
                    </div>
                </div>
                
                <div class="order-summary">
                    <h3>Order Summary</h3>
                    <div class="order-detail">
                        <span><strong>Order Number:</strong></span>
                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Items:</strong></span>
                        <span><?php echo esc_html($order->qr_count); ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Status:</strong></span>
                        <span style="color: #f59e0b; font-weight: 600;">Processing</span>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($payment['receipt_url']); ?>" class="receipt-button">
                        Download Receipt
                    </a>
                    <br>
                    <a href="<?php echo esc_url($order_url); ?>" class="receipt-button" style="margin-left: 10px;">
                        Track Your Order
                    </a>
                </div>
                
                <div class="security-note">
                    <h4>Security & Privacy</h4>
                    <p>Your payment information is secure and encrypted. We never store your full card details on our servers.</p>
                </div>
                
                <p>You'll receive another email once your order is ready to ship. If you have any questions, please don't hesitate to contact us.</p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler for getting order details
 */
function vqr_ajax_get_order_details() {
    if (!wp_verify_nonce($_POST['nonce'], 'vqr_admin_orders')) {
        wp_send_json_error('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $order_id = intval($_POST['order_id']);
    
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    $qr_table = $wpdb->prefix . 'vqr_codes';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT o.*, u.display_name, u.user_email 
         FROM {$orders_table} o 
         LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
         WHERE o.id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_send_json_error('Order not found');
    }
    
    // Get order items with QR code details
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT oi.*, qr.qr_code, qr.batch_code as qr_batch_code, qr.url, qr.scan_count 
         FROM {$order_items_table} oi 
         LEFT JOIN {$qr_table} qr ON oi.qr_code_id = qr.id 
         WHERE oi.order_id = %d 
         ORDER BY oi.created_at ASC",
        $order_id
    ));
    
    // Build HTML response
    ob_start();
    ?>
    <div class="order-detail-section">
        <h3>Order Information</h3>
        <table class="form-table">
            <tr>
                <th>Order Number:</th>
                <td><strong>#<?php echo esc_html($order->order_number); ?></strong></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <span class="status-badge status-<?php echo esc_attr($order->status); ?>">
                        <?php echo ucfirst($order->status); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Total Amount:</th>
                <td>£<?php echo number_format($order->total_amount, 2); ?></td>
            </tr>
            <tr>
                <th>Items:</th>
                <td><?php echo $order->qr_count; ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?></td>
            </tr>
            <?php if (!empty($order->tracking_number)): ?>
            <tr>
                <th>Tracking Number:</th>
                <td><code><?php echo esc_html($order->tracking_number); ?></code></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Created:</th>
                <td><?php echo date('F j, Y g:i a', strtotime($order->created_at)); ?></td>
            </tr>
            <?php if ($order->shipped_at): ?>
            <tr>
                <th>Shipped:</th>
                <td><?php echo date('F j, Y g:i a', strtotime($order->shipped_at)); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($order->delivered_at): ?>
            <tr>
                <th>Delivered:</th>
                <td><?php echo date('F j, Y g:i a', strtotime($order->delivered_at)); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="order-detail-section">
        <h3>Customer Information</h3>
        <table class="form-table">
            <tr>
                <th>Name:</th>
                <td><?php echo esc_html($order->shipping_name); ?></td>
            </tr>
            <tr>
                <th>Email:</th>
                <td><a href="mailto:<?php echo esc_attr($order->shipping_email); ?>"><?php echo esc_html($order->shipping_email); ?></a></td>
            </tr>
            <?php if ($order->display_name): ?>
            <tr>
                <th>WordPress User:</th>
                <td><?php echo esc_html($order->display_name); ?> (<?php echo esc_html($order->user_email); ?>)</td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Shipping Address:</th>
                <td>
                    <?php echo esc_html($order->shipping_address); ?><br>
                    <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                    <?php echo esc_html($order->shipping_country); ?>
                </td>
            </tr>
            <?php if (!empty($order->notes)): ?>
            <tr>
                <th>Order Notes:</th>
                <td><?php echo nl2br(esc_html($order->notes)); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="order-detail-section">
        <h3>Order Items (<?php echo count($items); ?>)</h3>
        <div class="order-items-grid">
            <?php foreach ($items as $item): ?>
                <div class="order-item">
                    <?php if (!empty($item->qr_code)): ?>
                        <img src="<?php echo esc_url($item->qr_code); ?>" alt="QR Code">
                    <?php else: ?>
                        <div style="width: 30px; height: 30px; background: #f0f0f0; border-radius: 3px; display: flex; align-items: center; justify-content: center; color: #999; font-size: 10px;">QR</div>
                    <?php endif; ?>
                    <div class="order-item-details">
                        <div class="order-item-batch">#<?php echo esc_html($item->batch_code); ?></div>
                        <div class="order-item-type"><?php echo ucfirst($item->sticker_type); ?> sticker</div>
                        <div>£<?php echo number_format($item->unit_price, 2); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="order-detail-section">
        <h3>Order Actions</h3>
        <p>
            <?php if ($order->status === 'pending'): ?>
                <a href="admin.php?page=vqr_sticker_orders&action=update_status&order_id=<?php echo $order->id; ?>&status=processing&nonce=<?php echo wp_create_nonce('vqr_update_order_status'); ?>" 
                   class="button button-primary" onclick="return confirm('Mark this order as processing?')">
                    Mark as Processing
                </a>
            <?php elseif ($order->status === 'processing'): ?>
                <a href="#" onclick="shipOrderFromModal(<?php echo $order->id; ?>); return false;" class="button button-primary">
                    Mark as Shipped
                </a>
            <?php elseif ($order->status === 'shipped'): ?>
                <a href="admin.php?page=vqr_sticker_orders&action=update_status&order_id=<?php echo $order->id; ?>&status=delivered&nonce=<?php echo wp_create_nonce('vqr_update_order_status'); ?>" 
                   class="button button-primary" onclick="return confirm('Mark this order as delivered?')">
                    Mark as Delivered
                </a>
            <?php endif; ?>
            
            <?php if (in_array($order->status, ['pending', 'processing'])): ?>
                <a href="admin.php?page=vqr_sticker_orders&action=update_status&order_id=<?php echo $order->id; ?>&status=cancelled&nonce=<?php echo wp_create_nonce('vqr_update_order_status'); ?>" 
                   class="button button-secondary" onclick="return confirm('Are you sure you want to cancel this order?')" 
                   style="margin-left: 10px;">
                    Cancel Order
                </a>
            <?php endif; ?>
        </p>
    </div>
    
    <script>
    function shipOrderFromModal(orderId) {
        const tracking = prompt('Enter tracking number (optional):');
        let url = `admin.php?page=vqr_sticker_orders&action=ship_order&order_id=${orderId}&nonce=<?php echo wp_create_nonce('vqr_ship_order'); ?>`;
        if (tracking) {
            url += `&tracking_number=${encodeURIComponent(tracking)}`;
        }
        window.location.href = url;
    }
    </script>
    <?php
    
    $html = ob_get_clean();
    
    wp_send_json_success(['html' => $html]);
}
add_action('wp_ajax_vqr_get_order_details', 'vqr_ajax_get_order_details');

/**
 * Send order confirmation email to customer
 */
function vqr_send_order_confirmation_email($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$order_items_table} WHERE order_id = %d",
        $order_id
    ));
    
    $subject = "Order Confirmation - #{$order->order_number}";
    $to = $order->shipping_email;
    
    // Generate HTML email body
    $body = vqr_get_order_confirmation_email_template($order, $items);
    
    // Send email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Verify 420 <noreply@verify420.com>',
        'Reply-To: support@verify420.com'
    ];
    
    $sent = wp_mail($to, $subject, $body, $headers);
    
    // Log the email attempt
    error_log(sprintf(
        'Order Confirmation Email: Order #%s confirmation sent to %s: %s',
        $order->order_number,
        $to,
        $sent ? 'SUCCESS' : 'FAILED'
    ));
    
    return $sent;
}

/**
 * Get order confirmation email template
 */
function vqr_get_order_confirmation_email_template($order, $items) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $order_url = home_url('/app/basket');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Confirmation - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px;
            }
            .content h2 {
                color: #1f2937;
                font-size: 20px;
                margin-bottom: 20px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .order-summary {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .order-summary h3 {
                margin: 0 0 16px 0;
                color: #1f2937;
                font-size: 18px;
            }
            .order-detail {
                display: flex;
                justify-content: space-between;
                margin: 8px 0;
                padding: 4px 0;
            }
            .order-detail strong {
                color: #1f2937;
            }
            .order-number {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #10b981;
            }
            .shipping-info {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .shipping-info h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .shipping-address {
                color: #4b5563;
                line-height: 1.5;
            }
            .order-items {
                margin: 20px 0;
            }
            .order-items h3 {
                color: #1f2937;
                font-size: 16px;
                margin-bottom: 12px;
            }
            .item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
                margin: 8px 0;
            }
            .item-info {
                flex: 1;
            }
            .batch-code {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #dc2626;
                font-size: 14px;
            }
            .sticker-type {
                color: #6b7280;
                font-size: 14px;
                text-transform: capitalize;
            }
            .item-price {
                font-weight: 600;
                color: #10b981;
            }
            .total-amount {
                background: #10b981;
                color: white;
                padding: 16px;
                border-radius: 6px;
                text-align: center;
                font-size: 18px;
                font-weight: 600;
                margin: 20px 0;
            }
            .next-steps {
                background: #eff6ff;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
                margin: 20px 0;
            }
            .next-steps h3 {
                margin: 0 0 12px 0;
                color: #1e40af;
                font-size: 16px;
            }
            .next-steps ol {
                margin: 0;
                padding-left: 20px;
                color: #1e40af;
            }
            .track-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 12px 24px;
                border-radius: 6px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .track-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
            .notes {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 16px;
                border-radius: 4px;
                margin: 16px 0;
            }
            .notes h4 {
                margin: 0 0 8px 0;
                color: #92400e;
                font-size: 14px;
            }
            .notes p {
                margin: 0;
                color: #78350f;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            </div>
            
            <div class="content">
                <h2>Order Confirmation</h2>
                
                <p>Hello <strong><?php echo esc_html($order->shipping_name); ?></strong>,</p>
                
                <p>Thank you for your Verify 420 sticker order! We've received your order and it's being prepared for processing.</p>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="order-detail">
                        <span><strong>Order Number:</strong></span>
                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Status:</strong></span>
                        <span style="color: #3b82f6; font-weight: 600;">Processing</span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Items:</strong></span>
                        <span><?php echo esc_html($order->qr_count); ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Order Date:</strong></span>
                        <span><?php echo date('F j, Y g:i a', strtotime($order->created_at)); ?></span>
                    </div>
                </div>
                
                <div class="total-amount">
                    Total: £<?php echo number_format($order->total_amount, 2); ?>
                </div>
                
                <?php if (!empty($items)): ?>
                <div class="order-items">
                    <h3>Order Items</h3>
                    <?php foreach ($items as $item): ?>
                    <div class="item">
                        <div class="item-info">
                            <div class="batch-code">#<?php echo esc_html($item->batch_code); ?></div>
                            <div class="sticker-type"><?php echo esc_html($item->sticker_type); ?> sticker</div>
                        </div>
                        <div class="item-price">£<?php echo number_format($item->unit_price, 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="shipping-info">
                    <h3>Shipping Address</h3>
                    <div class="shipping-address">
                        <?php echo esc_html($order->shipping_name); ?><br>
                        <?php echo esc_html($order->shipping_address); ?><br>
                        <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                        <?php echo esc_html($order->shipping_country); ?>
                    </div>
                </div>
                
                <?php if (!empty($order->notes)): ?>
                <div class="notes">
                    <h4>Special Instructions</h4>
                    <p><?php echo esc_html($order->notes); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="next-steps">
                    <h3>What happens next?</h3>
                    <ol>
                        <li>We'll process your order within 1-2 business days</li>
                        <li>Your stickers will be printed and prepared for shipping</li>
                        <li>You'll receive a shipping confirmation with tracking information</li>
                        <li>Your stickers will be delivered to your address</li>
                    </ol>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url($order_url); ?>" class="track-button">
                        Track Your Order
                    </a>
                </div>
                
                <p>If you have any questions about your order, please don't hesitate to contact us.</p>
            </div>
            
            <div class="footer">
                <p>
                    This email was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Send new order notification to admin
 */
function vqr_send_new_order_admin_email($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get order details with user info
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT o.*, u.display_name, u.user_email 
         FROM {$orders_table} o 
         LEFT JOIN {$wpdb->users} u ON o.user_id = u.ID 
         WHERE o.id = %d",
        $order_id
    ));
    
    if (!$order) {
        return false;
    }
    
    // Get order items
    $items = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$order_items_table} WHERE order_id = %d",
        $order_id
    ));
    
    $subject = "New Order Received - #{$order->order_number}";
    
    // Get admin email from settings or fallback to site admin
    $admin_email = get_option('vqr_admin_notification_email', get_option('admin_email'));
    
    // Generate HTML email body
    $body = vqr_get_admin_new_order_email_template($order, $items);
    
    // Send email with CC and BCC if configured
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Verify 420 <noreply@verify420.com>',
        'Reply-To: ' . $order->shipping_email
    ];
    
    // Add CC emails if configured
    $cc_emails = vqr_get_admin_cc_emails();
    if (!empty($cc_emails)) {
        $headers[] = 'Cc: ' . implode(', ', $cc_emails);
    }
    
    // Add BCC emails if configured
    $bcc_emails = vqr_get_admin_bcc_emails();
    if (!empty($bcc_emails)) {
        $headers[] = 'Bcc: ' . implode(', ', $bcc_emails);
    }
    
    $sent = wp_mail($admin_email, $subject, $body, $headers);
    
    // Log the email attempt with recipient details
    $recipients_info = "TO: {$admin_email}";
    if (!empty($cc_emails)) {
        $recipients_info .= " | CC: " . implode(', ', $cc_emails);
    }
    if (!empty($bcc_emails)) {
        $recipients_info .= " | BCC: " . implode(', ', $bcc_emails);
    }
    
    error_log(sprintf(
        'New Order Admin Email: Order #%s notification sent (%s): %s',
        $order->order_number,
        $recipients_info,
        $sent ? 'SUCCESS' : 'FAILED'
    ));
    
    return $sent;
}

/**
 * Get admin new order email template
 */
function vqr_get_admin_new_order_email_template($order, $items) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $admin_url = admin_url('admin.php?page=vqr_sticker_orders');
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>New Order Notification - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .content {
                padding: 30px;
            }
            .alert-banner {
                background: #ecfdf5;
                border-left: 4px solid #10b981;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                text-align: center;
            }
            .alert-banner h2 {
                margin: 0 0 10px 0;
                color: #047857;
                font-size: 24px;
                font-weight: 600;
            }
            .alert-icon {
                font-size: 48px;
                margin-bottom: 16px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .order-summary {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #10b981;
            }
            .order-summary h3 {
                margin: 0 0 16px 0;
                color: #1f2937;
                font-size: 18px;
            }
            .order-detail {
                display: flex;
                justify-content: space-between;
                margin: 8px 0;
                padding: 4px 0;
                border-bottom: 1px solid #e5e7eb;
            }
            .order-detail:last-child {
                border-bottom: none;
            }
            .order-detail strong {
                color: #1f2937;
            }
            .order-number {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #10b981;
            }
            .customer-info {
                background: #f3f4f6;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .customer-info h3 {
                margin: 0 0 12px 0;
                color: #1f2937;
                font-size: 16px;
            }
            .customer-detail {
                margin: 8px 0;
                color: #4b5563;
            }
            .customer-detail strong {
                color: #1f2937;
            }
            .customer-email {
                color: #3b82f6;
                text-decoration: none;
            }
            .order-items {
                margin: 20px 0;
            }
            .order-items h3 {
                color: #1f2937;
                font-size: 16px;
                margin-bottom: 12px;
            }
            .item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 12px;
                background: #f9fafb;
                border-radius: 6px;
                margin: 8px 0;
                border: 1px solid #e5e7eb;
            }
            .item-info {
                flex: 1;
            }
            .batch-code {
                font-family: 'Monaco', 'Menlo', monospace;
                font-weight: 600;
                color: #dc2626;
                font-size: 14px;
            }
            .sticker-type {
                color: #6b7280;
                font-size: 14px;
                text-transform: capitalize;
            }
            .item-price {
                font-weight: 600;
                color: #10b981;
            }
            .total-amount {
                background: #10b981;
                color: white;
                padding: 16px;
                border-radius: 6px;
                text-align: center;
                font-size: 18px;
                font-weight: 600;
                margin: 20px 0;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 14px 28px;
                border-radius: 6px;
                font-weight: 600;
                margin: 20px 0;
                text-align: center;
                font-size: 16px;
            }
            .action-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .priority-note {
                background: #fef3c7;
                border-left: 4px solid #f59e0b;
                padding: 16px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .priority-note h4 {
                margin: 0 0 8px 0;
                color: #92400e;
                font-size: 14px;
            }
            .priority-note p {
                margin: 0;
                color: #78350f;
                font-size: 14px;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
            .notes {
                background: #fef2f2;
                border-left: 4px solid #ef4444;
                padding: 16px;
                border-radius: 4px;
                margin: 16px 0;
            }
            .notes h4 {
                margin: 0 0 8px 0;
                color: #dc2626;
                font-size: 14px;
            }
            .notes p {
                margin: 0;
                color: #991b1b;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <h1><?php echo esc_html($site_name); ?> Admin</h1>
            </div>
            
            <div class="content">
                <div class="alert-banner">
                    <div class="alert-icon">📦</div>
                    <h2>New Order Received</h2>
                    <p style="color: #047857; font-weight: 500; margin: 0;">
                        A new sticker order has been placed and requires processing
                    </p>
                </div>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="order-detail">
                        <span><strong>Order Number:</strong></span>
                        <span class="order-number">#<?php echo esc_html($order->order_number); ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Status:</strong></span>
                        <span style="color: #3b82f6; font-weight: 600;">Processing</span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Items:</strong></span>
                        <span><?php echo esc_html($order->qr_count); ?> sticker<?php echo $order->qr_count > 1 ? 's' : ''; ?></span>
                    </div>
                    <div class="order-detail">
                        <span><strong>Order Date:</strong></span>
                        <span><?php echo date('F j, Y g:i a', strtotime($order->created_at)); ?></span>
                    </div>
                </div>
                
                <div class="total-amount">
                    Total: £<?php echo number_format($order->total_amount, 2); ?>
                </div>
                
                <div class="customer-info">
                    <h3>Customer Information</h3>
                    <div class="customer-detail">
                        <strong>Name:</strong> <?php echo esc_html($order->shipping_name); ?>
                    </div>
                    <div class="customer-detail">
                        <strong>Email:</strong> <a href="mailto:<?php echo esc_attr($order->shipping_email); ?>" class="customer-email"><?php echo esc_html($order->shipping_email); ?></a>
                    </div>
                    <?php if ($order->display_name): ?>
                    <div class="customer-detail">
                        <strong>WordPress User:</strong> <?php echo esc_html($order->display_name); ?> (<?php echo esc_html($order->user_email); ?>)
                    </div>
                    <?php endif; ?>
                    <div class="customer-detail" style="margin-top: 12px;">
                        <strong>Shipping Address:</strong><br>
                        <?php echo esc_html($order->shipping_name); ?><br>
                        <?php echo esc_html($order->shipping_address); ?><br>
                        <?php echo esc_html($order->shipping_city); ?>, <?php echo esc_html($order->shipping_state); ?> <?php echo esc_html($order->shipping_zip); ?><br>
                        <?php echo esc_html($order->shipping_country); ?>
                    </div>
                </div>
                
                <?php if (!empty($items)): ?>
                <div class="order-items">
                    <h3>Order Items</h3>
                    <?php foreach ($items as $item): ?>
                    <div class="item">
                        <div class="item-info">
                            <div class="batch-code">#<?php echo esc_html($item->batch_code); ?></div>
                            <div class="sticker-type"><?php echo esc_html($item->sticker_type); ?> sticker</div>
                        </div>
                        <div class="item-price">£<?php echo number_format($item->unit_price, 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order->notes)): ?>
                <div class="notes">
                    <h4>Customer Notes</h4>
                    <p><?php echo esc_html($order->notes); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="priority-note">
                    <h4>Action Required</h4>
                    <p>This order is now processing and ready for fulfillment. Please review and update the order status accordingly.</p>
                </div>
                
                <div class="action-buttons" style="text-align: center;">
                    <a href="<?php echo esc_url($admin_url); ?>" class="action-button">
                        Manage Order
                    </a>
                    
                    <div style="margin-top: 20px;">
                        <h3 style="color: #1f2937; font-size: 16px; margin-bottom: 12px;">Quick Downloads</h3>
                        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'download_qr_zip', 'order_id' => $order->id, 'nonce' => wp_create_nonce('vqr_download_zip')], $admin_url)); ?>" 
                               class="download-button" style="background: #6b7280; padding: 10px 20px; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Download ZIP
                            </a>
                            
                            <a href="<?php echo esc_url(add_query_arg(['action' => 'download_qr_pdf', 'order_id' => $order->id, 'nonce' => wp_create_nonce('vqr_download_pdf')], $admin_url)); ?>" 
                               class="download-button" style="background: #dc2626; padding: 10px 20px; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                                </svg>
                                Download PDF
                            </a>
                        </div>
                        <p style="font-size: 12px; color: #6b7280; margin: 8px 0 0 0; font-style: italic;">
                            Direct download links for QR codes in this order
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>
                    This admin notification was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Order management: <a href="<?php echo esc_url($admin_url); ?>">Admin Dashboard</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Delete a single order
 */
function vqr_delete_order($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Order not found')));
        exit;
    }
    
    // Only allow deletion of pending or cancelled orders
    if (!in_array($order->status, ['pending', 'cancelled'])) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Only pending or cancelled orders can be deleted')));
        exit;
    }
    
    try {
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        // Delete order items first (foreign key constraint)
        $wpdb->delete($order_items_table, ['order_id' => $order_id], ['%d']);
        
        // Delete the order
        $result = $wpdb->delete($orders_table, ['id' => $order_id], ['%d']);
        
        if ($result === false) {
            throw new Exception('Failed to delete order');
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        // Log the deletion
        error_log(sprintf(
            'Order Deleted: Admin deleted order #%s (ID: %d)',
            $order->order_number,
            $order_id
        ));
        
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&message=' . urlencode("Order #{$order->order_number} deleted successfully")));
    } catch (Exception $e) {
        // Rollback transaction
        $wpdb->query('ROLLBACK');
        error_log('Order Delete Error: ' . $e->getMessage());
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Failed to delete order')));
    }
    exit;
}

/**
 * Bulk delete orders
 */
function vqr_bulk_delete_orders($order_ids) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    
    $deleted_count = 0;
    $failed_orders = [];
    
    try {
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        foreach ($order_ids as $order_id) {
            // Get order details
            $order = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$orders_table} WHERE id = %d",
                $order_id
            ));
            
            if (!$order) {
                $failed_orders[] = "Order ID {$order_id} (not found)";
                continue;
            }
            
            // Only allow deletion of pending or cancelled orders
            if (!in_array($order->status, ['pending', 'cancelled'])) {
                $failed_orders[] = "#{$order->order_number} (status: {$order->status})";
                continue;
            }
            
            // Delete order items first
            $wpdb->delete($order_items_table, ['order_id' => $order_id], ['%d']);
            
            // Delete the order
            $result = $wpdb->delete($orders_table, ['id' => $order_id], ['%d']);
            
            if ($result !== false) {
                $deleted_count++;
                error_log(sprintf(
                    'Bulk Order Delete: Admin deleted order #%s (ID: %d)',
                    $order->order_number,
                    $order_id
                ));
            } else {
                $failed_orders[] = "#{$order->order_number} (database error)";
            }
        }
        
        // Commit transaction
        $wpdb->query('COMMIT');
        
        $message = "Deleted {$deleted_count} orders";
        if (!empty($failed_orders)) {
            $message .= ". Failed to delete: " . implode(', ', $failed_orders);
        }
        
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&message=' . urlencode($message)));
    } catch (Exception $e) {
        // Rollback transaction
        $wpdb->query('ROLLBACK');
        error_log('Bulk Order Delete Error: ' . $e->getMessage());
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Failed to delete orders')));
    }
    exit;
}

/**
 * Download order QR codes as ZIP - Using proven working logic
 */
function vqr_download_order_zip($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    $qr_table = $wpdb->prefix . 'vqr_codes';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Order not found')));
        exit;
    }
    
    // Get QR codes for this order
    $qr_codes = $wpdb->get_results($wpdb->prepare(
        "SELECT qr.* FROM {$qr_table} qr 
         INNER JOIN {$order_items_table} oi ON qr.id = oi.qr_code_id 
         WHERE oi.order_id = %d 
         ORDER BY qr.batch_code ASC",
        $order_id
    ));
    
    if (empty($qr_codes)) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('No QR codes found for this order')));
        exit;
    }
    
    // Use the same proven ZIP logic from bulk download
    $zip = new ZipArchive();
    $zip_filename = tempnam(sys_get_temp_dir(), 'order_qrcodes') . '.zip';
    
    if ($zip->open($zip_filename, ZipArchive::CREATE) === TRUE) {
        foreach ($qr_codes as $code) {
            $file_path = str_replace(home_url('/'), ABSPATH, $code->qr_code);
            if (file_exists($file_path)) {
                // Create a better filename using batch code
                $extension = pathinfo($file_path, PATHINFO_EXTENSION) ?: 'png';
                $clean_batch_code = preg_replace('/[^a-zA-Z0-9-_]/', '', $code->batch_code);
                $new_filename = 'qr-code-' . $clean_batch_code . '.' . $extension;
                $zip->addFile($file_path, $new_filename);
            }
        }
        $zip->close();

        // Clean any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Create proper filename for download
        $download_filename = "order-{$order->order_number}-qr-codes-" . date('Y-m-d') . ".zip";

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $download_filename . '"');
        header('Content-Length: ' . filesize($zip_filename));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($zip_filename);
        unlink($zip_filename);
        exit;
    } else {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Could not create ZIP file')));
        exit;
    }
}

/**
 * Download order QR codes as PDF with cutlines - EXACT COPY of working bulk PDF logic
 */
function vqr_download_order_pdf($order_id) {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'vqr_sticker_orders';
    $order_items_table = $wpdb->prefix . 'vqr_sticker_order_items';
    $qr_table = $wpdb->prefix . 'vqr_codes';
    
    // Get order details
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$orders_table} WHERE id = %d",
        $order_id
    ));
    
    if (!$order) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('Order not found')));
        exit;
    }
    
    // Get QR codes for this order
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT qr.* FROM {$qr_table} qr 
         INNER JOIN {$order_items_table} oi ON qr.id = oi.qr_code_id 
         WHERE oi.order_id = %d 
         ORDER BY qr.batch_code ASC",
        $order_id
    ));
    
    if (empty($rows)) {
        wp_redirect(admin_url('admin.php?page=vqr_sticker_orders&error=' . urlencode('No QR codes found for this order')));
        exit;
    }
    
    // EXACT SAME LOGIC FROM WORKING BULK PDF
    // Layout in mm
    $pageW    = 700;
    $mLeft    = 10; $mRight = 10;
    $mTop     = 10; $mBot   = 10;
    $stW      = 60; $stH    = 60;
    $gX       = 5;  $gY     = 5;

    $usableW = $pageW - $mLeft - $mRight;
    $perRow  = max(1, floor( ($usableW + $gX) / ($stW + $gX) ));
    $rowsN   = ceil( count($rows) / $perRow );
    $pageH   = $mTop + $rowsN*$stH + ($rowsN-1)*$gY + $mBot;

    // Adjust sticker size to be tighter around QR codes
    $actualQRSize = 50; // Actual QR code size in mm (height)
    $borderWidth = 2;   // 2mm border around QR code
    $stW = 35;          // Total sticker width (as requested)
    $stH = $actualQRSize + (2 * $borderWidth); // Total sticker height
    $gX = 2;            // Minimal gap between stickers to save paper space
    $gY = 2;            // Minimal gap between stickers to save paper space
    
    // Recalculate layout with new dimensions
    $usableW = $pageW - $mLeft - $mRight;
    $perRow  = max(1, floor( ($usableW + $gX) / ($stW + $gX) ));
    $rowsN   = ceil( count($rows) / $perRow );
    $pageH   = $mTop + $rowsN*$stH + ($rowsN-1)*$gY + $mBot;

    // Get category and timestamp info
    $category = 'Order: ' . $order->order_number;
    $timestamp = date('Y-m-d H:i:s');
    
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Generate HTML layout for print-to-PDF - EXACT COPY
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            @page {
                size: <?php echo $pageW; ?>mm <?php echo $pageH; ?>mm;
                margin: 0;
            }
            @media print {
                * {
                    color-adjust: exact !important;
                    -webkit-print-color-adjust: exact !important;
                }
            }
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background: white;
            }
            .container {
                position: relative;
                width: <?php echo $pageW; ?>mm;
                height: <?php echo $pageH; ?>mm;
                background: white;
            }
            .images-layer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
            }
            .cutlines-layer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2;
                pointer-events: none;
            }
            .qr-item {
                position: absolute;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .qr-image {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
            }
            .cut-contour {
                position: absolute;
                border: 0.25pt solid red;
                border-radius: 2mm;
                box-sizing: border-box;
                background: transparent;
                /* CutContour layer - rename to spot color in Illustrator */
            }
            .print-info {
                position: absolute;
                font-size: 12px;
                color: #333;
                top: 2mm;
                left: 2mm;
            }
        </style>
        <script>
            window.onload = function() {
                // Auto-trigger print dialog for PDF generation
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        </script>
    </head>
    <body>
        <div class="container">
            <!-- Print information -->
            <div class="print-info">Category: <?php echo esc_html($category); ?> | Printed: <?php echo esc_html($timestamp); ?></div>
            
            <!-- Images Layer (z-index: 1) -->
            <div class="images-layer">
                <?php foreach ( $rows as $i => $code ): 
                    $col = $i % $perRow;
                    $row = intdiv( $i, $perRow );
                    $x   = $mLeft + $col * ( $stW + $gX );
                    $y   = $mTop  + $row * ( $stH + $gY );
                    
                    $img_path = str_replace( home_url('/'), ABSPATH, $code->qr_code );
                    if ( file_exists($img_path) ):
                ?>
                    <div class="qr-item" style="left: <?php echo $x; ?>mm; top: <?php echo $y; ?>mm; width: <?php echo $stW; ?>mm; height: <?php echo $stH; ?>mm;">
                        <img src="<?php echo esc_url($code->qr_code); ?>" class="qr-image" alt="QR Code <?php echo esc_attr($code->batch_code); ?>">
                    </div>
                <?php endif; endforeach; ?>
            </div>
            
            <!-- CutContour Layer (z-index: 2) -->
            <div class="cutlines-layer">
                <?php foreach ( $rows as $i => $code ): 
                    $col = $i % $perRow;
                    $row = intdiv( $i, $perRow );
                    $x   = $mLeft + $col * ( $stW + $gX );
                    $y   = $mTop  + $row * ( $stH + $gY );
                    
                    $img_path = str_replace( home_url('/'), ABSPATH, $code->qr_code );
                    if ( file_exists($img_path) ):
                        // Get actual image dimensions to calculate proper cut line size
                        $img_info = getimagesize($img_path);
                        if ($img_info) {
                            $img_width_px = $img_info[0];
                            $img_height_px = $img_info[1];
                            
                            // Convert pixels to mm (assuming 96 DPI)
                            $px_to_mm = 25.4 / 96;
                            $img_width_mm = $img_width_px * $px_to_mm;
                            $img_height_mm = $img_height_px * $px_to_mm;
                            
                            // Ensure width never exceeds 35mm
                            $cut_width = min($img_width_mm, 35);
                            $cut_height = $img_height_mm * ($cut_width / $img_width_mm); // Maintain aspect ratio
                            
                            // Center the cut line within the sticker area
                            $cut_x = $x + ($stW - $cut_width) / 2;
                            $cut_y = $y + ($stH - $cut_height) / 2;
                        } else {
                            // Fallback to original dimensions if image info unavailable
                            $cut_width = $stW;
                            $cut_height = $stH;
                            $cut_x = $x;
                            $cut_y = $y;
                        }
                ?>
                    <div class="cut-contour" style="left: <?php echo $cut_x; ?>mm; top: <?php echo $cut_y; ?>mm; width: <?php echo $cut_width; ?>mm; height: <?php echo $cut_height; ?>mm;"></div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

