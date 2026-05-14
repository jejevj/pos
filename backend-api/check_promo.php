<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Use Outlet Baru schema
$schemaName = 'user_1_outlet_baru';
echo "=== USING SCHEMA: {$schemaName} ===\n\n";

// Get promo
$promos = DB::select("SELECT * FROM {$schemaName}.promos WHERE kode LIKE '%HAPPY%' LIMIT 1");

if (empty($promos)) {
    echo "Promo HAPPY-HOUR tidak ditemukan\n";
    
    // List all promos
    echo "\nDaftar semua promo:\n";
    $allPromos = DB::select("SELECT id, kode, nama FROM {$schemaName}.promos");
    foreach ($allPromos as $p) {
        echo "- {$p->kode}: {$p->nama}\n";
    }
    exit;
}

$promo = $promos[0];

echo "=== DATA PROMO ===\n";
echo "Kode: {$promo->kode}\n";
echo "Nama: {$promo->nama}\n";
echo "Is Active: " . ($promo->is_active ? 'true' : 'false') . "\n";
echo "Tanggal Mulai: {$promo->tanggal_mulai}\n";
echo "Tanggal Selesai: {$promo->tanggal_selesai}\n";
echo "Jam Mulai: {$promo->jam_mulai}\n";
echo "Jam Selesai: {$promo->jam_selesai}\n";
echo "Hari Aktif: {$promo->hari_aktif}\n";
echo "Minimum Pembelian: {$promo->minimum_pembelian}\n";
echo "Kuota: {$promo->kuota_penggunaan}\n";
echo "Terpakai: {$promo->jumlah_terpakai}\n";
echo "Is Stackable: " . ($promo->is_stackable ? 'true' : 'false') . "\n";
echo "Is Member Only: " . ($promo->is_member_only ? 'true' : 'false') . "\n";

echo "\n=== CURRENT TIME ===\n";
$now = Carbon::now();
echo "Date: " . $now->format('Y-m-d') . "\n";
echo "Time: " . $now->format('H:i:s') . "\n";
echo "Day of Week: " . $now->dayOfWeek . " (" . ['minggu','senin','selasa','rabu','kamis','jumat','sabtu'][$now->dayOfWeek] . ")\n";

echo "\n=== AVAILABILITY CHECK ===\n";

// Check is_active
if (!$promo->is_active) {
    echo "❌ FAILED: Promo tidak aktif\n";
} else {
    echo "✅ PASSED: Promo aktif\n";
}

// Check date range
$tanggalMulai = Carbon::parse($promo->tanggal_mulai);
$tanggalSelesai = Carbon::parse($promo->tanggal_selesai);
if ($now->lt($tanggalMulai) || $now->gt($tanggalSelesai)) {
    echo "❌ FAILED: Tanggal tidak dalam range ({$promo->tanggal_mulai} - {$promo->tanggal_selesai})\n";
} else {
    echo "✅ PASSED: Tanggal dalam range\n";
}

// Check time range
if ($promo->jam_mulai && $promo->jam_selesai) {
    $currentTime = $now->format('H:i:s');
    $jamMulai = $promo->jam_mulai;
    $jamSelesai = $promo->jam_selesai;
    
    // Normalize format
    if (strlen($jamMulai) === 5) {
        $jamMulai .= ':00';
    }
    if (strlen($jamSelesai) === 5) {
        $jamSelesai .= ':00';
    }
    
    echo "Current Time: {$currentTime}\n";
    echo "Jam Range: {$jamMulai} - {$jamSelesai}\n";
    
    if ($currentTime < $jamMulai || $currentTime > $jamSelesai) {
        echo "❌ FAILED: Waktu tidak dalam range\n";
    } else {
        echo "✅ PASSED: Waktu dalam range\n";
    }
} else {
    echo "✅ PASSED: Tidak ada batasan waktu (sepanjang hari)\n";
}

// Check day of week
if ($promo->hari_aktif) {
    $dayNames = [
        0 => 'minggu',
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
    ];
    
    $currentDay = $dayNames[$now->dayOfWeek];
    $activeDays = array_map('trim', explode(',', strtolower($promo->hari_aktif)));
    
    echo "Current Day: {$currentDay}\n";
    echo "Active Days: " . implode(', ', $activeDays) . "\n";
    
    if (!in_array($currentDay, $activeDays)) {
        echo "❌ FAILED: Hari ini tidak termasuk hari aktif\n";
    } else {
        echo "✅ PASSED: Hari ini termasuk hari aktif\n";
    }
} else {
    echo "✅ PASSED: Tidak ada batasan hari (semua hari)\n";
}

// Check quota
if ($promo->kuota_penggunaan !== null) {
    if ($promo->jumlah_terpakai >= $promo->kuota_penggunaan) {
        echo "❌ FAILED: Kuota habis ({$promo->jumlah_terpakai}/{$promo->kuota_penggunaan})\n";
    } else {
        echo "✅ PASSED: Kuota tersedia ({$promo->jumlah_terpakai}/{$promo->kuota_penggunaan})\n";
    }
} else {
    echo "✅ PASSED: Tidak ada batasan kuota (unlimited)\n";
}

echo "\n=== FINAL RESULT ===\n";
// Load model to check availability with proper schema context
DB::statement("SET search_path TO {$schemaName}, public");
$promoModel = App\Models\Promo::find($promo->id);
DB::statement("SET search_path TO public");

if ($promoModel && $promoModel->checkAvailability()) {
    echo "✅ PROMO AVAILABLE\n";
} else {
    echo "❌ PROMO NOT AVAILABLE\n";
}
