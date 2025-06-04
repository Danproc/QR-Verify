<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($error_data['title']); ?> - Verification Error</title>
    <?php wp_head(); ?>
</head>
<body class="vqr-strain-page vqr-layout-responsive">
    <div class="vqr-container vqr-main-container">
        
        <!-- Error Header -->
        <header class="vqr-header vqr-strain-header">
            <div class="vqr-title-section vqr-strain-info">
                <h1 class="vqr-strain-title vqr-product-name"><?php echo esc_html($error_data['title']); ?></h1>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="vqr-main-content vqr-product-details">
            
            <!-- Error Status Badge -->
            <div class="vqr-verification-badge vqr-authenticity-badge vqr-fake vqr-warning-badge" style="margin-bottom: 16px;">
                <span class="vqr-badge-icon vqr-authenticity-icon">⚠</span>
                <span class="vqr-badge-text vqr-authenticity-text">Could Not Verify</span>
            </div>
            
            <!-- Error Message -->
            <section class="vqr-description vqr-product-description" style="margin-bottom: 16px;">
                <div class="vqr-description-content vqr-description-text">
                    <p><?php echo esc_html($error_data['message']); ?></p>
                    <p><strong>To verify this product:</strong></p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Make sure you scanned the QR code properly</li>
                        <li>Check that the QR code is from an authentic product</li>
                        <li>Contact the manufacturer if you believe this is an error</li>
                    </ul>
                </div>
            </section>
            
        </main>
        
        <!-- Footer -->
        <footer class="vqr-footer vqr-system-footer">
            <p class="vqr-footer-text vqr-footer-credit">Verified by <a href="https://verify420.com" target="_blank" style="color: #008009; text-decoration: none;">Verify420</a></p>
        </footer>
        
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>