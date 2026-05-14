<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use SoftDeletes;

    protected $table = 'stock_opname';

    protected $fillable = [
        'kode',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'pic_name',
        'pic_user_id',
        'notes',
        'total_items',
        'total_difference_value',
        'approved_by',
        'approved_at',
        'approval_notes',
        'created_by',
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'total_difference_value' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'is_editable', 'can_approve'];

    /**
     * Relationships
     */
    public function details()
    {
        return $this->hasMany(StockOpnameDetail::class, 'stock_opname_id');
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft' => 'Draft',
            'in_progress' => 'Sedang Proses',
            'submitted' => 'Menunggu Approval',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getIsEditableAttribute()
    {
        return in_array($this->status, ['draft', 'in_progress', 'rejected']);
    }

    public function getCanApproveAttribute()
    {
        return $this->status === 'submitted';
    }

    /**
     * Methods (not accessors to avoid auto-triggering)
     */
    public function canSubmit()
    {
        if ($this->status !== 'in_progress') {
            return false;
        }
        
        // Use loaded relationship instead of query builder
        if ($this->relationLoaded('details')) {
            return $this->details->whereNotNull('physical_stock')->count() > 0;
        }
        
        // If not loaded, we can't determine, return false to be safe
        return false;
    }

    /**
     * Generate unique stock opname code
     */
    public static function generateKode()
    {
        $prefix = 'SO';
        $date = date('Ymd');
        
        $last = static::where('kode', 'like', "{$prefix}{$date}%")
            ->orderBy('kode', 'desc')
            ->first();
        
        if ($last) {
            $lastNumber = (int) substr($last->kode, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate total difference value
     */
    public function calculateTotalDifferenceValue()
    {
        // Don't use query builder here, calculate from loaded relationship
        $total = 0;
        
        if ($this->relationLoaded('details')) {
            foreach ($this->details as $detail) {
                if ($detail->difference_value !== null) {
                    $total += $detail->difference_value;
                }
            }
        } else {
            // If relationship not loaded, load it first
            $this->load('details');
            foreach ($this->details as $detail) {
                if ($detail->difference_value !== null) {
                    $total += $detail->difference_value;
                }
            }
        }
        
        $this->total_difference_value = $total;
        $this->total_items = $this->details->count();
        $this->save();
        
        return $total;
    }
}
