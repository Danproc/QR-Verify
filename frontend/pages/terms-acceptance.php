<?php
/**
 * Terms of Service Acceptance Page for Verify 420 SaaS
 */

defined('ABSPATH') || exit;

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_redirect(home_url('/app/login'));
    exit;
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Check if user has already accepted current TOS
if (vqr_user_has_accepted_current_tos($user_id)) {
    wp_redirect(home_url('/app/dashboard'));
    exit;
}

// Get current TOS version and URLs
$current_tos_version = vqr_get_current_tos_version();
$tos_url = vqr_get_tos_url();
$privacy_url = vqr_get_privacy_policy_url();

// Handle error messages
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'tos_required':
            $error_message = 'You must accept the Terms of Service to continue using our platform.';
            break;
        case 'acceptance_failed':
            $error_message = 'Failed to record your acceptance. Please try again.';
            break;
        default:
            $error_message = 'An error occurred. Please try again.';
    }
}

// Prepare page content
ob_start();
?>

<div class="vqr-terms-acceptance-page">
    <!-- Page Header -->
    <div class="vqr-page-header vqr-text-center">
        <div class="vqr-tos-icon">📋</div>
        <h1 class="vqr-page-title">Terms of Service Update</h1>
        <p class="vqr-page-description">Please review and accept our updated Terms of Service to continue using Verify 420.</p>
    </div>
    
    <!-- Error Message -->
    <?php if ($error_message): ?>
        <div class="vqr-alert vqr-alert-error">
            <svg class="vqr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <?php echo esc_html($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Terms Acceptance Card -->
    <div class="vqr-card vqr-terms-card">
        <div class="vqr-card-header">
            <h3 class="vqr-card-title">
                <svg class="vqr-title-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Updated Terms of Service (Version <?php echo esc_html($current_tos_version); ?>)
            </h3>
            <p class="vqr-card-description">
                We've updated our Terms of Service to better serve you and protect your data. Please review the changes and accept to continue.
            </p>
        </div>
        
        <div class="vqr-card-content">
            <!-- What's Changed Section -->
            <div class="vqr-tos-changes">
                <h4>
                    <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    What's Changed?
                </h4>
                <ul class="vqr-changes-list">
                    <li>
                        <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Improved data protection and privacy measures
                    </li>
                    <li>
                        <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Updated billing and subscription policies
                    </li>
                    <li>
                        <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Clarified service availability and limitations
                    </li>
                    <li>
                        <svg class="vqr-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enhanced user rights and responsibilities
                    </li>
                </ul>
            </div>
            
            <!-- Document Links -->
            <div class="vqr-document-links">
                <h4>
                    <svg class="vqr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Review Our Legal Documents
                </h4>
                <div class="vqr-links-grid">
                    <a href="<?php echo esc_url($tos_url); ?>" target="_blank" class="vqr-document-link">
                        <div class="vqr-link-icon">📄</div>
                        <div class="vqr-link-content">
                            <h5>Terms of Service</h5>
                            <p>Complete terms and conditions</p>
                        </div>
                        <svg class="vqr-external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                    
                    <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" class="vqr-document-link">
                        <div class="vqr-link-icon">🔒</div>
                        <div class="vqr-link-content">
                            <h5>Privacy Policy</h5>
                            <p>How we protect your data</p>
                        </div>
                        <svg class="vqr-external-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
            
            <!-- Acceptance Form -->
            <div class="vqr-acceptance-section">
                <form method="POST" class="vqr-tos-form">
                    <?php wp_nonce_field('vqr_accept_tos', 'vqr_accept_tos_nonce'); ?>
                    <input type="hidden" name="action" value="accept_tos">
                    
                    <div class="vqr-acceptance-checkbox">
                        <label class="vqr-checkbox-container">
                            <input type="checkbox" name="accept_tos" value="1" required id="acceptTosCheckbox">
                            <span class="vqr-checkmark"></span>
                            <div class="vqr-checkbox-text">
                                <strong>I have read, understood, and agree to the 
                                <a href="<?php echo esc_url($tos_url); ?>" target="_blank">Terms of Service</a> 
                                and 
                                <a href="<?php echo esc_url($privacy_url); ?>" target="_blank">Privacy Policy</a>.</strong>
                                <p class="vqr-checkbox-help">
                                    By checking this box, you acknowledge that you have reviewed the updated terms and agree to be bound by them.
                                </p>
                            </div>
                        </label>
                    </div>
                    
                    <div class="vqr-form-actions">
                        <button type="submit" class="vqr-btn vqr-btn-primary vqr-btn-lg" id="acceptTosButton" disabled>
                            <svg class="vqr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Accept Terms & Continue
                        </button>
                        
                        <div class="vqr-alternative-actions">
                            <p class="vqr-text-muted">
                                Can't accept these terms? 
                                <a href="mailto:support@verify420.com" class="vqr-link">Contact our support team</a> 
                                or 
                                <a href="<?php echo wp_logout_url(home_url()); ?>" class="vqr-link">sign out</a>.
                            </p>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Important Notice -->
            <div class="vqr-important-notice">
                <div class="vqr-notice-icon">⚠️</div>
                <div class="vqr-notice-content">
                    <h4>Important Notice</h4>
                    <p>
                        You must accept the updated Terms of Service to continue using Verify 420. 
                        If you cannot accept these terms, your account will remain suspended and you will not be able to access our services.
                    </p>
                    <p>
                        <strong>This acceptance will be recorded with your IP address and timestamp for legal compliance.</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Terms Acceptance Page Styles */
