<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $table = 'orders';

    protected $fillable = [
        'kode',
        'order_type',
        'table_id',
        'table_number',
        'customer_name',
        'customer_phone',
        'member_id',
        'points_earned',
        'points_redeemed',
        'status',
        'payment_status',  // 'paid' | 'bon' (deferred)
        'subtotal',
        'promo_id',
        'promo_code',
        'applied_promos',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_percentage',
        'tax_amount',
        'service_charge_percentage',
        'service_charge_amount',
        'total_amount',
        'payment_method_id',
        'paid_amount',
        'change_amount',
        'notes',
        'cashier_id',
        'paid_at',
        'settled_at',      // when bon is settled
        'settled_by',      // user who settled the bon
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        // Public table-order flow
        'source',              // 'pos' | 'public'
        'approval_status',     // null (POS), 'pending' | 'approved' | 'rejected'
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'customer_email',
        'payment_proof_path',
        'payment_proof_uploaded_at',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'points_earned' => 'integer',
        'points_redeemed' => 'integer',
        'subtotal' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'service_charge_percentage' => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'applied_promos' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'order_type_label'];

    /**
     * Relationships
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function table()
    {
        return $this->belongsTo(Table::class, 'table_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Scopes
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByOrderType($query, $type)
    {
        return $query->where('order_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'draft'     => 'Draft',
            'paid'      => 'Lunas',
            'bon'       => 'Bon',
            'cancelled' => 'Dibatalkan',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function getOrderTypeLabelAttribute()
    {
        $labels = [
            'dine_in' => 'Dine In',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
        ];

        return $labels[$this->order_type] ?? $this->order_type;
    }

    /**
     * Generate unique order code
     */
    public static function generateKode()
    {
        $prefix = 'ORD';
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
     * Calculate totals
     */
    public function calculateTotals()
    {
        // Calculate subtotal from items (must be loaded before calling this)
        if ($this->relationLoaded('items')) {
            $this->subtotal = $this->items->sum('subtotal');
        }

        // If applied_promos is set (multi-promo), discount_amount is already correct — don't recalculate.
        // Only recalculate discount from discount_type/value when there are no applied_promos.
        if (empty($this->applied_promos)) {
            if ($this->discount_type === 'percentage') {
                $this->discount_amount = $this->subtotal * ($this->discount_value / 100);
            } elseif ($this->discount_type === 'nominal') {
                $this->discount_amount = $this->discount_value;
            } else {
                $this->discount_amount = $this->discount_amount ?? 0;
            }
        }
        // else: discount_amount was already set by the promo application logic — keep it as-is

        $subtotalAfterDiscount = max(0, $this->subtotal - ($this->discount_amount ?? 0));

        // Calculate tax
        $this->tax_amount = $subtotalAfterDiscount * ($this->tax_percentage / 100);

        // Calculate service charge
        if ($this->service_charge_percentage) {
            $this->service_charge_amount = $subtotalAfterDiscount * ($this->service_charge_percentage / 100);
        } else {
            $this->service_charge_amount = 0;
        }

        // Calculate total
        $this->total_amount = $subtotalAfterDiscount + $this->tax_amount + $this->service_charge_amount;

        $this->save();
    }

    /**
     * Process payment
     */
    public function processPayment($paymentMethodId, $paidAmount)
    {
        $this->payment_method_id = $paymentMethodId;
        $this->paid_amount       = $paidAmount;
        $this->change_amount     = $paidAmount - $this->total_amount;

        // Check if payment method defers stock (e.g. Bon)
        $paymentMethod = PaymentMethod::find($paymentMethodId);
        $defersStock   = $paymentMethod && $paymentMethod->defers_stock;

        if ($defersStock) {
            $this->status         = 'bon';
            $this->payment_status = 'bon';
        } else {
            $this->status         = 'paid';
            $this->payment_status = 'paid';
        }

        $this->paid_at = now();
        $this->save();

        // Update table status if dine-in (even for bon — table is freed)
        if ($this->order_type === 'dine_in' && $this->table_id) {
            $table = Table::find($this->table_id);
            if ($table) {
                $table->markAsAvailable();
            }
        }

        // Only reduce stock immediately if payment method does NOT defer stock
        if (!$defersStock && $this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $this->reduceStockForMenuItem($item, false);
            }
        } elseif ($defersStock && $this->relationLoaded('items')) {
            // For bon: reduce stock for ingredients that do NOT defer on bon
            foreach ($this->items as $item) {
                $this->reduceStockForMenuItem($item, true); // skipDeferredItems = true
            }
        }
    }

    /**
     * Settle a bon order — reduce stock and mark as paid
     */
    public function settleBon($settledBy)
    {
        $this->status         = 'paid';
        $this->payment_status = 'paid';
        $this->settled_at     = now();
        $this->settled_by     = $settledBy;
        $this->save();

        // Now reduce ONLY the deferred ingredients (those with defers_on_bon = true)
        if ($this->relationLoaded('items')) {
            foreach ($this->items as $item) {
                $this->reduceStockForDeferredIngredients($item);
            }
        }
    }

    /**
     * Reduce ONLY deferred ingredients (defers_on_bon = true) — called on bon settlement
     */
    private function reduceStockForDeferredIngredients($orderItem)
    {
        if (!$orderItem->relationLoaded('menu')) return;
        $menu = $orderItem->menu;
        if (!$menu || !$menu->relationLoaded('bahanBaku')) return;

        foreach ($menu->bahanBaku as $ingredient) {
            if (!$ingredient->pivot) continue;
            if (!$ingredient->defers_on_bon) continue; // only deferred ones

            $requiredQty = $ingredient->pivot->jumlah * $orderItem->quantity;
            $bahanBaku   = BahanBaku::find($ingredient->id);
            if ($bahanBaku) {
                $bahanBaku->reduceStock(
                    $requiredQty,
                    "Bon settled: Order {$this->kode} - {$menu->nama}",
                    'order',
                    $this->id,
                    $this->cashier_id
                );
            }
        }
    }

    /**
     * Reduce stock for menu item.
     * @param bool $skipDeferredItems  When true (bon payment), skip bahan baku with defers_on_bon=true
     */
    private function reduceStockForMenuItem($orderItem, bool $skipDeferredItems = false)
    {
        if (!$orderItem->relationLoaded('menu')) return;
        $menu = $orderItem->menu;
        if (!$menu || !$menu->relationLoaded('bahanBaku')) return;

        foreach ($menu->bahanBaku as $ingredient) {
            if (!$ingredient->pivot) continue;

            // Skip this ingredient if it defers stock on bon payment
            if ($skipDeferredItems && $ingredient->defers_on_bon) continue;

            $requiredQty = $ingredient->pivot->jumlah * $orderItem->quantity;
            $bahanBaku   = BahanBaku::find($ingredient->id);
            if ($bahanBaku) {
                $bahanBaku->reduceStock(
                    $requiredQty,
                    "Order {$this->kode} - {$menu->nama}",
                    'order',
                    $this->id,
                    $this->cashier_id
                );
            }
        }
    }
}
