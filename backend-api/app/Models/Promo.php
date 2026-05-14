<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Promo extends Model
{
    use SoftDeletes;

    protected $table = 'promos';

    protected $fillable = [
        'kode',
        'nama',
        'deskripsi',
        'tipe',
        'nilai',
        'minimum_pembelian',
        'maksimum_diskon',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_mulai',
        'jam_selesai',
        'hari_aktif',
        'kuota_penggunaan',
        'jumlah_terpakai',
        'is_active',
        'is_stackable',
        'is_member_only',
    ];

    protected $casts = [
        'nilai' => 'decimal:2',
        'minimum_pembelian' => 'decimal:2',
        'maksimum_diskon' => 'decimal:2',
        'kuota_penggunaan' => 'integer',
        'jumlah_terpakai' => 'integer',
        'is_active' => 'boolean',
        'is_stackable' => 'boolean',
        'is_member_only' => 'boolean',
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    protected $appends = ['is_available', 'sisa_kuota'];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        $now = Carbon::now();
        
        return $query->where('is_active', true)
            ->where('tanggal_mulai', '<=', $now->toDateString())
            ->where('tanggal_selesai', '>=', $now->toDateString())
            ->where(function($q) {
                $q->whereNull('kuota_penggunaan')
                  ->orWhereRaw('jumlah_terpakai < kuota_penggunaan');
            });
    }

    /**
     * Accessors
     */
    public function getIsAvailableAttribute()
    {
        return $this->checkAvailability();
    }

    public function getSisaKuotaAttribute()
    {
        if ($this->kuota_penggunaan === null) {
            return null; // Unlimited
        }
        return max(0, $this->kuota_penggunaan - $this->jumlah_terpakai);
    }

    /**
     * Check if promo is available now
     */
    public function checkAvailability($dateTime = null)
    {
        if (!$this->is_active) {
            return false;
        }

        $now = $dateTime ? Carbon::parse($dateTime) : Carbon::now();

        // Check date range
        if ($now->lt($this->tanggal_mulai) || $now->gt($this->tanggal_selesai)) {
            return false;
        }

        // Check time range (if both are set)
        if ($this->jam_mulai && $this->jam_selesai) {
            $currentTime = $now->format('H:i:s');
            $jamMulai = $this->jam_mulai;
            $jamSelesai = $this->jam_selesai;
            
            // Ensure time format is consistent (HH:MM:SS)
            if (strlen($jamMulai) === 5) {
                $jamMulai .= ':00';
            }
            if (strlen($jamSelesai) === 5) {
                $jamSelesai .= ':00';
            }
            
            // Check if current time is within range
            if ($currentTime < $jamMulai || $currentTime > $jamSelesai) {
                return false;
            }
        }

        // Check day of week
        if ($this->hari_aktif) {
            $dayNames = [
                0 => 'minggu',
                1 => 'senin',
                2 => 'selasa',
                3 => 'rabu',
                4 => 'kamis',
                5 => 'jumat',
                6 => 'sabtu',
            ];
            
            $currentDay = $dayNames[$now->dayOfWeek];
            $activeDays = array_map('trim', explode(',', strtolower($this->hari_aktif)));
            
            if (!in_array($currentDay, $activeDays)) {
                return false;
            }
        }

        // Check quota
        if ($this->kuota_penggunaan !== null && $this->jumlah_terpakai >= $this->kuota_penggunaan) {
            return false;
        }

        return true;
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount($subtotal)
    {
        // Check minimum purchase
        if ($subtotal < $this->minimum_pembelian) {
            return 0;
        }

        $discount = 0;

        if ($this->tipe === 'percentage') {
            $discount = $subtotal * ($this->nilai / 100);
            
            // Apply maximum discount if set
            if ($this->maksimum_diskon && $discount > $this->maksimum_diskon) {
                $discount = $this->maksimum_diskon;
            }
        } elseif ($this->tipe === 'nominal') {
            $discount = $this->nilai;
        }

        // Discount cannot exceed subtotal
        return min($discount, $subtotal);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('jumlah_terpakai');
    }

    /**
     * Generate unique promo code
     */
    public static function generateKode()
    {
        $prefix = 'PROMO';
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
}