.vqr-terms-acceptance-page {
    max-width: 800px;
    margin: 0 auto;
    padding: var(--space-lg);
}

.vqr-tos-icon {
    font-size: 64px;
    margin-bottom: var(--space-md);
}

.vqr-terms-card {
    border: 2px solid var(--primary);
}

.vqr-title-icon,
.vqr-section-icon {
    width: 20px;
    height: 20px;
    margin-right: var(--space-sm);
    color: var(--primary);
}

.vqr-card-description {
    color: var(--text-muted);
    font-size: var(--font-size-lg);
    margin: var(--space-sm) 0 0 0;
}

/* Changes Section */
.vqr-tos-changes {
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: var(--space-lg);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-xl);
}

.vqr-tos-changes h4 {
    display: flex;
    align-items: center;
    margin: 0 0 var(--space-md) 0;
    color: #0c4a6e;
    font-size: var(--font-size-lg);
    font-weight: 600;
}

.vqr-changes-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.vqr-changes-list li {
    display: flex;
    align-items: flex-start;
    gap: var(--space-sm);
    padding: var(--space-sm) 0;
    color: #0c4a6e;
    font-weight: 500;
}

.vqr-check-icon {
    width: 16px;
    height: 16px;
    color: #059669;
    flex-shrink: 0;
    margin-top: 2px;
}

/* Document Links */
.vqr-document-links {
    margin-bottom: var(--space-xl);
}

.vqr-document-links h4 {
    display: flex;
    align-items: center;
    margin: 0 0 var(--space-lg) 0;
    color: var(--text-primary);
    font-size: var(--font-size-lg);
    font-weight: 600;
}

.vqr-links-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-md);
}

