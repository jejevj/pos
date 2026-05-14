<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$promos = DB::select("SELECT kode, nama, is_stackable FROM user_1_outlet_baru.promos WHERE kode IN ('HAPPY-HOUR', 'TUMBLR-3K')");

foreach ($promos as $promo) {
    echo "{$promo->kode}: is_stackable = " . ($promo->is_stackable ? 'true' : 'false') . "\n";
}
