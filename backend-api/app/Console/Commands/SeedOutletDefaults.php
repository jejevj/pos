<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\OutletDefaultDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedOutletDefaults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *   php artisan outlet:seed-defaults          → seed semua outlet
     *   php artisan outlet:seed-defaults 3        → seed outlet dengan id=3
     *   php artisan outlet:seed-defaults --force  → paksa seed meskipun data sudah ada
     */
    protected $signature = 'outlet:seed-defaults
                            {outlet_id? : ID outlet yang ingin di-seed (kosong = semua outlet)}
                            {--force : Paksa seed ulang meskipun data sudah ada (hapus cek idempotency)}';

    protected $description = 'Seed default master data (satuan, kategori, bahan baku, menu) ke outlet yang sudah ada';

    public function handle(): int
    {
        $outletId = $this->argument('outlet_id');
        $force    = $this->option('force');

        $outlets = $outletId
            ? Outlet::where('id', $outletId)->get()
            : Outlet::all();

        if ($outlets->isEmpty()) {
            $this->error($outletId
                ? "Outlet dengan ID {$outletId} tidak ditemukan."
                : 'Tidak ada outlet di database.'
            );
            return Command::FAILURE;
        }

        $seeder = new OutletDefaultDataSeeder();

        $success = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($outlets as $outlet) {
            $this->line("─────────────────────────────────────────");
            $this->info("Outlet: [{$outlet->id}] {$outlet->name} — schema: {$outlet->schema_name}");

            // Cek apakah sudah ada data satuan (indikator sudah di-seed)
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                $satuanCount = DB::table('satuan')->count();
                DB::statement('SET search_path TO public');
            } catch (\Throwable $e) {
                $this->warn("  ⚠ Gagal cek schema ({$outlet->schema_name}): " . $e->getMessage());
                $failed++;
                continue;
            }

            if ($satuanCount > 0 && !$force) {
                $this->warn("  ⟳ Sudah ada {$satuanCount} satuan — dilewati (gunakan --force untuk paksa seed ulang)");
                $skipped++;
                continue;
            }

            if ($satuanCount > 0 && $force) {
                $this->warn("  ⚠ --force aktif: data lama TIDAK dihapus, seeder akan skip karena idempotency guard");
                $this->warn("  ℹ Untuk benar-benar reset, hapus data manual dulu di schema {$outlet->schema_name}");
                $skipped++;
                continue;
            }

            // Jalankan seeder
            try {
                $this->line("  → Menjalankan seeder...");
                $seeder->seed($outlet->schema_name);
                $this->info("  ✓ Berhasil di-seed");
                $success++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Gagal: " . $e->getMessage());
                $failed++;
            }
        }

        $this->line("─────────────────────────────────────────");
        $this->info("Selesai. Berhasil: {$success} | Dilewati: {$skipped} | Gagal: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
