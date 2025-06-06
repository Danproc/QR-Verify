<?php
/**
 * QR Code generation functionality
 */

defined('ABSPATH') || exit;

/**
 * Create a default Verify 420 logo for free plan users
 * Returns the path to the generated logo image
 */
function vqr_create_default_logo() {
    $upload_dir = wp_upload_dir();
    $logo_dir = $upload_dir['basedir'] . '/vqr_logos';
    $logo_path = $logo_dir . '/verify420-default-logo.png';
    
    // First, check if admin has uploaded a global logo
    $global_logo_id = get_option('vqr_global_logo_id');
    if ($global_logo_id) {
        $global_logo_path = get_attached_file($global_logo_id);
        if ($global_logo_path && file_exists($global_logo_path)) {
            // Copy the admin logo to our cache location with consistent naming
            if (!file_exists($logo_dir)) {
                if (!mkdir($logo_dir, 0755, true)) {
                    error_log("Failed to create logo directory: $logo_dir");
                    return '';
                }
            }
            
            // Copy the global logo to our standard location
            if (copy($global_logo_path, $logo_path)) {
                return $logo_path;
            } else {
                error_log("Failed to copy global logo from $global_logo_path to $logo_path");
                // Continue to fallback text logo generation
            }
        }
    }
    
    // Check if text-based logo already exists
    if (file_exists($logo_path)) {
        return $logo_path;
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($logo_dir)) {
        if (!mkdir($logo_dir, 0755, true)) {
            error_log("Failed to create logo directory: $logo_dir");
            return '';
        }
    }
    
    // Create a simple text-based logo as fallback when no admin logo is available
    $width = 300;
    $height = 80;
    $logo = imagecreatetruecolor($width, $height);
    
    // Set background to transparent
    imagealphablending($logo, false);
    imagesavealpha($logo, true);
    $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
    imagefill($logo, 0, 0, $transparent);
    
    // Set colors - using Verify 420 green theme
    $green = imagecolorallocate($logo, 16, 112, 70); // Primary green
    $white = imagecolorallocate($logo, 255, 255, 255);
    
    // Try to use Montserrat Bold font
    $font_path = VQR_PLUGIN_DIR . 'assets/fonts/Montserrat-Bold.ttf';
    
    if (file_exists($font_path)) {
        // Use custom font
        $font_size = 18;
        
        // Calculate text positioning for center alignment
        $text = 'VERIFY 420';
        $bbox = imagettfbbox($font_size, 0, $font_path, $text);
        $text_width = abs($bbox[2] - $bbox[0]);
        $text_height = abs($bbox[1] - $bbox[7]);
        
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2 + $text_height;
        
        // Add white outline for better visibility
        for ($ox = -1; $ox <= 1; $ox++) {
            for ($oy = -1; $oy <= 1; $oy++) {
                if ($ox != 0 || $oy != 0) {
                    imagettftext($logo, $font_size, 0, $x + $ox, $y + $oy, $white, $font_path, $text);
                }
            }
        }
        
        // Add main text
        imagettftext($logo, $font_size, 0, $x, $y, $green, $font_path, $text);
    } else {
        // Fallback to built-in font
        $font_size = 5; // Built-in font size
        $text = 'VERIFY 420';
        
        $text_width = strlen($text) * imagefontwidth($font_size);
        $text_height = imagefontheight($font_size);
        
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2;
        
        // Add white outline
        imagestring($logo, $font_size, $x - 1, $y - 1, $text, $white);
        imagestring($logo, $font_size, $x + 1, $y - 1, $text, $white);
        imagestring($logo, $font_size, $x - 1, $y + 1, $text, $white);
        imagestring($logo, $font_size, $x + 1, $y + 1, $text, $white);
        
        // Add main text
        imagestring($logo, $font_size, $x, $y, $text, $green);
    }
    
    // Save the logo
    if (imagepng($logo, $logo_path)) {
        imagedestroy($logo);
        return $logo_path;
    } else {
        imagedestroy($logo);
        error_log("Failed to save default logo to: $logo_path");
        return '';
    }
}

