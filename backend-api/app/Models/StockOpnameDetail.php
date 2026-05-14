<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameDetail extends Model
{
    protected $table = 'stock_opname_detail';

    protected $fillable = [
        'stock_opname_id',
        'bahan_baku_id',
        'system_stock',
        'physical_stock',
        'difference',
        'difference_value',
        'notes',
    ];

    protected $casts = [
        'system_stock' => 'decimal:2',
        'physical_stock' => 'decimal:2',
        'difference' => 'decimal:2',
        'difference_value' => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function stockOpname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function bahanBaku()
    {
        return $this->belongsTo(BahanBaku::class, 'bahan_baku_id');
    }

    /**
     * Calculate difference and value
     */
    public function calculateDifference()
    {
        if ($this->physical_stock !== null) {
            // Calculate difference
            $this->difference = $this->physical_stock - $this->system_stock;
            
            // Calculate value based on harga_per_satuan_dasar
            if ($this->bahanBaku) {
                // Load the relationship if not loaded
                if (!$this->relationLoaded('bahanBaku')) {
                    $this->load('bahanBaku');
                }
                
                $pricePerUnit = $this->bahanBaku->harga_per_satuan_dasar ?? $this->bahanBaku->harga_beli ?? 0;
                $this->difference_value = $this->difference * $pricePerUnit;
            } else {
                $this->difference_value = 0;
            }
            
            $this->save();
        }
    }
}