.vqr-document-link {
    display: flex;
    align-items: center;
    gap: var(--space-md);
    padding: var(--space-lg);
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.vqr-document-link:hover {
    background: var(--white);
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
}

.vqr-link-icon {
    font-size: 24px;
}

.vqr-link-content h5 {
    margin: 0 0 var(--space-xs) 0;
    font-weight: 600;
    color: var(--text-primary);
}

.vqr-link-content p {
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-external-icon {
    width: 16px;
    height: 16px;
    color: var(--text-muted);
    margin-left: auto;
}

/* Acceptance Section */
.vqr-acceptance-section {
    background: #fefffe;
    border: 2px solid var(--primary);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    margin-bottom: var(--space-xl);
}

.vqr-checkbox-container {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    cursor: pointer;
    position: relative;
    padding-left: 36px;
}

.vqr-checkbox-container input[type="checkbox"] {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}

.vqr-checkmark {
    position: absolute;
    left: 0;
    top: 2px;
    height: 24px;
    width: 24px;
    background-color: var(--white);
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    transition: all 0.2s ease;
}

.vqr-checkbox-container:hover input ~ .vqr-checkmark {
    border-color: var(--primary);
}

.vqr-checkbox-container input:checked ~ .vqr-checkmark {
    background-color: var(--primary);
    border-color: var(--primary);
}

.vqr-checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 7px;
    top: 3px;
    width: 6px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.vqr-checkbox-container input:checked ~ .vqr-checkmark:after {
    display: block;
}

.vqr-checkbox-text {
    flex: 1;
}

.vqr-checkbox-text strong {
    display: block;
    margin-bottom: var(--space-sm);
    font-size: var(--font-size-lg);
    color: var(--text-primary);
}

.vqr-checkbox-text a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
}

.vqr-checkbox-text a:hover {
    text-decoration: underline;
}

.vqr-checkbox-help {
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.vqr-form-actions {
    margin-top: var(--space-xl);
    text-align: center;
}

.vqr-btn-lg {
    padding: var(--space-lg) var(--space-xl);
    font-size: var(--font-size-lg);
    font-weight: 600;
}

.vqr-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background: var(--gray-300);
    border-color: var(--gray-300);
    color: var(--gray-500);
}

.vqr-alternative-actions {
    margin-top: var(--space-lg);
}

.vqr-alternative-actions p {
    margin: 0;
}

.vqr-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
}

.vqr-link:hover {
    text-decoration: underline;
}

/* Important Notice */
.vqr-important-notice {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-left: 4px solid #f59e0b;
    padding: var(--space-lg);
    border-radius: var(--radius-md);
}

.vqr-notice-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.vqr-notice-content h4 {
    margin: 0 0 var(--space-sm) 0;
    color: #92400e;
    font-weight: 600;
}

.vqr-notice-content p {
    margin: 0 0 var(--space-sm) 0;
    color: #92400e;
    font-size: var(--font-size-sm);
}

.vqr-notice-content p:last-child {
    margin-bottom: 0;
}

/* Responsive Design */
@media (max-width: 768px) {
    .vqr-terms-acceptance-page {
        padding: var(--space-md);
    }
    
    .vqr-links-grid {
        grid-template-columns: 1fr;
    }
    
    .vqr-document-link {
        padding: var(--space-md);
    }
    
    .vqr-acceptance-section {
        padding: var(--space-lg);
    }
    
    .vqr-checkbox-container {
        padding-left: 32px;
    }
    
    .vqr-important-notice {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('acceptTosCheckbox');
    const button = document.getElementById('acceptTosButton');
    
    if (checkbox && button) {
        // Enable/disable button based on checkbox state
        checkbox.addEventListener('change', function() {
            button.disabled = !this.checked;
            
            if (this.checked) {
                button.classList.remove('vqr-btn-disabled');
            } else {
                button.classList.add('vqr-btn-disabled');
            }
        });
        
        // Form submission handling
        const form = document.querySelector('.vqr-tos-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!checkbox.checked) {
                    e.preventDefault();
                    alert('Please read and accept the Terms of Service to continue.');
                    return false;
                }
                
                // Show loading state
                button.disabled = true;
                button.innerHTML = '<svg class="vqr-btn-icon animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" class="opacity-75"></path></svg> Processing...';
                
                return true;
            });
        }
    }
    
    // Track document link clicks for analytics
    const documentLinks = document.querySelectorAll('.vqr-document-link');
    documentLinks.forEach(link => {
        link.addEventListener('click', function() {
            // You could add analytics tracking here
            console.log('Document link clicked:', this.href);
        });
    });
});
</script>

<?php
$page_content = ob_get_clean();
$page_title = 'Terms of Service Acceptance';

// Include base template
include VQR_PLUGIN_DIR . 'frontend/templates/base.php';
?>