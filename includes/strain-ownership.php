<?php
/**
 * Strain User Ownership and Capabilities
 */

defined('ABSPATH') || exit;

/**
 * Add strain capabilities to QR customer roles
 */
function vqr_add_strain_capabilities() {
    $roles = ['qr_customer_free', 'qr_customer_starter', 'qr_customer_pro', 'qr_customer_enterprise'];
    
    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            // Add strain management capabilities
            $role->add_cap('edit_strains');
            $role->add_cap('edit_published_strains');
            $role->add_cap('publish_strains');
            $role->add_cap('delete_strains');
            $role->add_cap('delete_published_strains');
            $role->add_cap('create_strains');
        }
    }
}

/**
 * Filter strain queries to show only user's own strains
 */
function vqr_filter_user_strains($query) {
    global $pagenow, $typenow;
    
    // Only apply to strain post type queries for non-admin users
    if ($typenow === 'strain' && !current_user_can('manage_options')) {
        $query->set('author', get_current_user_id());
    }
}
add_action('pre_get_posts', 'vqr_filter_user_strains');

/**
 * Prevent users from editing others' strains
 */
function vqr_restrict_strain_editing($caps, $cap, $user_id, $args) {
    if ($cap === 'edit_post' && isset($args[0])) {
        $post = get_post($args[0]);
        
        if ($post && $post->post_type === 'strain') {
            // Admins can edit any strain
            if (user_can($user_id, 'manage_options')) {
                return $caps;
            }
            
            // Users can only edit their own strains
            if ($post->post_author != $user_id) {
                return ['do_not_allow'];
            }
        }
    }
    
    return $caps;
}
add_filter('map_meta_cap', 'vqr_restrict_strain_editing', 10, 4);

/**
 * Get user's strains
 */
function vqr_get_user_strains($user_id = null, $args = []) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    $defaults = [
        'post_type' => 'strain',
        'post_status' => 'publish',
        'author' => $user_id,
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    return get_posts($args);
}

/**
 * Check if user can manage strain
 */
function vqr_user_can_manage_strain($strain_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Admins can manage any strain
    if (user_can($user_id, 'manage_options')) {
        return true;
    }
    
    $strain = get_post($strain_id);
    if (!$strain || $strain->post_type !== 'strain') {
        return false;
    }
    
    // Users can only manage their own strains
    return $strain->post_author == $user_id;
}

/**
 * Create a new strain for user
 */
function vqr_create_user_strain($strain_data, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if user can create strains
    if (!user_can($user_id, 'create_strains')) {
        return new WP_Error('no_permission', 'You do not have permission to create strains.');
    }
    
    $post_data = [
        'post_title' => sanitize_text_field($strain_data['strain_name']),
        'post_content' => wp_kses_post($strain_data['product_description'] ?? ''),
        'post_status' => 'publish',
        'post_type' => 'strain',
        'post_author' => $user_id
    ];
    
    $strain_id = wp_insert_post($post_data);
    
    if (is_wp_error($strain_id)) {
        return $strain_id;
    }
    
    // Save strain meta fields
    $meta_fields = [
        'strain_genetics',
        'batch_id',
        'thc_mg',
        'thc_percentage',
        'cbd_mg',
        'cbd_percentage',
        'product_logo',
        'product_image',
        'instagram_url',
        'telegram_url',
        'facebook_url',
        'twitter_url'
    ];
    
    foreach ($meta_fields as $field) {
        if (isset($strain_data[$field])) {
            update_post_meta($strain_id, $field, sanitize_text_field($strain_data[$field]));
        }
    }
    
    return $strain_id;
}

/**
 * Update user's strain
 */
function vqr_update_user_strain($strain_id, $strain_data, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if user can edit this strain
    if (!vqr_user_can_manage_strain($strain_id, $user_id)) {
        return new WP_Error('no_permission', 'You do not have permission to edit this strain.');
    }
    
    $post_data = [
        'ID' => $strain_id,
        'post_title' => sanitize_text_field($strain_data['strain_name']),
        'post_content' => wp_kses_post($strain_data['product_description'] ?? '')
    ];
    
    $result = wp_update_post($post_data);
    
    if (is_wp_error($result)) {
        return $result;
    }
    
    // Update strain meta fields
    $meta_fields = [
        'strain_genetics',
        'batch_id',
        'thc_mg',
        'thc_percentage',
        'cbd_mg',
        'cbd_percentage',
        'product_logo',
        'product_image',
        'instagram_url',
        'telegram_url',
        'facebook_url',
        'twitter_url'
    ];
    
    foreach ($meta_fields as $field) {
        if (isset($strain_data[$field])) {
            update_post_meta($strain_id, $field, sanitize_text_field($strain_data[$field]));
        }
    }
    
    return $strain_id;
}

/**
 * Delete user's strain
 */
function vqr_delete_user_strain($strain_id, $user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check if user can delete this strain
    if (!vqr_user_can_manage_strain($strain_id, $user_id)) {
        return new WP_Error('no_permission', 'You do not have permission to delete this strain.');
    }
    
    // Check if strain has associated QR codes
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';
    
    $qr_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d",
        $strain_id
    ));
    
    if ($qr_count > 0) {
        return new WP_Error('has_qr_codes', "Cannot delete strain with {$qr_count} associated QR codes. Delete QR codes first.");
    }
    
    return wp_delete_post($strain_id, true); // Force delete (skip trash)
}

// Add capabilities when roles are created
add_action('vqr_plugin_activated', 'vqr_add_strain_capabilities');