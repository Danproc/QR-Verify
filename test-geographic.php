<?php
/**
 * Simple test page for geographic analytics debugging
 */

// Include WordPress
require_once('../../../wp-load.php');

// Include our plugin files
require_once('verification-qr-manager.php');

// Get current user
$user_id = get_current_user_id();

echo "<h1>Geographic Analytics Test Page</h1>";
echo "<p>User ID: $user_id</p>";

// Test user access
$can_access = vqr_user_can_access_geographic_analytics();
echo "<p>Can access geographic analytics: " . ($can_access ? 'YES' : 'NO') . "</p>";

// Test data retrieval
if ($can_access) {
    echo "<h2>Testing Geographic Data Retrieval...</h2>";
    
    $geographic_data = vqr_get_geographic_analytics_data($user_id, 30);
    
    echo "<h3>Data Structure:</h3>";
    echo "<pre>";
    if ($geographic_data) {
        echo "Data Type: " . gettype($geographic_data) . "\n";
        echo "Keys: " . implode(', ', array_keys($geographic_data)) . "\n";
        
        if (isset($geographic_data['heat_map_data'])) {
            echo "Heat Map Data Count: " . count($geographic_data['heat_map_data']) . "\n";
        }
        
        if (isset($geographic_data['summary_stats'])) {
            echo "Summary Stats: " . json_encode($geographic_data['summary_stats'], JSON_PRETTY_PRINT) . "\n";
        }
        
        if (isset($geographic_data['heat_map_data']) && !empty($geographic_data['heat_map_data'])) {
            echo "\nFirst Heat Map Item:\n";
            echo json_encode($geographic_data['heat_map_data'][0], JSON_PRETTY_PRINT) . "\n";
        }
    } else {
        echo "No data returned\n";
    }
    echo "</pre>";
    
    // Test the condition that's failing
    echo "<h3>Condition Testing:</h3>";
    $condition1 = $geographic_data ? 'TRUE' : 'FALSE';
    $condition2 = !empty($geographic_data['heat_map_data']) ? 'TRUE' : 'FALSE';
    echo "<p>Geographic data exists: $condition1</p>";
    echo "<p>Heat map data not empty: $condition2</p>";
    
    // Show what would render
    if ($geographic_data && !empty($geographic_data['heat_map_data'])) {
        echo "<h3>✅ CONDITION PASSES - This is what should show in the analytics page:</h3>";
        echo "<div style='background: #f0f0f0; padding: 20px; border: 2px solid green;'>";
        echo "<h4>Geographic Summary</h4>";
        echo "<p>Countries Reached: " . $geographic_data['summary_stats']['countries_reached'] . "</p>";
        echo "<p>Total Locations: " . $geographic_data['summary_stats']['total_locations'] . "</p>";
        echo "<p>Total Scans: " . $geographic_data['summary_stats']['total_scans'] . "</p>";
        echo "</div>";
    } else {
        echo "<h3>❌ CONDITION FAILS - This explains why nothing shows</h3>";
    }
    
} else {
    echo "<p>User does not have access to geographic analytics</p>";
    $user_plan = vqr_get_user_plan($user_id);
    echo "<p>User plan: $user_plan</p>";
}
?>