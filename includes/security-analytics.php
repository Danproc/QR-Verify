<?php
/**
 * Security Analytics for Cannabis QR Verification
 * Detects counterfeiting, fraud, and suspicious scanning patterns
 */

defined('ABSPATH') || exit;

/**
 * Check if security tables exist and create them if needed
 */
function vqr_ensure_security_tables() {
    global $wpdb;
    
    $security_scans_table = $wpdb->prefix . 'vqr_security_scans';
    $security_alerts_table = $wpdb->prefix . 'vqr_security_alerts';
    
    // Check if tables exist
    $scans_exists = $wpdb->get_var("SHOW TABLES LIKE '{$security_scans_table}'") == $security_scans_table;
    $alerts_exists = $wpdb->get_var("SHOW TABLES LIKE '{$security_alerts_table}'") == $security_alerts_table;
    
    if (!$scans_exists || !$alerts_exists) {
        error_log("VQR Security: Security tables missing. Scans: " . ($scans_exists ? 'EXISTS' : 'MISSING') . ", Alerts: " . ($alerts_exists ? 'EXISTS' : 'MISSING'));
        vqr_create_tables(); // This will create all tables including security ones
        
        // Check again after creation
        $scans_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$security_scans_table}'") == $security_scans_table;
        $alerts_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$security_alerts_table}'") == $security_alerts_table;
        error_log("VQR Security: After creation - Scans: " . ($scans_exists_after ? 'EXISTS' : 'MISSING') . ", Alerts: " . ($alerts_exists_after ? 'EXISTS' : 'MISSING'));
        
        return $scans_exists_after && $alerts_exists_after;
    }
    
    return true;
}

/**
 * Enhanced scan logging with security data
 */
function vqr_log_security_scan($qr_key, $strain_id = null, $additional_data = array()) {
    global $wpdb;
    
    // Ensure security tables exist
    if (!vqr_ensure_security_tables()) {
        error_log("VQR Security: Tables not available for logging");
        return false;
    }
    
    // Get comprehensive scan data
    $ip_address = vqr_get_user_ip_address();
    $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
    $referer = sanitize_text_field($_SERVER['HTTP_REFERER'] ?? '');
    $timestamp = current_time('mysql', true); // Use GMT for consistency
    
    // Get geolocation data (basic implementation)
    $location_data = vqr_get_location_from_ip($ip_address);
    
    // Check if this is a suspicious scan
    $security_flags = vqr_analyze_scan_security($qr_key, $ip_address, $location_data);
    
    // Log the scan with security data
    $table_name = $wpdb->prefix . 'vqr_security_scans';
    
    $scan_data = array(
        'qr_key' => $qr_key,
        'strain_id' => $strain_id,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent,
        'referer' => $referer,
        'country' => $location_data['country'] ?? '',
        'region' => $location_data['region'] ?? '',
        'city' => $location_data['city'] ?? '',
        'latitude' => $location_data['latitude'] ?? null,
        'longitude' => $location_data['longitude'] ?? null,
        'timezone' => $location_data['timezone'] ?? '',
        'isp' => $location_data['isp'] ?? '',
        'security_score' => $security_flags['score'],
        'security_flags' => json_encode($security_flags['flags']),
        'is_suspicious' => $security_flags['is_suspicious'] ? 1 : 0,
        'scan_timestamp' => $timestamp
    );
    
    $formats = array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%d', '%s', '%d', '%s');
    
    $result = $wpdb->insert($table_name, $scan_data, $formats);
    
    // Debug logging
    error_log("VQR Security: Logged scan for QR {$qr_key}, Score: {$security_flags['score']}, Suspicious: " . ($security_flags['is_suspicious'] ? 'YES' : 'NO'));
    
    // If this is suspicious, create an alert
    if ($security_flags['is_suspicious']) {
        $alert_result = vqr_create_security_alert($qr_key, $security_flags, $scan_data);
        error_log("VQR Security: Created alert for QR {$qr_key}, Alert ID: " . ($alert_result ? 'Success' : 'Failed'));
    }
    
    return $result;
}

/**
 * Analyze scan for security threats
 */
function vqr_analyze_scan_security($qr_key, $ip_address, $location_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_security_scans';
    $security_score = 0;
    $flags = array();
    
    // Get recent scans for this QR code (last 7 days)
    $recent_scans = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM {$table_name} 
        WHERE qr_key = %s 
        AND scan_timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY scan_timestamp DESC
    ", $qr_key));
    
    // Check for geographic anomalies
    if (count($recent_scans) > 0) {
        $geographic_flags = vqr_check_geographic_anomalies($recent_scans, $location_data);
        $security_score += $geographic_flags['score'];
        $flags = array_merge($flags, $geographic_flags['flags']);
    }
    
    // Check for rapid scanning (same IP, short time)
    $rapid_scan_flags = vqr_check_rapid_scanning($qr_key, $ip_address);
    $security_score += $rapid_scan_flags['score'];
    $flags = array_merge($flags, $rapid_scan_flags['flags']);
    
    // Check for duplicate location scanning
    $duplicate_location_flags = vqr_check_duplicate_locations($recent_scans, $location_data);
    $security_score += $duplicate_location_flags['score'];
    $flags = array_merge($flags, $duplicate_location_flags['flags']);
    
    // Check for suspicious IP patterns
    $ip_flags = vqr_check_suspicious_ip($ip_address);
    $security_score += $ip_flags['score'];
    $flags = array_merge($flags, $ip_flags['flags']);
    
    // Check time-based anomalies
    $time_flags = vqr_check_time_anomalies($recent_scans);
    $security_score += $time_flags['score'];
    $flags = array_merge($flags, $time_flags['flags']);
    
    return array(
        'score' => $security_score,
        'flags' => $flags,
        'is_suspicious' => $security_score >= 10 // Lowered from 30 to 10 for easier testing
    );
}

