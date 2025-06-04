<?php
/**
 * QR Code generation functionality
 */

defined('ABSPATH') || exit;

/**
 * Generate QR Codes with batch codes and optional logos
 */
function vqr_generate_codes( $count, $base_url, $category, $post_id, $prefix = '', $logo_path = '' ) {
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

        // Handle logo if provided
        $logo_img = null;
        $logo_h   = $logo_w = 0;
        if ( $logo_path && file_exists( $logo_path ) ) {
            $info = getimagesize( $logo_path );
            $orig = false;
            
            if ( $info && isset( $info['mime'] ) ) {
                switch ( $info['mime'] ) {
                    case 'image/png':
                        $orig = imagecreatefrompng( $logo_path );
                        break;
                    case 'image/jpeg':
                    case 'image/jpg':
                        $orig = imagecreatefromjpeg( $logo_path );
                        break;
                    case 'image/webp':
                        if ( function_exists( 'imagecreatefromwebp' ) ) {
                            $orig = imagecreatefromwebp( $logo_path );
                        }
                        break;
                }
            }
            
            if ( !$orig ) {
                error_log( "Failed to create image from logo: {$logo_path} (mime: " . ($info['mime'] ?? 'unknown') . ")" );
                // Proceed without logo
                $logo_path = '';
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
        $wpdb->insert(
            $table_name,
            [
                'qr_key'     => $unique_id,
                'qr_code'    => $upload_dir['baseurl'] . '/vqr_codes/qr_code_' . $unique_id . '.png',
                'url'        => $qr_url,
                'batch_code' => $batchCode,
                'category'   => $category,
                'scan_count' => 0,
                'post_id'    => $post_id,
            ]
        );

        // Mirror the batch code into the Strain post's meta
        if ( ! empty( $post_id ) ) {
            update_post_meta( $post_id, 'batch_code', $batchCode );
        }

        if ( $wpdb->last_error ) {
            error_log( "Database insert error: " . $wpdb->last_error );
        }
    }
}
