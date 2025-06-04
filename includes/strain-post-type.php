<?php
/**
 * Strain Custom Post Type and Fields
 */

defined('ABSPATH') || exit;

/**
 * Register the Strain custom post type
 */
function vqr_register_strain_post_type() {
    register_post_type( 'strain', array(
        'labels' => array(
            'name' => 'Strains',
            'singular_name' => 'Strain',
            'menu_name' => 'Strains',
            'all_items' => 'All Strains',
            'edit_item' => 'Edit Strain',
            'view_item' => 'View Strain',
            'view_items' => 'View Strains',
            'add_new_item' => 'Add New Strain',
            'add_new' => 'Add New Strain',
            'new_item' => 'New Strain',
            'parent_item_colon' => 'Parent Strain:',
            'search_items' => 'Search Strains',
            'not_found' => 'No strains found',
            'not_found_in_trash' => 'No strains found in Trash',
            'archives' => 'Strain Archives',
            'attributes' => 'Strain Attributes',
            'insert_into_item' => 'Insert into strain',
            'uploaded_to_this_item' => 'Uploaded to this strain',
            'filter_items_list' => 'Filter strains list',
            'filter_by_date' => 'Filter strains by date',
            'items_list_navigation' => 'Strains list navigation',
            'items_list' => 'Strains list',
            'item_published' => 'Strain published.',
            'item_published_privately' => 'Strain published privately.',
            'item_reverted_to_draft' => 'Strain reverted to draft.',
            'item_scheduled' => 'Strain scheduled.',
            'item_updated' => 'Strain updated.',
            'item_link' => 'Strain Link',
            'item_link_description' => 'A link to a strain.',
        ),
        'public' => true,
        'hierarchical' => true,
        'show_in_rest' => true,
        'menu_position' => 1,
        'menu_icon' => 'dashicons-shortcode',
        'supports' => array(
            'title',
            'editor',
            'custom-fields',
        ),
        'rewrite' => false,
        'delete_with_user' => false,
    ));
}
add_action('init', 'vqr_register_strain_post_type');

/**
 * Register the Company taxonomy for strains
 */
function vqr_register_company_taxonomy() {
    register_taxonomy( 'company', array( 'strain' ), array(
        'labels' => array(
            'name' => 'Companies',
            'singular_name' => 'Company',
            'menu_name' => 'Companies',
            'all_items' => 'All Companies',
            'edit_item' => 'Edit Company',
            'view_item' => 'View Company',
            'update_item' => 'Update Company',
            'add_new_item' => 'Add New Company',
            'new_item_name' => 'New Company Name',
            'parent_item' => 'Parent Company',
            'parent_item_colon' => 'Parent Company:',
            'search_items' => 'Search Companies',
            'not_found' => 'No companies found',
            'no_terms' => 'No companies',
            'filter_by_item' => 'Filter by company',
            'items_list_navigation' => 'Companies list navigation',
            'items_list' => 'Companies list',
            'back_to_items' => '← Go to companies',
            'item_link' => 'Company Link',
            'item_link_description' => 'A link to a company',
        ),
        'public' => true,
        'hierarchical' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'show_admin_column' => true,
    ));
}
add_action('init', 'vqr_register_company_taxonomy');

/**
 * Add meta boxes for strain fields
 */
