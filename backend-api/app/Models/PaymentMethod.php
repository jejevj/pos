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
        'is_online_orderable', // available for public table/takeaway online orders
        'qr_image_path',  // stored QR image path (e.g. for QRIS) shown on public checkout
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'display_order'        => 'integer',
        'defers_stock'         => 'boolean',
        'requires_settlement'  => 'boolean',
        'is_online_orderable'  => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    public function scopeOnlineOrderable($query)
    {
        return $query->where('is_online_orderable', true);
    }
}
