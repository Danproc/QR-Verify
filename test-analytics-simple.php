<?php
/**
 * Simplified analytics page test
 */

defined('ABSPATH') || exit;

// Get user data
$user_id = get_current_user_id();
$geographic_data = null;

if (vqr_user_can_access_geographic_analytics()) {
    $geographic_data = vqr_get_geographic_analytics_data($user_id, 30);
}

// Simple HTML structure
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Analytics Test</title>
    <style>
        .tab-content {
            display: block;
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            margin: 10px 0;
        }
        .debug-box {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #999;
        }
    </style>
</head>
<body>

<h1>Simplified Analytics Test</h1>

<div class="tab-content">
    <h2>Geographic Tab Content</h2>
    
    <div class="debug-box">
        <strong>Debug Info:</strong><br>
        User ID: <?php echo $user_id; ?><br>
        Can access: <?php echo vqr_user_can_access_geographic_analytics() ? 'YES' : 'NO'; ?><br>
        Data exists: <?php echo $geographic_data ? 'YES' : 'NO'; ?><br>
        <?php if ($geographic_data): ?>
            Heat map count: <?php echo count($geographic_data['heat_map_data'] ?? []); ?><br>
        <?php endif; ?>
    </div>
    
    <?php if ($geographic_data && !empty($geographic_data['heat_map_data'])): ?>
        <div style="background: lightgreen; padding: 15px; margin: 10px 0;">
            <h3>✅ Geographic Summary (This should show in main analytics)</h3>
            <p><strong>Countries Reached:</strong> <?php echo $geographic_data['summary_stats']['countries_reached']; ?></p>
            <p><strong>Total Locations:</strong> <?php echo $geographic_data['summary_stats']['total_locations']; ?></p>
            <p><strong>Total Scans:</strong> <?php echo $geographic_data['summary_stats']['total_scans']; ?></p>
            
            <h4>Heat Map Data:</h4>
            <?php foreach ($geographic_data['heat_map_data'] as $location): ?>
                <div style="background: white; padding: 10px; margin: 5px 0; border: 1px solid #ddd;">
                    <strong><?php echo esc_html($location->city); ?>, <?php echo esc_html($location->region); ?></strong><br>
                    Scans: <?php echo $location->scan_count; ?> | 
                    Unique Codes: <?php echo $location->unique_codes; ?> |
                    Last Scan: <?php echo $location->last_scan; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="background: lightcoral; padding: 15px; margin: 10px 0;">
            <h3>❌ No Geographic Data</h3>
            <p>This explains why the main analytics page shows nothing.</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>