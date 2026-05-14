<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Receipt - {{ $order->kode }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.5;
            padding: 8px;
            color: #000;
        }

        .receipt { width: 100%; max-width: 76mm; }

        /* ── Header ── */
        .header {
            text-align: center;
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
        }
        .header h1 { font-size: 13px; font-weight: bold; margin-bottom: 3px; }
        .header p  { font-size: 9px; margin: 1px 0; }

        /* ── Info block ── */
        .info-block {
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
        }
        .info-row {
            width: 100%;
            display: table;
            margin: 1px 0;
        }
        .info-label {
            display: table-cell;
            width: 38%;
            font-weight: bold;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            width: 62%;
            vertical-align: top;
        }

        /* ── Items ── */
        .items {
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
        }
        .item { margin-bottom: 5px; }
        .item-name { font-weight: bold; font-size: 10px; }
        .item-line {
            width: 100%;
            display: table;
            font-size: 9px;
            color: #333;
        }
        .item-line-left  { display: table-cell; text-align: left; }
        .item-line-right { display: table-cell; text-align: right; font-weight: bold; color: #000; }
        .item-note { font-size: 8px; font-style: italic; color: #555; padding-left: 8px; }

        /* ── Totals ── */
        .totals {
            padding-bottom: 8px;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
        }
        .total-row {
            width: 100%;
            display: table;
            margin: 2px 0;
        }
        .total-left  { display: table-cell; text-align: left; }
        .total-right { display: table-cell; text-align: right; white-space: nowrap; }

        .total-row.discount .total-left,
        .total-row.discount .total-right { color: #006600; }

        .total-row.points .total-left,
        .total-row.points .total-right { color: #996600; }

        .total-row.grand .total-left,
        .total-row.grand .total-right {
            font-weight: bold;
            font-size: 12px;
            padding-top: 4px;
            border-top: 1px solid #000;
        }

        /* ── Payment ── */
        .payment { padding-bottom: 8px; margin-bottom: 8px; border-bottom: 1px dashed #000; }

        /* ── Footer ── */
        .footer { text-align: center; font-size: 9px; }
        .footer p { margin: 2px 0; }
        .footer .points-msg { font-weight: bold; font-size: 10px; margin-bottom: 4px; }
    </style>
</head>
<body>
<div class="receipt">

    {{-- ── Header ── --}}
    <div class="header">
        <h1>{{ $outlet->name }}</h1>
        @if($outlet->address)<p>{{ $outlet->address }}</p>@endif
        @if($outlet->phone)<p>Telp: {{ $outlet->phone }}</p>@endif
    </div>

    {{-- ── Order Info ── --}}
    <div class="info-block">
        <div class="info-row">
            <span class="info-label">No. Order</span>
            <span class="info-value">{{ $order->kode }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Tanggal</span>
            <span class="info-value">{{ $createdAt }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Tipe</span>
            <span class="info-value">{{ $order->order_type_label }}</span>
        </div>
        @if($order->table_number)
        <div class="info-row">
            <span class="info-label">Meja</span>
            <span class="info-value">{{ $order->table_number }}</span>
        </div>
        @endif
        @if(isset($member) && $member)
        <div class="info-row">
            <span class="info-label">Member</span>
            <span class="info-value">{{ $member->nama }} ({{ $member->tier }})</span>
        </div>
        <div class="info-row">
            <span class="info-label">No. Kartu</span>
            <span class="info-value">{{ $member->card_number }}</span>
        </div>
        @elseif($order->customer_name)
        <div class="info-row">
            <span class="info-label">Pelanggan</span>
            <span class="info-value">{{ $order->customer_name }}</span>
        </div>
        @endif
        <div class="info-row">
            <span class="info-label">Kasir</span>
            <span class="info-value">{{ $cashierName ?? $order->cashier_id }}</span>
        </div>
    </div>

    {{-- ── Items ── --}}
    <div class="items">
        @foreach($order->items as $item)
        <div class="item">
            <div class="item-name">{{ $item->menu_name }}</div>
            <div class="item-line">
                <span class="item-line-left">{{ $item->quantity }} x Rp {{ number_format($item->menu_price, 0, ',', '.') }}</span>
                <span class="item-line-right">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($item->notes)
            <div class="item-note">Catatan: {{ $item->notes }}</div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ── Totals ── --}}
    <div class="totals">
        <div class="total-row">
            <span class="total-left">Subtotal</span>
            <span class="total-right">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
        </div>

        @if(!empty($order->applied_promos))
            @foreach($order->applied_promos as $promo)
            <div class="total-row discount">
                <span class="total-left">Promo {{ $promo['kode'] }}</span>
                <span class="total-right">- Rp {{ number_format($promo['discount_amount'], 0, ',', '.') }}</span>
            </div>
            @endforeach
        @elseif($order->discount_amount > 0 && !$order->points_redeemed)
        <div class="total-row discount">
            <span class="total-left">Diskon{{ $order->promo_code ? ' ('.$order->promo_code.')' : '' }}</span>
            <span class="total-right">- Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</span>
        </div>
        @endif

        @if($order->points_redeemed > 0)
        @php
            $promoDiscount = !empty($order->applied_promos) ? collect($order->applied_promos)->sum('discount_amount') : 0;
            $pointValue = max(0, $order->discount_amount - $promoDiscount);
        @endphp
        <div class="total-row points">
            <span class="total-left">Tukar Poin ({{ $order->points_redeemed }} pts)</span>
            <span class="total-right">- Rp {{ number_format($pointValue, 0, ',', '.') }}</span>
        </div>
        @endif

        <div class="total-row">
            <span class="total-left">Pajak ({{ $order->tax_percentage }}%)</span>
            <span class="total-right">Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span>
        </div>

        @if($order->service_charge_amount > 0)
        <div class="total-row">
            <span class="total-left">Service Charge ({{ $order->service_charge_percentage }}%)</span>
            <span class="total-right">Rp {{ number_format($order->service_charge_amount, 0, ',', '.') }}</span>
        </div>
        @endif

        <div class="total-row grand">
            <span class="total-left">TOTAL</span>
            <span class="total-right">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- ── Payment ── --}}
    <div class="payment">
        <div class="total-row">
            <span class="total-left">Metode Bayar</span>
            <span class="total-right">{{ $order->paymentMethod->name ?? '-' }}</span>
        </div>
        <div class="total-row">
            <span class="total-left">Bayar</span>
            <span class="total-right">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</span>
        </div>
        <div class="total-row">
            <span class="total-left">Kembalian</span>
            <span class="total-right">Rp {{ number_format($order->change_amount, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- ── Footer ── --}}
    <div class="footer">
        @if($order->points_earned > 0)
        <p class="points-msg">+{{ $order->points_earned }} poin diperoleh!</p>
        @endif
        @if(isset($member) && $member)
        <p>Saldo poin: {{ $member->points }} pts</p>
        @endif
        <p>Terima kasih atas kunjungan Anda</p>
        <p>{{ $paidAt }}</p>
    </div>

</div>
</body>
</html>
