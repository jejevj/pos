<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuOutlet;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    use AuthorizesOutletAccess;


    /**
     * Get all orders
     */
    public function index(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        // Get timezone from client, fallback to Asia/Jakarta
        $timezone = $request->query('tz', 'Asia/Jakarta');
        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $timezone = 'Asia/Jakarta';
            $tz = new \DateTimeZone($timezone);
        }
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $query = Order::query();
            
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            
            if ($request->has('order_type')) {
                $query->where('order_type', $request->order_type);
            }
            
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }
            
            $orders = $query->orderBy('created_at', 'desc')->get();
            
            // Manually load relationships
            foreach ($orders as $order) {
                $items = DB::table('order_items')->where('order_id', $order->id)->get();
                $order->setRelation('items', $items);
                
                if ($order->table_id) {
                    $order->setRelation('table', Table::find($order->table_id));
                }
                
                if ($order->payment_method_id) {
                    $order->setRelation('paymentMethod', \App\Models\PaymentMethod::find($order->payment_method_id));
                }

                // Load member name for list display
                if ($order->member_id) {
                    $member = \App\Models\Member::find($order->member_id);
                    if ($member) {
                        $order->member = (object) [
                            'id'          => $member->id,
                            'nama'        => $member->nama,
                            'card_number' => $member->card_number,
                            'tier'        => $member->tier,
                            'phone'       => $member->phone,
                            'points'      => $member->points,
                        ];
                    }
                }
                
                // Convert timestamps to client timezone
                if ($order->created_at) {
                    $order->created_at_local = \Carbon\Carbon::parse($order->created_at)->setTimezone($tz)->toIso8601String();
                }
                if ($order->paid_at) {
                    $order->paid_at_local = \Carbon\Carbon::parse($order->paid_at)->setTimezone($tz)->toIso8601String();
                }
                if ($order->cancelled_at) {
                    $order->cancelled_at_local = \Carbon\Carbon::parse($order->cancelled_at)->setTimezone($tz)->toIso8601String();
                }
            }
            
            DB::statement("SET search_path TO public");
            
            return response()->json($orders);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create new order
     */
    public function store(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'order_type' => 'required|in:dine_in,takeaway,delivery',
            'table_id' => 'required_if:order_type,dine_in|nullable|integer',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'member_id' => 'nullable|integer',
            'promo_code' => 'nullable|string',
            'promo_codes' => 'nullable|array',
            'promo_codes.*' => 'string',
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();

            // Create order
            $order = Order::create([
                'kode' => Order::generateKode(),
                'order_type' => $request->order_type,
                'table_id' => $request->table_id,
                'table_number' => $request->table_id ? Table::find($request->table_id)->table_number : null,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'member_id' => $request->member_id,
                'status' => 'draft',
                'tax_percentage' => 11, // Default PPN 11%
                'cashier_id' => Auth::id(),
            ]);

            // Create order items
            $orderItems = [];
            foreach ($request->items as $itemData) {
                $menu = MenuOutlet::findOrFail($itemData['menu_id']);
                
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menu->id,
                    'menu_name' => $menu->nama,
                    'menu_price' => $menu->harga_jual,
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $menu->harga_jual * $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
                
                $orderItems[] = $orderItem;
            }

            // Manually set items relation
            $order->setRelation('items', collect($orderItems));
            
            // Apply promos if provided (support multiple promos)
            $promoCodes = $request->promo_codes ?? ($request->promo_code ? [$request->promo_code] : []);
            
            if (!empty($promoCodes)) {
                $appliedPromos = [];
                $subtotal = $order->items->sum('subtotal');
                $totalDiscount = 0;
                
                foreach ($promoCodes as $promoCode) {
                    $promo = \App\Models\Promo::where('kode', $promoCode)->first();
                    
                    if ($promo && $promo->checkAvailability()) {
                        if ($subtotal >= $promo->minimum_pembelian) {
                            $discount = $promo->calculateDiscount($subtotal);
                            $totalDiscount += $discount;
                            
                            $appliedPromos[] = [
                                'id' => $promo->id,
                                'kode' => $promo->kode,
                                'nama' => $promo->nama,
                                'tipe' => $promo->tipe,
                                'nilai' => $promo->nilai,
                                'discount_amount' => $discount,
                                'is_stackable' => $promo->is_stackable,
                            ];
                            
                            // If not stackable, break after first promo
                            if (!$promo->is_stackable) {
                                break;
                            }
                        }
                    }
                }
                
                if (!empty($appliedPromos)) {
                    $order->applied_promos = $appliedPromos;
                    $order->discount_amount = $totalDiscount;
                    
                    // Set first promo as primary for backward compatibility
                    $order->promo_id = $appliedPromos[0]['id'];
                    $order->promo_code = $appliedPromos[0]['kode'];
                    $order->discount_type = $appliedPromos[0]['tipe'];
                    $order->discount_value = $appliedPromos[0]['nilai'];
                    
                    $order->save();
                }
            }
            
            // Calculate totals
            $order->calculateTotals();

            // Mark table as occupied if dine-in
            if ($request->order_type === 'dine_in' && $request->table_id) {
                $table = Table::find($request->table_id);
                if ($table) {
                    $table->markAsOccupied();
                }
            }

            // Reload items for response
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->setRelation('items', $items);
            
            if ($order->table_id) {
                $table = Table::find($order->table_id);
                $order->setRelation('table', $table);
            }
            
            if ($order->promo_id) {
                $promo = \App\Models\Promo::find($order->promo_id);
                $order->setRelation('promo', $promo);
            }

            DB::commit();
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get order detail
     */
    public function show(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        // Get timezone from client, fallback to Asia/Jakarta
        $timezone = $request->query('tz', 'Asia/Jakarta');
        try {
            $tz = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $timezone = 'Asia/Jakarta';
            $tz = new \DateTimeZone($timezone);
        }
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $order = Order::findOrFail($id);
            
            // Load order items with KDS timing data
            $items = DB::select("
                SELECT oi.*, m.station_id,
                       s.nama as station_name, s.warna as station_color, s.icon as station_icon
                FROM order_items oi
                LEFT JOIN menu m ON m.id = oi.menu_id
                LEFT JOIN stations s ON s.id = m.station_id
                WHERE oi.order_id = ?
                ORDER BY oi.id ASC
            ", [$order->id]);

            // Calculate processing durations per item
            // All KDS timestamps (confirmed_at, preparing_at, ready_at, served_at) are stored in UTC.
            // order->created_at may carry a timezone offset — normalize everything to UTC for correct diffs.
            foreach ($items as $item) {
                $toUtc = fn($ts) => $ts ? \Carbon\Carbon::parse($ts)->utc() : null;

                // prep_duration: preparing_at (or confirmed_at) → ready_at (seconds)
                $item->prep_start = $item->preparing_at ?? $item->confirmed_at ?? null;
                $prepStart = $toUtc($item->prep_start);
                $readyAt   = $toUtc($item->ready_at);
                $item->prep_duration = ($prepStart && $readyAt)
                    ? (int) abs($prepStart->diffInSeconds($readyAt))
                    : null;

                // serve_duration: ready_at → served_at (seconds)
                $servedAt = $toUtc($item->served_at);
                $item->serve_duration = ($readyAt && $servedAt)
                    ? (int) abs($readyAt->diffInSeconds($servedAt))
                    : null;

                // total_duration: prep_start → served_at (seconds)
                $item->total_duration = ($prepStart && $servedAt)
                    ? (int) abs($prepStart->diffInSeconds($servedAt))
                    : null;
            }

            $order->setRelation('items', collect($items)->map(function($item) {
                $orderItem = new OrderItem((array)$item);
                $orderItem->exists = true;
                return $orderItem;
            }));

            // Compute order-level KDS summary
            $itemsWithPrep = collect($items)->filter(fn($i) => $i->prep_duration !== null);
            $order->kds_summary = [
                'avg_prep_seconds'  => $itemsWithPrep->count() > 0
                    ? (int) round($itemsWithPrep->avg('prep_duration')) : null,
                'avg_serve_seconds' => collect($items)->filter(fn($i) => $i->serve_duration !== null)->count() > 0
                    ? (int) round(collect($items)->filter(fn($i) => $i->serve_duration !== null)->avg('serve_duration')) : null,
                'max_prep_seconds'  => $itemsWithPrep->count() > 0
                    ? (int) $itemsWithPrep->max('prep_duration') : null,
                'kitchen_status'    => $order->kitchen_status ?? null,
            ];

            // Calculate end-to-end time (order created → all items served to customer)
            $allItemsServed = collect($items)->filter(fn($i) => $i->served_at !== null);
            if ($allItemsServed->count() > 0) {
                // Find the last served item
                $lastServedAt = $allItemsServed->max('served_at');
                
                // Calculate total time from order creation to last item served
                $orderCreatedUtc   = \Carbon\Carbon::parse($order->created_at)->utc();
                $lastServedAtUtc   = \Carbon\Carbon::parse($lastServedAt)->utc();
                $order->end_to_end_seconds = (int) abs($orderCreatedUtc->diffInSeconds($lastServedAtUtc));
                
                // Calculate detailed breakdown with station awareness
                // Use preparing_at if set, otherwise fall back to confirmed_at
                $firstItemPreparingAt = collect($items)->map(function($i) {
                    $i->prep_start = $i->preparing_at ?? $i->confirmed_at ?? null;
                    return $i;
                })->filter(fn($i) => $i->prep_start !== null)->min('prep_start');
                $firstItemReadyAt = collect($items)->filter(fn($i) => $i->ready_at !== null)->min('ready_at');
                $lastItemReadyAt = collect($items)->filter(fn($i) => $i->ready_at !== null)->max('ready_at');
                
                $breakdown = [];
                
                // Step 1: Order created → First item starts preparing (any station)
                if ($firstItemPreparingAt) {
                    $breakdown[] = [
                        'step' => 'order_to_kitchen',
                        'label' => 'Order → Mulai Proses',
                        'from' => $order->created_at,
                        'to' => $firstItemPreparingAt,
                        'seconds' => (int) abs(\Carbon\Carbon::parse($order->created_at)->utc()->diffInSeconds(\Carbon\Carbon::parse($firstItemPreparingAt)->utc())),
                    ];
                }
                
                // Group items by station for parallel processing analysis
                $itemsByStation = collect($items)->filter(fn($i) => $i->station_id !== null)->groupBy('station_id');
                
                if ($itemsByStation->count() > 1) {
                    // Multiple stations - show parallel processing
                    foreach ($itemsByStation as $stationId => $stationItems) {
                        $stationItems = collect($stationItems);
                        $stationName = $stationItems->first()->station_name ?? "Station {$stationId}";
                        
                        $stationFirstPrep = $stationItems->map(fn($i) => tap($i, fn($x) => $x->prep_start = $x->preparing_at ?? $x->confirmed_at ?? null))->filter(fn($i) => $i->prep_start !== null)->min('prep_start');
                        $stationLastReady = $stationItems->filter(fn($i) => $i->ready_at !== null)->max('ready_at');
                        
                        if ($stationFirstPrep && $stationLastReady) {
                            $breakdown[] = [
                                'step' => 'station_processing',
                                'station_name' => $stationName,
                                'station_color' => $stationItems->first()->station_color,
                                'from' => $stationFirstPrep,
                                'to' => $stationLastReady,
                                'seconds' => (int) abs(\Carbon\Carbon::parse($stationFirstPrep)->utc()->diffInSeconds(\Carbon\Carbon::parse($stationLastReady)->utc())),
                                'items_count' => $stationItems->count(),
                            ];
                        }
                    }
                } else {
                    // Single station or no station - show traditional breakdown
                    // Step 2: First item preparing → First item ready
                    if ($firstItemPreparingAt && $firstItemReadyAt) {
                        $breakdown[] = [
                            'step' => 'first_item_prep',
                            'label' => 'Persiapan Item Pertama',
                            'from' => $firstItemPreparingAt,
                            'to' => $firstItemReadyAt,
                            'seconds' => (int) abs(\Carbon\Carbon::parse($firstItemPreparingAt)->utc()->diffInSeconds(\Carbon\Carbon::parse($firstItemReadyAt)->utc())),
                        ];
                    }
                    
                    // Step 3: First item ready → All items ready (if multiple items)
                    if ($firstItemReadyAt && $lastItemReadyAt && $firstItemReadyAt !== $lastItemReadyAt) {
                        $breakdown[] = [
                            'step' => 'remaining_items_prep',
                            'from' => $firstItemReadyAt,
                            'to' => $lastItemReadyAt,
                            'seconds' => (int) abs(\Carbon\Carbon::parse($firstItemReadyAt)->utc()->diffInSeconds(\Carbon\Carbon::parse($lastItemReadyAt)->utc())),
                        ];
                    }
                }
                
                // Final step: Last item ready → Last item served (always shown)
                if ($lastItemReadyAt && $lastServedAt) {
                    $breakdown[] = [
                        'step' => 'ready_to_served',
                        'from' => $lastItemReadyAt,
                        'to' => $lastServedAt,
                        'seconds' => (int) abs(\Carbon\Carbon::parse($lastItemReadyAt)->utc()->diffInSeconds(\Carbon\Carbon::parse($lastServedAt)->utc())),
                    ];
                }
                
                $order->end_to_end_summary = [
                    'total_seconds' => $order->end_to_end_seconds,
                    'order_created_at' => $order->created_at,
                    'last_item_served_at' => $lastServedAt,
                    'items_served_count' => $allItemsServed->count(),
                    'total_items_count' => count($items),
                    'stations_count' => $itemsByStation->count(),
                    'breakdown' => $breakdown,
                ];
            } else {
                $order->end_to_end_seconds = null;
                $order->end_to_end_summary = null;
            }
            
            // Load table
            if ($order->table_id) {
                $order->setRelation('table', Table::find($order->table_id));
            }
            
            // Load payment method
            if ($order->payment_method_id) {
                $order->setRelation('paymentMethod', \App\Models\PaymentMethod::find($order->payment_method_id));
            }
            
            // Load member with tier info
            if ($order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                $order->setRelation('member', $member);
                
                // Load point transactions for this order
                $pointTransactions = DB::table('point_transactions')
                    ->where('member_id', $order->member_id)
                    ->where('order_id', $order->id)
                    ->get();
                $order->point_transactions = $pointTransactions;
            } else {
                $order->point_transactions = collect();
            }
            
            // Load cashier info from public schema
            DB::statement("SET search_path TO public");
            $cashier = \App\Models\User::find($order->cashier_id);
            $order->cashier = $cashier ? ['id' => $cashier->id, 'name' => $cashier->name] : null;
            
            // Convert timestamps to client timezone
            if ($order->created_at) {
                $order->created_at_local = \Carbon\Carbon::parse($order->created_at)->setTimezone($tz)->toIso8601String();
            }
            if ($order->paid_at) {
                $order->paid_at_local = \Carbon\Carbon::parse($order->paid_at)->setTimezone($tz)->toIso8601String();
            }
            if ($order->cancelled_at) {
                $order->cancelled_at_local = \Carbon\Carbon::parse($order->cancelled_at)->setTimezone($tz)->toIso8601String();
            }
            
            return response()->json($order);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update order (add/remove items)
     */
    public function update(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.menu_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();

            $order = Order::findOrFail($id);
            
            if ($order->status !== 'draft') {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot update paid or cancelled order'], 400);
            }

            // Delete existing items
            DB::table('order_items')->where('order_id', $order->id)->delete();

            // Create new items
            $orderItems = [];
            foreach ($request->items as $itemData) {
                $menu = MenuOutlet::findOrFail($itemData['menu_id']);
                
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $menu->id,
                    'menu_name' => $menu->nama,
                    'menu_price' => $menu->harga_jual,
                    'quantity' => $itemData['quantity'],
                    'subtotal' => $menu->harga_jual * $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
                
                $orderItems[] = $orderItem;
            }

            // Manually set items relation
            $order->setRelation('items', collect($orderItems));
            
            // Recalculate totals
            $order->calculateTotals();

            // Reload for response
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->setRelation('items', $items);
            
            if ($order->table_id) {
                $table = Table::find($order->table_id);
                $order->setRelation('table', $table);
            }

            DB::commit();
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Order updated successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Process payment
     */
    public function payment(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|integer',
            'paid_amount' => 'required|numeric|min:0',
            'redeem_points' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            DB::beginTransaction();

            // Load order
            $order = Order::findOrFail($id);
            
            if ($order->status !== 'draft') {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order already paid or cancelled'], 400);
            }

            // Handle point redemption
            $pointsRedeemed = 0;
            $pointDiscount = 0;
            
            if ($request->redeem_points && $order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                
                if (!$member) {
                    DB::rollBack();
                    DB::statement("SET search_path TO public");
                    return response()->json(['message' => 'Member not found'], 404);
                }
                
                if ($member->points < $request->redeem_points) {
                    DB::rollBack();
                    DB::statement("SET search_path TO public");
                    return response()->json(['message' => 'Insufficient points'], 400);
                }
                
                // Calculate point value
                $settings = \App\Models\MembershipSetting::first();
                if ($settings) {
                    $pointDiscount = $settings->calculateRupiah($request->redeem_points);
                    $pointsRedeemed = $request->redeem_points;
                    
                    // Apply point discount to order
                    $order->discount_amount = ($order->discount_amount ?? 0) + $pointDiscount;
                    $order->points_redeemed = $pointsRedeemed;
                    $order->calculateTotals();
                }
            }

            if ($request->paid_amount < $order->total_amount - 0.01) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Paid amount is less than total amount'], 400);
            }

            // Manually load items to ensure schema context
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->setRelation('items', $items->map(function($item) {
                $orderItem = new OrderItem((array)$item);
                $orderItem->exists = true;
                
                // Load menu with ingredients using query builder to respect schema
                $menu = MenuOutlet::find($item->menu_id);
                if ($menu) {
                    // Load bahanBaku with pivot data
                    $bahanBakuIds = DB::table('menu_bahan_baku')
                        ->where('menu_id', $menu->id)
                        ->pluck('bahan_baku_id');
                    
                    if ($bahanBakuIds->isNotEmpty()) {
                        $bahanBakuItems = \App\Models\BahanBaku::whereIn('id', $bahanBakuIds)->get();
                        
                        // Attach pivot data
                        $bahanBakuItems->each(function($bahanBaku) use ($menu) {
                            $pivotData = DB::table('menu_bahan_baku')
                                ->where('menu_id', $menu->id)
                                ->where('bahan_baku_id', $bahanBaku->id)
                                ->first();
                            
                            if ($pivotData) {
                                $bahanBaku->pivot = $pivotData;
                            }
                        });
                        
                        $menu->setRelation('bahanBaku', $bahanBakuItems);
                    } else {
                        $menu->setRelation('bahanBaku', collect());
                    }
                    
                    $orderItem->setRelation('menu', $menu);
                }
                
                return $orderItem;
            }));

            // Process payment
            $order->processPayment($request->payment_method_id, $request->paid_amount);

            // Redeem points if applicable
            if ($pointsRedeemed > 0 && $order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                if ($member) {
                    $member->redeemPoints($pointsRedeemed, "Redeem for order {$order->kode}", $order->id);
                }
            }

            // Award points to member
            if ($order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                $settings = \App\Models\MembershipSetting::first();
                
                if ($member && $settings) {
                    $pointsEarned = $settings->calculatePoints($order->total_amount);
                    
                    if ($pointsEarned > 0) {
                        $member->addPoints($pointsEarned, "Earned from order {$order->kode}", $order->id);
                        $order->points_earned = $pointsEarned;
                        $order->save();
                    }
                }
            }

            // Increment promo usage for all applied promos
            if (!empty($order->applied_promos)) {
                foreach ($order->applied_promos as $appliedPromo) {
                    $promo = \App\Models\Promo::find($appliedPromo['id']);
                    if ($promo) {
                        $promo->incrementUsage();
                    }
                }
            } elseif ($order->promo_id) {
                // Fallback for old single promo
                $promo = \App\Models\Promo::find($order->promo_id);
                if ($promo) {
                    $promo->incrementUsage();
                }
            }

            // Reload for response
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->setRelation('items', $items);
            
            if ($order->table_id) {
                $table = Table::find($order->table_id);
                $order->setRelation('table', $table);
            }
            
            if ($order->payment_method_id) {
                $paymentMethod = \App\Models\PaymentMethod::find($order->payment_method_id);
                $order->setRelation('paymentMethod', $paymentMethod);
            }

            DB::commit();
            DB::statement("SET search_path TO public");
            
            return response()->json([
                'message' => 'Payment processed successfully',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate receipt PDF
     */
    public function receipt(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        // Get timezone from client, fallback to Asia/Jakarta
        $timezone = $request->query('tz', 'Asia/Jakarta');
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $timezone = 'Asia/Jakarta';
        }
        
        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $order = Order::findOrFail($id);
            
            if ($order->status !== 'paid') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order not paid yet'], 400);
            }

            // Load all relationships while schema is still set
            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $order->setRelation('items', $items);
            
            if ($order->table_id) {
                $order->setRelation('table', Table::find($order->table_id));
            }
            
            if ($order->payment_method_id) {
                $order->setRelation('paymentMethod', \App\Models\PaymentMethod::find($order->payment_method_id));
            }

            // Load member eagerly while schema context is active
            $memberData = null;
            if ($order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                if ($member) {
                    $memberData = (object) [
                        'nama'        => $member->nama,
                        'card_number' => $member->card_number,
                        'tier'        => $member->tier,
                        'phone'       => $member->phone,
                        'points'      => $member->points,
                    ];
                }
            }

            // Load cashier from public schema — switch first
            DB::statement("SET search_path TO public");
            $cashierName = null;
            if ($order->cashier_id) {
                $cashier = \App\Models\User::find($order->cashier_id);
                $cashierName = $cashier?->name;
            }

            // Pre-format timestamps in the requested timezone
            $tz = new \DateTimeZone($timezone);
            $createdAt = \Carbon\Carbon::parse($order->created_at)->setTimezone($tz)->format('d/m/Y H:i');
            $paidAt    = \Carbon\Carbon::parse($order->paid_at)->setTimezone($tz)->format('d/m/Y H:i:s');

            $pdf = Pdf::loadView('receipts.order', [
                'order'       => $order,
                'outlet'      => $outlet,
                'member'      => $memberData,
                'cashierName' => $cashierName,
                'createdAt'   => $createdAt,
                'paidAt'      => $paidAt,
            ])->setPaper([0, 0, 226.77, 841.89], 'portrait');
            
            $safeKode = preg_replace('/[^A-Za-z0-9\-_]/', '', trim($order->kode));
            
            return response($pdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="receipt-' . $safeKode . '.pdf"',
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate thermal receipt data (ESC/POS formatted text)
     */
    public function thermalReceipt(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $timezone = $request->query('tz', 'Asia/Jakarta');
        try { new \DateTimeZone($timezone); } catch (\Exception $e) { $timezone = 'Asia/Jakarta'; }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $order = Order::findOrFail($id);

            if ($order->status !== 'paid') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order not paid yet'], 400);
            }

            $items = DB::table('order_items')->where('order_id', $order->id)->get();
            $paymentMethod = $order->payment_method_id
                ? \App\Models\PaymentMethod::find($order->payment_method_id)
                : null;

            $memberData = null;
            if ($order->member_id) {
                $member = \App\Models\Member::find($order->member_id);
                if ($member) $memberData = ['nama' => $member->nama, 'tier' => $member->tier];
            }

            DB::statement("SET search_path TO public");

            $cashierName = $order->cashier_id
                ? optional(\App\Models\User::find($order->cashier_id))->name
                : null;

            $tz = new \DateTimeZone($timezone);
            $paidAt = \Carbon\Carbon::parse($order->paid_at)->setTimezone($tz)->format('d/m/Y H:i:s');

            // Build lines array for frontend ESC/POS rendering
            $lines = [];

            // Header
            $lines[] = ['type' => 'align', 'value' => 'center'];
            $lines[] = ['type' => 'bold', 'value' => true];
            $lines[] = ['type' => 'text', 'value' => strtoupper($outlet->name ?? 'OUTLET')];
            $lines[] = ['type' => 'bold', 'value' => false];
            if ($outlet->address) $lines[] = ['type' => 'text', 'value' => $outlet->address];
            if ($outlet->phone) $lines[] = ['type' => 'text', 'value' => $outlet->phone];
            $lines[] = ['type' => 'divider'];

            // Order info
            $lines[] = ['type' => 'align', 'value' => 'left'];
            $lines[] = ['type' => 'row', 'left' => 'No', 'right' => $order->kode];
            $lines[] = ['type' => 'row', 'left' => 'Tanggal', 'right' => $paidAt];
            $lines[] = ['type' => 'row', 'left' => 'Kasir', 'right' => $cashierName ?? '-'];
            if ($order->table_number) {
                $lines[] = ['type' => 'row', 'left' => 'Meja', 'right' => $order->table_number];
            }
            if ($memberData) {
                $lines[] = ['type' => 'row', 'left' => 'Member', 'right' => $memberData['nama']];
            }
            $lines[] = ['type' => 'divider'];

            // Items
            foreach ($items as $item) {
                $lines[] = ['type' => 'text', 'value' => $item->menu_name];
                $lines[] = ['type' => 'row',
                    'left'  => "  {$item->quantity} x " . number_format($item->menu_price, 0, ',', '.'),
                    'right' => number_format($item->subtotal, 0, ',', '.')
                ];
            }
            $lines[] = ['type' => 'divider'];

            // Totals
            $lines[] = ['type' => 'row', 'left' => 'Subtotal', 'right' => 'Rp ' . number_format($order->subtotal, 0, ',', '.')];

            if ($order->discount_amount > 0) {
                $lines[] = ['type' => 'row', 'left' => 'Diskon', 'right' => '- Rp ' . number_format($order->discount_amount, 0, ',', '.')];
            }

            $lines[] = ['type' => 'row', 'left' => "Pajak ({$order->tax_percentage}%)", 'right' => 'Rp ' . number_format($order->tax_amount, 0, ',', '.')];
            $lines[] = ['type' => 'divider'];
            $lines[] = ['type' => 'bold', 'value' => true];
            $lines[] = ['type' => 'row', 'left' => 'TOTAL', 'right' => 'Rp ' . number_format($order->total_amount, 0, ',', '.')];
            $lines[] = ['type' => 'bold', 'value' => false];
            $lines[] = ['type' => 'row', 'left' => 'Bayar', 'right' => 'Rp ' . number_format($order->paid_amount, 0, ',', '.')];
            $lines[] = ['type' => 'row', 'left' => 'Kembali', 'right' => 'Rp ' . number_format($order->change_amount, 0, ',', '.')];
            $lines[] = ['type' => 'row', 'left' => 'Metode', 'right' => $paymentMethod?->name ?? '-'];

            // Footer
            $lines[] = ['type' => 'divider'];
            $lines[] = ['type' => 'align', 'value' => 'center'];
            $lines[] = ['type' => 'text', 'value' => 'Terima kasih!'];
            $lines[] = ['type' => 'text', 'value' => 'Simpan struk ini sebagai bukti'];

            // QR Code tracking URL
            $trackingUrl = rtrim(config('app.url'), '/') . '/track/' . $outlet->id . '/' . $order->kode;
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $trackingUrl = rtrim($frontendUrl, '/') . '/track/' . $outlet->id . '/' . $order->kode;
            $lines[] = ['type' => 'feed', 'lines' => 1];
            $lines[] = ['type' => 'text', 'value' => 'Cek status pesanan:'];
            $lines[] = ['type' => 'qr', 'value' => $trackingUrl];
            $lines[] = ['type' => 'text', 'value' => $trackingUrl];

            $lines[] = ['type' => 'feed', 'lines' => 4];
            $lines[] = ['type' => 'cut'];

            return response()->json([
                'outlet_name' => $outlet->nama,
                'order_kode'  => $order->kode,
                'lines'       => $lines,
            ]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Settle a bon order — mark as paid and reduce stock
     */
    public function settleBon(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        try {
            DB::beginTransaction();
            DB::statement("SET search_path TO {$outlet->schema_name}, public");

            $order = Order::with([
                'items.menu.bahanBaku',
                'paymentMethod',
            ])->find($id);

            if (!$order) {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order not found'], 404);
            }

            if ($order->status !== 'bon') {
                DB::rollBack();
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Order is not a bon order'], 422);
            }

            $order->settleBon(Auth::id());

            DB::commit();
            DB::statement("SET search_path TO public");

            return response()->json(['message' => 'Bon settled successfully', 'data' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $outletId, $id)
    {
        $outlet = $this->authorizeOutlet($outletId);
        
        $validator = Validator::make($request->all(), [
            'cancellation_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            DB::statement("SET search_path TO {$outlet->schema_name}, public");
            
            $order = Order::findOrFail($id);
            
            if ($order->status !== 'draft') {
                DB::statement("SET search_path TO public");
                return response()->json(['message' => 'Cannot cancel paid order'], 400);
            }

            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancelled_by = Auth::id();
            $order->cancellation_reason = $request->cancellation_reason;
            $order->save();

            // Mark table as available if dine-in
            if ($order->order_type === 'dine_in' && $order->table) {
                $order->table->markAsAvailable();
            }

            DB::statement("SET search_path TO public");
            
            return response()->json(['message' => 'Order cancelled successfully', 'data' => $order]);
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
