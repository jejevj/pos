<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipSetting extends Model
{
    protected $table = 'membership_settings';

    protected $fillable = [
        'point_conversion_rate',
        'point_per_rupiah',
        'point_expiry_days',
        'min_transaction_for_points',
        'tiers',
    ];

    protected $casts = [
        'point_conversion_rate' => 'integer',
        'point_per_rupiah' => 'decimal:2',
        'point_expiry_days' => 'integer',
        'min_transaction_for_points' => 'decimal:2',
        'tiers' => 'array',
    ];

    /**
     * Calculate points earned from transaction amount
     */
    public function calculatePoints($amount)
    {
        if ($amount < $this->min_transaction_for_points) {
            return 0;
        }

        // Points = (Amount / Conversion Rate) * Point Per Rupiah
        // Example: Rp 50,000 / 1000 * 1 = 50 points
        return floor(($amount / $this->point_conversion_rate) * $this->point_per_rupiah);
    }

    /**
     * Calculate rupiah value from points
     */
    public function calculateRupiah($points)
    {
        // Rupiah = (Points / Point Per Rupiah) * Conversion Rate
        // Example: 50 points / 1 * 1000 = Rp 50,000
        return floor(($points / $this->point_per_rupiah) * $this->point_conversion_rate);
    }
}
