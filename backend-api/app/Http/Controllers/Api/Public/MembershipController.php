<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Public membership endpoints — no authentication required.
 *   GET  /api/public/membership/{outletSlug}
 *   POST /api/public/membership/{outletSlug}/register
 *
 * Outlet is resolved by `outlets.slug`. The membership_settings table
 * (per-outlet schema) is ensured/extended with the public-page columns.
 */
class MembershipController extends Controller
{
    /**
     * Ensure the per-outlet membership_settings table has the public-page
     * columns. The base table is created by OutletProvisioner.
     */
    private function ensurePublicColumns(string $schema): void
    {
        // If the table doesn't exist yet (very old outlet), create the minimal shape.
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

        // Ensure a row exists
        $hasRow = DB::selectOne("SELECT EXISTS (SELECT 1 FROM {$schema}.membership_settings LIMIT 1) AS ex");
        if (!$hasRow->ex) {
            DB::statement("INSERT INTO {$schema}.membership_settings (id) VALUES (DEFAULT)");
        }
    }

    private function addColumnIfMissing(string $schema, string $column, string $definition): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = 'membership_settings' AND column_name = ?
            ) AS ex
        ", [$schema, $column]);
        if (!$exists->ex) {
            DB::statement("ALTER TABLE {$schema}.membership_settings ADD COLUMN {$column} {$definition}");
        }
    }

    /**
     * Ensure members table has a `status` column for pending/active flow.
     */
    private function ensureMemberStatusColumn(string $schema): void
    {
        $exists = DB::selectOne("
            SELECT EXISTS (
                SELECT 1 FROM information_schema.columns
                WHERE table_schema = ? AND table_name = 'members' AND column_name = 'status'
            ) AS ex
        ", [$schema]);
        if (!$exists->ex) {
            DB::statement("ALTER TABLE {$schema}.members ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
        }
    }

    private function resolveOutlet(string $slug): ?Outlet
    {
        return Outlet::where('slug', $slug)->first();
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
     * POST /api/public/membership/{outletSlug}/register
     */
    public function register(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutlet($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        try {
            $this->ensurePublicColumns($outlet->schema_name);
            $this->ensureMemberStatusColumn($outlet->schema_name);

            $settings = DB::table("{$outlet->schema_name}.membership_settings")->first();

            if (!$settings || !($settings->registration_open ?? false)) {
                return response()->json(['message' => 'Pendaftaran sedang ditutup'], 403);
            }

            $rules = [
                'name'  => 'required|string|max:100',
                'email' => 'nullable|email|max:100',
            ];
            if ($settings->require_phone ?? true) {
                $rules['phone'] = 'required|string|max:50';
            } else {
                $rules['phone'] = 'nullable|string|max:50';
            }
            if ($settings->require_address ?? false) {
                $rules['address'] = 'required|string|max:500';
            } else {
                $rules['address'] = 'nullable|string|max:500';
            }

            // Need either email or phone to identify the member
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
            }

            if (empty($request->email) && empty($request->phone)) {
                return response()->json([
                    'message' => 'Email atau nomor telepon harus diisi',
                ], 422);
            }

            $schema = $outlet->schema_name;

            // Duplicate check
            $duplicateQ = DB::table("{$schema}.members")->whereNull('deleted_at');
            $duplicateQ->where(function ($q) use ($request) {
                if (!empty($request->email)) {
                    $q->orWhere('email', $request->email);
                }
                if (!empty($request->phone)) {
                    $q->orWhere('phone', $request->phone);
                }
            });
            if ($duplicateQ->exists()) {
                return response()->json([
                    'message' => 'Member dengan email atau nomor telepon ini sudah terdaftar',
                ], 409);
            }

            // Generate card number
            $cardNumber = 'MBR' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            // Make sure unique (retry once if collision — extremely unlikely)
            while (DB::table("{$schema}.members")->where('card_number', $cardNumber)->exists()) {
                $cardNumber = 'MBR' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            }

            $autoApprove = (bool) ($settings->auto_approve ?? true);
            $status = $autoApprove ? 'active' : 'pending';

            DB::table("{$schema}.members")->insert([
                'card_number'  => $cardNumber,
                'nama'         => $request->name,
                'phone'        => $request->phone,
                'email'        => $request->email,
                'alamat'       => $request->address,
                'points'       => 0,
                'tier'         => 'Silver',
                'joined_at'    => now(),
                'is_active'    => $autoApprove,
                'status'       => $status,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            return response()->json([
                'success' => true,
                'member'  => [
                    'name'        => $request->name,
                    'member_code' => $cardNumber,
                    'status'      => $status,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
