<?php
/**
 * Strain Management page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's strains
$user_strains = vqr_get_user_strains($user_id);

// Get strain count and QR code associations with scan details
global $wpdb;
$table_name = $wpdb->prefix . 'vqr_codes';

$strain_stats = [];
$strain_qr_details = [];
foreach ($user_strains as $strain) {
    $qr_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d",
        $strain->ID
    ));
    $total_scans = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(scan_count) FROM {$table_name} WHERE post_id = %d",
        $strain->ID
    )) ?: 0;
    
    // Get QR codes with scan status for this strain
    $qr_codes = $wpdb->get_results($wpdb->prepare(
        "SELECT id, batch_code, qr_code, scan_count FROM {$table_name} WHERE post_id = %d ORDER BY batch_code ASC",
        $strain->ID
    ));
    
    $scanned_count = 0;
    $unscanned_count = 0;
    foreach ($qr_codes as $qr) {
        if ($qr->scan_count > 0) {
            $scanned_count++;
        } else {
            $unscanned_count++;
        }
    }
    
    $strain_stats[$strain->ID] = [
        'qr_count' => $qr_count,
        'total_scans' => $total_scans,
        'scanned_count' => $scanned_count,
        'unscanned_count' => $unscanned_count
    ];
    
    $strain_qr_details[$strain->ID] = $qr_codes;
}

// Prepare page content
ob_start();
?>

<div class="vqr-strains-page">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <div class="vqr-page-header-content">
            <div class="vqr-page-header-text">
                <h1 class="vqr-page-title">Your Strains</h1>
                <p class="vqr-page-description">Manage your cannabis product strains and their information.</p>
            </div>
            <div class="vqr-page-header-actions">
                <button class="vqr-btn vqr-btn-primary" onclick="VQR.showCreateStrainModal()">
                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New Strain
                </button>
            </div>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="vqr-grid vqr-grid-cols-3 vqr-mb-lg">
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value"><?php echo count($user_strains); ?></span>
                    <div class="vqr-stat-label">Total Strains</div>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value">
                        <?php 
                        $total_qr_codes = array_sum(array_column($strain_stats, 'qr_count'));
                        echo number_format($total_qr_codes); 
                        ?>
                    </span>
                    <div class="vqr-stat-label">QR Codes Created</div>
                </div>
            </div>
        </div>
        
        <div class="vqr-card">
            <div class="vqr-card-content">
                <div class="vqr-stat">
                    <span class="vqr-stat-value">
                        <?php 
                        $total_scans = array_sum(array_column($strain_stats, 'total_scans'));
                        echo number_format($total_scans); 
                        ?>
                    </span>
                    <div class="vqr-stat-label">Total Scans</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Strains List -->
    <div class="vqr-card">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Your Strains</h3>
        </div>
        <div class="vqr-card-content">
            <?php if ($user_strains): ?>
                <div class="vqr-strains-grid">
                    <?php foreach ($user_strains as $strain): ?>
                        <?php
                        $stats = $strain_stats[$strain->ID];
                        $product_image = get_post_meta($strain->ID, 'product_image', true);
                        $image_url = $product_image ? wp_get_attachment_image_url($product_image, 'medium') : '';
                        $thc_percentage = get_post_meta($strain->ID, 'thc_percentage', true);
                        $cbd_percentage = get_post_meta($strain->ID, 'cbd_percentage', true);
                        $strain_genetics = get_post_meta($strain->ID, 'strain_genetics', true);
                        ?>
                        <div class="vqr-strain-card">
                            <div class="vqr-strain-image">
                                <?php if ($image_url): ?>
                                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($strain->post_title); ?>">
                                <?php else: ?>
                                    <div class="vqr-strain-placeholder">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($stats['qr_count'] > 0): ?>
                                    <?php $print_info = vqr_get_strain_print_order_info($strain->ID); ?>
                                    <?php if ($print_info): ?>
                                        <a href="<?php echo esc_url($print_info['link_url']); ?>" 
                                           class="vqr-strain-print-overlay <?php echo esc_attr($print_info['status_class']); ?>"
                                           title="Order #<?php echo esc_attr($print_info['order_number']); ?> - <?php echo esc_attr($print_info['qr_count']); ?> QR code<?php echo $print_info['qr_count'] > 1 ? 's' : ''; ?> (<?php echo ucfirst($print_info['status']); ?>)">
                                            <svg class="vqr-print-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="vqr-strain-content">
                                <h4 class="vqr-strain-name"><?php echo esc_html($strain->post_title); ?></h4>
                                
                                <?php if ($strain_genetics): ?>
                                    <p class="vqr-strain-genetics"><?php echo esc_html($strain_genetics); ?></p>
                                <?php endif; ?>
                                
                                <div class="vqr-strain-cannabinoids">
                                    <?php if ($thc_percentage): ?>
                                        <span class="vqr-cannabinoid">THC: <?php echo esc_html($thc_percentage); ?>%</span>
                                    <?php endif; ?>
                                    <?php if ($cbd_percentage): ?>
                                        <span class="vqr-cannabinoid">CBD: <?php echo esc_html($cbd_percentage); ?>%</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="vqr-strain-stats">
                                    <div class="vqr-strain-stat">
                                        <span class="vqr-strain-stat-value"><?php echo $stats['qr_count']; ?></span>
                                        <span class="vqr-strain-stat-label">QR Codes</span>
                                    </div>
                                    <div class="vqr-strain-stat">
                                        <span class="vqr-strain-stat-value"><?php echo number_format($stats['total_scans']); ?></span>
                                        <span class="vqr-strain-stat-label">Scans</span>
                                    </div>
                                    <?php if ($stats['qr_count'] > 0): ?>
                                    <div class="vqr-strain-stat">
                                        <span class="vqr-strain-stat-value vqr-scan-status">
                                            <span class="vqr-scanned"><?php echo $stats['scanned_count']; ?></span>/<span class="vqr-unscanned"><?php echo $stats['unscanned_count']; ?></span>
                                        </span>
                                        <span class="vqr-strain-stat-label">Scanned/New</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="vqr-strain-actions">
                                    <button class="vqr-btn vqr-btn-secondary vqr-btn-sm" 
                                            onclick="VQR.editStrain(<?php echo $strain->ID; ?>)">
                                        Edit
                                    </button>
                                    <a href="<?php echo home_url('/app/preview/' . $strain->ID); ?>" 
                                       target="_blank"
                                       class="vqr-btn vqr-btn-secondary vqr-btn-sm">
                                        Preview
                                    </a>
                                    <a href="<?php echo home_url('/app/generate/'); ?>" 
                                       class="vqr-btn vqr-btn-primary vqr-btn-sm">
                                        Create QR
                                    </a>
                                    <?php if ($stats['qr_count'] > 0): ?>
                                        <button class="vqr-btn vqr-btn-secondary vqr-btn-sm vqr-order-stickers-btn" 
                                                onclick="VQR.showStrainStickerOrder(<?php echo $strain->ID; ?>, '<?php echo esc_js($strain->post_title); ?>')">
                                            <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                            </svg>
                                            Order Stickers
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($stats['qr_count'] == 0): ?>
                                        <button class="vqr-btn vqr-btn-secondary vqr-btn-sm vqr-text-error" 
                                                onclick="VQR.deleteStrain(<?php echo $strain->ID; ?>, '<?php echo esc_js($strain->post_title); ?>')">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="vqr-empty-state">
                    <svg width="64" height="64" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: var(--space-lg);">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <h3>No strains yet</h3>
                    <p class="vqr-text-muted">Create your first strain to start generating QR codes for your cannabis products.</p>
                    <button class="vqr-btn vqr-btn-primary" onclick="VQR.showCreateStrainModal()" style="margin-top: var(--space-lg);">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Your First Strain
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Create/Edit Strain Modal -->
<div id="vqr-strain-modal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-overlay" onclick="VQR.closeStrainModal()"></div>
    <div class="vqr-modal-content vqr-modal-large">
        <div class="vqr-modal-header">
            <h3 id="vqr-strain-modal-title">Add New Strain</h3>
            <button class="vqr-modal-close" onclick="VQR.closeStrainModal()">×</button>
        </div>
        <div class="vqr-modal-body">
            <form id="vqr-strain-form" class="vqr-form" data-action="vqr_save_strain" enctype="multipart/form-data">
                <input type="hidden" id="strain_id" name="strain_id" value="">
                
                <!-- Basic Information -->
                <div class="vqr-form-section">
                    <h4 class="vqr-form-section-title">
                        <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Basic Information
                    </h4>
                    <div class="vqr-form-grid">
                        <div class="vqr-form-group vqr-form-full">
                            <label for="strain_name" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Strain Name *
                            </label>
                            <input type="text" id="strain_name" name="strain_name" class="vqr-input" required>
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="strain_genetics" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                                Genetics
                            </label>
                            <input type="text" id="strain_genetics" name="strain_genetics" class="vqr-input" 
                                   placeholder="e.g., Sunset Sherbet x Thin Mint Cookies">
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="batch_id" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                                </svg>
                                Batch ID
                            </label>
                            <input type="text" id="batch_id" name="batch_id" class="vqr-input" 
                                   placeholder="Internal batch identifier">
                        </div>
                        
                        <div class="vqr-form-group vqr-form-full">
                            <label for="product_description" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                </svg>
                                Description
                            </label>
                            <textarea id="product_description" name="product_description" class="vqr-input vqr-textarea" 
                                      rows="4" placeholder="Describe your strain's effects, flavor profile, and characteristics..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Media Section -->
                <div class="vqr-form-section">
                    <h4 class="vqr-form-section-title">
                        <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Product Images
                    </h4>
                    <div class="vqr-form-grid">
                        <div class="vqr-form-group">
                            <label for="product_logo" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Product Logo
                                <?php if (!vqr_user_can_upload_custom_logo()): ?>
                                    <span class="vqr-locked-feature-badge">Starter+</span>
                                <?php endif; ?>
                            </label>
                            <?php if (vqr_user_can_upload_custom_logo()): ?>
                                <input type="file" id="product_logo" name="product_logo" class="vqr-input vqr-file-input" 
                                       accept="image/png,image/jpeg,image/jpg">
                                <div class="vqr-field-help">Brand logo or small product image (PNG/JPEG, max 2MB)</div>
                            <?php else: ?>
                                <div class="vqr-locked-field">
                                    <div class="vqr-locked-field-content">
                                        <svg class="vqr-locked-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 0h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        <div>
                                            <p class="vqr-locked-title">Custom Logo Upload Locked</p>
                                            <p class="vqr-locked-description">Upgrade to Starter plan or higher to upload custom logos. Free users get Verify 420 branding.</p>
                                            <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                                                Upgrade Plan
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="product_image" class="vqr-label">
                                <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Product Image
                            </label>
                            <input type="file" id="product_image" name="product_image" class="vqr-input vqr-file-input" 
                                   accept="image/png,image/jpeg,image/jpg">
                            <div class="vqr-field-help">Main product photo (PNG/JPEG, max 2MB)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Cannabinoid Information -->
                <div class="vqr-form-section">
                    <h4 class="vqr-form-section-title">
                        <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Cannabinoid Content
                    </h4>
                    <div class="vqr-form-grid">
                        <div class="vqr-form-group">
                            <label for="thc_percentage" class="vqr-label">
                                <span class="vqr-cannabinoid-indicator vqr-thc">THC</span>
                                THC Percentage
                            </label>
                            <div class="vqr-input-group">
                                <input type="number" id="thc_percentage" name="thc_percentage" class="vqr-input" 
                                       step="0.001" min="0" max="100" placeholder="25.125">
                                <span class="vqr-input-suffix">%</span>
                            </div>
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="thc_mg" class="vqr-label">
                                <span class="vqr-cannabinoid-indicator vqr-thc">THC</span>
                                THC Milligrams
                            </label>
                            <div class="vqr-input-group">
                                <input type="number" id="thc_mg" name="thc_mg" class="vqr-input" 
                                       step="0.001" min="0" placeholder="100.250">
                                <span class="vqr-input-suffix">mg</span>
                            </div>
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="cbd_percentage" class="vqr-label">
                                <span class="vqr-cannabinoid-indicator vqr-cbd">CBD</span>
                                CBD Percentage
                            </label>
                            <div class="vqr-input-group">
                                <input type="number" id="cbd_percentage" name="cbd_percentage" class="vqr-input" 
                                       step="0.001" min="0" max="100" placeholder="2.750">
                                <span class="vqr-input-suffix">%</span>
                            </div>
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="cbd_mg" class="vqr-label">
                                <span class="vqr-cannabinoid-indicator vqr-cbd">CBD</span>
                                CBD Milligrams
                            </label>
                            <div class="vqr-input-group">
                                <input type="number" id="cbd_mg" name="cbd_mg" class="vqr-input" 
                                       step="0.001" min="0" placeholder="10.500">
                                <span class="vqr-input-suffix">mg</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Social Media Links -->
                <div class="vqr-form-section">
                    <h4 class="vqr-form-section-title">
                        <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                        Social Media & Links
                    </h4>
                    <div class="vqr-form-grid">
                        <div class="vqr-form-group">
                            <label for="instagram_url" class="vqr-label">
                                <svg class="vqr-label-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                                Instagram
                            </label>
                            <input type="url" id="instagram_url" name="instagram_url" class="vqr-input" 
                                   placeholder="https://instagram.com/yourhandle">
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="facebook_url" class="vqr-label">
                                <svg class="vqr-label-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                Facebook
                            </label>
                            <input type="url" id="facebook_url" name="facebook_url" class="vqr-input" 
                                   placeholder="https://facebook.com/yourpage">
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="twitter_url" class="vqr-label">
                                <svg class="vqr-label-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                </svg>
                                Twitter
                            </label>
                            <input type="url" id="twitter_url" name="twitter_url" class="vqr-input" 
                                   placeholder="https://twitter.com/yourhandle">
                        </div>
                        
                        <div class="vqr-form-group">
                            <label for="telegram_url" class="vqr-label">
                                <svg class="vqr-label-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                </svg>
                                Telegram
                            </label>
                            <input type="url" id="telegram_url" name="telegram_url" class="vqr-input" 
                                   placeholder="https://t.me/yourchannel">
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions Inside Form -->
                <div class="vqr-form-actions">
                    <button type="button" class="vqr-btn vqr-btn-secondary" onclick="VQR.closeStrainModal()">Cancel</button>
                    <button type="submit" class="vqr-btn vqr-btn-primary" id="vqr-strain-submit">
                        <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Strain
                    </button>
                </div>
                
            </form>
        </div>
    </div>
</div>

<!-- Strain Sticker Order Modal -->
<div id="vqr-strain-sticker-modal" class="vqr-modal" style="display: none;">
    <div class="vqr-modal-overlay" onclick="VQR.closeStrainStickerModal()"></div>
    <div class="vqr-modal-content vqr-modal-large">
        <div class="vqr-modal-header">
            <h3 id="vqr-strain-sticker-modal-title">Order Stickers</h3>
            <button class="vqr-modal-close" onclick="VQR.closeStrainStickerModal()">×</button>
        </div>
        <div class="vqr-modal-body">
            <div id="vqr-strain-sticker-content">
                <!-- Content will be populated dynamically -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// Store strain QR details for JavaScript access
window.strainQRDetails = <?php echo json_encode($strain_qr_details); ?>;
</script>

<style>
/* Strains Page Styles */
.vqr-strains-page {
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

.vqr-page-header-content {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-lg);
}

