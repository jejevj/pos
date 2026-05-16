<?php

namespace App\Jobs;

use App\Services\WahaService;
use App\Support\OrderMessageTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
    public ?string $schema;

    public function __construct(
        string $phone,
        string $customerName,
        string $orderCode,
        string $outletName,
        string $status,
        ?string $reason = null,
        ?string $schema = null
    ) {
        $this->phone        = $phone;
        $this->customerName = $customerName;
        $this->orderCode    = $orderCode;
        $this->outletName   = $outletName;
        $this->status       = $status;
        $this->reason       = $reason;
        $this->schema       = $schema;
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
        $template = $this->resolveTemplate();
        $key = $this->status === 'approved' ? 'approved' : 'rejected';

        // Build a synthetic order object since this job is dispatched with
        // primitive fields only (avoids serializing Eloquent models).
        $pseudoOrder = (object) [
            'customer_name' => $this->customerName,
            'kode'          => $this->orderCode,
            'order_type'    => '',
            'table_number'  => '',
            'total_amount'  => 0,
            'status'        => $this->status,
        ];

        return OrderMessageTemplate::render(
            $template,
            OrderMessageTemplate::vars($pseudoOrder, $this->outletName, $this->reason),
            $key
        );
    }

    /**
     * Pull the outlet's custom template if a schema was provided and
     * wa_settings exists. Returns null to fall back to the default.
     */
    protected function resolveTemplate(): ?string
    {
        if (!$this->schema) return null;

        try {
            DB::statement("SET search_path TO {$this->schema}, public");
            if (!DB::getSchemaBuilder()->hasTable('wa_settings')) {
                DB::statement("SET search_path TO public");
                return null;
            }
            $row = DB::table('wa_settings')->first();
            DB::statement("SET search_path TO public");
            if (!$row) return null;
            return $this->status === 'approved'
                ? ($row->tpl_approved ?? null)
                : ($row->tpl_rejected ?? null);
        } catch (\Throwable $e) {
            try { DB::statement("SET search_path TO public"); } catch (\Throwable $ignored) {}
            return null;
        }
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
