<?php
/**
 * PDF generation functionality
 */

defined('ABSPATH') || exit;

/**
 * Admin-post handler to build and download the 700 mm PDF sheet.
 */
function vqr_download_pdf_sheet() {
    // Make sure QR IDs are coming from a valid POST
    if ( empty( $_POST['qr_ids'] ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( 'No QR codes selected or insufficient permissions.' );
    }
    check_admin_referer( 'vqr_bulk_action', 'vqr_bulk_action_nonce' );

    global $wpdb;
    $table = $wpdb->prefix . 'vqr_codes';
    $ids   = array_map( 'intval', $_POST['qr_ids'] );
    $in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
    $rows  = $wpdb->get_results(
        $wpdb->prepare( "SELECT * FROM $table WHERE id IN ($in)", ...$ids )
    );
    if ( ! $rows ) {
        wp_die( 'No records found.' );
    }

    // Layout in mm
    $pageW    = 700;
    $mLeft    = 10; $mRight = 10;
    $mTop     = 10; $mBot   = 10;
    $stW      = 60; $stH    = 60;
    $gX       = 5;  $gY     = 5;

    $usableW = $pageW - $mLeft - $mRight;
    $perRow  = max(1, floor( ($usableW + $gX) / ($stW + $gX) ));
    $rowsN   = ceil( count($rows) / $perRow );
    $pageH   = $mTop + $rowsN*$stH + ($rowsN-1)*$gY + $mBot;

    // Using HTML-to-PDF approach for better compatibility
    
    // Adjust sticker size to be tighter around QR codes
    $actualQRSize = 50; // Actual QR code size in mm (height)
    $borderWidth = 2;   // 2mm border around QR code
    $stW = 35;          // Total sticker width (as requested)
    $stH = $actualQRSize + (2 * $borderWidth); // Total sticker height
    $gX = 2;            // Minimal gap between stickers to save paper space
    $gY = 2;            // Minimal gap between stickers to save paper space
    
    // Recalculate layout with new dimensions
    $usableW = $pageW - $mLeft - $mRight;
    $perRow  = max(1, floor( ($usableW + $gX) / ($stW + $gX) ));
    $rowsN   = ceil( count($rows) / $perRow );
    $pageH   = $mTop + $rowsN*$stH + ($rowsN-1)*$gY + $mBot;

    // Get category and timestamp info
    $category = !empty($rows) ? $rows[0]->category : 'Unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    // Generate HTML layout for print-to-PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            @page {
                size: <?php echo $pageW; ?>mm <?php echo $pageH; ?>mm;
                margin: 0;
            }
            @media print {
                * {
                    color-adjust: exact !important;
                    -webkit-print-color-adjust: exact !important;
                }
            }
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                background: white;
            }
            .container {
                position: relative;
                width: <?php echo $pageW; ?>mm;
                height: <?php echo $pageH; ?>mm;
                background: white;
            }
            .images-layer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 1;
            }
            .cutlines-layer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 2;
                pointer-events: none;
            }
            .qr-item {
                position: absolute;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .qr-image {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
            }
            .cut-contour {
                position: absolute;
                border: 0.25pt solid red;
                border-radius: 2mm;
                box-sizing: border-box;
                background: transparent;
                /* CutContour layer - rename to spot color in Illustrator */
            }
            .print-info {
                position: absolute;
                font-size: 12px;
                color: #333;
                top: 2mm;
                left: 2mm;
            }
        </style>
        <script>
            window.onload = function() {
                // Auto-trigger print dialog for PDF generation
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        </script>
    </head>
    <body>
        <div class="container">
            <!-- Print information -->
            <div class="print-info">Category: <?php echo esc_html($category); ?> | Printed: <?php echo esc_html($timestamp); ?></div>
            
            <!-- Images Layer (z-index: 1) -->
            <div class="images-layer">
                <?php foreach ( $rows as $i => $code ): 
                    $col = $i % $perRow;
                    $row = intdiv( $i, $perRow );
                    $x   = $mLeft + $col * ( $stW + $gX );
                    $y   = $mTop  + $row * ( $stH + $gY );
                    
                    $img_path = str_replace( home_url('/'), ABSPATH, $code->qr_code );
                    if ( file_exists($img_path) ):
                ?>
                    <div class="qr-item" style="left: <?php echo $x; ?>mm; top: <?php echo $y; ?>mm; width: <?php echo $stW; ?>mm; height: <?php echo $stH; ?>mm;">
                        <img src="<?php echo esc_url($code->qr_code); ?>" class="qr-image" alt="QR Code <?php echo esc_attr($code->batch_code); ?>">
                    </div>
                <?php endif; endforeach; ?>
            </div>
            
            <!-- CutContour Layer (z-index: 2) -->
            <div class="cutlines-layer">
                <?php foreach ( $rows as $i => $code ): 
                    $col = $i % $perRow;
                    $row = intdiv( $i, $perRow );
                    $x   = $mLeft + $col * ( $stW + $gX );
                    $y   = $mTop  + $row * ( $stH + $gY );
                    
                    $img_path = str_replace( home_url('/'), ABSPATH, $code->qr_code );
                    if ( file_exists($img_path) ):
                        // Get actual image dimensions to calculate proper cut line size
                        $img_info = getimagesize($img_path);
                        if ($img_info) {
                            $img_width_px = $img_info[0];
                            $img_height_px = $img_info[1];
                            
                            // Convert pixels to mm (assuming 96 DPI)
                            $px_to_mm = 25.4 / 96;
                            $img_width_mm = $img_width_px * $px_to_mm;
                            $img_height_mm = $img_height_px * $px_to_mm;
                            
                            // Ensure width never exceeds 35mm
                            $cut_width = min($img_width_mm, 35);
                            $cut_height = $img_height_mm * ($cut_width / $img_width_mm); // Maintain aspect ratio
                            
                            // Center the cut line within the sticker area
                            $cut_x = $x + ($stW - $cut_width) / 2;
                            $cut_y = $y + ($stH - $cut_height) / 2;
                        } else {
                            // Fallback to original dimensions if image info unavailable
                            $cut_width = $stW;
                            $cut_height = $stH;
                            $cut_x = $x;
                            $cut_y = $y;
                        }
                ?>
                    <div class="cut-contour" style="left: <?php echo $cut_x; ?>mm; top: <?php echo $cut_y; ?>mm; width: <?php echo $cut_width; ?>mm; height: <?php echo $cut_height; ?>mm;"></div>
                <?php endif; endforeach; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Output HTML that auto-triggers print dialog
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}
add_action( 'admin_post_download_qr_pdf', 'vqr_download_pdf_sheet' );

/**
 * Generate PDF sticker sheet for order download
 */
function vqr_generate_sticker_sheet_pdf($qr_codes, $with_cutlines = true) {
    if (empty($qr_codes)) {
        return false;
    }
    
    // Layout configuration - optimized for sticker printing
    $pageW = 210; // A4 width in mm
    $pageH = 297; // A4 height in mm
    $mLeft = 10; $mRight = 10;
    $mTop = 10; $mBot = 10;
    $stW = 35; // Sticker width in mm
    $stH = 35; // Sticker height in mm
    $gX = 5; $gY = 5; // Gaps between stickers
    
    $usableW = $pageW - $mLeft - $mRight;
    $usableH = $pageH - $mTop - $mBot;
    $perRow = max(1, floor(($usableW + $gX) / ($stW + $gX)));
    $perCol = max(1, floor(($usableH + $gY) / ($stH + $gY)));
    $perPage = $perRow * $perCol;
    
    // Start building HTML for PDF
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            size: A4;
            margin: ' . $mTop . 'mm ' . $mRight . 'mm ' . $mBot . 'mm ' . $mLeft . 'mm;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            font-size: 8px;
        }
        .page {
            width: 100%;
            height: 100%;
            position: relative;
        }
        .sticker-grid {
            display: grid;
            grid-template-columns: repeat(' . $perRow . ', ' . $stW . 'mm);
            grid-template-rows: repeat(' . $perCol . ', ' . $stH . 'mm);
            gap: ' . $gY . 'mm ' . $gX . 'mm;
            width: 100%;
            height: 100%;
        }
        .sticker {
            width: ' . $stW . 'mm;
            height: ' . $stH . 'mm;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: ' . ($with_cutlines ? '0.5px dashed #ccc;' : 'none;') . '
            box-sizing: border-box;
            padding: 2mm;
        }
        .qr-image {
            max-width: ' . ($stW - 4) . 'mm;
            max-height: ' . ($stH - 8) . 'mm;
            width: auto;
            height: auto;
        }
        .batch-code {
            font-size: 6px;
            margin-top: 1mm;
            text-align: center;
            font-weight: bold;
            color: #333;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>';
    
    $current_page = 0;
    $current_position = 0;
    
    foreach ($qr_codes as $index => $qr_code) {
        // Start new page if needed
        if ($current_position === 0) {
            if ($current_page > 0) {
                $html .= '<div class="page-break"></div>';
            }
            $html .= '<div class="page"><div class="sticker-grid">';
            $current_page++;
        }
        
        // Get QR code image data
        $qr_image_data = '';
        if (!empty($qr_code->qr_code)) {
            if (strpos($qr_code->qr_code, home_url()) === 0) {
                // Local file
                $qr_image_path = str_replace(home_url(), ABSPATH, $qr_code->qr_code);
                $qr_image_path = str_replace('//', '/', $qr_image_path);
                
                if (file_exists($qr_image_path)) {
                    $image_data = file_get_contents($qr_image_path);
                    if ($image_data !== false) {
                        $image_type = pathinfo($qr_image_path, PATHINFO_EXTENSION);
                        $qr_image_data = 'data:image/' . $image_type . ';base64,' . base64_encode($image_data);
                    }
                }
            } else {
                // Remote URL - use directly
                $qr_image_data = $qr_code->qr_code;
            }
        }
        
        // Add sticker to HTML
        $html .= '<div class="sticker">';
        if ($qr_image_data) {
            $html .= '<img src="' . $qr_image_data . '" alt="QR Code" class="qr-image">';
        }
        $html .= '<div class="batch-code">' . htmlspecialchars($qr_code->batch_code) . '</div>';
        $html .= '</div>';
        
        $current_position++;
        
        // Check if page is full
        if ($current_position >= $perPage) {
            $html .= '</div></div>'; // Close sticker-grid and page
            $current_position = 0;
        }
    }
    
    // Close any remaining open page
    if ($current_position > 0) {
        // Fill remaining spots with empty stickers if needed
        while ($current_position < $perPage) {
            $html .= '<div class="sticker"></div>';
            $current_position++;
        }
        $html .= '</div></div>'; // Close sticker-grid and page
    }
    
    $html .= '</body></html>';
    
    // For now, return HTML that can be used for printing
    // In a real implementation, you'd use a library like TCPDF or Dompdf
    return $html;
}
