<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::update("UPDATE user_1_outlet_baru.promos SET is_stackable = true WHERE kode = 'HAPPY-HOUR'");
echo "✅ HAPPY-HOUR updated to stackable\n";

// Verify
$promo = DB::selectOne("SELECT kode, nama, is_stackable FROM user_1_outlet_baru.promos WHERE kode = 'HAPPY-HOUR'");
echo "Verification: {$promo->kode} is_stackable = " . ($promo->is_stackable ? 'true' : 'false') . "\n";
