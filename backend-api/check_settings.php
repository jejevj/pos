<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

DB::statement('SET search_path TO user_1_outlet_baru, public');
$settings = DB::table('payroll_settings')->first();

if ($settings) {
    echo "Attendance Location Settings:\n";
    echo "Latitude: " . ($settings->attendance_location_lat ?? 'NULL') . "\n";
    echo "Longitude: " . ($settings->attendance_location_lng ?? 'NULL') . "\n";
    echo "Radius: " . ($settings->attendance_radius ?? 'NULL') . " meters\n";
} else {
    echo "No payroll settings found!\n";
}

DB::statement('SET search_path TO public');
