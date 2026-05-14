<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuOutlet extends Model
{
    use SoftDeletes;

    protected $table = 'menu';

    protected $fillable = [
        'kode', 'nama', 'kategori_id', 'station_id', 'deskripsi',
        'harga_jual', 'harga_modal', 'apply_fixed_cost', 'gambar_url',
        'is_available', 'is_active',
    ];

    protected $casts = [
        'harga_jual'  => 'decimal:2',
        'harga_modal' => 'decimal:2',
        'apply_fixed_cost' => 'boolean',
        'is_available' => 'boolean',
        'is_active'   => 'boolean',
    ];

    protected $appends = ['available_quantity', 'can_be_made', 'image_url'];

    public function kategori()
    {
        return $this->belongsTo(KategoriMenu::class, 'kategori_id');
    }

    public function station()
    {
        return $this->belongsTo(Station::class, 'station_id');
    }

    public function bahanBaku()
    {
        return $this->hasMany(MenuBahanBaku::class, 'menu_id');
    }

    public function bahanBakuItems()
    {
        return $this->hasManyThrough(BahanBaku::class, MenuBahanBaku::class, 'menu_id', 'id', 'id', 'bahan_baku_id');
    }

    /**
     * Calculate how many portions of this menu can be made based on available ingredients
     */
    public function getAvailableQuantityAttribute()
    {
        if (!$this->relationLoaded('bahanBaku')) {
            return null;
        }

        $minQuantity = PHP_INT_MAX;

        foreach ($this->bahanBaku as $ingredient) {
            if (!$ingredient->bahanBaku) {
                continue;
            }

            $required = $ingredient->jumlah;
            $available = $ingredient->bahanBaku->current_stock;

            if ($required <= 0) {
                continue;
            }

            $canMake = floor($available / $required);
            $minQuantity = min($minQuantity, $canMake);
        }

        return $minQuantity === PHP_INT_MAX ? 0 : $minQuantity;
    }

    /**
     * Check if this menu can be made at least once
     */
    public function getCanBeMadeAttribute()
    {
        return $this->available_quantity > 0;
    }

    /**
     * Get full URL for image
     */
    public function getImageUrlAttribute()
    {
        if (!$this->gambar_url) {
            return null;
        }

        // If already a full URL, return as is
        if (filter_var($this->gambar_url, FILTER_VALIDATE_URL)) {
            return $this->gambar_url;
        }

        // If starts with /storage, convert to full URL
        if (str_starts_with($this->gambar_url, '/storage')) {
            return url($this->gambar_url);
        }

        // Otherwise return as is
        return $this->gambar_url;
    }

    public static function generateKode($kategoriId = null)
    {
        $prefix = 'MN';
        if ($kategoriId) {
            $kategori = KategoriMenu::find($kategoriId);
            if ($kategori) {
                $prefix = strtoupper(substr($kategori->nama, 0, 2));
            }
        }
        $date = date('Ymd');
        $last = static::where('kode', 'like', "{$prefix}{$date}%")->orderBy('kode', 'desc')->first();
        $num = $last ? ((int) substr($last->kode, -4)) + 1 : 1;
        return $prefix . $date . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}
