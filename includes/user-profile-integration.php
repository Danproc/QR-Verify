<?php
/**
 * User Profile Integration for QR Code Quota Management
 */

defined('ABSPATH') || exit;

/**
 * Add QR quota fields to user profile pages
 */
function vqr_add_user_profile_fields($user) {
    // Only show for QR customer users or when admin is viewing any user
    if (!current_user_can('manage_options') && !vqr_is_qr_customer($user->ID)) {
        return;
    }
    
    // Get user quota information
    $quota_info = vqr_admin_get_user_quota_info($user->ID);
    if (is_wp_error($quota_info)) {
        $quota_info = array(
            'subscription_plan' => get_user_meta($user->ID, 'vqr_subscription_plan', true) ?: 'free',
            'monthly_quota' => vqr_get_user_quota($user->ID),
            'current_usage' => vqr_get_user_usage($user->ID),
            'remaining_quota' => vqr_get_user_quota($user->ID) === -1 ? 'Unlimited' : (vqr_get_user_quota($user->ID) - vqr_get_user_usage($user->ID)),
            'last_quota_reset' => get_user_meta($user->ID, 'vqr_last_quota_reset', true),
            'quota_updated_date' => get_user_meta($user->ID, 'vqr_quota_updated_date', true),
        );
    }
    
    // Only show management controls to admins
    $is_admin = current_user_can('manage_options');
    ?>
    
    <h3>Verify 420 QR Code Information</h3>
    <table class="form-table" role="presentation">
        <tr>
            <th><label>Subscription Plan</label></th>
            <td>
                <strong><?php echo esc_html(ucfirst($quota_info['subscription_plan'])); ?></strong>
                <?php if ($is_admin): ?>
                    <br><small class="description">User's current subscription tier</small>
                <?php endif; ?>
            </td>
        </tr>
        
        <tr>
            <th><label>Monthly QR Quota</label></th>
            <td>
                <strong><?php echo $quota_info['monthly_quota'] === -1 ? 'Unlimited' : number_format($quota_info['monthly_quota']); ?></strong>
                <?php if ($is_admin): ?>
                    <div style="margin-top: 10px;">
                        <input type="number" id="vqr_new_quota" min="-1" placeholder="Enter new quota (-1 for unlimited)" style="width: 200px;" />
                        <button type="button" class="button button-primary" onclick="vqrSetQuota(<?php echo $user->ID; ?>)">Set Quota</button>
                        <br><small class="description">Set exact monthly QR generation limit</small>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        
        <tr>
            <th><label>Current Usage</label></th>
            <td>
                <strong><?php echo number_format($quota_info['current_usage']); ?></strong>
                <?php if ($is_admin): ?>
                    <div style="margin-top: 10px;">
                        <input type="number" id="vqr_new_usage" min="0" placeholder="Enter usage amount" style="width: 200px;" />
                        <button type="button" class="button" onclick="vqrSetUsage(<?php echo $user->ID; ?>)">Set Usage</button>
                        <button type="button" class="button button-secondary" onclick="vqrResetUsage(<?php echo $user->ID; ?>)">Reset to 0</button>
                        <br><small class="description">Manually adjust how many QR codes used this month</small>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        
        <tr>
            <th><label>Remaining Quota</label></th>
            <td>
                <strong style="color: <?php echo ($quota_info['remaining_quota'] === 'Unlimited' || $quota_info['remaining_quota'] > 0) ? '#0073aa' : '#d63638'; ?>">
                    <?php echo is_numeric($quota_info['remaining_quota']) ? number_format($quota_info['remaining_quota']) : $quota_info['remaining_quota']; ?>
                </strong>
                <?php if ($is_admin): ?>
                    <div style="margin-top: 10px;">
                        <input type="number" id="vqr_quota_tokens" placeholder="Tokens to add/remove" style="width: 200px;" />
                        <button type="button" class="button" onclick="vqrAddTokens(<?php echo $user->ID; ?>)">Add/Remove Tokens</button>
                        <br><small class="description">Add positive numbers to increase, negative to decrease quota</small>
                    </div>
                <?php endif; ?>
            </td>
        </tr>
        
        <?php if (!empty($quota_info['last_quota_reset'])): ?>
        <tr>
            <th><label>Last Quota Reset</label></th>
            <td><?php echo esc_html(date('F j, Y', strtotime($quota_info['last_quota_reset']))); ?></td>
        </tr>
        <?php endif; ?>
        
        <?php if (!empty($quota_info['quota_updated_date'])): ?>
        <tr>
            <th><label>Last Quota Update</label></th>
            <td><?php echo esc_html(date('F j, Y g:i A', strtotime($quota_info['quota_updated_date']))); ?></td>
        </tr>
        <?php endif; ?>
        
        <?php if ($is_admin): ?>
        <tr>
            <th><label>QR Management</label></th>
            <td>
                <a href="<?php echo admin_url('admin.php?page=verification_qr_manager'); ?>" class="button">
                    View User's QR Codes
                </a>
                <br><small class="description">Manage QR codes associated with this user</small>
            </td>
        </tr>
        <?php endif; ?>
    </table>
    
    <!-- Messages area -->
    <div id="vqr-profile-messages" style="margin-top: 20px;"></div>
    
    <style>
        .vqr-profile-notice {
            background: #fff;
            border-left: 4px solid #0073aa;
            padding: 12px;
            margin: 15px 0;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .vqr-profile-notice.error {
            border-left-color: #d63638;
        }
        
        .vqr-profile-notice.success {
            border-left-color: #00a32a;
        }
    </style>
    
    <?php if ($is_admin): ?>
    <script>
    function vqrSetQuota(userId) {
        console.log('vqrSetQuota called with userId:', userId);
        const quota = parseInt(document.getElementById('vqr_new_quota').value);
        console.log('Quota value:', quota);
        if (isNaN(quota) || quota < -1) {
            vqrShowMessage('Please enter a valid quota (-1 for unlimited or positive number).', 'error');
            return;
        }
        
        vqrPerformAction('vqr_admin_set_quota', userId, {quota: quota}, function(response) {
            document.getElementById('vqr_new_quota').value = '';
            location.reload(); // Refresh to show updated values
        });
    }
    
    function vqrSetUsage(userId) {
        const usage = parseInt(document.getElementById('vqr_new_usage').value);
        if (isNaN(usage) || usage < 0) {
            vqrShowMessage('Please enter a valid usage number (0 or positive).', 'error');
            return;
        }
        
        vqrPerformAction('vqr_admin_set_usage', userId, {usage: usage}, function(response) {
            document.getElementById('vqr_new_usage').value = '';
            location.reload();
        });
    }
    
    function vqrResetUsage(userId) {
        if (confirm('Are you sure you want to reset this user\'s usage to 0?')) {
            vqrPerformAction('vqr_admin_reset_usage', userId, {}, function(response) {
                location.reload();
            });
        }
    }
    
    function vqrAddTokens(userId) {
        const tokens = parseInt(document.getElementById('vqr_quota_tokens').value);
        if (isNaN(tokens) || tokens === 0) {
            vqrShowMessage('Please enter a valid number of tokens.', 'error');
            return;
        }
        
        vqrPerformAction('vqr_admin_add_tokens', userId, {tokens: tokens}, function(response) {
            document.getElementById('vqr_quota_tokens').value = '';
            location.reload();
        });
    }
    
    function vqrPerformAction(action, userId, extraData, successCallback) {
        const data = {
            action: action,
            user_id: userId,
            nonce: '<?php echo wp_create_nonce('vqr_admin_nonce'); ?>',
            ...extraData
        };
        
        console.log('Sending AJAX request:', data);
        console.log('URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                vqrShowMessage(data.data.message, 'success');
                if (successCallback) successCallback(data);
            } else {
                vqrShowMessage(data.data, 'error');
            }
        })
        .catch(error => {
            vqrShowMessage('Network error. Please try again.', 'error');
            console.error('Error:', error);
        });
    }
    
    function vqrShowMessage(message, type) {
        const messagesContainer = document.getElementById('vqr-profile-messages');
        const messageClass = type === 'error' ? 'error' : 'success';
        messagesContainer.innerHTML = `<div class="vqr-profile-notice ${messageClass}"><p>${message}</p></div>`;
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            messagesContainer.innerHTML = '';
        }, 5000);
    }
    </script>
    <?php endif; ?>
    
    <?php
}

