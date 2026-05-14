<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes;

    protected $table = 'payment_methods';

    protected $fillable = [
        'name',
        'code',
        'icon',
        'is_active',
        'display_order',
        'defers_stock',   // if true, stock is NOT reduced until bon is settled
        'requires_settlement', // alias for defers_stock — same concept
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'display_order'        => 'integer',
        'defers_stock'         => 'boolean',
        'requires_settlement'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }
}
