<?php

namespace App\Jobs;

use App\Services\WahaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued job that notifies the customer via WhatsApp (through WAHA)
 * after a public order is approved or rejected by outlet staff.
 *
 * Designed to be safe when:
 * - WAHA is disabled (WahaService logs and returns false).
 * - The queue worker is not running (job sits in the queue table; nothing
 *   in the staff approve/reject HTTP response depends on it).
 * - The customer phone is missing or invalid (logged & skipped).
 */
class SendOrderStatusWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public string $phone;
    public string $customerName;
    public string $orderCode;
    public string $outletName;
    public string $status; // 'approved' | 'rejected'
    public ?string $reason;

    public function __construct(
        string $phone,
        string $customerName,
        string $orderCode,
        string $outletName,
        string $status,
        ?string $reason = null
    ) {
        $this->phone        = $phone;
        $this->customerName = $customerName;
        $this->orderCode    = $orderCode;
        $this->outletName   = $outletName;
        $this->status       = $status;
        $this->reason       = $reason;
    }

    public function handle(WahaService $waha): void
    {
        $phone = trim($this->phone);
        if ($phone === '') {
            Log::info("[WAHA] Skip notify order {$this->orderCode}: no customer phone");
            return;
        }

        if (!$waha->isEnabled()) {
            Log::info("[WAHA] Skip notify order {$this->orderCode}: WAHA disabled");
            return;
        }

        $message = $this->buildMessage();
        $waha->sendText($phone, $message);
    }

    protected function buildMessage(): string
    {
        $hi = $this->customerName !== ''
            ? "Halo {$this->customerName},"
            : "Halo,";

        if ($this->status === 'approved') {
            return implode("\n", array_filter([
                $hi,
                "Pesanan Anda dengan kode *{$this->orderCode}* di *{$this->outletName}* telah *DISETUJUI* dan sedang diproses oleh dapur.",
                "Mohon ditunggu, terima kasih telah memesan di {$this->outletName}!",
            ]));
        }

        $reasonLine = $this->reason ? "Alasan: {$this->reason}" : null;
        return implode("\n", array_filter([
            $hi,
            "Mohon maaf, pesanan Anda dengan kode *{$this->orderCode}* di *{$this->outletName}* tidak dapat kami proses (*DITOLAK*).",
            $reasonLine,
            "Silakan hubungi kasir untuk informasi pengembalian dana atau pemesanan ulang. Terima kasih atas pengertiannya.",
        ]));
    }

    /**
     * Swallow failures instead of bubbling up so a misconfigured WAHA cannot
     * spam the failed_jobs table. The underlying WahaService already logs the
     * specific cause.
     */
    public function failed(\Throwable $e): void
    {
        Log::warning("[WAHA] Job failed for order {$this->orderCode}: " . $e->getMessage());
    }
}
