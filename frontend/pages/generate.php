<?php
/**
 * QR Code Generation page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Get current user data
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get user's subscription info
$user_plan = vqr_get_user_plan();
$plan_details = vqr_get_plan_details($user_plan);
$monthly_quota = vqr_get_user_quota();
$current_usage = vqr_get_user_usage();
$remaining_quota = $monthly_quota === -1 ? 'Unlimited' : ($monthly_quota - $current_usage);

// Get user's strains only
$strains = vqr_get_user_strains($user_id);

// Check if strain is pre-selected via URL
$preselected_strain_id = isset($_GET['strain']) ? intval($_GET['strain']) : 0;
$preselected_strain = null;

if ($preselected_strain_id > 0) {
    foreach ($strains as $strain) {
        if ($strain->ID === $preselected_strain_id) {
            $preselected_strain = $strain;
            break;
        }
    }
}

// Prepare page content
ob_start();
?>

<div class="vqr-generate-page">
    <div class="vqr-generate-form-container">
    <!-- Page Header -->
    <div class="vqr-page-header">
        <h1 class="vqr-page-title">Generate QR Codes</h1>
        <p class="vqr-page-description">Create secure QR codes for your cannabis products.</p>
    </div>
    
    <!-- Quota Status Card -->
    <div class="vqr-quota-card vqr-card vqr-mb-lg">
        <div class="vqr-card-content">
            <div class="vqr-quota-header">
                <h3>Monthly Usage</h3>
                <span class="vqr-plan-badge vqr-badge vqr-badge-success"><?php echo esc_html($plan_details['name']); ?> Plan</span>
            </div>
            
            <div class="vqr-quota-bar-container">
                <?php if ($monthly_quota === -1): ?>
                    <div class="vqr-quota-unlimited">
                        <svg class="vqr-quota-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        <span>Unlimited QR Codes</span>
                    </div>
                <?php else: ?>
                    <div class="vqr-quota-numbers">
                        <span class="vqr-quota-used"><?php echo number_format($current_usage); ?></span>
                        <span class="vqr-quota-separator">/</span>
                        <span class="vqr-quota-total"><?php echo number_format($monthly_quota); ?></span>
                        <span class="vqr-quota-label">QR codes used this month</span>
                    </div>
                    
                    <div class="vqr-quota-bar">
                        <div class="vqr-quota-progress" style="width: <?php echo min(($current_usage / $monthly_quota) * 100, 100); ?>%;"></div>
                    </div>
                    
                    <div class="vqr-quota-remaining">
                        <?php if ($remaining_quota > 0): ?>
                            <span class="vqr-text-success">✓ <?php echo number_format($remaining_quota); ?> QR codes remaining</span>
                        <?php else: ?>
                            <span class="vqr-text-error">⚠️ Monthly quota reached</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($user_plan === 'free' && ($current_usage / $monthly_quota) > 0.8): ?>
                <div class="vqr-upgrade-prompt">
                    <p>Running low on QR codes?</p>
                    <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                        Upgrade Plan
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- QR Generation Form -->
    <div class="vqr-card">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">Generate New QR Codes</h3>
        </div>
        <div class="vqr-card-content">
            <form class="vqr-form vqr-generate-form" data-action="vqr_generate_qr_codes" method="post">
                <?php wp_nonce_field('vqr_generate_qr_codes', 'vqr_generate_nonce'); ?>
                
                <!-- Mobile-First Form Layout -->
                <div class="vqr-form-grid">
                    
                    <!-- Strain Selection -->
                    <div class="vqr-form-group vqr-form-full">
                        <label for="strain_id" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            Select Strain
                        </label>
                        <?php if (!empty($strains)): ?>
                            <select id="strain_id" name="strain_id" class="vqr-input vqr-select" required>
                                <option value="">Choose a strain...</option>
                                <?php foreach ($strains as $strain): ?>
                                    <option value="<?php echo esc_attr($strain->ID); ?>" 
                                            data-url="<?php echo esc_attr(get_permalink($strain->ID)); ?>"
                                            <?php echo ($preselected_strain && $strain->ID === $preselected_strain->ID) ? 'selected' : ''; ?>>
                                        <?php echo esc_html($strain->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="vqr-field-help">QR codes will link to this strain's verification page</div>
                        <?php else: ?>
                            <div class="vqr-no-strains-message">
                                <p class="vqr-text-muted">You need to create a strain first before generating QR codes.</p>
                                <a href="<?php echo home_url('/app/strains'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-sm">
                                    <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Create Your First Strain
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Auto-filled URL -->
                    <div class="vqr-form-group vqr-form-full">
                        <label for="base_url" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            Verification URL
                        </label>
                        <input type="url" 
                               id="base_url" 
                               name="base_url" 
                               class="vqr-input" 
                               readonly 
                               placeholder="Select a strain first..."
                               required>
                        <div class="vqr-field-help">Automatically generated from selected strain</div>
                    </div>
                    
                    <!-- Quantity and Category Row -->
                    <div class="vqr-form-group vqr-form-half">
                        <label for="qr_count" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/>
                            </svg>
                            Quantity
                        </label>
                        <input type="number" 
                               id="qr_count" 
                               name="qr_count" 
                               class="vqr-input" 
                               min="1" 
                               max="<?php echo $monthly_quota === -1 ? 1000 : min(1000, $remaining_quota); ?>" 
                               value="10" 
                               required>
                        <div class="vqr-field-help">
                            <?php if ($monthly_quota !== -1): ?>
                                Max: <?php echo number_format($remaining_quota); ?> remaining
                            <?php else: ?>
                                Max: 1,000 per batch
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="vqr-form-group vqr-form-half">
                        <label for="category" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.997 1.997 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            Category
                        </label>
                        <input type="text" 
                               id="category" 
                               name="category" 
                               class="vqr-input" 
                               placeholder="e.g., Batch A, Summer 2024"
                               required>
                        <div class="vqr-field-help">Organize your QR codes</div>
                    </div>
                    
                    <!-- Prefix -->
                    <div class="vqr-form-group vqr-form-half">
                        <label for="code_prefix" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Code Prefix
                        </label>
                        <input type="text" 
                               id="code_prefix" 
                               name="code_prefix" 
                               class="vqr-input" 
                               minlength="4"
                               maxlength="4" 
                               placeholder="e.g., AB12"
                               style="text-transform: uppercase;"
                               required>
                        <div class="vqr-field-help">Exactly 4 characters required</div>
                    </div>
                    
                    <!-- Logo Upload -->
                    <div class="vqr-form-group vqr-form-half">
                        <label for="logo_file" class="vqr-label">
                            <svg class="vqr-label-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Logo (Optional)
                            <?php if (!vqr_user_can_upload_custom_logo()): ?>
                                <span class="vqr-locked-feature-badge">Starter+</span>
                            <?php endif; ?>
                        </label>
                        <?php if (vqr_user_can_upload_custom_logo()): ?>
                            <input type="file" 
                                   id="logo_file" 
                                   name="logo_file" 
                                   class="vqr-input vqr-file-input" 
                                   accept="image/png,image/jpeg">
                            <div class="vqr-field-help">PNG or JPEG format</div>
                        <?php else: ?>
                            <div class="vqr-locked-field-compact">
                                <div class="vqr-locked-content-compact">
                                    <svg class="vqr-locked-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Verify 420 branding on verification page</span>
                                </div>
                                <a href="<?php echo home_url('/app/billing'); ?>?utm_source=generate_logo&utm_medium=upgrade_prompt" class="vqr-btn vqr-btn-outline vqr-btn-xs">
                                    Upgrade for Custom
                                </a>
                            </div>
                            <div class="vqr-field-help">Free plan: Verify 420 logo appears on your verification page. Upgrade to Starter+ for custom logos on QR codes.</div>
                        <?php endif; ?>
                    </div>
                    
                </div>
                
                <!-- Generate Button -->
                <div class="vqr-form-actions">
                    <?php if (empty($strains)): ?>
                        <div class="vqr-no-strains-action">
                            <p class="vqr-text-muted">Create a strain first to generate QR codes</p>
                            <a href="<?php echo home_url('/app/strains'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-lg">
                                <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Create Your First Strain
                            </a>
                        </div>
                    <?php elseif ($monthly_quota === -1 || $remaining_quota > 0): ?>
                        <button type="submit" class="vqr-btn vqr-btn-primary vqr-btn-lg vqr-generate-btn">
                            <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Generate QR Codes
                        </button>
                    <?php else: ?>
                        <div class="vqr-quota-exceeded">
                            <p class="vqr-text-error">Monthly quota exceeded</p>
                            <a href="<?php echo home_url('/app/billing'); ?>" class="vqr-btn vqr-btn-primary vqr-btn-lg">
                                Upgrade Plan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
            </form>
        </div>
    </div>
    
    <!-- Generated QR Codes Results -->
    <div id="vqr-generation-results" style="display: none;"></div>
    
    </div>
</div>

<style>
/* QR Generation Page Styles */
.vqr-generate-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 16px;
    box-sizing: border-box;
    width: 100%;
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