.vqr-page-header-text {
    flex: 1;
}

.vqr-page-header-actions {
    flex-shrink: 0;
}

.vqr-strains-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: var(--space-lg);
}

.vqr-strain-card {
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    transition: all 0.2s ease;
    background: var(--white);
}

.vqr-strain-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.vqr-strain-image {
    height: 200px;
    background: var(--surface);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.vqr-strain-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vqr-strain-placeholder {
    width: 64px;
    height: 64px;
    color: var(--text-muted);
}

.vqr-strain-content {
    padding: var(--space-lg);
}

.vqr-strain-name {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 var(--space-sm) 0;
}

.vqr-strain-genetics {
    color: var(--text-muted);
    font-size: var(--font-size-sm);
    margin: 0 0 var(--space-md) 0;
}

.vqr-strain-cannabinoids {
    display: flex;
    gap: var(--space-md);
    margin-bottom: var(--space-md);
}

.vqr-cannabinoid {
    background: var(--surface);
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
    font-weight: 500;
}

.vqr-strain-stats {
    display: flex;
    gap: var(--space-lg);
    margin-bottom: var(--space-lg);
    padding: var(--space-md) 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.vqr-strain-stat {
    text-align: center;
}

.vqr-strain-stat-value {
    display: block;
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--primary);
}

.vqr-strain-stat-label {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: var(--space-xs);
}

.vqr-strain-actions {
    display: flex;
    gap: var(--space-sm);
    flex-wrap: wrap;
}

/* Scan Status Styling */
.vqr-scan-status {
    display: flex;
    gap: 2px;
    align-items: center;
}

.vqr-scanned {
    color: #ef4444;
    font-weight: 600;
}

.vqr-unscanned {
    color: #10b981;
    font-weight: 600;
}

/* Sticker Order Modal Styles */
.vqr-strain-qr-list {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    background: var(--surface);
}

.vqr-qr-code-item {
    display: flex;
    align-items: center;
    padding: var(--space-md);
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
}

.vqr-qr-code-item:last-child {
    border-bottom: none;
}

.vqr-qr-code-item:hover {
    background: var(--surface-hover);
}

.vqr-qr-code-item.disabled {
    opacity: 0.6;
    pointer-events: none;
}

.vqr-qr-checkbox {
    margin-right: var(--space-md);
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.vqr-qr-thumbnail {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    margin-right: var(--space-md);
    border: 1px solid var(--border);
}

.vqr-qr-details {
    flex: 1;
}

.vqr-qr-batch-code {
    font-family: monospace;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: var(--space-xs);
}

.vqr-qr-scan-status {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-scan-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 500;
}

.vqr-scan-badge.scanned {
    background: #fee2e2;
    color: #991b1b;
}

.vqr-scan-badge.unscanned {
    background: #dcfdf7;
    color: #065f46;
}

.vqr-strain-order-summary {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: var(--space-lg);
    margin-top: var(--space-lg);
}

.vqr-filter-buttons {
    display: flex;
    gap: var(--space-sm);
    margin-bottom: var(--space-md);
}

.vqr-filter-btn {
    padding: var(--space-xs) var(--space-sm);
    border: 1px solid var(--border);
    background: var(--white);
    border-radius: var(--radius-sm);
    font-size: var(--font-size-sm);
    cursor: pointer;
    transition: all 0.2s ease;
}

.vqr-filter-btn.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.vqr-filter-btn:hover:not(.active) {
    background: var(--surface);
}

.vqr-strain-order-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: flex-end;
    margin-top: var(--space-lg);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

/* Form Sections */
.vqr-form-section {
    margin-bottom: var(--space-xl);
}

.vqr-form-section-title {
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 var(--space-lg) 0;
    padding-bottom: var(--space-sm);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
}

.vqr-section-icon {
    width: 20px;
    height: 20px;
    color: var(--primary);
}

/* Enhanced Form Styling */
.vqr-label {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    font-weight: 500;
    margin-bottom: var(--space-sm);
}

.vqr-label-icon {
    width: 16px;
    height: 16px;
    opacity: 0.7;
    flex-shrink: 0;
}

/* Input Groups */
.vqr-input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.vqr-input-group .vqr-input {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}

.vqr-input-suffix {
    background: var(--surface);
    border: 1px solid var(--border);
    border-left: none;
    border-top-right-radius: var(--radius-md);
    border-bottom-right-radius: var(--radius-md);
    padding: var(--space-sm) var(--space-md);
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    font-weight: 500;
}

/* Cannabinoid Indicators */
.vqr-cannabinoid-indicator {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 20px;
    border-radius: var(--radius-sm);
    font-size: var(--font-size-xs);
    font-weight: 600;
    text-transform: uppercase;
    margin-right: var(--space-xs);
}

.vqr-cannabinoid-indicator.vqr-thc {
    background: #fee2e2;
    color: #991b1b;
}

.vqr-cannabinoid-indicator.vqr-cbd {
    background: #dcfdf7;
    color: #065f46;
}

/* File Input Styling */
.vqr-file-input {
    padding: var(--space-sm);
    border: 2px dashed var(--border);
    background: var(--surface);
    cursor: pointer;
    transition: all 0.2s ease;
}

.vqr-file-input:hover {
    border-color: var(--primary);
    background: rgba(16, 112, 70, 0.05);
}

.vqr-file-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(16, 112, 70, 0.1);
}

/* Form Actions */
.vqr-form-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: flex-end;
    padding-top: var(--space-xl);
    border-top: 1px solid var(--border);
    margin-top: var(--space-xl);
}

