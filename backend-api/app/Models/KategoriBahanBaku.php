<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KategoriBahanBaku extends Model
{
    use SoftDeletes;

    // This model works with outlet schemas
    protected $table = 'kategori_bahan_baku';

    protected $fillable = [
        'nama',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all bahan baku in this category
     */
    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'kategori_id');
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
