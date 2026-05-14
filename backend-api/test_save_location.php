<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET search_path TO user_1_outlet_baru, public');

// Save test location (Jakarta coordinates)
$lat = -6.2088;
$lng = 106.8456;
$radius = 100;

DB::table('payroll_settings')->update([
    'attendance_location_lat' => $lat,
    'attendance_location_lng' => $lng,
    'attendance_radius' => $radius,
    'updated_at' => now()
]);

echo "Location saved successfully!\n";
echo "Latitude: $lat\n";
echo "Longitude: $lng\n";
echo "Radius: $radius meters\n\n";

// Verify
$settings = DB::table('payroll_settings')->first();
echo "Verified from database:\n";
echo "Latitude: " . ($settings->attendance_location_lat ?? 'NULL') . "\n";
echo "Longitude: " . ($settings->attendance_location_lng ?? 'NULL') . "\n";
echo "Radius: " . ($settings->attendance_radius ?? 'NULL') . " meters\n";

DB::statement('SET search_path TO public');