/* Large Modal */
/* Base Modal Styles */
.vqr-modal {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-lg);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.vqr-modal.show {
    opacity: 1;
    visibility: visible;
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
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.vqr-modal.show .vqr-modal-content {
    transform: scale(1);
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
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
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
    color: var(--text-muted);
}

.vqr-modal-close:hover {
    color: var(--text-primary);
}

.vqr-modal-body {
    padding: var(--space-lg);
}

.vqr-modal-footer {
    display: flex;
    gap: var(--space-sm);
    padding: var(--space-lg);
    border-top: 1px solid var(--border);
    justify-content: flex-end;
}

/* Large Modal Variant */
.vqr-modal-large .vqr-modal-content {
    max-width: 900px;
    width: 95vw;
    max-height: 95vh;
    overflow-y: auto;
}

.vqr-modal-large .vqr-modal-body {
    max-height: calc(95vh - 120px);
    overflow-y: auto;
    padding: var(--space-xl);
}

/* Responsive Form Grid */
@media (min-width: 768px) {
    .vqr-form-grid {
        grid-template-columns: 1fr 1fr;
        gap: var(--space-lg);
    }
    
    .vqr-form-full {
        grid-column: 1 / -1;
    }
}

@media (max-width: 768px) {
    .vqr-page-header-content {
        flex-direction: column;
        gap: var(--space-md);
    }
    
    .vqr-strains-grid {
        grid-template-columns: 1fr;
    }
    
    .vqr-strain-actions {
        justify-content: center;
    }
    
    .vqr-strains-page {
        padding: var(--space-md);
    }
    
    
    .vqr-page-header-content {
        flex-direction: column;
        gap: var(--space-md);
        align-items: flex-start;
    }
    
    .vqr-modal-large .vqr-modal-content {
        margin: var(--space-md);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Extend VQR object with strain functions
    window.VQR = window.VQR || {};
    
    // Show create strain modal
    VQR.showCreateStrainModal = function() {
        const modal = document.getElementById('vqr-strain-modal');
        const title = document.getElementById('vqr-strain-modal-title');
        const form = document.getElementById('vqr-strain-form');
        
        title.textContent = 'Add New Strain';
        form.reset();
        document.getElementById('strain_id').value = '';
        
        // Show modal with animation
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
            // Focus on the first input field for better UX
            const firstInput = modal.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        }, 10); // Small delay to ensure display change is processed
    };
    
    // Edit strain
    VQR.editStrain = function(strainId) {
        const modal = document.getElementById('vqr-strain-modal');
        const title = document.getElementById('vqr-strain-modal-title');
        
        title.textContent = 'Edit Strain';
        document.getElementById('strain_id').value = strainId;
        
        // Show modal with animation
        modal.style.display = 'flex';
        setTimeout(() => {
            modal.classList.add('show');
            // Focus on the first input field for better UX
            const firstInput = modal.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        }, 10);
        
        // Load strain data
        VQR.loadStrainData(strainId);
    };
    
    // Load strain data for editing
    VQR.loadStrainData = function(strainId) {
        fetch(vqr_ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=vqr_load_strain&strain_id=${strainId}&nonce=${vqr_ajax.nonce}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const strainData = data.data.strain_data;
                
                // Populate form fields (skip file inputs)
                Object.keys(strainData).forEach(key => {
                    const field = document.getElementById(key);
                    if (field && field.type !== 'file') {
                        field.value = strainData[key] || '';
                    }
                });
            } else {
                VQR.showNotification('Error', data.data || 'Failed to load strain data.', 'error');
            }
        })
        .catch(error => {
            console.error('Load strain error:', error);
            VQR.showNotification('Error', 'Network error loading strain data.', 'error');
        });
    };
    
    // Delete strain
    VQR.deleteStrain = function(strainId, strainName) {
        if (confirm('Are you sure you want to delete "' + strainName + '"? This action cannot be undone.')) {
            fetch(vqr_ajax.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=vqr_delete_strain&strain_id=${strainId}&nonce=${vqr_ajax.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    VQR.showNotification('Success!', data.data.message, 'success');
                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    VQR.showNotification('Error', data.data || 'Failed to delete strain.', 'error');
                }
            })
            .catch(error => {
                console.error('Delete strain error:', error);
                VQR.showNotification('Error', 'Network error deleting strain.', 'error');
            });
        }
    };
    
    // Close strain modal
    VQR.closeStrainModal = function() {
        const modal = document.getElementById('vqr-strain-modal');
        modal.classList.remove('show');
        
        // Hide modal after animation completes
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Match the CSS transition duration
    };
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            VQR.closeStrainModal();
            VQR.closeStrainStickerModal();
        }
    });
    
    // Strain sticker ordering functions
    VQR.showStrainStickerOrder = function(strainId, strainName) {
        const modal = document.getElementById('vqr-strain-sticker-modal');
        const title = document.getElementById('vqr-strain-sticker-modal-title');
        const content = document.getElementById('vqr-strain-sticker-content');
        
        title.textContent = `Order Stickers - ${strainName}`;
        
        // Get QR codes for this strain
        const qrCodes = window.strainQRDetails[strainId] || [];
        
        if (qrCodes.length === 0) {
            content.innerHTML = `
                <div class="vqr-empty-state">
                    <p>No QR codes found for this strain.</p>
                    <a href="<?php echo home_url('/app/generate/'); ?>" class="vqr-btn vqr-btn-primary">Create QR Codes</a>
                </div>
            `;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
            return;
        }
        
        const scannedCount = qrCodes.filter(qr => parseInt(qr.scan_count) > 0).length;
        const unscannedCount = qrCodes.length - scannedCount;
        
        content.innerHTML = `
            <div class="vqr-strain-sticker-order">
                <div class="vqr-order-info">
                    <h4>Select QR codes to order stickers for:</h4>
                    <p>Found ${qrCodes.length} QR codes: <span class="vqr-scanned">${scannedCount} scanned</span>, <span class="vqr-unscanned">${unscannedCount} new</span></p>
                </div>
                
                <div class="vqr-filter-buttons">
                    <button class="vqr-filter-btn active" onclick="VQR.filterStrainQRs('all')">All (${qrCodes.length})</button>
                    <button class="vqr-filter-btn" onclick="VQR.filterStrainQRs('unscanned')">New Only (${unscannedCount})</button>
                    <button class="vqr-filter-btn" onclick="VQR.filterStrainQRs('scanned')">Scanned Only (${scannedCount})</button>
                </div>
                
                <div class="vqr-selection-controls" style="margin-bottom: var(--space-md);">
                    <button class="vqr-btn vqr-btn-sm vqr-btn-secondary" onclick="VQR.selectAllStrainQRs(true)">Select All</button>
                    <button class="vqr-btn vqr-btn-sm vqr-btn-secondary" onclick="VQR.selectAllStrainQRs(false)">Deselect All</button>
                    <button class="vqr-btn vqr-btn-sm vqr-btn-secondary" onclick="VQR.selectUnscannedOnly()">Select New Only</button>
                </div>
                
                <div class="vqr-strain-qr-list" id="strain-qr-list">
                    ${qrCodes.map(qr => {
                        const isScanned = parseInt(qr.scan_count) > 0;
                        const scanText = isScanned ? `Scanned ${qr.scan_count} times` : 'Never scanned';
                        const badgeClass = isScanned ? 'scanned' : 'unscanned';
                        
                        return `
                            <div class="vqr-qr-code-item" data-qr-id="${qr.id}" data-scanned="${isScanned ? '1' : '0'}">
                                <input type="checkbox" class="vqr-qr-checkbox" id="qr_${qr.id}" value="${qr.id}" ${!isScanned ? 'checked' : ''}>
                                <img src="${qr.qr_code}" alt="QR Code" class="vqr-qr-thumbnail">
                                <div class="vqr-qr-details">
                                    <div class="vqr-qr-batch-code">${qr.batch_code}</div>
                                    <div class="vqr-qr-scan-status">
                                        <span class="vqr-scan-badge ${badgeClass}">${scanText}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
                
                <div class="vqr-strain-order-summary">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong id="selected-count">0</strong> QR codes selected for sticker order
                        </div>
                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);">
                            Estimated cost will be calculated on the order page
                        </div>
                    </div>
                </div>
                
                <div class="vqr-strain-order-actions">
                    <button class="vqr-btn vqr-btn-secondary" onclick="VQR.closeStrainStickerModal()">Cancel</button>
                    <button class="vqr-btn vqr-btn-primary" id="proceed-to-order" onclick="VQR.proceedToStrainOrder()" disabled>
                        Proceed to Order
                    </button>
                </div>
            </div>
        `;
        
        // Show modal
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
        
        // Add event listeners for checkboxes
        VQR.updateStrainOrderSelection();
        const checkboxes = content.querySelectorAll('.vqr-qr-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', VQR.updateStrainOrderSelection);
        });
    };
    
    VQR.closeStrainStickerModal = function() {
        const modal = document.getElementById('vqr-strain-sticker-modal');
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
    };
    
    VQR.filterStrainQRs = function(filter) {
        const buttons = document.querySelectorAll('.vqr-filter-btn');
        const items = document.querySelectorAll('.vqr-qr-code-item');
        
        // Update active button
        buttons.forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        // Filter items
        items.forEach(item => {
            const isScanned = item.dataset.scanned === '1';
            let show = true;
            
            if (filter === 'scanned' && !isScanned) show = false;
            if (filter === 'unscanned' && isScanned) show = false;
            
            item.style.display = show ? 'flex' : 'none';
        });
    };
    
    VQR.selectAllStrainQRs = function(select) {
        const visibleCheckboxes = document.querySelectorAll('.vqr-qr-code-item:not([style*="display: none"]) .vqr-qr-checkbox');
        visibleCheckboxes.forEach(checkbox => {
            checkbox.checked = select;
        });
        VQR.updateStrainOrderSelection();
    };
    
    VQR.selectUnscannedOnly = function() {
        const checkboxes = document.querySelectorAll('.vqr-qr-checkbox');
        checkboxes.forEach(checkbox => {
            const item = checkbox.closest('.vqr-qr-code-item');
            const isScanned = item.dataset.scanned === '1';
            checkbox.checked = !isScanned;
        });
        VQR.updateStrainOrderSelection();
    };
    
    VQR.updateStrainOrderSelection = function() {
        const selectedCheckboxes = document.querySelectorAll('.vqr-qr-checkbox:checked');
        const count = selectedCheckboxes.length;
        
        document.getElementById('selected-count').textContent = count;
        document.getElementById('proceed-to-order').disabled = count === 0;
    };
    
    VQR.proceedToStrainOrder = function() {
        const selectedCheckboxes = document.querySelectorAll('.vqr-qr-checkbox:checked');
        const selectedIds = Array.from(selectedCheckboxes).map(cb => cb.value);
        
        if (selectedIds.length === 0) {
            alert('Please select at least one QR code to order stickers for.');
            return;
        }
        
        // Navigate to order page with selected QR IDs
        const url = new URL('<?php echo home_url('/app/order'); ?>');
        url.searchParams.set('qr_ids', selectedIds.join(','));
        window.location.href = url.toString();
    };
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Strains';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>