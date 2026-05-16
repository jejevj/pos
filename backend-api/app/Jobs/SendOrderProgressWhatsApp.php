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
 * Notify the customer via WAHA when their order transitions to
 * "processing" (kitchen/bar started) or "ready" (all items prepared).
 *
 * Idempotency is enforced at dispatch time by setting wa_*_notified_at
 * columns on the order before dispatching the job — see StationController.
 * The job itself is intentionally tolerant: it logs and exits cleanly when
 * WAHA is disabled, the phone is missing, or the templates table is absent.
 *
 * @property-read string $schema    Outlet schema to read wa_settings from
 * @property-read int    $orderId   Order id (in $schema)
 * @property-read string $outletName Display name (already resolved)
 * @property-read string $event     'processing' | 'ready'
 */
class SendOrderProgressWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public string $schema;
    public int $orderId;
    public string $outletName;
    public string $event;

    public function __construct(string $schema, int $orderId, string $outletName, string $event)
    {
        $this->schema     = $schema;
        $this->orderId    = $orderId;
        $this->outletName = $outletName;
        $this->event      = $event;
    }

    public function handle(WahaService $waha): void
    {
        if (!$waha->isEnabled()) {
            Log::info("[WAHA] Skip order #{$this->orderId} {$this->event}: WAHA disabled (set WAHA_ENABLED=true)");
            return;
        }

        try {
            DB::statement("SET search_path TO {$this->schema}, public");

            $order = DB::table('orders')->where('id', $this->orderId)->first();
            if (!$order) {
                Log::info("[WAHA] Skip order #{$this->orderId} {$this->event}: order missing in schema {$this->schema}");
                DB::statement("SET search_path TO public");
                return;
            }

            $phone = trim((string) ($order->customer_phone ?? ''));
            if ($phone === '') {
                Log::info("[WAHA] Skip order #{$this->orderId} {$this->event}: customer_phone empty");
                DB::statement("SET search_path TO public");
                return;
            }

            // wa_settings might not exist yet for legacy outlets — tolerate.
            $settings = null;
            if (DB::getSchemaBuilder()->hasTable('wa_settings')) {
                $settings = DB::table('wa_settings')->first();
            } else {
                Log::info("[WAHA] wa_settings table missing for {$this->schema}; using default templates");
            }

            // Allow outlet to opt-out per event
            if ($this->event === 'processing' && $settings && isset($settings->notify_processing) && !$settings->notify_processing) {
                Log::info("[WAHA] Skip order #{$this->orderId} processing: notify_processing disabled in outlet settings");
                DB::statement("SET search_path TO public");
                return;
            }
            if ($this->event === 'ready' && $settings && isset($settings->notify_ready) && !$settings->notify_ready) {
                Log::info("[WAHA] Skip order #{$this->orderId} ready: notify_ready disabled in outlet settings");
                DB::statement("SET search_path TO public");
                return;
            }

            [$tplKey, $tpl] = $this->pickTemplate($order, $settings);

            $message = OrderMessageTemplate::render(
                $tpl,
                OrderMessageTemplate::vars($order, $this->outletName),
                $tplKey
            );

            DB::statement("SET search_path TO public");
            $ok = $waha->sendText($phone, $message);
            if (!$ok) {
                Log::warning("[WAHA] sendText returned false for order #{$this->orderId} ({$this->event}) — cek koneksi/sesi WAHA");
            }
        } catch (\Throwable $e) {
            DB::statement("SET search_path TO public");
            Log::warning("[WAHA] Progress notify failed for order #{$this->orderId} ({$this->event}): " . $e->getMessage());
        }
    }

    /**
     * @return array{0:string,1:?string} [template-key, template-string-or-null]
     */
    protected function pickTemplate(object $order, ?object $settings): array
    {
        if ($this->event === 'processing') {
            return ['processing', $settings->tpl_processing ?? null];
        }

        // ready — pick dine-in vs takeaway template
        $type = (string) ($order->order_type ?? '');
        if ($type === 'takeaway' || $type === 'delivery') {
            return ['ready_takeaway', $settings->tpl_ready_takeaway ?? null];
        }
        return ['ready_dinein', $settings->tpl_ready_dinein ?? null];
    }

    public function failed(\Throwable $e): void
    {
        Log::warning("[WAHA] Progress job failed for order #{$this->orderId}: " . $e->getMessage());
    }
}
