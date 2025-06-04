<?php
/**
 * Admin page functionality
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
 * Display the main admin page
 */
function vqr_display_admin_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';

    //
    // 1) HANDLE ALL POST ACTIONS
    //
    // a) Generate new QR codes
    if ( isset( $_POST['generate_qr_codes'] ) ) {
        $count    = intval( $_POST['qr_count'] );
        $base_url = esc_url_raw( $_POST['base_url'] );
        $gen_cat  = sanitize_text_field( $_POST['category'] );
        $post_id  = intval( $_POST['post_id'] );
        $prefix   = sanitize_text_field( $_POST['code_prefix'] );

        // Optional logo
        $logo_path = '';
        if ( ! empty( $_FILES['logo_file']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upl = wp_handle_upload( $_FILES['logo_file'], [
                'test_form' => false,
                'mimes'     => [ 'png' => 'image/png', 'jpg|jpeg' => 'image/jpeg' ],
            ] );
            if ( empty( $upl['error'] ) ) {
                $logo_path = $upl['file'];
            }
        }

        if ( $count > 0 && filter_var( $base_url, FILTER_VALIDATE_URL ) ) {
            vqr_generate_codes( $count, $base_url, $gen_cat, $post_id, $prefix, $logo_path );
            echo "<div class='updated notice is-dismissible'><p>Generated {$count} QR codes.</p></div>";
        } else {
            echo "<div class='error notice is-dismissible'><p>Invalid input for generating QR codes.</p></div>";
        }
    }

    // b) Delete selected
    if ( isset( $_POST['delete_qr_codes'] ) && ! empty( $_POST['qr_ids'] ) ) {
        $ids        = array_map( 'intval', $_POST['qr_ids'] );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids )
        );
        echo "<div class='updated notice is-dismissible'><p>Deleted selected QR codes.</p></div>";
    }

    // c) Individual reset (single-row button)
    if ( isset( $_POST['reset_scan_count'] ) && ! empty( $_POST['qr_id'] ) ) {
        if ( check_admin_referer( 'vqr_reset_scan_count', 'vqr_reset_scan_count_nonce' ) ) {
            $qr_id = intval( $_POST['qr_id'] );
            $old   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $qr_id ) );

            $updated = $wpdb->update(
                $table_name,
                [ 'scan_count' => 0, 'first_scanned_at' => null ],
                [ 'id' => $qr_id ],
                [ '%d', '%s' ],
                [ '%d' ]
            );

            if ( $updated !== false ) {
                if ( $old && $old->post_id ) {
                    delete_post_meta( $old->post_id, 'times_scanned' );
                    delete_post_meta( $old->post_id, 'first_scanned_date' );
                }
                echo "<div class='updated notice is-dismissible'><p>Reset scan count for ID {$qr_id}.</p></div>";
            } else {
                echo "<div class='error notice is-dismissible'><p>Failed to reset scan count for ID {$qr_id}.</p></div>";
            }
        } else {
            echo "<div class='error notice is-dismissible'><p>Security check failed.</p></div>";
        }
    }

    // d) Bulk reset
    if ( isset( $_POST['reset_scan_counts'] ) && ! empty( $_POST['qr_ids'] ) ) {
        if ( check_admin_referer( 'vqr_bulk_action', 'vqr_bulk_action_nonce' ) ) {
            $ids        = array_map( 'intval', $_POST['qr_ids'] );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            // Clear CPT meta on each
            $rows = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id IN ({$placeholders})", ...$ids )
            );
            foreach ( $rows as $r ) {
                if ( $r->post_id ) {
                    delete_post_meta( $r->post_id, 'times_scanned' );
                    delete_post_meta( $r->post_id, 'first_scanned_date' );
                }
            }
            // Reset DB counts
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table_name}
                     SET scan_count = 0, first_scanned_at = NULL
                     WHERE id IN ({$placeholders})",
                    ...$ids
                )
            );
            echo "<div class='updated notice is-dismissible'><p>Bulk reset scan counts.</p></div>";
        } else {
            echo "<div class='error notice is-dismissible'><p>Security check failed.</p></div>";
        }
    }

    //
    // 2) READ & SANITIZE GET FILTERS
    //
    $batch_code      = sanitize_text_field( $_GET['batch_code_search'] ?? '' );
    $filter_category = sanitize_text_field( $_GET['category']          ?? '' );
    $scanned         = sanitize_text_field( $_GET['scanned_status']    ?? '' );
    $order           = in_array( $_GET['order_scan'] ?? '', [ 'asc','desc' ], true )
                       ? $_GET['order_scan']
                       : '';

    //
    // 3) BUILD WHERE + ORDER BY
    //
    $where = []; $vars = [];
    if ( $batch_code ) {
        $where[] = 'batch_code LIKE %s';
        $vars[]  = '%' . $wpdb->esc_like( $batch_code ) . '%';
    }
    if ( $filter_category ) {
        $where[] = 'category = %s';
        $vars[]  = $filter_category;
    }
    if ( $scanned === 'scanned' ) {
        $where[] = 'scan_count > 0';
    } elseif ( $scanned === 'not_scanned' ) {
        $where[] = 'scan_count = 0';
    }
    $sql_where = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
    $sql_order = '';
    if ( $order === 'asc' ) {
        $sql_order = 'ORDER BY scan_count ASC';
    } elseif ( $order === 'desc' ) {
        $sql_order = 'ORDER BY scan_count DESC';
    }

    //
    // 4) FETCH FILTERED & ORDERED RESULTS
    //
    // Build the raw SQL
    $sql = "SELECT * FROM {$table_name}
            {$sql_where}
            {$sql_order}";

    // If we have bindings, prepare; otherwise run raw
    if ( ! empty( $vars ) ) {
        $sql = $wpdb->prepare( $sql, ...$vars );
    }

    $qr_codes = $wpdb->get_results( $sql );

    //
    // 5) FETCH DISTINCT CATEGORIES FOR THE DROPDOWN
    //
    $categories = $wpdb->get_col(
        "SELECT DISTINCT category
           FROM {$table_name}
          WHERE category != ''"
    );

    //
    // 6) RENDER THE PAGE
    //
    ?>
    <div class="wrap">
      <h1>Verification QR Manager</h1>

      <!-- GENERATE FORM -->
      <form method="POST" enctype="multipart/form-data" style="margin-bottom:2em;">
        <h2>Generate QR Codes</h2>
        <label>Number of QR Codes:
          <input type="number" name="qr_count" min="1" max="1000" required>
        </label>
        <label>Base URL:
          <input type="url" name="base_url" required>
        </label>
        <label>Category:
          <input type="text" name="category" required>
        </label>
        <label>Code Prefix (4 chars):
          <input type="text" name="code_prefix" maxlength="4" required>
        </label>
        <label>Optional Logo:
          <input type="file" name="logo_file" accept="image/png,image/jpeg">
        </label>
        <label>Select Strain:
          <select name="post_id" required>
            <?php
              $posts = get_posts([
                'post_type'   => 'strain',
                'numberposts' => -1,
                'post_status' => 'publish',
              ]);
              foreach ( $posts as $p ) {
                echo '<option value="'.esc_attr($p->ID).'">'.esc_html($p->post_title).'</option>';
              }
            ?>
          </select>
        </label>
        <input type="submit" name="generate_qr_codes" class="button button-primary" value="Generate QR Codes">
      </form>

      <!-- FILTER FORM -->
      <h2>Filter QR Codes</h2>
      <form method="get" style="margin-bottom:2em;">
        <input type="hidden" name="page" value="verification_qr_manager">

        <label style="margin-right:8px;">
          Batch Code:
          <input type="text" name="batch_code_search" value="<?php echo esc_attr( $batch_code ); ?>" style="width:150px;">
        </label>

        <label style="margin-right:8px;">
          Category:
          <select name="category">
            <option value="">All Categories</option>
            <?php foreach ( $categories as $cat ): ?>
              <option value="<?php echo esc_attr($cat); ?>" <?php selected( $filter_category, $cat ); ?>>
                <?php echo esc_html($cat); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label style="margin-right:8px;">
          Scanned?
          <select name="scanned_status">
            <option value=""           <?php selected( $scanned, '' ); ?>>Either</option>
            <option value="scanned"    <?php selected( $scanned, 'scanned' ); ?>>Scanned</option>
            <option value="not_scanned"<?php selected( $scanned, 'not_scanned' ); ?>>Not scanned</option>
          </select>
        </label>

        <label style="margin-right:8px;">
          Order by
          <select name="order_scan">
            <option value=""    <?php selected( $order, '' ); ?>>Default</option>
            <option value="asc" <?php selected( $order, 'asc' ); ?>>Scan count ↑</option>
            <option value="desc"<?php selected( $order, 'desc' ); ?>>Scan count ↓</option>
          </select>
        </label>

        <button type="submit" class="button">Filter</button>
      </form>

      <!-- BULK-ACTIONS TABLE -->
      <h2>Generated QR Codes</h2>
      <form method="POST">
        <?php wp_nonce_field( 'vqr_bulk_action', 'vqr_bulk_action_nonce' ); ?>
        <table class="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th><input type="checkbox" id="select-all"></th>
              <th>ID</th>
              <th>QR Code</th>
              <th>URL</th>
              <th>Batch Code</th>
              <th>Category</th>
              <th>Scan Count</th>
              <th>First Scanned At</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( $qr_codes ): ?>
              <?php foreach ( $qr_codes as $code ): ?>
                <tr>
                  <td><input type="checkbox" name="qr_ids[]" value="<?php echo esc_attr( $code->id ); ?>"></td>
                  <td><?php echo esc_html( $code->id ); ?></td>
                  <td><img src="<?php echo esc_url( $code->qr_code ); ?>" width="50" alt=""></td>
                  <td><a href="<?php echo esc_url( $code->url ); ?>" target="_blank"><?php echo esc_html( $code->url ); ?></a></td>
                  <td><?php echo esc_html( $code->batch_code ); ?></td>
                  <td><?php echo esc_html( $code->category ); ?></td>
                  <td><?php echo esc_html( $code->scan_count ); ?></td>
                  <td><?php echo esc_html( $code->first_scanned_at ?: 'Never Scanned' ); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8">No QR codes found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <input type="submit"
               name="delete_qr_codes"
               class="button button-secondary"
               value="Delete Selected"
               onclick="return confirm('Delete selected QR codes?');">

        <input type="submit"
               name="download_qr_codes"
               class="button button-primary"
               value="Download Selected">

        <input type="submit"
               name="reset_scan_counts"
               class="button button-secondary"
               value="Reset Selected"
               onclick="return confirm('Reset scan counts for selected codes?');">
      </form>

    </div>

    <script>
      document.getElementById('select-all').addEventListener('click', function(e) {
        document.querySelectorAll("input[name='qr_ids[]']").forEach(cb => cb.checked = e.target.checked);
      });
    </script>
    <?php
}

/**
 * Add meta boxes for custom post types
 */
function vqr_add_meta_boxes() {
    add_meta_box(
        'vqr_scan_data',
        'QR Code Scan Data',
        'vqr_display_scan_data',
        'strain',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'vqr_add_meta_boxes');
