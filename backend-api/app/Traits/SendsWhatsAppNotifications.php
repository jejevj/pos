<?php

namespace App\Traits;

use App\Services\WahaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait SendsWhatsAppNotifications
{
    /**
     * Get WA settings for the current outlet schema (must be set before calling).
     */
    protected function getWaSettings(): ?object
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('wa_settings')) return null;
            return DB::table('wa_settings')->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function waha(): WahaService
    {
        return app(WahaService::class);
    }

    // -------------------------------------------------------------------------
    // Notification senders
    // -------------------------------------------------------------------------

    protected function notifyPayrollPaid(object $payroll, string $employeeName, string $employeePhone = null): void
    {
        $settings = $this->getWaSettings();
        if (!$settings || !($settings->notify_payroll ?? true)) return;

        $bulan = \Carbon\Carbon::create($payroll->period_year, $payroll->period_month)->translatedFormat('F Y');
        $net   = 'Rp ' . number_format($payroll->net_salary, 0, ',', '.');

        $msg = "💰 *Slip Gaji {$bulan}*\n\n"
             . "Halo *{$employeeName}*,\n"
             . "Gaji Anda untuk periode *{$bulan}* telah dibayarkan.\n\n"
             . "• Gaji Pokok : Rp " . number_format($payroll->basic_salary, 0, ',', '.') . "\n"
             . "• Lembur     : Rp " . number_format($payroll->overtime_pay, 0, ',', '.') . "\n"
             . "• Bonus      : Rp " . number_format($payroll->bonuses, 0, ',', '.') . "\n"
             . "• Potongan   : Rp " . number_format($payroll->deductions, 0, ',', '.') . "\n"
             . "━━━━━━━━━━━━━━━\n"
             . "• *Total     : {$net}*\n\n"
             . "_Terima kasih atas kerja keras Anda!_ 🙏";

        // Send to employee if they have a phone
        if ($employeePhone) {
            $this->waha()->sendText($employeePhone, $msg);
        }

        // Also notify owner
        if ($settings->owner_phone) {
            $ownerMsg = "📋 *Payroll Dibayar*\n\nGaji *{$employeeName}* periode *{$bulan}* sebesar *{$net}* telah ditandai lunas.";
            $this->waha()->sendText($settings->owner_phone, $ownerMsg);
        }
    }

    protected function notifyKasbonApproved(string $employeeName, string $employeePhone = null, float $amount = 0): void
    {
        $settings = $this->getWaSettings();
        if (!$settings || !($settings->notify_kasbon ?? true)) return;

        $nominal = 'Rp ' . number_format($amount, 0, ',', '.');
        $msg = "✅ *Kasbon Disetujui*\n\n"
             . "Halo *{$employeeName}*,\n"
             . "Pengajuan kasbon Anda sebesar *{$nominal}* telah *disetujui*.\n\n"
             . "_Silakan hubungi admin untuk pencairan._";

        if ($employeePhone) {
            $this->waha()->sendText($employeePhone, $msg);
        }
    }

    protected function notifyKasbonRejected(string $employeeName, string $employeePhone = null, float $amount = 0, string $reason = ''): void
    {
        $settings = $this->getWaSettings();
        if (!$settings || !($settings->notify_kasbon ?? true)) return;

        $nominal = 'Rp ' . number_format($amount, 0, ',', '.');
        $msg = "❌ *Kasbon Ditolak*\n\n"
             . "Halo *{$employeeName}*,\n"
             . "Pengajuan kasbon Anda sebesar *{$nominal}* telah *ditolak*.\n"
             . ($reason ? "\nAlasan: _{$reason}_" : '');

        if ($employeePhone) {
            $this->waha()->sendText($employeePhone, $msg);
        }
    }

    protected function notifyLowStock(string $ownerPhone = null, string $itemName = '', float $currentStock = 0, float $minStock = 0, string $unit = ''): void
    {
        $settings = $this->getWaSettings();
        if (!$settings || !($settings->notify_low_stock ?? true)) return;

        $phone = $ownerPhone ?? ($settings->owner_phone ?? null);
        if (!$phone) return;

        $msg = "⚠️ *Stok Menipis!*\n\n"
             . "Bahan baku *{$itemName}* hampir habis.\n\n"
             . "• Stok saat ini : {$currentStock} {$unit}\n"
             . "• Minimum stok  : {$minStock} {$unit}\n\n"
             . "_Segera lakukan pembelian ulang._";

        $this->waha()->sendText($phone, $msg);
    }

    protected function notifyOrderReceipt(string $customerPhone, string $outletName, object $order): void
    {
        $settings = $this->getWaSettings();
        if (!$settings || !($settings->notify_order ?? false)) return;

        $total = 'Rp ' . number_format($order->total_amount ?? 0, 0, ',', '.');
        $msg = "🧾 *Struk Pesanan*\n\n"
             . "*{$outletName}*\n"
             . "No. Order: #{$order->order_number}\n\n"
             . "Total: *{$total}*\n\n"
             . "_Terima kasih telah berbelanja!_ 😊";

        $this->waha()->sendText($customerPhone, $msg);
    }
}
