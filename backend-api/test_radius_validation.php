<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET search_path TO user_1_outlet_baru, public');

$settings = DB::table('payroll_settings')->first();

echo "Office Location:\n";
echo "Latitude: " . $settings->attendance_location_lat . "\n";
echo "Longitude: " . $settings->attendance_location_lng . "\n";
echo "Allowed Radius: " . $settings->attendance_radius . " meters\n\n";

// Test locations
$testLocations = [
    ['name' => 'Same location', 'lat' => -6.2088, 'lng' => 106.8456],
    ['name' => '50m away', 'lat' => -6.2083, 'lng' => 106.8456],
    ['name' => '150m away', 'lat' => -6.2075, 'lng' => 106.8456],
    ['name' => '500m away', 'lat' => -6.2043, 'lng' => 106.8456],
];

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // meters
    
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);
    
    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLon / 2) * sin($deltaLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

foreach ($testLocations as $test) {
    $distance = calculateDistance(
        $test['lat'],
        $test['lng'],
        $settings->attendance_location_lat,
        $settings->attendance_location_lng
    );
    
    $allowed = $distance <= $settings->attendance_radius;
    $status = $allowed ? '✓ ALLOWED' : '✗ BLOCKED';
    
    echo "{$test['name']}: {$status}\n";
    echo "  Distance: " . round($distance) . "m\n";
    echo "  Coordinates: {$test['lat']}, {$test['lng']}\n\n";
}

DB::statement('SET search_path TO public');
