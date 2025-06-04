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
    
    // Get categories for dropdown
    $categories = vqr_get_categories($wpdb, $table_name);

    // Render the modern admin page
    vqr_render_modern_admin_page($qr_codes, $categories, $filters);
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
        'order' => in_array($_GET['order_scan'] ?? '', ['asc', 'desc'], true) ? $_GET['order_scan'] : ''
    ];
}

/**
 * Get filtered QR codes from database
 */
function vqr_get_filtered_qr_codes($wpdb, $table_name, $filters) {
    $where = [];
    $vars = [];

    if ($filters['batch_code']) {
        $where[] = 'batch_code LIKE %s';
        $vars[] = '%' . $wpdb->esc_like($filters['batch_code']) . '%';
    }
    if ($filters['category']) {
        $where[] = 'category = %s';
        $vars[] = $filters['category'];
    }
    if ($filters['scanned'] === 'scanned') {
        $where[] = 'scan_count > 0';
    } elseif ($filters['scanned'] === 'not_scanned') {
        $where[] = 'scan_count = 0';
    }

    $sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql_order = '';
    if ($filters['order'] === 'asc') {
        $sql_order = 'ORDER BY scan_count ASC';
    } elseif ($filters['order'] === 'desc') {
        $sql_order = 'ORDER BY scan_count DESC';
    }

    $sql = "SELECT * FROM {$table_name} {$sql_where} {$sql_order}";

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
 * Render the modern admin page
 */
function vqr_render_modern_admin_page($qr_codes, $categories, $filters) {
    ?>
    <div class="vqr-admin-wrap wrap">
        <h1><span class="dashicons dashicons-qrcode"></span> QR Code Manager</h1>
        
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
                    <?php vqr_render_filters_form($categories, $filters); ?>
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
function vqr_render_filters_form($categories, $filters) {
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
                <button type="submit" class="button button-primary">Apply Filters</button>
            </div>
            
            <?php 
            // Check if any filters are applied
            $has_filters = !empty($filters['batch_code']) || !empty($filters['category']) || !empty($filters['scanned']) || !empty($filters['order']);
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
                        <th>QR Code</th>
                        <th>URL</th>
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
                                    <div class="vqr-qr-preview">
                                        <img src="<?php echo esc_url($code->qr_code); ?>" 
                                             class="vqr-qr-image" 
                                             alt="QR Code <?php echo esc_attr($code->id); ?>"
                                             title="Click to enlarge">
                                    </div>
                                </td>
                                <td><a href="<?php echo esc_url($code->url); ?>" target="_blank"><?php echo esc_html($code->url); ?></a></td>
                                <td><code><?php echo esc_html($code->batch_code); ?></code></td>
                                <td><?php echo esc_html($code->category); ?></td>
                                <td><?php echo vqr_get_status_badge($code->scan_count); ?></td>
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
                        <tr><td colspan="10" style="text-align: center; padding: 40px;">No QR codes found.</td></tr>
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