/**
 * Check for geographic anomalies (possible counterfeiting)
 */
function vqr_check_geographic_anomalies($recent_scans, $current_location) {
    $score = 0;
    $flags = array();
    
    if (empty($current_location['latitude']) || empty($current_location['longitude'])) {
        return array('score' => 0, 'flags' => array());
    }
    
    $current_lat = floatval($current_location['latitude']);
    $current_lng = floatval($current_location['longitude']);
    
    foreach ($recent_scans as $scan) {
        if (empty($scan->latitude) || empty($scan->longitude)) {
            continue;
        }
        
        $distance = vqr_calculate_distance($current_lat, $current_lng, $scan->latitude, $scan->longitude);
        $time_diff = strtotime('now') - strtotime($scan->scan_timestamp);
        $hours_diff = $time_diff / 3600;
        
        // Check for impossible travel times
        $max_speed_kmh = 900; // Maximum reasonable speed (flight)
        $required_speed = $distance / max($hours_diff, 0.1);
        
        if ($required_speed > $max_speed_kmh) {
            $score += 25;
            $flags[] = array(
                'type' => 'impossible_travel',
                'severity' => 'high',
                'message' => "Impossible travel: {$distance}km in {$hours_diff} hours",
                'details' => array(
                    'distance_km' => round($distance, 2),
                    'time_hours' => round($hours_diff, 2),
                    'required_speed_kmh' => round($required_speed, 2),
                    'previous_location' => array('lat' => $scan->latitude, 'lng' => $scan->longitude),
                    'current_location' => array('lat' => $current_lat, 'lng' => $current_lng)
                )
            );
        }
        
        // Check for multiple distant locations within short timeframe
        if ($distance > 500 && $hours_diff < 24) {
            $score += 15;
            $flags[] = array(
                'type' => 'distant_locations',
                'severity' => 'medium',
                'message' => "Scanned in distant locations: {$distance}km apart within 24 hours",
                'details' => array(
                    'distance_km' => round($distance, 2),
                    'time_hours' => round($hours_diff, 2)
                )
            );
        }
        
        // Check for different countries
        if (!empty($scan->country) && !empty($current_location['country']) && 
            $scan->country !== $current_location['country']) {
            $score += 10;
            $flags[] = array(
                'type' => 'multiple_countries',
                'severity' => 'medium',
                'message' => "Scanned in multiple countries: {$scan->country} and {$current_location['country']}",
                'details' => array(
                    'previous_country' => $scan->country,
                    'current_country' => $current_location['country']
                )
            );
        }
    }
    
    return array('score' => $score, 'flags' => $flags);
}

/**
 * Check for rapid scanning patterns
 */
function vqr_check_rapid_scanning($qr_key, $ip_address) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_security_scans';
    $score = 0;
    $flags = array();
    
    // Check for multiple scans from same IP in short time
    $recent_ip_scans = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$table_name} 
        WHERE qr_key = %s 
        AND ip_address = %s 
        AND scan_timestamp > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
    ", $qr_key, $ip_address));
    
    error_log("VQR Security: Rapid scan check for QR {$qr_key} from IP {$ip_address}: {$recent_ip_scans} scans in last hour");
    
    // Also check total scans for this QR key for debugging
    $total_scans = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE qr_key = %s", $qr_key));
    error_log("VQR Security: Total scans in database for QR {$qr_key}: {$total_scans}");
    
    if ($recent_ip_scans >= 1) { // Lowered to >= 1 for easier testing
        $score += 20;
        $flags[] = array(
            'type' => 'rapid_scanning_ip',
            'severity' => 'high',
            'message' => "Rapid scanning: {$recent_ip_scans} scans from same IP in 1 hour",
            'details' => array(
                'scan_count' => $recent_ip_scans,
                'ip_address' => $ip_address,
                'timeframe' => '1 hour'
            )
        );
    }
    
    // Check for burst scanning (many scans in very short time)
    $burst_scans = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$table_name} 
        WHERE qr_key = %s 
        AND scan_timestamp > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)
    ", $qr_key));
    
    error_log("VQR Security: Burst scan check for QR {$qr_key}: {$burst_scans} scans in last 5 minutes");
    
    if ($burst_scans >= 1) { // Lowered to >= 1 for easier testing
        $score += 15;
        $flags[] = array(
            'type' => 'burst_scanning',
            'severity' => 'medium',
            'message' => "Burst scanning: {$burst_scans} scans in 5 minutes",
            'details' => array(
                'scan_count' => $burst_scans,
                'timeframe' => '5 minutes'
            )
        );
    }
    
    return array('score' => $score, 'flags' => $flags);
}

/**
 * Check for duplicate location patterns
 */
function vqr_check_duplicate_locations($recent_scans, $current_location) {
    $score = 0;
    $flags = array();
    
    if (empty($current_location['city'])) {
        return array('score' => 0, 'flags' => array());
    }
    
    $location_counts = array();
    
    // Count scans per location
    foreach ($recent_scans as $scan) {
        if (!empty($scan->city)) {
            $location_key = $scan->city . '|' . $scan->region . '|' . $scan->country;
            $location_counts[$location_key] = ($location_counts[$location_key] ?? 0) + 1;
        }
    }
    
    // Add current scan location
    $current_key = $current_location['city'] . '|' . $current_location['region'] . '|' . $current_location['country'];
    $location_counts[$current_key] = ($location_counts[$current_key] ?? 0) + 1;
    
    // Check for suspicious patterns
    $unique_locations = count($location_counts);
    $max_scans_per_location = max($location_counts);
    
    if ($unique_locations >= 5) {
        $score += 20;
        $flags[] = array(
            'type' => 'multiple_locations',
            'severity' => 'high',
            'message' => "Scanned in {$unique_locations} different locations within 7 days",
            'details' => array(
                'unique_locations' => $unique_locations,
                'locations' => array_keys($location_counts)
            )
        );
    }
    
    if ($max_scans_per_location > 10) {
        $score += 10;
        $flags[] = array(
            'type' => 'repeated_location_scanning',
            'severity' => 'medium',
            'message' => "Same location scanned {$max_scans_per_location} times",
            'details' => array(
                'max_scans' => $max_scans_per_location
            )
        );
    }
    
    return array('score' => $score, 'flags' => $flags);
}

