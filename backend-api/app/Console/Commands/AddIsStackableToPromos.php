<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Outlet;

class AddIsStackableToPromos extends Command
{
    protected $signature = 'promos:add-stackable-column';
    protected $description = 'Add is_stackable column to promos table in all outlet schemas';

    public function handle()
    {
        $outlets = Outlet::all();
        
        if ($outlets->isEmpty()) {
            $this->error('No outlets found.');
            return 1;
        }

        foreach ($outlets as $outlet) {
            $this->info("Updating promos table for outlet: {$outlet->name} (Schema: {$outlet->schema_name})");
            
            try {
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Check if column exists
                $columnExists = DB::select("
                    SELECT EXISTS (
                        SELECT FROM information_schema.columns 
                        WHERE table_schema = current_schema()
                        AND table_name = 'promos'
                        AND column_name = 'is_stackable'
                    )
                ")[0]->exists;
                
                if (!$columnExists) {
                    DB::statement("ALTER TABLE promos ADD COLUMN is_stackable BOOLEAN DEFAULT false");
                    $this->info('  ✓ Added is_stackable column');
                    
                    // Add sample stackable promo
                    $exists = DB::table('promos')->where('kode', 'TUMBLR-3K')->exists();
                    if (!$exists) {
                        DB::table('promos')->insert([
                            'kode' => 'TUMBLR-3K',
                            'nama' => 'Promo Tumblr',
                            'deskripsi' => 'Potongan Rp 3.000 untuk pembelian dengan tumblr sendiri (bisa ditumpuk dengan promo lain)',
                            'tipe' => 'nominal',
                            'nilai' => 3000,
                            'minimum_pembelian' => 0,
                            'maksimum_diskon' => null,
                            'tanggal_mulai' => date('Y-m-d'),
                            'tanggal_selesai' => date('Y-m-d', strtotime('+1 year')),
                            'jam_mulai' => null,
                            'jam_selesai' => null,
                            'hari_aktif' => 'senin,selasa,rabu,kamis,jumat,sabtu,minggu',
                            'kuota_penggunaan' => null,
                            'jumlah_terpakai' => 0,
                            'is_active' => true,
                            'is_stackable' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->info('  ✓ Added sample stackable promo (TUMBLR-3K)');
                    }
                } else {
                    $this->warn('  - is_stackable column already exists');
                }
                
                DB::statement("SET search_path TO public");
                
                $this->info("✓ Successfully updated {$outlet->name}\n");
                
            } catch (\Exception $e) {
                DB::statement("SET search_path TO public");
                $this->error("Failed to update {$outlet->name}: " . $e->getMessage());
                return 1;
            }
        }

        $this->info('All promo tables updated successfully!');
        return 0;
    }
}
