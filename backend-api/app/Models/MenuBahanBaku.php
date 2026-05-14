<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuBahanBaku extends Model
{
    protected $table = 'menu_bahan_baku';

    protected $fillable = ['menu_id', 'bahan_baku_id', 'satuan_id', 'jumlah', 'keterangan'];

    protected $casts = ['jumlah' => 'decimal:4'];

    public function menu()
    {
        return $this->belongsTo(MenuOutlet::class, 'menu_id');
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'satuan_id');
    }
}
