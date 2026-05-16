<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\Outlet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared helpers for public (no-auth) ordering controllers — table & takeaway.
 *
 * Centralises:
 *   - outlet resolution
 *   - search_path swap
 *   - online-orderable payment-method lookup & validation
 *   - safe storage of customer-uploaded payment proof
 */
trait PublicOrderingHelpers
{
    protected function resolveOutletBySlug(string $slug): ?Outlet
    {
        return Outlet::where('slug', $slug)->first();
    }

    protected function useSchema(string $schema): void
    {
        DB::statement("SET search_path TO {$schema}, public");
    }

    protected function resetSchema(): void
    {
        DB::statement("SET search_path TO public");
    }

    /**
     * Heal outlet schema for online-order columns on read paths. Idempotent.
     * Older outlets provisioned before the online-order migration may still be
     * missing these columns and should not 500 on the public page.
     */
    protected function healOnlineOrderColumns(): void
    {
        $builder = DB::getSchemaBuilder();
        try {
            if (!$builder->hasColumn('payment_methods', 'is_online_orderable')) {
                DB::statement("ALTER TABLE payment_methods ADD COLUMN is_online_orderable BOOLEAN DEFAULT FALSE");
                DB::statement("UPDATE payment_methods SET is_online_orderable = TRUE WHERE code = 'qris'");
            }
            if (!$builder->hasColumn('payment_methods', 'qr_image_path')) {
                DB::statement("ALTER TABLE payment_methods ADD COLUMN qr_image_path VARCHAR(500) NULL");
            }
            if (!$builder->hasColumn('orders', 'payment_proof_path')) {
                DB::statement("ALTER TABLE orders ADD COLUMN payment_proof_path VARCHAR(500) NULL");
            }
            if (!$builder->hasColumn('orders', 'payment_proof_uploaded_at')) {
                DB::statement("ALTER TABLE orders ADD COLUMN payment_proof_uploaded_at TIMESTAMP NULL");
            }
        } catch (\Throwable $e) {
            // best-effort heal; ignore and let caller fail explicitly if column needed
        }
    }

    /**
     * Returns the payment methods that are active AND flagged as online-orderable.
     * Caller must already have switched search_path to the outlet schema.
     */
    protected function onlineOrderablePaymentMethods()
    {
        $rows = DB::table('payment_methods')
            ->where('is_active', true)
            ->where('is_online_orderable', true)
            ->whereNull('deleted_at')
            ->orderBy('display_order')
            ->get([
                'id', 'name', 'code', 'icon', 'display_order', 'qr_image_path',
            ]);
        return $rows->map(function ($r) {
            $r->qr_image_url = $this->publicProofUrl($r->qr_image_path ?? null);
            // do not leak raw filesystem path to public clients
            unset($r->qr_image_path);
            return $r;
        });
    }

    /**
     * Validate that $paymentMethodId belongs to the current outlet, is active,
     * and is allowed for public/online ordering. Caller must have set search_path.
     */
    protected function assertOnlinePaymentMethod($paymentMethodId): bool
    {
        return DB::table('payment_methods')
            ->where('id', $paymentMethodId)
            ->where('is_active', true)
            ->where('is_online_orderable', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Store an uploaded payment proof file safely.
     * Returns the storage path (relative to the 'public' disk) so caller can
     * persist it to orders.payment_proof_path.
     *
     * File is namespaced by outlet slug and ordercode to avoid collisions.
     */
    protected function storePaymentProof(UploadedFile $file, Outlet $outlet, string $orderCode): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeExt = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true) ? $ext : 'bin';
        $filename = 'proof_' . $orderCode . '_' . Str::random(8) . '.' . $safeExt;
        $dir = 'uploads/payment_proofs/' . $outlet->slug;
        return $file->storeAs($dir, $filename, 'public');
    }

    /**
     * Public URL for a stored proof path. Returns null if path empty.
     */
    protected function publicProofUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        return url(Storage::url($path));
    }
}