/**
 * Generate QR Codes with batch codes and optional logos
 */
function vqr_generate_codes( $count, $base_url, $category, $post_id, $prefix = '', $logo_path = '', $user_id = null ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vqr_codes';

    // Include the QR library
    if ( ! class_exists( 'QRcode' ) ) {
        $qr_lib_path = VQR_PLUGIN_DIR . 'phpqrcode/qrlib.php';
        if ( file_exists( $qr_lib_path ) ) {
            require_once $qr_lib_path;
        } else {
            error_log( "QR library not found at: $qr_lib_path" );
            return;
        }
    }

    $upload_dir = wp_upload_dir();
    $qr_dir     = $upload_dir['basedir'] . '/vqr_codes';
    if ( ! file_exists( $qr_dir ) ) {
        if ( ! mkdir( $qr_dir, 0755, true ) ) {
            error_log( "Failed to create directory: $qr_dir" );
            return;
        }
    }

    // Sanitize prefix
    $prefix = strtoupper( sanitize_text_field( $prefix ) );

    for ( $i = 1; $i <= $count; $i++ ) {
        $unique_id = uniqid( '', true );

        // Build the 8-char batch code, force uppercase
        $random4   = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 4);
        $batchCode = strtoupper(substr($prefix . $random4, 0, 8));

        // Generate the QR code image
        $qr_url   = esc_url_raw( add_query_arg( 'qr_id', $unique_id, $base_url ) );
        $file_path = $qr_dir . '/qr_code_' . $unique_id . '.png';
        QRcode::png( $qr_url, $file_path, QR_ECLEVEL_L, 6 );

        if ( ! file_exists( $file_path ) ) {
            error_log( "QR code file was not created at: $file_path" );
            continue;
        }

        // Burn the batchCode into the bottom of the image using Montserrat Bold
        $fontFile = VQR_PLUGIN_DIR . 'assets/fonts/Montserrat-Bold.ttf';
        $fontSize = 24;
        $img      = imagecreatefrompng( $file_path );
        $w        = imagesx( $img );
        $h        = imagesy( $img );

        // Handle logo based on user plan and uploads
        $logo_img = null;
        $logo_h   = $logo_w = 0;
        
        // Determine final logo path based on plan
        $final_logo_path = '';
        if ($user_id) {
            $user_plan = vqr_get_user_plan($user_id);
            if ($user_plan === 'free') {
                // Free plan: No logo on QR codes (logo will be on strain page instead)
                $final_logo_path = '';
                error_log("VQR: Free plan user $user_id - no logo on QR code (will be on strain page)");
            } else {
                // Paid plans: Only use logo if user uploaded one
                $final_logo_path = ($logo_path && file_exists($logo_path)) ? $logo_path : '';
                error_log("VQR: Paid plan user $user_id ($user_plan) - logo path: " . ($final_logo_path ? $final_logo_path : 'none'));
            }
        } else {
            // Fallback for admin or cases without user_id
            $final_logo_path = ($logo_path && file_exists($logo_path)) ? $logo_path : '';
            error_log("VQR: No user_id provided - logo path: " . ($final_logo_path ? $final_logo_path : 'none'));
        }
        
        if ( $final_logo_path && file_exists( $final_logo_path ) ) {
            $info = getimagesize( $final_logo_path );
            $orig = false;
            
            if ( $info && isset( $info['mime'] ) ) {
                switch ( $info['mime'] ) {
                    case 'image/png':
                        $orig = imagecreatefrompng( $final_logo_path );
                        break;
                    case 'image/jpeg':
                    case 'image/jpg':
                        $orig = imagecreatefromjpeg( $final_logo_path );
                        break;
                    case 'image/webp':
                        if ( function_exists( 'imagecreatefromwebp' ) ) {
                            $orig = imagecreatefromwebp( $final_logo_path );
                        }
                        break;
                }
            }
            
            if ( !$orig ) {
                error_log( "Failed to create image from logo: {$final_logo_path} (mime: " . ($info['mime'] ?? 'unknown') . ")" );
                // Proceed without logo
                $final_logo_path = '';
            } else {
                $ow = imagesx( $orig );
                $oh = imagesy( $orig );

                // Scale logo to fit inside the QR's dark area (exclude the white border)
                $moduleSize    = 6;   // Must match the 4th param of QRcode::png()
                $marginModules = 2;   // phpqrcode default margin
                $innerWidth    = $w - (2 * $marginModules * $moduleSize);

                // Clamp logo width to inner dark-area width
                $logo_w = min( $innerWidth, $w );
                $logo_h = intval( ( $oh / $ow ) * $logo_w );

                $logo_img = imagecreatetruecolor( $logo_w, $logo_h );
                imagealphablending( $logo_img, false );
                imagesavealpha( $logo_img, true );
                imagecopyresampled(
                    $logo_img,
                    $orig,
                    0, 0, 0, 0,
                    $logo_w, $logo_h,
                    $ow, $oh
                );
                imagedestroy( $orig );
            }
        }

        // Build final canvas with border, logo, QR and text
        // Recompute text width for true centering
        $bbox  = imagettfbbox( $fontSize, 0, $fontFile, $batchCode );
        $textW = abs( $bbox[2] - $bbox[0] );

        // Set border & bottom padding
        $pad    = 20;  // White border all around
        $bottom = 10;  // Extra space under text

        // Compute canvas dimensions
        $canvasW = $w + 2 * $pad;
        $canvasH = $pad     // Top border
                + $logo_h  // Logo height (0 if none)
                + $h       // QR height
                + $fontSize
                + $bottom  // Bottom padding under text
                + $pad;    // Bottom border

        // Create and fill canvas
        $canvas = imagecreatetruecolor( $canvasW, $canvasH );
        $white  = imagecolorallocate( $canvas, 255, 255, 255 );
        imagefilledrectangle( $canvas, 0, 0, $canvasW, $canvasH, $white );

        // Draw logo (if uploaded) at top-center
        if ( $logo_img ) {
            $logoX = $pad + ( $w - $logo_w ) / 2;
            $logoY = $pad;
            imagecopy( $canvas, $logo_img, $logoX, $logoY, 0, 0, $logo_w, $logo_h );
            imagedestroy( $logo_img );
        }

        // Draw QR code directly below the logo
        $qrX = $pad;
        $qrY = $pad + $logo_h;
        imagecopy( $canvas, $img, $qrX, $qrY, 0, 0, $w, $h );

        // Draw batch code text under the QR
        $textX = $pad + ( $w - $textW ) / 2;
        $textY = $qrY + $h + $fontSize; // Baseline
        $black = imagecolorallocate( $canvas, 0, 0, 0 );
        imagettftext( $canvas, $fontSize, 0, $textX, $textY, $black, $fontFile, $batchCode );

        // Save and clean up
        imagepng( $canvas, $file_path );
        imagedestroy( $img );
        imagedestroy( $canvas );

        // Insert into the database
        $insert_data = [
            'qr_key'     => $unique_id,
            'qr_code'    => $upload_dir['baseurl'] . '/vqr_codes/qr_code_' . $unique_id . '.png',
            'url'        => $qr_url,
            'batch_code' => $batchCode,
            'category'   => $category,
            'scan_count' => 0,
            'post_id'    => $post_id,
        ];
        
        // Add user_id if provided (for frontend generation)
        if ($user_id) {
            $insert_data['user_id'] = $user_id;
        }
        
        $wpdb->insert($table_name, $insert_data);

        // Mirror the batch code into the Strain post's meta
        if ( ! empty( $post_id ) ) {
            update_post_meta( $post_id, 'batch_code', $batchCode );
        }

        if ( $wpdb->last_error ) {
            error_log( "Database insert error: " . $wpdb->last_error );
        }
    }
}
