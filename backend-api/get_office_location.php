<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET search_path TO user_1_outlet_baru, public');

$settings = DB::table('payroll_settings')->first();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           OFFICE LOCATION FOR ATTENDANCE                  ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($settings && $settings->attendance_location_lat && $settings->attendance_location_lng) {
    echo "✓ Office location is configured\n\n";
    echo "Coordinates to use for testing:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Latitude:  " . $settings->attendance_location_lat . "\n";
    echo "Longitude: " . $settings->attendance_location_lng . "\n";
    echo "Radius:    " . $settings->attendance_radius . " meters\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    
    echo "Instructions:\n";
    echo "1. Open Attendance page\n";
    echo "2. Click 'Set Manual Location' button\n";
    echo "3. Enter the coordinates above\n";
    echo "4. Click 'Use This Location'\n";
    echo "5. Take photo and clock in/out\n\n";
    
    echo "Google Maps Link:\n";
    echo "https://www.google.com/maps?q={$settings->attendance_location_lat},{$settings->attendance_location_lng}\n\n";
    
    // Test locations
    echo "Test Locations:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    // Inside radius
    $testLat1 = $settings->attendance_location_lat + 0.0003;
    $testLng1 = $settings->attendance_location_lng;
    echo "✓ INSIDE (50m away):\n";
    echo "  Lat: $testLat1, Lng: $testLng1\n\n";
    
    // Outside radius
    $testLat2 = $settings->attendance_location_lat + 0.002;
    $testLng2 = $settings->attendance_location_lng;
    echo "✗ OUTSIDE (200m away):\n";
    echo "  Lat: $testLat2, Lng: $testLng2\n\n";
    
} else {
    echo "✗ Office location is NOT configured\n\n";
    echo "Please configure the office location first:\n";
    echo "1. Go to HR Management > Settings tab\n";
    echo "2. Click on the map or drag the marker\n";
    echo "3. Set the radius\n";
    echo "4. Click Save\n\n";
}

DB::statement('SET search_path TO public');