/**
 * Check if user is a QR customer
 */
function vqr_is_qr_customer($user_id) {
    $user = get_user_by('ID', $user_id);
    if (!$user) return false;
    
    $qr_roles = ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'];
    return array_intersect($qr_roles, $user->roles);
}

/**
 * Add additional user columns to user list table
 */
function vqr_add_user_columns($columns) {
    $columns['vqr_plan'] = 'QR Plan';
    $columns['vqr_quota'] = 'QR Quota';
    $columns['vqr_usage'] = 'QR Usage';
    return $columns;
}

/**
 * Display user column content
 */
function vqr_display_user_columns($value, $column_name, $user_id) {
    if (!vqr_is_qr_customer($user_id)) {
        return $column_name === 'vqr_plan' ? '-' : $value;
    }
    
    switch ($column_name) {
        case 'vqr_plan':
            $plan = get_user_meta($user_id, 'vqr_subscription_plan', true) ?: 'free';
            return ucfirst($plan);
            
        case 'vqr_quota':
            $quota = vqr_get_user_quota($user_id);
            return $quota === -1 ? 'Unlimited' : number_format($quota);
            
        case 'vqr_usage':
            $usage = vqr_get_user_usage($user_id);
            $quota = vqr_get_user_quota($user_id);
            if ($quota === -1) {
                return number_format($usage);
            } else {
                $percentage = $quota > 0 ? round(($usage / $quota) * 100) : 0;
                $color = $percentage > 80 ? '#d63638' : ($percentage > 60 ? '#dba617' : '#00a32a');
                return sprintf(
                    '<span style="color: %s;">%s/%s (%d%%)</span>',
                    $color,
                    number_format($usage),
                    number_format($quota),
                    $percentage
                );
            }
    }
    
    return $value;
}

