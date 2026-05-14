<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET search_path TO user_1_outlet_baru, public');

$settings = DB::table('payroll_settings')->first();

echo "Current Attendance Location Settings:\n";
echo "=====================================\n";
echo "Latitude: " . ($settings->attendance_location_lat ?? 'NULL') . "\n";
echo "Longitude: " . ($settings->attendance_location_lng ?? 'NULL') . "\n";
echo "Radius: " . ($settings->attendance_radius ?? 'NULL') . " meters\n\n";

if ($settings->attendance_location_lat && $settings->attendance_location_lng) {
    echo "✓ Location is configured\n";
    echo "Office coordinates: {$settings->attendance_location_lat}, {$settings->attendance_location_lng}\n";
    echo "Employees must be within {$settings->attendance_radius}m to clock in/out\n";
} else {
    echo "✗ Location is NOT configured\n";
    echo "Please set the office location in HR Settings > Attendance Location\n";
}

DB::statement('SET search_path TO public');
