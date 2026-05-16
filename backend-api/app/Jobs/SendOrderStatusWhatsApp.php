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
    public ?string $orderType;   // 'dine_in' | 'takeaway' | 'delivery' | null
    public ?string $tableNumber;
    public ?int    $outletDbId;

    public function __construct(
        string $phone,
        string $customerName,
        string $orderCode,
        string $outletName,
        string $status,
        ?string $reason = null,
        ?string $schema = null,
        ?string $orderType = null,
        ?string $tableNumber = null,
        ?int $outletDbId = null
    ) {
        $this->phone        = $phone;
        $this->customerName = $customerName;
        $this->orderCode    = $orderCode;
        $this->outletName   = $outletName;
        $this->status       = $status;
        $this->reason       = $reason;
        $this->schema       = $schema;
        $this->orderType    = $orderType;
        $this->tableNumber  = $tableNumber;
        $this->outletDbId   = $outletDbId;
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
        [$tplKey, $template] = $this->resolveTemplate();

        // Build a synthetic order object since this job is dispatched with
        // primitive fields only (avoids serializing Eloquent models).
        $pseudoOrder = (object) [
            'customer_name' => $this->customerName,
            'kode'          => $this->orderCode,
            'order_type'    => (string) ($this->orderType ?? ''),
            'table_number'  => (string) ($this->tableNumber ?? ''),
            'total_amount'  => 0,
            'status'        => $this->status,
        ];

        $trackingUrl = $this->outletDbId
            ? OrderMessageTemplate::buildTrackingUrl($this->outletDbId, $this->orderCode)
            : null;

        return OrderMessageTemplate::render(
            $template,
            OrderMessageTemplate::vars($pseudoOrder, $this->outletName, $this->reason, $trackingUrl),
            $tplKey
        );
    }

    /**
     * Resolve the (key, template) pair for the outlet. Picks the
     * order-type-specific template first, then falls back to the shared
     * tpl_approved / tpl_rejected, then to the default in OrderMessageTemplate.
     *
     * @return array{0:string,1:?string}
     */
    protected function resolveTemplate(): array
    {
        $status = $this->status === 'approved' ? 'approved' : 'rejected';
        $type = (string) ($this->orderType ?? '');
        $isTakeaway = ($type === 'takeaway' || $type === 'delivery');
        $variant = $isTakeaway ? 'takeaway' : 'dinein';
        $variantKey = "{$status}_{$variant}";

        if (!$this->schema) {
            return [$variantKey, null]; // falls back to default for variant
        }

        try {
            DB::statement("SET search_path TO {$this->schema}, public");
            if (!DB::getSchemaBuilder()->hasTable('wa_settings')) {
                DB::statement("SET search_path TO public");
                return [$variantKey, null];
            }
            $row = DB::table('wa_settings')->first();
            DB::statement("SET search_path TO public");
            if (!$row) return [$variantKey, null];

            $variantCol = "tpl_{$status}_{$variant}";
            $sharedCol  = "tpl_{$status}";

            $tpl = $row->{$variantCol} ?? null;
            if (trim((string) $tpl) !== '') {
                return [$variantKey, $tpl];
            }
            // Backward-compat: outlet only set the shared tpl_approved/tpl_rejected.
            $shared = $row->{$sharedCol} ?? null;
            if (trim((string) $shared) !== '') {
                return [$status, $shared];
            }
            return [$variantKey, null];
        } catch (\Throwable $e) {
            try { DB::statement("SET search_path TO public"); } catch (\Throwable $ignored) {}
            return [$variantKey, null];
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
