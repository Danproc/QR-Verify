/**
 * Verify 420 SaaS Dashboard JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.vqr-mobile-menu-btn');
    const sidebar = document.querySelector('.vqr-app-sidebar');
    const overlay = document.querySelector('.vqr-mobile-overlay');
    
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
    
    // User dropdown menu toggle
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const isActive = userDropdown.classList.contains('active');
            
            // Close all other dropdowns first
            closeAllDropdowns();
            
            // Toggle current dropdown
            if (!isActive) {
                userDropdown.classList.add('active');
                userMenuToggle.classList.add('active');
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuToggle.contains(e.target) && !userDropdown.contains(e.target)) {
                closeAllDropdowns();
            }
        });
        
        // Close dropdown on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });
        
        // Handle dropdown item clicks
        userDropdown.addEventListener('click', function(e) {
            if (e.target.classList.contains('vqr-dropdown-item')) {
                // Close dropdown after clicking an item (except for links that navigate)
                setTimeout(() => {
                    closeAllDropdowns();
                }, 100);
            }
        });
    }
    
    // Function to close all dropdowns
    function closeAllDropdowns() {
        const dropdowns = document.querySelectorAll('.vqr-user-dropdown');
        const toggles = document.querySelectorAll('.vqr-user-menu-trigger');
        
        dropdowns.forEach(dropdown => dropdown.classList.remove('active'));
        toggles.forEach(toggle => toggle.classList.remove('active'));
    }
    
    // Form handling - exclude account page forms that handle their own submission
    const forms = document.querySelectorAll('.vqr-form:not(.vqr-account-form)');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmit);
    });
    
    // Auto-refresh functionality
    const autoRefreshElements = document.querySelectorAll('[data-auto-refresh]');
    autoRefreshElements.forEach(element => {
        const interval = parseInt(element.getAttribute('data-auto-refresh')) || 30000;
        setInterval(() => {
            refreshElement(element);
        }, interval);
    });
});

/**
 * Handle form submissions with AJAX
 */
function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    
    // Find submit button safely
    let submitBtn = form.querySelector('button[type="submit"]');
    if (!submitBtn) {
        submitBtn = event.submitter || document.activeElement;
        if (!submitBtn || submitBtn.type !== 'submit') {
            submitBtn = null;
        }
    }
    
    // Get original text safely
    const originalText = submitBtn ? submitBtn.textContent : 'Submit';
    
    // Show loading state
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="vqr-loading"></span> Processing...';
    }
    
    // Prepare form data
    const formData = new FormData(form);
    formData.append('action', form.getAttribute('data-action') || 'vqr_form_submit');
    formData.append('nonce', vqr_ajax.nonce);
    
    // Submit via AJAX
    fetch(vqr_ajax.url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Success!', data.data.message || 'Operation completed successfully.', 'success');
            
            // Handle redirect
            if (data.data.redirect) {
                window.location.href = data.data.redirect;
                return;
            }
            
            // Handle form reset
            if (data.data.reset_form) {
                form.reset();
            }
            
            // Handle element refresh
            if (data.data.refresh_element) {
                const element = document.querySelector(data.data.refresh_element);
                if (element) {
                    refreshElement(element);
                }
            }
        } else {
            showNotification('Error', data.data || 'An error occurred. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Form submission error:', error);
        showNotification('Error', 'Network error. Please check your connection and try again.', 'error');
    })
    .finally(() => {
        // Reset button state
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

/**
 * Show notification to user
 */
function showNotification(title, message, type = 'info') {
    // Remove existing notifications
    const existing = document.querySelectorAll('.vqr-notification');
    existing.forEach(el => el.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `vqr-notification vqr-notification-${type}`;
    notification.innerHTML = `
        <div class="vqr-notification-content">
            <div class="vqr-notification-title">${title}</div>
            <div class="vqr-notification-message">${message}</div>
        </div>
        <button class="vqr-notification-close">&times;</button>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        padding: var(--space-lg);
        max-width: 400px;
        z-index: 1000;
        display: flex;
        align-items: flex-start;
        gap: var(--space-md);
    `;
    
    if (type === 'success') {
        notification.style.borderColor = 'var(--success)';
    } else if (type === 'error') {
        notification.style.borderColor = 'var(--error)';
    }
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
    
    // Close button handler
    notification.querySelector('.vqr-notification-close').addEventListener('click', () => {
        notification.remove();
    });
}

/**
 * Refresh element content via AJAX
 */
function refreshElement(element) {
    const refreshUrl = element.getAttribute('data-refresh-url');
    if (!refreshUrl) return;
    
    fetch(refreshUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=vqr_refresh_element&nonce=${vqr_ajax.nonce}&element=${element.id}`
    })
    .then(response => response.text())
    .then(html => {
        element.innerHTML = html;
    })
    .catch(error => {
        console.error('Element refresh error:', error);
    });
}

/**
 * Copy to clipboard functionality
 */
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied!', 'Text copied to clipboard.', 'success');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Copied!', 'Text copied to clipboard.', 'success');
    }
}

/**
 * Confirm dialog wrapper
 */
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functions for global use (preserve existing functions)
window.VQR = window.VQR || {};
Object.assign(window.VQR, {
    showNotification,
    copyToClipboard,
    confirmAction,
    refreshElement
});