.vqr-generate-form-container {
    max-width: 800px;
    margin: 0 auto;
}

.vqr-quota-card {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    border: none;
}

.vqr-quota-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-lg);
}

.vqr-quota-header h3 {
    margin: 0;
    font-size: var(--font-size-lg);
    font-weight: 600;
}

.vqr-plan-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.vqr-quota-bar-container {
    margin-bottom: var(--space-lg);
}

.vqr-quota-unlimited {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    font-size: var(--font-size-lg);
    font-weight: 600;
}

.vqr-quota-icon {
    width: 24px;
    height: 24px;
}

.vqr-quota-numbers {
    display: flex;
    align-items: baseline;
    gap: var(--space-xs);
    margin-bottom: var(--space-md);
}

.vqr-quota-used {
    font-size: var(--font-size-3xl);
    font-weight: 700;
}

.vqr-quota-separator {
    font-size: var(--font-size-xl);
    opacity: 0.7;
}

.vqr-quota-total {
    font-size: var(--font-size-xl);
    font-weight: 600;
}

.vqr-quota-label {
    font-size: var(--font-size-sm);
    opacity: 0.8;
    margin-left: var(--space-sm);
}

.vqr-quota-bar {
    width: 100%;
    height: 8px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: var(--radius-sm);
    overflow: hidden;
    margin-bottom: var(--space-sm);
}