/**
 * Check for suspicious IP patterns
 */
function vqr_check_suspicious_ip($ip_address) {
    $score = 0;
    $flags = array();
    
    // Check if IP is from known VPN/proxy services (basic check)
    $suspicious_patterns = array(
        '10.0.0.0/8',     // Private network
        '172.16.0.0/12',  // Private network
        '192.168.0.0/16'  // Private network
    );
    
    foreach ($suspicious_patterns as $pattern) {
        if (vqr_ip_in_range($ip_address, $pattern)) {
            $score += 5;
            $flags[] = array(
                'type' => 'private_ip',
                'severity' => 'low',
                'message' => "Scan from private IP address",
                'details' => array(
                    'ip_address' => $ip_address,
                    'pattern' => $pattern
                )
            );
            break;
        }
    }
    
    // Check for localhost/development scanning
    if (in_array($ip_address, array('127.0.0.1', '::1', 'localhost'))) {
        $score += 5;
        $flags[] = array(
            'type' => 'localhost_scan',
            'severity' => 'low',
            'message' => "Scan from localhost/development environment",
            'details' => array(
                'ip_address' => $ip_address
            )
        );
    }
    
    return array('score' => $score, 'flags' => $flags);
}

/**
 * Check for time-based anomalies
 */
function vqr_check_time_anomalies($recent_scans) {
    $score = 0;
    $flags = array();
    
    $unusual_hour_scans = 0;
    $weekend_scans = 0;
    
    foreach ($recent_scans as $scan) {
        $hour = intval(date('H', strtotime($scan->scan_timestamp)));
        $day_of_week = intval(date('w', strtotime($scan->scan_timestamp))); // 0 = Sunday, 6 = Saturday
        
        // Check for unusual hours (2 AM - 6 AM)
        if ($hour >= 2 && $hour <= 6) {
            $unusual_hour_scans++;
        }
        
        // Check for weekend scanning (if business product)
        if ($day_of_week == 0 || $day_of_week == 6) {
            $weekend_scans++;
        }
    }
    
    if ($unusual_hour_scans > 3) {
        $score += 10;
        $flags[] = array(
            'type' => 'unusual_hours',
            'severity' => 'medium',
            'message' => "{$unusual_hour_scans} scans during unusual hours (2 AM - 6 AM)",
            'details' => array(
                'unusual_hour_scans' => $unusual_hour_scans
            )
        );
    }
    
    return array('score' => $score, 'flags' => $flags);
}

/**
 * Create security alert
 */
function vqr_create_security_alert($qr_key, $security_flags, $scan_data) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'vqr_security_alerts';
    
    // Get QR code details
    $qr_table = $wpdb->prefix . 'vqr_codes';
    $qr_code = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$qr_table} WHERE qr_key = %s", $qr_key));
    
    if (!$qr_code) {
        return false;
    }
    
    $alert_data = array(
        'qr_code_id' => $qr_code->id,
        'qr_key' => $qr_key,
        'batch_code' => $qr_code->batch_code, // Store the 8-digit batch code
        'user_id' => $qr_code->user_id,
        'strain_id' => $qr_code->post_id,
        'alert_type' => vqr_determine_alert_type($security_flags['flags']),
        'severity' => vqr_determine_alert_severity($security_flags['score']),
        'security_score' => $security_flags['score'],
        'security_flags' => json_encode($security_flags['flags']),
        'scan_data' => json_encode($scan_data),
        'ip_address' => $scan_data['ip_address'],
        'location' => $scan_data['city'] . ', ' . $scan_data['region'] . ', ' . $scan_data['country'],
        'is_resolved' => 0,
        'created_at' => current_time('mysql', true) // Use GMT for consistency
    );
    
    $formats = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s');
    
    $result = $wpdb->insert($table_name, $alert_data, $formats);
    
    // Send notification email for high severity alerts
    if ($alert_data['severity'] === 'high') {
        vqr_send_security_alert_email($alert_data);
    }
    
    return $result;
}

/**
 * Determine alert type from security flags
 */
function vqr_determine_alert_type($flags) {
    $types = array();
    
    foreach ($flags as $flag) {
        switch ($flag['type']) {
            case 'impossible_travel':
            case 'distant_locations':
            case 'multiple_countries':
            case 'multiple_locations':
                $types[] = 'geographic_anomaly';
                break;
            case 'rapid_scanning_ip':
            case 'burst_scanning':
                $types[] = 'scanning_anomaly';
                break;
            case 'repeated_location_scanning':
                $types[] = 'duplication_suspected';
                break;
            case 'private_ip':
            case 'localhost_scan':
                $types[] = 'suspicious_ip';
                break;
            case 'unusual_hours':
                $types[] = 'time_anomaly';
                break;
        }
    }
    
    $types = array_unique($types);
    
    if (in_array('geographic_anomaly', $types)) {
        return 'counterfeit_suspected';
    } elseif (in_array('scanning_anomaly', $types)) {
        return 'bot_activity';
    } elseif (in_array('duplication_suspected', $types)) {
        return 'duplication_suspected';
    } else {
        return 'general_suspicious';
    }
}

/**
 * Determine alert severity
 */
function vqr_determine_alert_severity($score) {
    if ($score >= 50) {
        return 'critical';
    } elseif ($score >= 30) {
        return 'high';
    } elseif ($score >= 15) {
        return 'medium';
    } else {
        return 'low';
    }
}

