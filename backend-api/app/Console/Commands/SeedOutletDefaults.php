<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Services\OutletDefaultDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedOutletDefaults extends Command
{
    /**
     * Seed default master data ke outlet yang sudah ada.
     *
     * Usage:
     *   php artisan outlet:seed-defaults          → seed semua outlet
     *   php artisan outlet:seed-defaults 3        → seed outlet ID=3 saja
     *
     * Idempotent per-item: data yang sudah ada (cek by nama) dilewati,
     * data yang belum ada akan di-insert. Aman dijalankan berulang kali.
     */
    protected $signature = 'outlet:seed-defaults
                            {outlet_id? : ID outlet yang ingin di-seed (kosong = semua outlet)}';

    protected $description = 'Seed default master data (satuan, kategori BB, bahan baku, kategori menu, menu) ke outlet';

    public function handle(): int
    {
        $outletId = $this->argument('outlet_id');

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

        $seeder  = new OutletDefaultDataSeeder();
        $success = 0;
        $failed  = 0;

        foreach ($outlets as $outlet) {
            $this->line('');
            $this->line('─────────────────────────────────────────────────');
            $this->info("Outlet [{$outlet->id}]: {$outlet->name}");
            $this->line("Schema: {$outlet->schema_name}");

            try {
                $seeder->seed($outlet->schema_name);

                $stats = $seeder->stats;

                $this->table(
                    ['Bagian', 'Ditambah', 'Dilewati (sudah ada)'],
                    [
                        ['Satuan',          $stats['satuan']['inserted'],        $stats['satuan']['skipped']],
                        ['Kategori BB',     $stats['kategori_bb']['inserted'],   $stats['kategori_bb']['skipped']],
                        ['Bahan Baku',      $stats['bahan_baku']['inserted'],    $stats['bahan_baku']['skipped']],
                        ['Kategori Menu',   $stats['kategori_menu']['inserted'], $stats['kategori_menu']['skipped']],
                        ['Menu',            $stats['menu']['inserted'],          $stats['menu']['skipped']],
                    ]
                );

                $totalInserted = array_sum(array_column($stats, 'inserted'));
                if ($totalInserted > 0) {
                    $this->info("  ✓ Selesai — {$totalInserted} item baru ditambahkan");
                } else {
                    $this->warn('  ⟳ Semua data sudah ada, tidak ada yang ditambahkan');
                }

                $success++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Gagal: " . $e->getMessage());
                $failed++;
            }
        }

        $this->line('');
        $this->line('─────────────────────────────────────────────────');
        $this->info("Selesai. Outlet berhasil: {$success} | Gagal: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