.vqr-quota-progress {
    height: 100%;
    background: white;
    transition: width 0.3s ease;
    border-radius: var(--radius-sm);
}

.vqr-quota-remaining {
    font-size: var(--font-size-sm);
}

.vqr-upgrade-prompt {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: var(--space-md);
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.vqr-upgrade-prompt p {
    margin: 0;
    font-size: var(--font-size-sm);
}

/* Form Styles */
.vqr-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--space-lg);
}

@media (min-width: 768px) {
    .vqr-form-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .vqr-form-full {
        grid-column: 1 / -1;
    }
}

.vqr-label {
    display: flex;
    align-items: center;
    gap: var(--space-xs);
    font-weight: 600;
}

.vqr-label-icon {
    width: 16px;
    height: 16px;
    opacity: 0.7;
}

.vqr-field-help {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    margin-top: var(--space-xs);
}

.vqr-file-input {
    padding: var(--space-sm);
}

.vqr-form-actions {
    grid-column: 1 / -1;
    padding-top: var(--space-lg);
    border-top: 1px solid var(--border);
}

.vqr-generate-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
}

.vqr-btn-icon {
    width: 20px;
    height: 20px;
}

.vqr-quota-exceeded {
    text-align: center;
    padding: var(--space-lg);
    background: var(--surface);
    border-radius: var(--radius-md);
}

.vqr-quota-exceeded p {
    margin: 0 0 var(--space-md) 0;
    font-weight: 500;
}

.vqr-no-strains-message,
.vqr-no-strains-action {
    text-align: center;
    padding: var(--space-lg);
    background: var(--surface);
    border-radius: var(--radius-md);
    border: 2px dashed var(--border);
}

.vqr-no-strains-message p,
.vqr-no-strains-action p {
    margin: 0 0 var(--space-md) 0;
}

/* Auto-uppercase for prefix */
#code_prefix {
    text-transform: uppercase;
}

/* Loading state for form */
.vqr-form.loading .vqr-generate-btn {
    opacity: 0.6;
    pointer-events: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const strainSelect = document.getElementById('strain_id');
    const urlField = document.getElementById('base_url');
    const quantityField = document.getElementById('qr_count');
    const form = document.querySelector('.vqr-generate-form');
    
    // Auto-fill URL when strain is selected
    function updateUrlFromStrain() {
        const selectedOption = strainSelect.options[strainSelect.selectedIndex];
        if (selectedOption.value) {
            const strainUrl = selectedOption.getAttribute('data-url');
            urlField.value = strainUrl;
        } else {
            urlField.value = '';
        }
    }
    
    strainSelect.addEventListener('change', updateUrlFromStrain);
    
    // Trigger URL update on page load if strain is pre-selected
    <?php if ($preselected_strain): ?>
    updateUrlFromStrain();
    <?php endif; ?>
    
    // Real-time quota checking
    quantityField.addEventListener('input', function() {
        const quantity = parseInt(this.value) || 0;
        const maxAllowed = parseInt(this.getAttribute('max'));
        
        if (quantity > maxAllowed) {
            this.value = maxAllowed;
        }
    });
    
    // Auto-uppercase prefix and enforce 4-character length
    const prefixField = document.getElementById('code_prefix');
    prefixField.addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        
        // Update validation state
        if (this.value.length === 4) {
            this.setCustomValidity('');
        } else if (this.value.length < 4) {
            this.setCustomValidity('Code prefix must be exactly 4 characters');
        } else {
            // This shouldn't happen due to maxlength, but just in case
            this.value = this.value.substring(0, 4);
            this.setCustomValidity('');
        }
    });
    
    // Also validate on blur
    prefixField.addEventListener('blur', function() {
        if (this.value.length > 0 && this.value.length !== 4) {
            this.setCustomValidity('Code prefix must be exactly 4 characters');
        } else {
            this.setCustomValidity('');
        }
    });
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Generate QR Codes';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>