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

    // Init TCPDF
    $pdf = new \Com\Tecnick\Pdf\Tcpdf('P','mm',[$pageW,$pageH],true,'UTF-8',false);
    $pdf->SetMargins($mLeft,$mTop,$mRight);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();

    // CutContour layer
    if ( method_exists($pdf,'StartLayer') ) {
        $pdf->StartLayer('CutContour');
    }
    $pdf->SetLineStyle([ 'width'=>0.25, 'color'=>[0,0,0] ]);

    foreach ( $rows as $i => $code ) {
        $col = $i % $perRow;
        $row = intdiv( $i, $perRow );
        $x   = $mLeft + $col * ( $stW + $gX );
        $y   = $mTop  + $row * ( $stH + $gY );

        $img = str_replace( home_url('/'), ABSPATH, $code->qr_code );
        if ( file_exists($img) ) {
            $pdf->Image( $img, $x, $y, $stW, $stH, '', '', '', false, 300 );
        }
        $pdf->Rect( $x, $y, $stW, $stH, 'D' );
    }

    // End layer & output
    if ( method_exists($pdf,'EndLayer') ) {
        $pdf->EndLayer();
    }
    $pdf->Output( 'qr_stickers.pdf', 'D' );
    exit;
}
add_action( 'admin_post_download_qr_pdf', 'vqr_download_pdf_sheet' );
