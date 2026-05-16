<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public membership endpoints — no authentication required.
 *
 *   GET  /api/public/membership/{outletSlug}
 *   POST /api/public/membership/{outletSlug}/otp/request
 *   POST /api/public/membership/{outletSlug}/otp/verify
 *   POST /api/public/membership/{outletSlug}/register   (legacy: now requires verify_token)
 *
 * Outlet is resolved by `outlets.slug`. The membership_settings table
 * (per-outlet schema) is ensured/extended with the public-page columns.
 *
 * Registration is OTP-gated: the customer's WhatsApp number is verified by a
 * 6-digit code sent via WAHA before the member row is created and the
 * password is stored. OTPs are stored hashed with bcrypt and expire after
 * 10 minutes.
 */
class MembershipController extends Controller
{
    /** OTP behaviour constants */
    private const OTP_TTL_SECONDS    = 600;   // 10 min
    private const OTP_RESEND_COOLDOWN = 60;   // 60 s between resends per phone
    private const OTP_MAX_ATTEMPTS   = 5;     // brute-force guard

    /**
     * Ensure the per-outlet membership_settings table has the public-page
     * columns. The base table is created by OutletProvisioner.
     */
    private function ensurePublicColumns(string $schema): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'membership_settings'
            ) AS ex
        ", [$schema]);

        if (!$exists->ex) {
            DB::statement("
                CREATE TABLE {$schema}.membership_settings (
                    id SERIAL PRIMARY KEY,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            DB::statement("INSERT INTO {$schema}.membership_settings (id) VALUES (DEFAULT)");
        }

        $this->addColumnIfMissing($schema, 'registration_open',    'BOOLEAN DEFAULT false');
        $this->addColumnIfMissing($schema, 'page_title',           "VARCHAR(200) DEFAULT 'Daftar Member'");
        $this->addColumnIfMissing($schema, 'page_description',     "TEXT DEFAULT ''");
        $this->addColumnIfMissing($schema, 'benefits',             "JSONB DEFAULT '[]'::jsonb");
        $this->addColumnIfMissing($schema, 'welcome_message',      "TEXT DEFAULT 'Selamat datang di program member kami!'");
        $this->addColumnIfMissing($schema, 'require_phone',        'BOOLEAN DEFAULT true');
        $this->addColumnIfMissing($schema, 'require_address',      'BOOLEAN DEFAULT false');
        $this->addColumnIfMissing($schema, 'auto_approve',         'BOOLEAN DEFAULT true');
        $this->addColumnIfMissing($schema, 'custom_primary_color', "VARCHAR(20) DEFAULT ''");
        $this->addColumnIfMissing($schema, 'custom_logo_url',      "TEXT DEFAULT ''");

        $hasRow = DB::selectOne("SELECT EXISTS (SELECT 1 FROM {$schema}.membership_settings LIMIT 1) AS ex");
        if (!$hasRow->ex) {
            DB::statement("INSERT INTO {$schema}.membership_settings (id) VALUES (DEFAULT)");
        }
    }

    /** Default form: ensure a column on `membership_settings`. */
    private function addColumnIfMissing(string $schema, string $column, string $definition): void
    {
        $this->addColumnTo($schema, 'membership_settings', $column, $definition);
    }

    /** Generic form: ensure a column on an arbitrary table. */
    private function addColumnTo(string $schema, string $table, string $column, string $definition): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name = ?
            ) AS ex
        ", [$schema, $table, $column]);
        if (!$exists->ex) {
            DB::statement("ALTER TABLE {$schema}.{$table} ADD COLUMN {$column} {$definition}");
        }
    }

    /**
     * Ensure members table has `status` and `phone_verified_at` columns.
     */
    private function ensureMemberStatusColumn(string $schema): void
    {
        $this->addColumnTo($schema, 'members', 'status',             "VARCHAR(20) DEFAULT 'active'");
        $this->addColumnTo($schema, 'members', 'phone_verified_at',  "TIMESTAMP NULL");
    }

    /**
     * Per-outlet table that stores pending OTPs (hashed). Created on demand
     * the first time an outlet's OTP flow is used.
     */
    private function ensureOtpTable(string $schema): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS {$schema}.wa_otp_codes (
                id SERIAL PRIMARY KEY,
                phone VARCHAR(50) NOT NULL,
                code_hash VARCHAR(255) NOT NULL,
                purpose VARCHAR(30) NOT NULL DEFAULT 'member_register',
                attempts INTEGER NOT NULL DEFAULT 0,
                payload JSONB,
                expires_at TIMESTAMP NOT NULL,
                verified_at TIMESTAMP NULL,
                verify_token VARCHAR(80) NULL,
                last_sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_wa_otp_phone ON {$schema}.wa_otp_codes(phone, purpose)");
    }

    private function resolveOutlet(string $slug): ?Outlet
    {
        return Outlet::where('slug', $slug)->first();
    }

    /**
     * Normalise an Indonesian phone number to a single canonical form so
     * "0812…", "+62812…" and "62812…" don't end up as different rows.
     */
    private function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') return '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }

    /**
     * GET /api/public/membership/{outletSlug}
     */
    public function show(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->ensurePublicColumns($outlet->schema_name);

            $row = DB::table("{$outlet->schema_name}.membership_settings")->first();

            $benefits = [];
            if ($row && !empty($row->benefits)) {
                $benefits = is_string($row->benefits)
                    ? (json_decode($row->benefits, true) ?: [])
                    : (array) $row->benefits;
            }

            return response()->json([
                'outlet' => [
                    'id'      => $outlet->id,
                    'name'    => $outlet->name,
                    'slug'    => $outlet->slug,
                    'logo'    => $outlet->logo,
                    'address' => $outlet->address,
                    'phone'   => $outlet->phone,
                ],
                'settings' => [
                    'registration_open'    => $row ? (bool) ($row->registration_open ?? false) : false,
                    'page_title'           => $row->page_title           ?? 'Daftar Member',
                    'page_description'     => $row->page_description     ?? '',
                    'benefits'             => $benefits,
                    'welcome_message'      => $row->welcome_message      ?? 'Selamat datang di program member kami!',
                    'require_phone'        => $row ? (bool) ($row->require_phone ?? true) : true,
                    'require_address'      => $row ? (bool) ($row->require_address ?? false) : false,
                    'custom_primary_color' => $row->custom_primary_color ?? '',
                    'custom_logo_url'      => $row->custom_logo_url      ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/public/membership/{outletSlug}/otp/request
     *
     * Body: { name, phone, email?, address? }
     *
     * Sends a 6-digit OTP via WAHA to the supplied phone. Stores the OTP
     * hash plus the prospective profile payload in `wa_otp_codes` so a
     * subsequent verify call can finalise the registration without
     * trusting the client to resend the same payload.
     */
    public function requestOtp(Request $request, string $outletSlug, WahaService $waha)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->ensurePublicColumns($outlet->schema_name);
            $this->ensureMemberStatusColumn($outlet->schema_name);
            $this->ensureOtpTable($outlet->schema_name);

            $settings = DB::table("{$outlet->schema_name}.membership_settings")->first();
            if (!$settings || !($settings->registration_open ?? false)) {
                return response()->json(['message' => 'Pendaftaran sedang ditutup'], 403);
            }

            $validator = Validator::make($request->all(), [
                'name'    => 'required|string|max:100',
                'phone'   => 'required|string|max:50',
                'email'   => 'nullable|email|max:100',
                'address' => 'nullable|string|max:500',
            ]);
            if ($validator->fails()) {
                return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
            }

            $phone = $this->normalisePhone((string) $request->input('phone'));
            if ($phone === '') {
                return response()->json(['message' => 'Nomor HP tidak valid'], 422);
            }

            $schema = $outlet->schema_name;

            // Duplicate check against existing members (active or pending)
            $duplicate = DB::table("{$schema}.members")
                ->whereNull('deleted_at')
                ->where(function ($q) use ($phone, $request) {
                    $q->where('phone', $phone);
                    if (!empty($request->email)) {
                        $q->orWhereRaw('LOWER(email) = ?', [strtolower((string) $request->email)]);
                    }
                })
                ->exists();
            if ($duplicate) {
                return response()->json([
                    'message' => 'Member dengan nomor HP atau email ini sudah terdaftar. Silakan login.',
                ], 409);
            }

            // Resend cooldown — return success but skip re-send if the previous
            // OTP for this phone is still fresh and a new one was sent < 60s ago.
            $existing = DB::table("{$schema}.wa_otp_codes")
                ->where('phone', $phone)
                ->where('purpose', 'member_register')
                ->orderByDesc('id')
                ->first();
            if ($existing && $existing->last_sent_at) {
                $age = now()->diffInSeconds(\Carbon\Carbon::parse($existing->last_sent_at));
                if ($age < self::OTP_RESEND_COOLDOWN && empty($existing->verified_at)) {
                    return response()->json([
                        'success' => true,
                        'message' => 'OTP baru saja dikirim. Mohon tunggu sebentar sebelum meminta ulang.',
                        'cooldown_seconds' => max(1, self::OTP_RESEND_COOLDOWN - $age),
                    ]);
                }
            }

            // Generate OTP and persist (hashed)
            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $hash = Hash::make($otp);
            $payload = [
                'name'    => (string) $request->input('name'),
                'email'   => $request->input('email'),
                'phone'   => $phone,
                'address' => $request->input('address'),
            ];

            // Replace any previous pending OTPs for this phone+purpose so the
            // table doesn't grow unbounded.
            DB::table("{$schema}.wa_otp_codes")
                ->where('phone', $phone)
                ->where('purpose', 'member_register')
                ->whereNull('verified_at')
                ->delete();

            DB::table("{$schema}.wa_otp_codes")->insert([
                'phone'        => $phone,
                'code_hash'    => $hash,
                'purpose'      => 'member_register',
                'attempts'     => 0,
                'payload'      => json_encode($payload),
                'expires_at'   => now()->addSeconds(self::OTP_TTL_SECONDS),
                'last_sent_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Send via WAHA synchronously (UX needs the message within seconds).
            // Failure should NOT leak the OTP — log a generic warning instead.
            $message = "Kode OTP {$outlet->name}: *{$otp}*\n"
                . "Berlaku 10 menit. Jangan bagikan kode ini kepada siapa pun.";
            $sent = false;
            try {
                $sent = $waha->sendText($phone, $message);
            } catch (\Throwable $e) {
                Log::warning('[WAHA][OTP] Exception sending OTP: ' . $e->getMessage());
            }

            if (!$waha->isEnabled()) {
                // Operator hasn't enabled WAHA — we can't deliver OTP.
                return response()->json([
                    'message' => 'Layanan verifikasi WhatsApp belum aktif di outlet ini. Hubungi outlet.',
                ], 503);
            }

            if (!$sent) {
                Log::warning('[WAHA][OTP] sendText returned false for phone ' . $phone);
                return response()->json([
                    'message' => 'Gagal mengirim OTP ke WhatsApp. Pastikan nomor sudah aktif di WhatsApp.',
                ], 502);
            }

            return response()->json([
                'success' => true,
                'message' => 'OTP telah dikirim ke WhatsApp Anda. Kode berlaku 10 menit.',
                'expires_in' => self::OTP_TTL_SECONDS,
                'phone' => $phone,
            ]);
        } catch (\Exception $e) {
            Log::error('[OTP] request failed: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan. Coba lagi.'], 500);
        }
    }

    /**
     * POST /api/public/membership/{outletSlug}/otp/verify
     *
     * Body: { phone, code, password, password_confirmation }
     *
     * On success: creates the member row (using the payload from request_otp)
     * and returns the same shape as the legacy register endpoint.
     */
    public function verifyOtp(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->ensurePublicColumns($outlet->schema_name);
            $this->ensureMemberStatusColumn($outlet->schema_name);
            $this->ensureOtpTable($outlet->schema_name);

            $validator = Validator::make($request->all(), [
                'phone' => 'required|string|max:50',
                'code'  => 'required|string|size:6',
                'password' => 'required|string|min:6|max:255|confirmed',
            ]);
            if ($validator->fails()) {
                return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
            }

            $schema = $outlet->schema_name;
            $phone  = $this->normalisePhone((string) $request->input('phone'));

            $otp = DB::table("{$schema}.wa_otp_codes")
                ->where('phone', $phone)
                ->where('purpose', 'member_register')
                ->whereNull('verified_at')
                ->orderByDesc('id')
                ->first();

            if (!$otp) {
                return response()->json(['message' => 'OTP tidak ditemukan. Mohon kirim ulang OTP.'], 404);
            }
            if ($otp->expires_at && now()->greaterThan(\Carbon\Carbon::parse($otp->expires_at))) {
                return response()->json(['message' => 'OTP sudah kadaluarsa. Kirim ulang OTP.'], 410);
            }
            if ((int) $otp->attempts >= self::OTP_MAX_ATTEMPTS) {
                return response()->json(['message' => 'Terlalu banyak percobaan. Kirim ulang OTP.'], 429);
            }

            if (!Hash::check((string) $request->input('code'), $otp->code_hash)) {
                DB::table("{$schema}.wa_otp_codes")
                    ->where('id', $otp->id)
                    ->update(['attempts' => (int) $otp->attempts + 1, 'updated_at' => now()]);
                return response()->json(['message' => 'Kode OTP salah.'], 422);
            }

            $payload = json_decode((string) $otp->payload, true) ?: [];

            // Duplicate guard (race between request_otp and verify_otp)
            $duplicate = DB::table("{$schema}.members")
                ->whereNull('deleted_at')
                ->where(function ($q) use ($phone, $payload) {
                    $q->where('phone', $phone);
                    if (!empty($payload['email'])) {
                        $q->orWhereRaw('LOWER(email) = ?', [strtolower((string) $payload['email'])]);
                    }
                })
                ->exists();
            if ($duplicate) {
                DB::table("{$schema}.wa_otp_codes")->where('id', $otp->id)->update([
                    'verified_at' => now(),
                    'updated_at'  => now(),
                ]);
                return response()->json([
                    'message' => 'Member dengan nomor HP atau email ini sudah terdaftar. Silakan login.',
                ], 409);
            }

            $settings = DB::table("{$schema}.membership_settings")->first();
            $autoApprove = (bool) ($settings->auto_approve ?? true);
            $status = $autoApprove ? 'active' : 'pending';

            $cardNumber = 'MBR' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            while (DB::table("{$schema}.members")->where('card_number', $cardNumber)->exists()) {
                $cardNumber = 'MBR' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            $memberId = DB::table("{$schema}.members")->insertGetId([
                'card_number'        => $cardNumber,
                'nama'               => (string) ($payload['name'] ?? ''),
                'phone'              => $phone,
                'email'              => $payload['email'] ?? null,
                'alamat'             => $payload['address'] ?? null,
                'password'           => Hash::make((string) $request->input('password')),
                'points'             => 0,
                'tier'               => 'Silver',
                'joined_at'          => now(),
                'is_active'          => $autoApprove,
                'status'             => $status,
                'phone_verified_at'  => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            DB::table("{$schema}.wa_otp_codes")->where('id', $otp->id)->update([
                'verified_at' => now(),
                'updated_at'  => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pendaftaran berhasil. Selamat datang!',
                'member'  => [
                    'id'          => $memberId,
                    'name'        => (string) ($payload['name'] ?? ''),
                    'nama'        => (string) ($payload['name'] ?? ''),
                    'card_number' => $cardNumber,
                    'member_code' => $cardNumber,
                    'phone'       => $phone,
                    'email'       => $payload['email'] ?? null,
                    'tier'        => 'Silver',
                    'points'      => 0,
                    'status'      => $status,
                    'has_password'=> true,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('[OTP] verify failed: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan. Coba lagi.'], 500);
        }
    }

    /**
     * POST /api/public/membership/{outletSlug}/register
     *
     * Legacy endpoint kept for backward compatibility. It now requires the
     * client to have completed the OTP flow first (verify_token); without
     * one it returns 403 directing the user to the new flow.
     */
    public function register(Request $request, string $outletSlug)
    {
        return response()->json([
            'message' => 'Pendaftaran member kini harus melalui verifikasi OTP WhatsApp. Gunakan endpoint /otp/request lalu /otp/verify.',
            'code'    => 'OTP_REQUIRED',
        ], 410);
    }
}