/**
 * Calculate distance between two coordinates (Haversine formula)
 */
function vqr_calculate_distance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // Earth's radius in kilometers
    
    $lat_delta = deg2rad($lat2 - $lat1);
    $lng_delta = deg2rad($lng2 - $lng1);
    
    $a = sin($lat_delta / 2) * sin($lat_delta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lng_delta / 2) * sin($lng_delta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * Check if IP is in range
 */
function vqr_ip_in_range($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($range_ip, $netmask) = explode('/', $range, 2);
    $range_decimal = ip2long($range_ip);
    $ip_decimal = ip2long($ip);
    $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
    $netmask_decimal = ~ $wildcard_decimal;
    
    return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
}

/**
 * Get basic location from IP (placeholder - integrate with IP geolocation service)
 */
function vqr_get_location_from_ip($ip_address) {
    // For development/localhost - provide accurate local info
    if (in_array($ip_address, array('127.0.0.1', '::1', 'localhost'))) {
        return array(
            'country' => 'Local Development',
            'region' => 'Local Machine',
            'city' => 'Localhost',
            'latitude' => null,
            'longitude' => null,
            'timezone' => wp_timezone_string(),
            'isp' => 'Local Development Server'
        );
    }
    
    // For real IPs, try a simple free API call
    $response = wp_remote_get("http://ip-api.com/json/{$ip_address}?fields=status,country,regionName,city,lat,lon,timezone,isp", array(
        'timeout' => 5,
        'sslverify' => false
    ));
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'] ?? 'Unknown',
                'region' => $data['regionName'] ?? 'Unknown',
                'city' => $data['city'] ?? 'Unknown',
                'latitude' => $data['lat'] ?? null,
                'longitude' => $data['lon'] ?? null,
                'timezone' => $data['timezone'] ?? 'Unknown',
                'isp' => $data['isp'] ?? 'Unknown'
            );
        }
    }
    
    // Fallback for any errors
    return array(
        'country' => 'Unknown',
        'region' => 'Unknown', 
        'city' => 'Unknown',
        'latitude' => null,
        'longitude' => null,
        'timezone' => 'Unknown',
        'isp' => 'Unknown'
    );
}

/**
 * Get security alert email template
 */
