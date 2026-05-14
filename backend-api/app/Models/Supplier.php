<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = 'supplier';

    protected $fillable = [
        'kode',
        'nama',
        'contact_person',
        'phone',
        'email',
        'alamat',
        'kota',
        'provinsi',
        'kode_pos',
        'payment_terms',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all bahan baku from this supplier
     */
    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'supplier_id');
    }

    /**
     * Scope for active suppliers
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Generate unique supplier code
     */
    public static function generateKode($outletId)
    {
        $prefix = 'SUP';
        $date = date('Ymd');
        
        // Get last supplier code for today
        $lastSupplier = static::where('kode', 'like', "{$prefix}{$date}%")
            ->orderBy('kode', 'desc')
            ->first();
        
        if ($lastSupplier) {
            $lastNumber = (int) substr($lastSupplier->kode, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