function vqr_add_strain_meta_boxes() {
    add_meta_box(
        'strain_basic_info',
        'Basic Information',
        'vqr_strain_basic_info_callback',
        'strain',
        'normal',
        'high'
    );
    
    add_meta_box(
        'strain_cannabinoids',
        'Cannabinoid Information',
        'vqr_strain_cannabinoids_callback',
        'strain',
        'normal',
        'high'
    );
    
    add_meta_box(
        'strain_media',
        'Media',
        'vqr_strain_media_callback',
        'strain',
        'side',
        'default'
    );
    
    add_meta_box(
        'strain_social',
        'Social Media Links',
        'vqr_strain_social_callback',
        'strain',
        'normal',
        'default'
    );
    
    add_meta_box(
        'strain_tracking',
        'QR Tracking',
        'vqr_strain_tracking_callback',
        'strain',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'vqr_add_strain_meta_boxes');

/**
 * Basic Information meta box callback
 */
function vqr_strain_basic_info_callback($post) {
    wp_nonce_field('vqr_strain_meta_nonce', 'vqr_strain_meta_nonce');
    
    $strain_genetics = get_post_meta($post->ID, 'strain_genetics', true);
    $batch_id = get_post_meta($post->ID, 'batch_id', true);
    $batch_code = get_post_meta($post->ID, 'batch_code', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="strain_genetics">Strain Genetics</label></th>
            <td><input type="text" id="strain_genetics" name="strain_genetics" value="<?php echo esc_attr($strain_genetics); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="batch_id">Batch ID</label></th>
            <td><input type="text" id="batch_id" name="batch_id" value="<?php echo esc_attr($batch_id); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="batch_code">Batch Code</label></th>
            <td>
                <input type="text" id="batch_code" name="batch_code" value="<?php echo esc_attr($batch_code); ?>" class="regular-text" readonly />
                <p class="description">This is automatically generated when QR codes are created.</p>
            </td>
        </tr>
    </table>
    
    <h4>Product Description</h4>
    <?php
    $product_description = get_post_meta($post->ID, 'product_description', true);
    wp_editor($product_description, 'product_description', array(
        'textarea_name' => 'product_description',
        'media_buttons' => true,
        'textarea_rows' => 10,
    ));
    ?>
    <?php
}

/**
 * Cannabinoids meta box callback
 */
function vqr_strain_cannabinoids_callback($post) {
    $thc_mg = get_post_meta($post->ID, 'thc_mg', true);
    $thc_percentage = get_post_meta($post->ID, 'thc_percentage', true);
    $cbd_mg = get_post_meta($post->ID, 'cbd_mg', true);
    $cbd_percentage = get_post_meta($post->ID, 'cbd_percentage', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="thc_mg">THC MG</label></th>
            <td><input type="text" id="thc_mg" name="thc_mg" value="<?php echo esc_attr($thc_mg); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="thc_percentage">THC Percentage</label></th>
            <td><input type="text" id="thc_percentage" name="thc_percentage" value="<?php echo esc_attr($thc_percentage); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="cbd_mg">CBD MG</label></th>
            <td><input type="text" id="cbd_mg" name="cbd_mg" value="<?php echo esc_attr($cbd_mg); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="cbd_percentage">CBD Percentage</label></th>
            <td><input type="text" id="cbd_percentage" name="cbd_percentage" value="<?php echo esc_attr($cbd_percentage); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}

/**
 * Media meta box callback
 */
function vqr_strain_media_callback($post) {
    $product_logo = get_post_meta($post->ID, 'product_logo', true);
    $product_image = get_post_meta($post->ID, 'product_image', true);
    
    ?>
    <p><strong>Product Logo</strong></p>
    <div class="vqr-image-upload">
        <input type="hidden" id="product_logo" name="product_logo" value="<?php echo esc_attr($product_logo); ?>" />
        <div class="vqr-image-preview">
            <?php if ($product_logo): 
                $image = wp_get_attachment_image_src($product_logo, 'medium');
                if ($image): ?>
                    <img src="<?php echo esc_url($image[0]); ?>" style="max-width: 150px;" />
                <?php endif;
            endif; ?>
        </div>
        <button type="button" class="button vqr-upload-image" data-target="product_logo">Choose Logo</button>
        <button type="button" class="button vqr-remove-image" data-target="product_logo">Remove</button>
    </div>
    
    <hr />
    
    <p><strong>Product Image</strong></p>
    <div class="vqr-image-upload">
        <input type="hidden" id="product_image" name="product_image" value="<?php echo esc_attr($product_image); ?>" />
        <div class="vqr-image-preview">
            <?php if ($product_image): 
                $image = wp_get_attachment_image_src($product_image, 'medium');
                if ($image): ?>
                    <img src="<?php echo esc_url($image[0]); ?>" style="max-width: 150px;" />
                <?php endif;
            endif; ?>
        </div>
        <button type="button" class="button vqr-upload-image" data-target="product_image">Choose Image</button>
        <button type="button" class="button vqr-remove-image" data-target="product_image">Remove</button>
    </div>
    <?php
}

/**
 * Social media meta box callback
 */
function vqr_strain_social_callback($post) {
    $instagram_url = get_post_meta($post->ID, 'instagram_url', true);
    $telegram_url = get_post_meta($post->ID, 'telegram_url', true);
    $facebook_url = get_post_meta($post->ID, 'facebook_url', true);
    $twitter_url = get_post_meta($post->ID, 'twitter_url', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="instagram_url">Instagram URL</label></th>
            <td><input type="url" id="instagram_url" name="instagram_url" value="<?php echo esc_attr($instagram_url); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="telegram_url">Telegram URL</label></th>
            <td><input type="url" id="telegram_url" name="telegram_url" value="<?php echo esc_attr($telegram_url); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="facebook_url">Facebook URL</label></th>
            <td><input type="url" id="facebook_url" name="facebook_url" value="<?php echo esc_attr($facebook_url); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label for="twitter_url">Twitter URL</label></th>
            <td><input type="url" id="twitter_url" name="twitter_url" value="<?php echo esc_attr($twitter_url); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}

/**
 * QR Tracking meta box callback
 */
function vqr_strain_tracking_callback($post) {
    $scan_count = get_post_meta($post->ID, 'scan_count', true);
    $first_scanned_at = get_post_meta($post->ID, 'first_scanned_at', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="scan_count">Scan Count</label></th>
            <td>
                <input type="number" id="scan_count" name="scan_count" value="<?php echo esc_attr($scan_count); ?>" class="small-text" readonly />
                <p class="description">Updated automatically when QR codes are scanned.</p>
            </td>
        </tr>
        <tr>
            <th><label for="first_scanned_at">First Scanned</label></th>
            <td>
                <input type="text" id="first_scanned_at" name="first_scanned_at" value="<?php echo esc_attr($first_scanned_at); ?>" class="regular-text" readonly />
                <p class="description">Timestamp of the first scan.</p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Save strain meta data
 */
function vqr_save_strain_meta($post_id) {
    if (!isset($_POST['vqr_strain_meta_nonce']) || !wp_verify_nonce($_POST['vqr_strain_meta_nonce'], 'vqr_strain_meta_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    $fields = array(
        'strain_genetics',
        'batch_id',
        'product_description',
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
    );
    
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
add_action('save_post', 'vqr_save_strain_meta');

/**
 * Enqueue admin scripts for media upload
 */
function vqr_admin_scripts($hook) {
    global $post_type;
    
    if ($post_type === 'strain' && ($hook === 'post.php' || $hook === 'post-new.php')) {
        wp_enqueue_media();
        wp_enqueue_script('vqr-admin', VQR_PLUGIN_URL . 'assets/admin.js', array('jquery'), '1.0', true);
    }
}
add_action('admin_enqueue_scripts', 'vqr_admin_scripts');