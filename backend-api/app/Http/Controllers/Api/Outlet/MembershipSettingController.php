<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MembershipSettingController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Per-outlet membership_settings table. The base shape (point-system columns)
     * is created by OutletProvisioner. This controller also ensures the
     * public-page columns (registration_open, page_title, ...) exist.
     */
    private function ensureTable(string $schema): void
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
                    point_conversion_rate       INTEGER DEFAULT 1000,
                    point_per_rupiah            DECIMAL(10,2) DEFAULT 1.00,
                    point_expiry_days           INTEGER,
                    min_transaction_for_points  DECIMAL(15,2) DEFAULT 0,
                    tiers JSONB DEFAULT '[
                        {\"name\": \"Silver\", \"min_points\": 0, \"discount_percentage\": 0},
                        {\"name\": \"Gold\", \"min_points\": 1000, \"discount_percentage\": 5},
                        {\"name\": \"Platinum\", \"min_points\": 5000, \"discount_percentage\": 10}
                    ]'::jsonb,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            DB::statement("INSERT INTO {$schema}.membership_settings (id) VALUES (DEFAULT)");
        }

        // Public-page columns
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
     * GET /outlets/{outlet}/membership-settings
     */
    public function index($outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        try {
            $this->ensureTable($outlet->schema_name);

            $row = DB::table("{$outlet->schema_name}.membership_settings")->first();

            // Decode JSON columns
            if ($row) {
                $row->tiers    = $this->decodeJson($row->tiers ?? '[]');
                $row->benefits = $this->decodeJson($row->benefits ?? '[]');
            }

            return response()->json($row);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /outlets/{outlet}/membership-settings
     */
    public function update(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            // Point-system (legacy, all nullable so partial updates work)
            'point_conversion_rate'        => 'nullable|integer|min:1',
            'point_per_rupiah'             => 'nullable|numeric|min:0.01',
            'point_expiry_days'            => 'nullable|integer|min:1',
            'min_transaction_for_points'   => 'nullable|numeric|min:0',
            'tiers'                        => 'nullable',
            // Public page
            'registration_open'            => 'nullable|boolean',
            'page_title'                   => 'nullable|string|max:200',
            'page_description'             => 'nullable|string|max:2000',
            'benefits'                     => 'nullable',
            'welcome_message'              => 'nullable|string|max:2000',
            'require_phone'                => 'nullable|boolean',
            'require_address'              => 'nullable|boolean',
            'auto_approve'                 => 'nullable|boolean',
            'custom_primary_color'         => 'nullable|string|max:20',
            'custom_logo_url'              => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->ensureTable($outlet->schema_name);

            $bool = fn($key) => $request->has($key) ? (bool) $request->input($key) : null;

            $data = array_filter([
                'point_conversion_rate'      => $request->point_conversion_rate,
                'point_per_rupiah'           => $request->point_per_rupiah,
                'point_expiry_days'          => $request->point_expiry_days,
                'min_transaction_for_points' => $request->min_transaction_for_points,
                'tiers'                      => $request->has('tiers')    ? $this->encodeJson($request->tiers) : null,
                'registration_open'          => $bool('registration_open'),
                'page_title'                 => $request->page_title,
                'page_description'           => $request->page_description,
                'benefits'                   => $request->has('benefits') ? $this->encodeJson($request->benefits) : null,
                'welcome_message'            => $request->welcome_message,
                'require_phone'              => $bool('require_phone'),
                'require_address'            => $bool('require_address'),
                'auto_approve'               => $bool('auto_approve'),
                'custom_primary_color'       => $request->custom_primary_color,
                'custom_logo_url'            => $request->custom_logo_url,
                'updated_at'                 => now(),
            ], fn($v) => $v !== null);

            DB::table("{$outlet->schema_name}.membership_settings")->update($data);

            $row = DB::table("{$outlet->schema_name}.membership_settings")->first();
            if ($row) {
                $row->tiers    = $this->decodeJson($row->tiers ?? '[]');
                $row->benefits = $this->decodeJson($row->benefits ?? '[]');
            }

            return response()->json([
                'message' => 'Pengaturan membership berhasil disimpan',
                'data'    => $row,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function decodeJson($value)
    {
        if (is_array($value)) return $value;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function encodeJson($value): string
    {
        if (is_string($value)) {
            // Validate it's already JSON; else wrap it
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE ? $value : json_encode([]);
        }
        return json_encode($value ?? []);
    }
}
