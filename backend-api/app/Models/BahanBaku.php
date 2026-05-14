<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BahanBaku extends Model
{
    use SoftDeletes;

    protected $table = 'bahan_baku';

    protected $fillable = [
        'kode',
        'nama',
        'kategori_id',
        'satuan_id',
        'satuan_pembelian_id',
        'jumlah_per_unit_pembelian',
        'supplier_id',
        'harga_beli',
        'minimum_stock',
        'current_stock',
        'lokasi_penyimpanan',
        'expired_date',
        'gambar_url',
        'deskripsi',
        'is_active',
        'defers_on_bon', // if true, stock is NOT reduced when order is paid via bon
    ];

    protected $casts = [
        'is_active'        => 'boolean',
        'defers_on_bon'    => 'boolean',
        'harga_beli'       => 'decimal:2',
        'jumlah_per_unit_pembelian' => 'decimal:4',
        'minimum_stock'    => 'decimal:2',
        'current_stock'    => 'decimal:2',
        'expired_date'     => 'date',
    ];

    protected $appends = ['is_low_stock', 'stock_status', 'harga_per_satuan_dasar'];

    /**
     * Relationships
     */
    public function kategori()
    {
        return $this->belongsTo(KategoriBahanBaku::class, 'kategori_id');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'satuan_id');
    }

    public function satuanPembelian()
    {
        return $this->belongsTo(Satuan::class, 'satuan_pembelian_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function stockHistory()
    {
        return $this->hasMany(StockHistory::class, 'bahan_baku_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock <= minimum_stock');
    }

    public function scopeByKategori($query, $kategoriId)
    {
        return $query->where('kategori_id', $kategoriId);
    }

    /**
     * Accessors
     */
    public function getIsLowStockAttribute()
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    public function getStockStatusAttribute()
    {
        if ($this->current_stock <= 0) {
            return 'out_of_stock';
        } elseif ($this->current_stock <= $this->minimum_stock) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    public function getHargaPerSatuanDasarAttribute()
    {
        // If no unit conversion is set, return the purchase price as is
        if (!$this->satuan_pembelian_id || !$this->jumlah_per_unit_pembelian) {
            return $this->harga_beli;
        }

        // Calculate price per base unit
        // Example: 1 Galon (20L) = Rp 3,000 → Rp 3,000 / 20,000ml = Rp 0.15/ml
        return $this->harga_beli / $this->jumlah_per_unit_pembelian;
    }

    /**
     * Stock Management Methods
     */
    public function addStock($quantity, $notes = null, $referenceType = null, $referenceId = null, $createdBy = null)
    {
        $stockBefore = $this->current_stock;
        $this->current_stock += $quantity;
        $this->save();

        // Record history
        StockHistory::create([
            'bahan_baku_id' => $this->id,
            'tipe' => 'in',
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $this->current_stock,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return $this;
    }

    public function reduceStock($quantity, $notes = null, $referenceType = null, $referenceId = null, $createdBy = null)
    {
        if ($this->current_stock < $quantity) {
            throw new \Exception("Insufficient stock. Available: {$this->current_stock}, Required: {$quantity}");
        }

        $stockBefore = $this->current_stock;
        $this->current_stock -= $quantity;
        $this->save();

        // Record history
        StockHistory::create([
            'bahan_baku_id' => $this->id,
            'tipe' => 'out',
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $this->current_stock,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return $this;
    }

    public function adjustStock($newStock, $notes = null, $createdBy = null)
    {
        $stockBefore = $this->current_stock;
        $difference = $newStock - $stockBefore;
        $this->current_stock = $newStock;
        $this->save();

        // Record history
        StockHistory::create([
            'bahan_baku_id' => $this->id,
            'tipe' => 'adjustment',
            'quantity' => $difference,
            'stock_before' => $stockBefore,
            'stock_after' => $this->current_stock,
            'reference_type' => 'manual_adjustment',
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        return $this;
    }

    /**
     * Generate unique bahan baku code
     */
    public static function generateKode($kategoriId = null)
    {
        $prefix = 'BB';
        
        if ($kategoriId) {
            $kategori = KategoriBahanBaku::find($kategoriId);
            if ($kategori) {
                // Use first 2 letters of category name
                $prefix = strtoupper(substr($kategori->nama, 0, 2));
            }
        }
        
        $date = date('Ymd');
        
        // Get last code for today
        $lastBahan = static::where('kode', 'like', "{$prefix}{$date}%")
            ->orderBy('kode', 'desc')
            ->first();
        
        if ($lastBahan) {
            $lastNumber = (int) substr($lastBahan->kode, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
