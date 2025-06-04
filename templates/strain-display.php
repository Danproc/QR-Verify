<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($strain_data['title']); ?> - Strain Information</title>
    <?php wp_head(); ?>
</head>
<body class="vqr-strain-page vqr-layout-responsive">
    <div class="vqr-container vqr-main-container">
        
        <!-- Logo Section -->
        <?php if (isset($strain_data['logo'])): ?>
            <div class="vqr-logo-section">
                <img src="<?php echo esc_url($strain_data['logo']['url']); ?>" 
                     alt="<?php echo esc_attr($strain_data['title']); ?> Logo" 
                     class="vqr-centered-logo">
            </div>
        <?php endif; ?>
        
        <!-- Product Image -->
        <?php if (isset($strain_data['image'])): ?>
            <div class="vqr-product-image vqr-hero-image">
                <img src="<?php echo esc_url($strain_data['image']['url']); ?>" 
                     alt="<?php echo esc_attr($strain_data['title']); ?>" 
                     class="vqr-product-img vqr-main-image">
            </div>
        <?php endif; ?>
        
        <!-- Strain Name - Large -->
        <header class="vqr-header vqr-strain-header">
            <div class="vqr-title-section vqr-strain-info">
                <h1 class="vqr-strain-title vqr-product-name"><?php echo esc_html($strain_data['title']); ?></h1>
                
                <?php if (isset($strain_data['companies']) && !empty($strain_data['companies'])): ?>
                    <div class="vqr-companies vqr-brand-tags">
                        <?php foreach ($strain_data['companies'] as $company): ?>
                            <span class="vqr-company-tag vqr-brand-badge"><?php echo esc_html($company->name); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="vqr-main-content vqr-product-details">
            
            <!-- Verification Status Badge -->
            <?php 
            $scan_count = isset($strain_data['scan_count']) ? $strain_data['scan_count'] : 0;
            $is_authentic = $scan_count <= 1;
            ?>
            <div class="vqr-verification-badge vqr-authenticity-badge <?php echo $is_authentic ? 'vqr-authentic vqr-verified-badge' : 'vqr-fake vqr-warning-badge'; ?>" style="margin-bottom: 16px;">
                <span class="vqr-badge-icon vqr-authenticity-icon"><?php echo $is_authentic ? '✓' : '⚠'; ?></span>
                <span class="vqr-badge-text vqr-authenticity-text"><?php echo $is_authentic ? 'Verified Authentic' : 'WARNING: Potential Fake'; ?></span>
            </div>
            
            <!-- Times Verified / First Verified -->
            <section class="vqr-scan-info vqr-verification-section" style="margin-bottom: 16px;">
                <div class="vqr-scan-stats vqr-verification-stats">
                    <?php if (isset($strain_data['scan_count'])): ?>
                        <div class="vqr-stat-item vqr-scan-count-item">
                            <span class="vqr-stat-label vqr-scan-count-label">Times Verified:</span>
                            <span class="vqr-stat-value vqr-scan-count-value"><?php echo esc_html($strain_data['scan_count']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($strain_data['first_scanned_at'])): ?>
                        <div class="vqr-stat-item vqr-first-scan-item">
                            <span class="vqr-stat-label vqr-first-scan-label">First Verified:</span>
                            <span class="vqr-stat-value vqr-first-scan-value"><?php echo esc_html(date('M j, Y g:i A', strtotime($strain_data['first_scanned_at']))); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
                    
            <!-- Genetics -->
            <?php if (isset($strain_data['meta']['strain_genetics'])): ?>
                <section class="vqr-basic-info vqr-product-info" style="margin-bottom: 16px;">
                    <div class="vqr-info-item vqr-genetics-item">
                        <span class="vqr-label vqr-field-label">Genetics:</span>
                        <span class="vqr-value vqr-field-value vqr-genetics-value"><?php echo esc_html($strain_data['meta']['strain_genetics']['value']); ?></span>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Batch Information -->
            <?php if (isset($strain_data['meta']['batch_id']) || isset($strain_data['meta']['batch_code'])): ?>
                <section class="vqr-basic-info vqr-product-info" style="margin-bottom: 16px;">
                    <?php if (isset($strain_data['meta']['batch_id'])): ?>
                        <div class="vqr-info-item vqr-batch-item">
                            <span class="vqr-label vqr-field-label">Batch ID:</span>
                            <span class="vqr-value vqr-field-value vqr-batch-id"><?php echo esc_html($strain_data['meta']['batch_id']['value']); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($strain_data['meta']['batch_code'])): ?>
                        <div class="vqr-info-item vqr-code-item">
                            <span class="vqr-label vqr-field-label">Batch Code:</span>
                            <span class="vqr-value vqr-field-value vqr-batch-code"><?php echo esc_html($strain_data['meta']['batch_code']['value']); ?></span>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            
            <!-- Cannabinoid Information -->
            <?php 
            $has_cannabinoids = isset($strain_data['meta']['thc_mg']) || 
                               isset($strain_data['meta']['thc_percentage']) || 
                               isset($strain_data['meta']['cbd_mg']) || 
                               isset($strain_data['meta']['cbd_percentage']);
            ?>
            <?php if ($has_cannabinoids): ?>
                <section class="vqr-cannabinoids vqr-lab-results" style="margin-bottom: 16px;">
                    <h2 class="vqr-section-title vqr-cannabinoid-title">Cannabinoid Profile</h2>
                    <div class="vqr-cannabinoid-grid vqr-lab-grid">
                        <?php if (isset($strain_data['meta']['thc_percentage'])): ?>
                            <div class="vqr-cannabinoid-item vqr-thc vqr-thc-card">
                                <div class="vqr-cannabinoid-label vqr-compound-label vqr-thc-label">THC</div>
                                <div class="vqr-cannabinoid-value vqr-compound-value vqr-thc-percentage"><?php echo esc_html($strain_data['meta']['thc_percentage']['value']); ?>%</div>
                                <?php if (isset($strain_data['meta']['thc_mg'])): ?>
                                    <div class="vqr-cannabinoid-mg vqr-compound-mg vqr-thc-mg"><?php echo esc_html($strain_data['meta']['thc_mg']['value']); ?>mg</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($strain_data['meta']['cbd_percentage'])): ?>
                            <div class="vqr-cannabinoid-item vqr-cbd vqr-cbd-card">
                                <div class="vqr-cannabinoid-label vqr-compound-label vqr-cbd-label">CBD</div>
                                <div class="vqr-cannabinoid-value vqr-compound-value vqr-cbd-percentage"><?php echo esc_html($strain_data['meta']['cbd_percentage']['value']); ?>%</div>
                                <?php if (isset($strain_data['meta']['cbd_mg'])): ?>
                                    <div class="vqr-cannabinoid-mg vqr-compound-mg vqr-cbd-mg"><?php echo esc_html($strain_data['meta']['cbd_mg']['value']); ?>mg</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Product Description -->
            <?php if (isset($strain_data['meta']['product_description']) && !empty($strain_data['meta']['product_description']['value'])): ?>
                <section class="vqr-description vqr-product-description" style="margin-bottom: 16px;">
                    <h2 class="vqr-section-title vqr-description-title">Description</h2>
                    <div class="vqr-description-content vqr-description-text">
                        <?php echo wp_kses_post($strain_data['meta']['product_description']['value']); ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <!-- Social Media Links -->
            <?php 
            $has_social = isset($strain_data['meta']['instagram_url']) || 
                         isset($strain_data['meta']['telegram_url']) || 
                         isset($strain_data['meta']['facebook_url']) || 
                         isset($strain_data['meta']['twitter_url']);
            ?>
            <?php if ($has_social): ?>
                <section class="vqr-social-links vqr-social-section" style="margin-bottom: 16px;">
                    <h2 class="vqr-section-title vqr-social-title">Follow Us</h2>
                    <div class="vqr-social-grid vqr-social-buttons">
                        <?php if (isset($strain_data['meta']['instagram_url'])): ?>
                            <a href="<?php echo esc_url($strain_data['meta']['instagram_url']['value']); ?>" 
                               target="_blank" class="vqr-social-link vqr-instagram vqr-instagram-btn">
                                <svg class="vqr-social-icon vqr-instagram-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isset($strain_data['meta']['telegram_url'])): ?>
                            <a href="<?php echo esc_url($strain_data['meta']['telegram_url']['value']); ?>" 
                               target="_blank" class="vqr-social-link vqr-telegram vqr-telegram-btn">
                                <svg class="vqr-social-icon vqr-telegram-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isset($strain_data['meta']['facebook_url'])): ?>
                            <a href="<?php echo esc_url($strain_data['meta']['facebook_url']['value']); ?>" 
                               target="_blank" class="vqr-social-link vqr-facebook vqr-facebook-btn">
                                <svg class="vqr-social-icon vqr-facebook-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        
                        <?php if (isset($strain_data['meta']['twitter_url'])): ?>
                            <a href="<?php echo esc_url($strain_data['meta']['twitter_url']['value']); ?>" 
                               target="_blank" class="vqr-social-link vqr-twitter vqr-twitter-btn">
                                <svg class="vqr-social-icon vqr-twitter-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            
        </main>
        
        <!-- Footer -->
        <footer class="vqr-footer vqr-system-footer">
            <p class="vqr-footer-text vqr-footer-credit">Verified by <a href="https://verify420.com" target="_blank" style="color: #008009; text-decoration: none;">Verify420</a></p>
        </footer>
        
    </div>
    
    <?php wp_footer(); ?>
</body>
</html>