<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Satuan extends Model
{
    use SoftDeletes;

    protected $table = 'satuan';

    protected $fillable = [
        'nama',
        'singkatan',
        'tipe',
        'is_base_unit',
        'conversion_to_base',
        'deskripsi',
        'is_active',
    ];

    protected $casts = [
        'is_base_unit' => 'boolean',
        'is_active' => 'boolean',
        'conversion_to_base' => 'decimal:4',
    ];

    /**
     * Get all bahan baku using this unit
     */
    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'satuan_id');
    }

    /**
     * Scope for active units
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for base units only
     */
    public function scopeBaseUnits($query)
    {
        return $query->where('is_base_unit', true);
    }

    /**
     * Scope by type (berat, volume, jumlah)
     */
    public function scopeByType($query, $type)
    {
        return $query->where('tipe', $type);
    }

    /**
     * Convert quantity to base unit
     */
    public function toBaseUnit($quantity)
    {
        if ($this->is_base_unit) {
            return $quantity;
        }
        
        return $quantity * $this->conversion_to_base;
    }

    /**
     * Convert quantity from base unit to this unit
     */
    public function fromBaseUnit($quantity)
    {
        if ($this->is_base_unit) {
            return $quantity;
        }
        
        return $quantity / $this->conversion_to_base;
    }
}
