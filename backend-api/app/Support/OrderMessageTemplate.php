<?php

namespace App\Support;

/**
 * Render outlet-defined WhatsApp templates for order status notifications.
 *
 * Placeholders are simple {nama_pelanggan} style tokens — missing values fall
 * back to empty strings so a template never explodes on a partially-populated
 * order (e.g. a takeaway with no member name).
 */
class OrderMessageTemplate
{
    /**
     * Sensible defaults used when an outlet has not customized the template.
     */
    public const DEFAULTS = [
        'approved' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* telah *DISETUJUI* dan sedang diproses.\n" .
            "Mohon ditunggu, terima kasih telah memesan!",
        'rejected' =>
            "Halo {nama_pelanggan},\n" .
            "Mohon maaf, pesanan Anda dengan kode *{kode_pesanan}* di *{nama_outlet}* tidak dapat kami proses (*DITOLAK*).\n" .
            "{alasan}\n" .
            "Silakan hubungi kasir untuk informasi lebih lanjut. Terima kasih.",
        'processing' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda *{kode_pesanan}* di *{nama_outlet}* sedang *DIPROSES* oleh dapur/bar kami.\n" .
            "Mohon ditunggu sebentar lagi.",
        'ready_dinein' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SIAP* dan akan segera diantar ke meja {nomor_meja}.\n" .
            "Selamat menikmati!",
        'ready_takeaway' =>
            "Halo {nama_pelanggan},\n" .
            "Pesanan takeaway Anda *{kode_pesanan}* di *{nama_outlet}* sudah *SIAP DIAMBIL* di kasir.\n" .
            "Terima kasih sudah memesan!",
    ];

    /**
     * Render a template string with placeholders.
     * Missing placeholders become empty strings and consecutive blank lines
     * are collapsed so optional fields (e.g. {alasan}) do not leave gaps.
     */
    public static function render(?string $template, array $vars, string $fallbackKey): string
    {
        $tpl = trim((string) $template) !== ''
            ? $template
            : (self::DEFAULTS[$fallbackKey] ?? '');

        $replacements = [];
        foreach ($vars as $k => $v) {
            $replacements['{' . $k . '}'] = (string) ($v ?? '');
        }

        $out = strtr($tpl, $replacements);
        // Strip leftover placeholders the caller did not supply
        $out = preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '', $out);
        // Collapse 3+ newlines (created by empty optional lines) to 2
        $out = preg_replace("/\n{3,}/", "\n\n", $out);
        return trim($out);
    }

    /**
     * Build the variable map used by all templates from an order row.
     * Expects $order to be the orders table row (object with same fields).
     */
    public static function vars(object $order, string $outletName, ?string $reason = null): array
    {
        $type = (string) ($order->order_type ?? '');
        $tipeLabel = match ($type) {
            'dine_in'  => 'Dine-in',
            'takeaway' => 'Takeaway',
            'delivery' => 'Delivery',
            default    => $type,
        };

        return [
            'nama_pelanggan' => (string) ($order->customer_name ?? ''),
            'kode_pesanan'   => (string) ($order->kode ?? ''),
            'nama_outlet'    => (string) $outletName,
            'tipe_pesanan'   => $tipeLabel,
            'nomor_meja'     => (string) ($order->table_number ?? ''),
            'total'          => isset($order->total_amount)
                ? 'Rp ' . number_format((float) $order->total_amount, 0, ',', '.')
                : '',
            'status'         => (string) ($order->status ?? ''),
            'alasan'         => $reason ? 'Alasan: ' . $reason : '',
        ];
    }
}