/**
 * Make user columns sortable
 */
function vqr_make_user_columns_sortable($columns) {
    $columns['vqr_plan'] = 'vqr_plan';
    $columns['vqr_quota'] = 'vqr_quota';
    $columns['vqr_usage'] = 'vqr_usage';
    return $columns;
}

/**
 * Handle sorting for user columns
 */
function vqr_handle_user_column_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    switch ($orderby) {
        case 'vqr_plan':
            $query->set('meta_key', 'vqr_subscription_plan');
            $query->set('orderby', 'meta_value');
            break;
            
        case 'vqr_quota':
            $query->set('meta_key', 'vqr_monthly_quota');
            $query->set('orderby', 'meta_value_num');
            break;
            
        case 'vqr_usage':
            $query->set('meta_key', 'vqr_current_usage');
            $query->set('orderby', 'meta_value_num');
            break;
    }
}

// Hook into WordPress user profile system
add_action('show_user_profile', 'vqr_add_user_profile_fields');
add_action('edit_user_profile', 'vqr_add_user_profile_fields');

// Hook into user list table
add_filter('manage_users_columns', 'vqr_add_user_columns');
add_filter('manage_users_custom_column', 'vqr_display_user_columns', 10, 3);
add_filter('manage_users_sortable_columns', 'vqr_make_user_columns_sortable');
add_action('pre_get_users', 'vqr_handle_user_column_sorting');