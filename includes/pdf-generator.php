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
                ?>
                    <div class="cut-contour" style="left: <?php echo $x; ?>mm; top: <?php echo $y; ?>mm; width: <?php echo $stW; ?>mm; height: <?php echo $stH; ?>mm;"></div>
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
