<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class Member extends Model
{
    use SoftDeletes;

    protected $table = 'members';

    protected $fillable = [
        'card_number',
        'nama',
        'phone',
        'email',
        'password',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'points',
        'tier',
        'joined_at',
        'last_transaction_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
        'tanggal_lahir' => 'date',
        'joined_at' => 'datetime',
        'last_transaction_at' => 'datetime',
    ];

    /**
     * Automatically hash password when setting
     */
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Hash::make($value);
        }
    }

    /**
     * Relationships
     */
    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Add points to member
     */
    public function addPoints($amount, $description, $orderId = null)
    {
        $balanceBefore = $this->points;
        $this->points += $amount;
        $this->last_transaction_at = now();
        $this->save();

        // Update tier
        $this->updateTier();

        // Create transaction record
        PointTransaction::create([
            'member_id' => $this->id,
            'type' => 'earn',
            'amount' => $amount,
            'description' => $description,
            'order_id' => $orderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->points,
        ]);

        return $this;
    }

    /**
     * Redeem points from member
     */
    public function redeemPoints($amount, $description, $orderId = null)
    {
        if ($this->points < $amount) {
            throw new \Exception('Insufficient points');
        }

        $balanceBefore = $this->points;
        $this->points -= $amount;
        $this->last_transaction_at = now();
        $this->save();

        // Update tier
        $this->updateTier();

        // Create transaction record
        PointTransaction::create([
            'member_id' => $this->id,
            'type' => 'redeem',
            'amount' => $amount,
            'description' => $description,
            'order_id' => $orderId,
            'balance_before' => $balanceBefore,
            'balance_after' => $this->points,
        ]);

        return $this;
    }

    /**
     * Update member tier based on points
     */
    public function updateTier()
    {
        $settings = MembershipSetting::first();
        if (!$settings || !$settings->tiers) {
            return;
        }

        $tiers = $settings->tiers;
        $currentTier = 'Silver';

        foreach ($tiers as $tier) {
            if ($this->points >= $tier['min_points']) {
                $currentTier = $tier['name'];
            }
        }

        if ($this->tier !== $currentTier) {
            $this->tier = $currentTier;
            $this->save();
        }
    }

    /**
     * Get tier discount percentage
     */
    public function getTierDiscount()
    {
        $settings = MembershipSetting::first();
        if (!$settings || !$settings->tiers) {
            return 0;
        }

        $tier = collect($settings->tiers)->firstWhere('name', $this->tier);
        return $tier['discount_percentage'] ?? 0;
    }

    /**
     * Generate unique card number
     */
    public static function generateCardNumber()
    {
        $prefix = 'MBR';
        
        $last = static::orderBy('card_number', 'desc')->first();
        
        if ($last && preg_match('/MBR(\d+)/', $last->card_number, $matches)) {
            $lastNumber = (int) $matches[1];
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}