function vqr_get_security_alert_email_template($user, $alert_data, $strain_title) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    
    // Format alert type for display
    $alert_type_display = ucwords(str_replace('_', ' ', $alert_data['alert_type']));
    $severity_display = strtoupper($alert_data['severity']);
    
    // Determine severity styling
    $severity_colors = array(
        'critical' => array('bg' => '#dc2626', 'light' => '#fef2f2', 'border' => '#fecaca', 'text' => '#dc2626'),
        'high' => array('bg' => '#ea580c', 'light' => '#fff7ed', 'border' => '#fed7aa', 'text' => '#ea580c'),
        'medium' => array('bg' => '#d97706', 'light' => '#fffbeb', 'border' => '#fde68a', 'text' => '#d97706'),
        'low' => array('bg' => '#059669', 'light' => '#ecfdf5', 'border' => '#a7f3d0', 'text' => '#059669')
    );
    
    $severity_color = $severity_colors[$alert_data['severity']] ?? $severity_colors['medium'];
    
    // Format timestamp
    $alert_time = get_date_from_gmt($alert_data['created_at']);
    $formatted_time = date('M j, Y \a\t g:i A', strtotime($alert_time));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Security Alert - <?php echo esc_html($site_name); ?></title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f8fafc;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                overflow: hidden;
            }
            .header {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .header h1 {
                margin: 0;
                font-size: 24px;
                font-weight: 600;
            }
            .header .alert-icon {
                font-size: 48px;
                margin-bottom: 10px;
                display: block;
            }
            .content {
                padding: 30px;
            }
            .content h2 {
                color: #1f2937;
                font-size: 20px;
                margin-bottom: 20px;
            }
            .content p {
                margin-bottom: 16px;
                color: #6b7280;
            }
            .alert-details {
                background: <?php echo $severity_color['light']; ?>;
                border: 1px solid <?php echo $severity_color['border']; ?>;
                border-left: 4px solid <?php echo $severity_color['bg']; ?>;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
            }
            .alert-details h3 {
                margin: 0 0 15px 0;
                color: <?php echo $severity_color['text']; ?>;
                font-size: 16px;
                font-weight: 600;
            }
            .detail-grid {
                display: grid;
                grid-template-columns: 140px 1fr;
                gap: 8px;
                margin-bottom: 12px;
            }
            .detail-label {
                font-weight: 500;
                color: #374151;
            }
            .detail-value {
                color: #6b7280;
                word-break: break-word;
            }
            .batch-code {
                font-family: 'Monaco', 'Menlo', monospace;
                background: #f3f4f6;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 14px;
            }
            .severity-badge {
                display: inline-block;
                background: <?php echo $severity_color['bg']; ?>;
                color: white;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .action-button {
                display: inline-block;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                text-decoration: none;
                padding: 14px 28px;
                border-radius: 8px;
                font-weight: 500;
                margin: 20px 0;
                text-align: center;
            }
            .action-button:hover {
                background: linear-gradient(135deg, #059669 0%, #047857 100%);
            }
            .recommendations {
                background: #f9fafb;
                padding: 20px;
                border-radius: 8px;
                margin: 20px 0;
                border-left: 4px solid #6b7280;
            }
            .recommendations h4 {
                margin: 0 0 12px 0;
                color: #374151;
                font-size: 16px;
                font-weight: 600;
            }
            .recommendations ul {
                margin: 0;
                padding-left: 20px;
                color: #6b7280;
            }
            .recommendations li {
                margin-bottom: 8px;
            }
            .footer {
                background: #f9fafb;
                padding: 20px 30px;
                border-top: 1px solid #e5e7eb;
                text-align: center;
                font-size: 14px;
                color: #6b7280;
            }
            .footer a {
                color: #10b981;
                text-decoration: none;
            }
            .timestamp {
                font-size: 14px;
                color: #9ca3af;
                font-style: italic;
            }
            @media (max-width: 600px) {
                .detail-grid {
                    grid-template-columns: 1fr;
                    gap: 4px;
                }
                .detail-label {
                    font-weight: 600;
                }
                .content {
                    padding: 20px;
                }
                .header {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <?php 
            $global_logo = vqr_get_global_logo();
            ?>
            <div class="header">
                <?php if ($global_logo): ?>
                    <img src="<?php echo esc_url($global_logo['url']); ?>" 
                         alt="<?php echo esc_attr($global_logo['alt']); ?>" 
                         style="max-height: 60px; max-width: 200px; margin-bottom: 16px; object-fit: contain;">
                <?php endif; ?>
                <div class="alert-icon">🚨</div>
                <h1><?php echo esc_html($site_name); ?></h1>
                <div style="font-size: 18px; margin-top: 8px;">Security Alert</div>
            </div>
            
            <div class="content">
                <h2>Suspicious QR Code Activity Detected</h2>
                
                <p>Hello <?php echo esc_html($user->display_name); ?>,</p>
                
                <p>We've detected suspicious activity on one of your QR codes that may indicate potential counterfeiting or unauthorized use. Please review the details below and take appropriate action.</p>
                
                <div class="alert-details">
                    <h3>🔍 Security Alert Details</h3>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Alert Type:</div>
                        <div class="detail-value"><?php echo esc_html($alert_type_display); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Severity:</div>
                        <div class="detail-value">
                            <span class="severity-badge"><?php echo esc_html($severity_display); ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Product:</div>
                        <div class="detail-value"><?php echo esc_html($strain_title); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Batch Code:</div>
                        <div class="detail-value">
                            <span class="batch-code"><?php echo esc_html($alert_data['batch_code'] ?? $alert_data['qr_key']); ?></span>
                        </div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Location:</div>
                        <div class="detail-value"><?php echo esc_html($alert_data['location']); ?></div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Risk Score:</div>
                        <div class="detail-value"><?php echo esc_html($alert_data['security_score']); ?>/100</div>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-label">Time:</div>
                        <div class="detail-value timestamp"><?php echo esc_html($formatted_time); ?></div>
                    </div>
                </div>
                
                <div style="text-align: center;">
                    <a href="<?php echo esc_url(home_url('/app/analytics')); ?>" class="action-button">
                        View Full Security Report
                    </a>
                </div>
                
                <div class="recommendations">
                    <h4>🛡️ Recommended Actions</h4>
                    <ul>
                        <li><strong>Review scanning patterns</strong> - Check if this activity matches your expected distribution channels</li>
                        <li><strong>Verify authenticity</strong> - Investigate whether this could indicate counterfeiting attempts</li>
                        <li><strong>Monitor closely</strong> - Watch for additional suspicious activity on this and other products</li>
                        <li><strong>Contact authorities</strong> - If counterfeiting is suspected, consider reporting to relevant authorities</li>
                        <li><strong>Update security</strong> - Review your product distribution and security measures</li>
                    </ul>
                </div>
                
                <p><strong>Need help?</strong> Our security team is here to assist you in investigating this alert and protecting your cannabis products from counterfeiting.</p>
                
                <p style="font-size: 14px; color: #9ca3af; font-style: italic;">
                    This is an automated security alert. If you believe this is a false positive, please contact our support team for assistance.
                </p>
            </div>
            
            <div class="footer">
                <p>
                    This security alert was sent by <a href="<?php echo esc_url($site_url); ?>"><?php echo esc_html($site_name); ?></a><br>
                    Need help? Contact us at <a href="mailto:support@verify420.com">support@verify420.com</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Send security alert email
 */
function vqr_send_security_alert_email($alert_data) {
    $user = get_user_by('ID', $alert_data['user_id']);
    if (!$user) {
        return false;
    }
    
    $strain_title = 'Unknown Product';
    if ($alert_data['strain_id']) {
        $strain = get_post($alert_data['strain_id']);
        $strain_title = $strain ? $strain->post_title : 'Unknown Product';
    }
    
    $site_name = get_bloginfo('name');
    $subject = sprintf('[%s] Security Alert - Suspicious Activity Detected', $site_name);
    
    $message = vqr_get_security_alert_email_template($user, $alert_data, $strain_title);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' Security <noreply@' . parse_url(home_url(), PHP_URL_HOST) . '>'
    );
    
    return wp_mail($user->user_email, $subject, $message, $headers);
}

/**
 * Get security dashboard data
 */
function vqr_get_security_dashboard_data($user_id, $days = 30, $strain_id = null) {
    global $wpdb;
    
    $alerts_table = $wpdb->prefix . 'vqr_security_alerts';
    $scans_table = $wpdb->prefix . 'vqr_security_scans';
    
    // Build strain filter condition
    $strain_filter_sql = '';
    $strain_filter_params = [];
    if ($strain_id && $strain_id > 0) {
        $strain_filter_sql = ' AND a.strain_id = %d';
        $strain_filter_params[] = $strain_id;
    }
    
    // Get alert summary for all QR codes owned by this user
    $alert_summary = $wpdb->get_results($wpdb->prepare("
        SELECT 
            a.severity,
            a.alert_type,
            COUNT(*) as count
        FROM {$alerts_table} a
        INNER JOIN {$wpdb->prefix}vqr_codes c ON a.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
        {$strain_filter_sql}
        GROUP BY a.severity, a.alert_type
        ORDER BY a.severity DESC, count DESC
    ", ...array_merge([$user_id, $days], $strain_filter_params)));
    
    // Get recent alerts for all QR codes owned by this user (increased to 100)
    $recent_alerts = $wpdb->get_results($wpdb->prepare("
        SELECT a.*
        FROM {$alerts_table} a
        INNER JOIN {$wpdb->prefix}vqr_codes c ON a.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND a.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY)
        {$strain_filter_sql}
        ORDER BY a.created_at DESC
        LIMIT 100
    ", ...array_merge([$user_id, $days], $strain_filter_params)));
    
    // Debug: Get ALL alerts for this user's QR codes
    $all_alerts = $wpdb->get_results($wpdb->prepare("
        SELECT COUNT(*) as count 
        FROM {$alerts_table} a
        INNER JOIN {$wpdb->prefix}vqr_codes c ON a.qr_key = c.qr_key
        WHERE c.user_id = %d
    ", $user_id));
    error_log("VQR Security: Total alerts in database for user {$user_id}'s QR codes: " . ($all_alerts[0]->count ?? 'N/A'));
    error_log("VQR Security: Recent alerts retrieved (last {$days} days): " . count($recent_alerts));
    
    // Debug: Show most recent alert timestamp
    if (!empty($recent_alerts)) {
        error_log("VQR Security: Most recent alert timestamp: " . $recent_alerts[0]->created_at);
    }
    
    // Get scanning patterns
    $scanning_patterns = $wpdb->get_results($wpdb->prepare("
        SELECT 
            DATE(s.scan_timestamp) as scan_date,
            COUNT(*) as total_scans,
            COUNT(DISTINCT s.qr_key) as unique_codes,
            SUM(s.is_suspicious) as suspicious_scans,
            AVG(s.security_score) as avg_security_score
        FROM {$scans_table} s
        INNER JOIN {$wpdb->prefix}vqr_codes c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND s.scan_timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY DATE(s.scan_timestamp)
        ORDER BY scan_date DESC
    ", $user_id, $days));
    
    // Get geographic distribution
    $geographic_data = $wpdb->get_results($wpdb->prepare("
        SELECT 
            s.country,
            s.region,
            s.city,
            COUNT(*) as scan_count,
            COUNT(DISTINCT s.qr_key) as unique_codes,
            SUM(s.is_suspicious) as suspicious_scans
        FROM {$scans_table} s
        INNER JOIN {$wpdb->prefix}vqr_codes c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND s.scan_timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
        AND s.country != ''
        GROUP BY s.country, s.region, s.city
        ORDER BY scan_count DESC
        LIMIT 50
    ", $user_id, $days));
    
    return array(
        'alert_summary' => $alert_summary,
        'recent_alerts' => $recent_alerts,
        'scanning_patterns' => $scanning_patterns,
        'geographic_data' => $geographic_data,
        'period_days' => $days
    );
}

/**
 * Debug function to test security logging manually
 */
function vqr_test_security_logging() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    global $wpdb;
    $scans_table = $wpdb->prefix . 'vqr_security_scans';
    $alerts_table = $wpdb->prefix . 'vqr_security_alerts';
    
    $debug_info = array(
        'security_scans_table_exists' => $wpdb->get_var("SHOW TABLES LIKE '{$scans_table}'") == $scans_table,
        'security_alerts_table_exists' => $wpdb->get_var("SHOW TABLES LIKE '{$alerts_table}'") == $alerts_table,
        'total_security_scans' => $wpdb->get_var("SELECT COUNT(*) FROM {$scans_table}"),
        'total_security_alerts' => $wpdb->get_var("SELECT COUNT(*) FROM {$alerts_table}"),
        'vqr_log_security_scan_exists' => function_exists('vqr_log_security_scan'),
        'vqr_get_security_dashboard_data_exists' => function_exists('vqr_get_security_dashboard_data')
    );
    
    return $debug_info;
}

/**
 * Get geographic analytics data for heat maps and distribution tracking
 */
function vqr_get_geographic_analytics_data($user_id, $days = 30, $strain_id = null) {
    global $wpdb;
    
    $scans_table = $wpdb->prefix . 'vqr_security_scans';
    $codes_table = $wpdb->prefix . 'vqr_codes';
    
    // Build strain filter condition
    $strain_filter_sql = '';
    $strain_filter_params = [];
    if ($strain_id && $strain_id > 0) {
        $strain_filter_sql = ' AND c.post_id = %d';
        $strain_filter_params[] = $strain_id;
    }
    
    // Debug: Check if tables exist and have data
    $scans_exist = $wpdb->get_var("SHOW TABLES LIKE '{$scans_table}'") == $scans_table;
    $codes_exist = $wpdb->get_var("SHOW TABLES LIKE '{$codes_table}'") == $codes_table;
    
    error_log("VQR Geographic Debug: Scans table exists: " . ($scans_exist ? 'YES' : 'NO'));
    error_log("VQR Geographic Debug: Codes table exists: " . ($codes_exist ? 'YES' : 'NO'));
    
    if (!$scans_exist || !$codes_exist) {
        return vqr_get_sample_geographic_data(); // Return sample data for testing
    }
    
    // Check total scan data for this user
    $total_scans = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) 
        FROM {$scans_table} s
        INNER JOIN {$codes_table} c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        {$strain_filter_sql}
    ", ...array_merge([$user_id], $strain_filter_params)));
    
    error_log("VQR Geographic Debug: Total scans for user {$user_id}: {$total_scans}");
    
    // If no scan data exists, return sample data for testing
    if ($total_scans == 0) {
        error_log("VQR Geographic Debug: No scans found, returning sample data");
        return vqr_get_sample_geographic_data();
    }
    
    // Get scan heat map data (city/region aggregation)
    $heat_map_data = $wpdb->get_results($wpdb->prepare("
        SELECT 
            s.country,
            s.region,
            s.city,
            s.latitude,
            s.longitude,
            COUNT(*) as scan_count,
            COUNT(DISTINCT s.qr_key) as unique_codes,
            AVG(s.security_score) as avg_security_score,
            MAX(s.scan_timestamp) as last_scan
        FROM {$scans_table} s
        INNER JOIN {$codes_table} c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND s.scan_timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
        AND s.country != ''
        AND s.latitude IS NOT NULL
        AND s.longitude IS NOT NULL
        {$strain_filter_sql}
        GROUP BY s.country, s.region, s.city, s.latitude, s.longitude
        ORDER BY scan_count DESC
        LIMIT 100
    ", ...array_merge([$user_id, $days], $strain_filter_params)));
    
    // Get country-level distribution
    $country_distribution = $wpdb->get_results($wpdb->prepare("
        SELECT 
            s.country,
            COUNT(*) as scan_count,
            COUNT(DISTINCT s.qr_key) as unique_codes,
            COUNT(DISTINCT DATE(s.scan_timestamp)) as active_days,
            AVG(s.security_score) as avg_security_score
        FROM {$scans_table} s
        INNER JOIN {$codes_table} c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND s.scan_timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
        AND s.country != ''
        {$strain_filter_sql}
        GROUP BY s.country
        ORDER BY scan_count DESC
        LIMIT 20
    ", ...array_merge([$user_id, $days], $strain_filter_params)));
    
    // Get distribution tracking (distance analysis)
    $distribution_tracking = $wpdb->get_results($wpdb->prepare("
        SELECT 
            c.qr_key,
            c.batch_code,
            c.created_at,
            COUNT(s.id) as total_scans,
            MIN(s.scan_timestamp) as first_scan,
            MAX(s.scan_timestamp) as last_scan,
            COUNT(DISTINCT CONCAT(s.country, '|', s.region, '|', s.city)) as unique_locations,
            AVG(s.latitude) as avg_lat,
            AVG(s.longitude) as avg_lng
        FROM {$codes_table} c
        LEFT JOIN {$scans_table} s ON c.qr_key = s.qr_key
        WHERE c.user_id = %d
        AND c.created_at > DATE_SUB(NOW(), INTERVAL %d DAY)
        GROUP BY c.id
        HAVING total_scans > 0
        ORDER BY unique_locations DESC, total_scans DESC
        LIMIT 50
    ", $user_id, $days));
    
    // Get market penetration by region
    $market_penetration = $wpdb->get_results($wpdb->prepare("
        SELECT 
            s.region,
            s.country,
            COUNT(*) as scan_count,
            COUNT(DISTINCT s.qr_key) as unique_codes,
            COUNT(DISTINCT DATE(s.scan_timestamp)) as active_days,
            AVG(s.security_score) as avg_security_score,
            (COUNT(*) / COUNT(DISTINCT s.qr_key)) as engagement_ratio
        FROM {$scans_table} s
        INNER JOIN {$codes_table} c ON s.qr_key = c.qr_key
        WHERE c.user_id = %d
        AND s.scan_timestamp > DATE_SUB(NOW(), INTERVAL %d DAY)
        AND s.region != ''
        GROUP BY s.country, s.region
        HAVING scan_count >= 5
        ORDER BY engagement_ratio DESC, scan_count DESC
        LIMIT 30
    ", $user_id, $days));
    
    // Calculate distribution statistics from actual QR codes table scan counts
    $total_scans_query = "
        SELECT SUM(c.scan_count) as total_scans
        FROM {$codes_table} c
        WHERE c.user_id = %d
        {$strain_filter_sql}
    ";
    $actual_total_scans = $wpdb->get_var($wpdb->prepare($total_scans_query, ...array_merge([$user_id], $strain_filter_params))) ?: 0;
    
    $total_locations = count($heat_map_data);
    $countries_reached = count($country_distribution);
    
    error_log("VQR Geographic Debug: Calculated total scans from QR codes table: {$actual_total_scans}");
    error_log("VQR Geographic Debug: Heat map data scan total: " . array_sum(array_column($heat_map_data, 'scan_count')));
    
    return array(
        'heat_map_data' => $heat_map_data,
        'country_distribution' => $country_distribution,
        'distribution_tracking' => $distribution_tracking,
        'market_penetration' => $market_penetration,
        'summary_stats' => array(
            'total_scans' => $actual_total_scans,
            'total_locations' => $total_locations,
            'countries_reached' => $countries_reached,
            'period_days' => $days
        )
    );
}

/**
 * Get sample geographic data for testing/demo purposes
 */
function vqr_get_sample_geographic_data() {
    // Create sample heat map data
    $sample_heat_map = array(
        (object) array(
            'country' => 'United States',
            'region' => 'California',
            'city' => 'Los Angeles',
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'scan_count' => 156,
            'unique_codes' => 23,
            'avg_security_score' => 15.2,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ),
        (object) array(
            'country' => 'United States',
            'region' => 'Colorado',
            'city' => 'Denver',
            'latitude' => 39.7392,
            'longitude' => -104.9903,
            'scan_count' => 89,
            'unique_codes' => 15,
            'avg_security_score' => 8.7,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ),
        (object) array(
            'country' => 'United States',
            'region' => 'Oregon',
            'city' => 'Portland',
            'latitude' => 45.5152,
            'longitude' => -122.6784,
            'scan_count' => 67,
            'unique_codes' => 12,
            'avg_security_score' => 12.1,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-6 hours'))
        ),
        (object) array(
            'country' => 'Canada',
            'region' => 'British Columbia',
            'city' => 'Vancouver',
            'latitude' => 49.2827,
            'longitude' => -123.1207,
            'scan_count' => 43,
            'unique_codes' => 8,
            'avg_security_score' => 6.3,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-3 days'))
        ),
        (object) array(
            'country' => 'United States',
            'region' => 'Washington',
            'city' => 'Seattle',
            'latitude' => 47.6062,
            'longitude' => -122.3321,
            'scan_count' => 34,
            'unique_codes' => 7,
            'avg_security_score' => 9.8,
            'last_scan' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        )
    );
    
    // Create sample country distribution
    $sample_countries = array(
        (object) array(
            'country' => 'United States',
            'scan_count' => 346,
            'unique_codes' => 57,
            'active_days' => 28,
            'avg_security_score' => 11.4
        ),
        (object) array(
            'country' => 'Canada',
            'scan_count' => 43,
            'unique_codes' => 8,
            'active_days' => 15,
            'avg_security_score' => 6.3
        )
    );
    
    // Create sample distribution tracking
    $sample_distribution = array(
        (object) array(
            'qr_key' => 'sample_key_1',
            'batch_code' => 'ABC123XY',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 weeks')),
            'total_scans' => 45,
            'first_scan' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'last_scan' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'unique_locations' => 8,
            'avg_lat' => 40.7128,
            'avg_lng' => -74.0060
        ),
        (object) array(
            'qr_key' => 'sample_key_2',
            'batch_code' => 'DEF456ZW',
            'created_at' => date('Y-m-d H:i:s', strtotime('-3 weeks')),
            'total_scans' => 67,
            'first_scan' => date('Y-m-d H:i:s', strtotime('-18 days')),
            'last_scan' => date('Y-m-d H:i:s', strtotime('-3 hours')),
            'unique_locations' => 12,
            'avg_lat' => 34.0522,
            'avg_lng' => -118.2437
        )
    );
    
    // Create sample market penetration
    $sample_penetration = array(
        (object) array(
            'region' => 'California',
            'country' => 'United States',
            'scan_count' => 156,
            'unique_codes' => 23,
            'active_days' => 25,
            'avg_security_score' => 15.2,
            'engagement_ratio' => 6.78
        ),
        (object) array(
            'region' => 'Colorado',
            'country' => 'United States',
            'scan_count' => 89,
            'unique_codes' => 15,
            'active_days' => 22,
            'avg_security_score' => 8.7,
            'engagement_ratio' => 5.93
        ),
        (object) array(
            'region' => 'Oregon',
            'country' => 'United States',
            'scan_count' => 67,
            'unique_codes' => 12,
            'active_days' => 18,
            'avg_security_score' => 12.1,
            'engagement_ratio' => 5.58
        )
    );
    
    return array(
        'heat_map_data' => $sample_heat_map,
        'country_distribution' => $sample_countries,
        'distribution_tracking' => $sample_distribution,
        'market_penetration' => $sample_penetration,
        'summary_stats' => array(
            'total_scans' => 389,
            'total_locations' => 5,
            'countries_reached' => 2,
            'period_days' => 30
        )
    );
}

/**
 * Create sample security scan data for testing (admin only)
 */
function vqr_create_sample_security_scans() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Get a QR code from the current user
    $qr_codes_table = $wpdb->prefix . 'vqr_codes';
    $sample_qr = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$qr_codes_table} 
        WHERE user_id = %d 
        LIMIT 1
    ", $user_id));
    
    if (!$sample_qr) {
        return "No QR codes found for current user";
    }
    
    // Ensure security tables exist
    if (!vqr_ensure_security_tables()) {
        return "Security tables not available";
    }
    
    // Sample locations for testing
    $sample_locations = array(
        array('country' => 'United States', 'region' => 'California', 'city' => 'Los Angeles', 'lat' => 34.0522, 'lng' => -118.2437, 'ip' => '192.168.1.100'),
        array('country' => 'United States', 'region' => 'Colorado', 'city' => 'Denver', 'lat' => 39.7392, 'lng' => -104.9903, 'ip' => '192.168.1.101'),
        array('country' => 'Canada', 'region' => 'British Columbia', 'city' => 'Vancouver', 'lat' => 49.2827, 'lng' => -123.1207, 'ip' => '192.168.1.102'),
    );
    
    $created_scans = 0;
    
    // Create sample scans with different timestamps
    foreach ($sample_locations as $i => $location) {
        // Simulate different IP addresses and times
        $_SERVER['REMOTE_ADDR'] = $location['ip'];
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) Sample Test';
        $_SERVER['HTTP_REFERER'] = 'https://verify420.com/test';
        
        // Create scan with artificial location data
        $location_data = array(
            'country' => $location['country'],
            'region' => $location['region'],
            'city' => $location['city'],
            'latitude' => $location['lat'],
            'longitude' => $location['lng'],
            'timezone' => 'America/Los_Angeles',
            'isp' => 'Test ISP Provider'
        );
        
        // Use varied security scores for testing
        $security_flags = array(
            'score' => 5 + ($i * 3), // Scores: 5, 8, 11
            'flags' => array(),
            'is_suspicious' => false
        );
        
        // Insert scan directly into database
        $scans_table = $wpdb->prefix . 'vqr_security_scans';
        $scan_result = $wpdb->insert($scans_table, array(
            'qr_key' => $sample_qr->qr_key,
            'strain_id' => $sample_qr->post_id,
            'ip_address' => $location['ip'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'referer' => $_SERVER['HTTP_REFERER'],
            'country' => $location_data['country'],
            'region' => $location_data['region'],
            'city' => $location_data['city'],
            'latitude' => $location_data['latitude'],
            'longitude' => $location_data['longitude'],
            'timezone' => $location_data['timezone'],
            'isp' => $location_data['isp'],
            'security_score' => $security_flags['score'],
            'security_flags' => json_encode($security_flags['flags']),
            'is_suspicious' => $security_flags['is_suspicious'] ? 1 : 0,
            'scan_timestamp' => date('Y-m-d H:i:s', strtotime("-" . ($i + 1) . " hours"))
        ));
        
        if ($scan_result) {
            $created_scans++;
        }
    }
    
    return "Created {$created_scans} sample security scans for QR code: {$sample_qr->qr_key}";
}