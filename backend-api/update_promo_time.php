<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$schemaName = 'user_1_outlet_baru';

echo "Pilih opsi:\n";
echo "1. Set jam aktif ke jam sekarang (untuk testing)\n";
echo "2. Hapus batasan jam (aktif sepanjang hari)\n";
echo "3. Kembalikan ke jam asli (14:00-16:00)\n";
echo "\nPilihan (1/2/3): ";

$handle = fopen("php://stdin", "r");
$choice = trim(fgets($handle));

switch ($choice) {
    case '1':
        // Set to current time + 1 hour window
        $now = new DateTime();
        $jamMulai = $now->format('H:i:s');
        $now->modify('+2 hours');
        $jamSelesai = $now->format('H:i:s');
        
        DB::update("UPDATE {$schemaName}.promos SET jam_mulai = ?, jam_selesai = ? WHERE kode = 'HAPPY-HOUR'", [$jamMulai, $jamSelesai]);
        echo "\n✅ Promo HAPPY-HOUR diupdate:\n";
        echo "   Jam Mulai: {$jamMulai}\n";
        echo "   Jam Selesai: {$jamSelesai}\n";
        break;
        
    case '2':
        DB::update("UPDATE {$schemaName}.promos SET jam_mulai = NULL, jam_selesai = NULL WHERE kode = 'HAPPY-HOUR'");
        echo "\n✅ Promo HAPPY-HOUR sekarang aktif sepanjang hari\n";
        break;
        
    case '3':
        DB::update("UPDATE {$schemaName}.promos SET jam_mulai = '14:00:00', jam_selesai = '16:00:00' WHERE kode = 'HAPPY-HOUR'");
        echo "\n✅ Promo HAPPY-HOUR dikembalikan ke jam asli (14:00-16:00)\n";
        break;
        
    default:
        echo "\n❌ Pilihan tidak valid\n";
        exit(1);
}

echo "\nJalankan 'php check_promo.php' untuk verifikasi\n";
