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
        .header-logo {
            max-width: 60px;
            max-height: 60px;
            margin: 0 auto 4px;
            display: block;
        }
        .header h1 { font-size: 13px; font-weight: bold; margin-bottom: 3px; text-transform: uppercase; }
        .header p  { font-size: 9px; margin: 1px 0; }
        .header .custom-header { font-size: 9px; margin: 2px 0; font-style: italic; }

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
        .footer .custom-footer { margin: 4px 0; font-style: italic; white-space: pre-line; }

        /* ── QR Code ── */
        .qr-section { text-align: center; margin: 6px 0; }
        .qr-section p { font-size: 8px; margin-bottom: 3px; }
        .qr-section img { width: 70px; height: 70px; }

        /* ── WiFi ── */
        .wifi-section {
            margin-top: 6px;
            padding: 5px;
            border: 1px dashed #000;
            text-align: center;
        }
        .wifi-title { font-weight: bold; font-size: 10px; margin-bottom: 3px; }
        .wifi-row {
            width: 100%;
            display: table;
            margin: 1px 0;
            font-size: 9px;
        }
        .wifi-label { display: table-cell; text-align: left; font-weight: bold; width: 40%; }
        .wifi-value { display: table-cell; text-align: left; }
        .wifi-qr { margin-top: 4px; }
        .wifi-qr img { width: 60px; height: 60px; }
    </style>
</head>
<body>
<div class="receipt">

@php
    // Ambil nilai dari receipt settings ($rs), dengan fallback aman
    $rLogoEnabled   = $rs ? (bool) ($rs->receipt_logo_enabled ?? true)  : true;
    $rHeader        = $rs ? ($rs->receipt_header        ?? '')          : '';
    $rFooter        = $rs ? ($rs->receipt_footer        ?? '')          : '';
    $rShowQr        = $rs ? (bool) ($rs->receipt_show_qr    ?? true)   : true;
    $rWifiEnabled   = $rs ? (bool) ($rs->receipt_wifi_enabled  ?? false): false;
    $rWifiSsid      = $rs ? ($rs->receipt_wifi_ssid     ?? '')          : '';
    $rWifiPassword  = $rs ? ($rs->receipt_wifi_password ?? '')          : '';
    $rShowCashier   = $rs ? (bool) ($rs->receipt_show_cashier ?? true)  : true;
    $rShowTable     = $rs ? (bool) ($rs->receipt_show_table   ?? true)  : true;
    $rShowMember    = $rs ? (bool) ($rs->receipt_show_member  ?? true)  : true;
    $rCustomLogoUrl = $rs ? ($rs->receipt_custom_logo_url ?? '')         : '';
    $taxLabel       = $rs ? ($rs->tax_label ?? 'PPN')                   : 'PPN';
    $scLabel        = $rs ? ($rs->service_charge_label ?? 'Service Charge') : 'Service Charge';

    // Tentukan logo yang dipakai
    $logoUrl = '';
    if ($rLogoEnabled) {
        if (!empty($rCustomLogoUrl)) {
            $logoUrl = $rCustomLogoUrl;
        } elseif (!empty($outlet->logo)) {
            $logoUrl = $outlet->logo;
        }
    }

    // Tracking URL untuk QR
    $trackingUrl = rtrim($frontendUrl ?? 'http://localhost:5173', '/') . '/track/' . $outlet->id . '/' . $order->kode;

    // WiFi QR (format standar WIFI:)
    $wifiQrUrl = '';
    if ($rWifiEnabled && !empty($rWifiSsid)) {
        $wifiData  = 'WIFI:T:WPA;S:' . $rWifiSsid . ';P:' . $rWifiPassword . ';H:false;;';
        $wifiQrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=' . urlencode($wifiData);
    }
@endphp

    {{-- ── Header ── --}}
    <div class="header">
        @if(!empty($logoUrl))
        <img src="{{ $logoUrl }}" class="header-logo" alt="Logo">
        @endif
        <h1>{{ $outlet->name }}</h1>
        @if($outlet->address)<p>{{ $outlet->address }}</p>@endif
        @if($outlet->phone)<p>Telp: {{ $outlet->phone }}</p>@endif
        @if(!empty(trim($rHeader)))
            @foreach(explode("\n", $rHeader) as $hLine)
            <p class="custom-header">{{ trim($hLine) }}</p>
            @endforeach
        @endif
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
        @if($rShowTable && $order->table_number)
        <div class="info-row">
            <span class="info-label">Meja</span>
            <span class="info-value">{{ $order->table_number }}</span>
        </div>
        @endif
        @if($rShowMember && isset($member) && $member)
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
        @if($rShowCashier)
        <div class="info-row">
            <span class="info-label">Kasir</span>
            <span class="info-value">{{ $cashierName ?? $order->cashier_id }}</span>
        </div>
        @endif
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

        @if($order->tax_amount > 0)
        <div class="total-row">
            <span class="total-left">{{ $taxLabel }} ({{ $order->tax_percentage }}%)</span>
            <span class="total-right">Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span>
        </div>
        @endif

        @if($order->service_charge_amount > 0)
        <div class="total-row">
            <span class="total-left">{{ $scLabel }} ({{ $order->service_charge_percentage }}%)</span>
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
        @if($rShowMember && isset($member) && $member)
        <p>Saldo poin: {{ $member->points }} pts</p>
        @endif

        @if(!empty(trim($rFooter)))
        <p class="custom-footer">{{ trim($rFooter) }}</p>
        @else
        <p>Terima kasih atas kunjungan Anda</p>
        @endif
        <p>{{ $paidAt }}</p>

        {{-- QR tracking --}}
        @if($rShowQr)
        <div class="qr-section">
            <p>Cek status pesanan:</p>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={{ urlencode($trackingUrl) }}" alt="QR">
        </div>
        @endif

        {{-- WiFi --}}
        @if($rWifiEnabled && !empty($rWifiSsid))
        <div class="wifi-section">
            <div class="wifi-title">📶 WiFi Gratis</div>
            <div class="wifi-row">
                <span class="wifi-label">SSID</span>
                <span class="wifi-value">{{ $rWifiSsid }}</span>
            </div>
            <div class="wifi-row">
                <span class="wifi-label">Password</span>
                <span class="wifi-value">{{ !empty($rWifiPassword) ? $rWifiPassword : '(tanpa password)' }}</span>
            </div>
            @if(!empty($wifiQrUrl))
            <div class="wifi-qr">
                <img src="{{ $wifiQrUrl }}" alt="WiFi QR">
            </div>
            @endif
        </div>
        @endif
    </div>

</div>
</body>
</html>
