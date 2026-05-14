<?php

namespace App\Models;

use App\Services\OutletProvisioner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'schema_name',
        'description',
        'address',
        'phone',
        'email',
        'logo',
        'website',
        'business_hours',
        'latitude',
        'longitude',
        'social_media',
        'is_active',
        'fixed_cost_percentage',
        'fixed_cost_type',
        'fixed_cost_nominal',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fixed_cost_percentage' => 'decimal:2',
        'fixed_cost_nominal' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'settings' => 'array',
        'social_media' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug and schema_name before creating
        static::creating(function ($outlet) {
            if (empty($outlet->slug)) {
                $outlet->slug = Str::slug($outlet->name);
            }
            
            // Generate unique schema name: user_{user_id}_{slug}
            // Replace dashes with underscores for PostgreSQL compatibility
            $cleanSlug = str_replace('-', '_', $outlet->slug);
            $outlet->schema_name = 'user_' . $outlet->user_id . '_' . $cleanSlug;
            
            // Ensure schema name is unique
            $counter = 1;
            $originalSchemaName = $outlet->schema_name;
            while (static::where('schema_name', $outlet->schema_name)->exists()) {
                $outlet->schema_name = $originalSchemaName . '_' . $counter;
                $counter++;
            }
        });

        // Fully provision the outlet (schema + all per-outlet tables + RBAC
        // seed) immediately after creation, and map the creating global user
        // as the outlet owner so they can clock in / manage this outlet.
        // Idempotent — safe to call repeatedly.
        static::created(function ($outlet) {
            $provisioner = app(OutletProvisioner::class);
            $provisioner->provision($outlet);

            $creator = null;
            if ($outlet->user_id) {
                $creator = \App\Models\User::find($outlet->user_id);
            }
            $creator = $creator ?: Auth::user();
            if ($creator) {
                $provisioner->mapOwner($outlet, $creator);
            }
        });

        // Drop PostgreSQL schema when outlet is deleted
        static::deleted(function ($outlet) {
            if ($outlet->isForceDeleting()) {
                $outlet->dropSchema();
            }
        });
    }

    /**
     * Provision the per-outlet schema and every table needed for the app.
     * Delegates to {@see OutletProvisioner} so command, controller, and
     * model-event call sites share one idempotent implementation.
     */
    public function createSchema()
    {
        return app(OutletProvisioner::class)->provision($this);
    }

    /**
     * Ensure HR tables (attendances, payroll_settings, leave_*, payrolls,
     * payroll_details, employee_info) exist in this outlet's schema.
     *
     * Idempotent: uses CREATE TABLE IF NOT EXISTS. Safe to call on every
     * HR-related request as a self-healing guard for outlets that were
     * created before HR provisioning ran.
     */
    public function ensureHRTables()
    {
        try {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$this->schema_name}");
            DB::statement("SET search_path TO {$this->schema_name}, public");

            DB::statement("
                CREATE TABLE IF NOT EXISTS employee_info (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    employee_code VARCHAR(50) UNIQUE,
                    join_date DATE NOT NULL,
                    employment_type VARCHAR(20) DEFAULT 'full_time',
                    basic_salary DECIMAL(15,2) DEFAULT 0,
                    hourly_rate DECIMAL(10,2) DEFAULT 0,
                    overtime_rate DECIMAL(10,2) DEFAULT 0,
                    bank_name VARCHAR(100),
                    bank_account VARCHAR(50),
                    bank_account_name VARCHAR(100),
                    emergency_contact_name VARCHAR(100),
                    emergency_contact_phone VARCHAR(20),
                    address TEXT,
                    day_off INTEGER DEFAULT 0,
                    is_active BOOLEAN DEFAULT true,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            DB::statement("
                CREATE TABLE IF NOT EXISTS attendances (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    date DATE NOT NULL,
                    clock_in TIMESTAMP,
                    clock_out TIMESTAMP,
                    clock_in_photo TEXT,
                    clock_out_photo TEXT,
                    clock_in_location TEXT,
                    clock_out_location TEXT,
                    clock_in_notes TEXT,
                    clock_out_notes TEXT,
                    work_hours DECIMAL(5,2) DEFAULT 0,
                    overtime_hours DECIMAL(5,2) DEFAULT 0,
                    status VARCHAR(20) DEFAULT 'present',
                    approved_by INTEGER REFERENCES outlet_users(id),
                    approved_at TIMESTAMP,
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, date)
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_attendances_user_date ON attendances(user_id, date)");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_attendances_date ON attendances(date)");

            DB::statement("
                CREATE TABLE IF NOT EXISTS leave_requests (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    leave_type VARCHAR(20) NOT NULL,
                    start_date DATE NOT NULL,
                    end_date DATE NOT NULL,
                    total_days INTEGER NOT NULL,
                    reason TEXT NOT NULL,
                    attachment VARCHAR(255),
                    status VARCHAR(20) DEFAULT 'pending',
                    reviewed_by INTEGER REFERENCES outlet_users(id),
                    reviewed_at TIMESTAMP,
                    review_notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_leave_requests_user ON leave_requests(user_id)");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON leave_requests(status)");

            DB::statement("
                CREATE TABLE IF NOT EXISTS leave_balances (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    year INTEGER NOT NULL,
                    leave_type VARCHAR(20) NOT NULL,
                    total_days INTEGER DEFAULT 0,
                    used_days INTEGER DEFAULT 0,
                    remaining_days INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, year, leave_type)
                )
            ");

            DB::statement("
                CREATE TABLE IF NOT EXISTS payrolls (
                    id SERIAL PRIMARY KEY,
                    user_id INTEGER NOT NULL REFERENCES outlet_users(id) ON DELETE CASCADE,
                    period_month INTEGER NOT NULL,
                    period_year INTEGER NOT NULL,
                    basic_salary DECIMAL(15,2) DEFAULT 0,
                    overtime_pay DECIMAL(15,2) DEFAULT 0,
                    allowances DECIMAL(15,2) DEFAULT 0,
                    bonuses DECIMAL(15,2) DEFAULT 0,
                    deductions DECIMAL(15,2) DEFAULT 0,
                    gross_salary DECIMAL(15,2) DEFAULT 0,
                    net_salary DECIMAL(15,2) DEFAULT 0,
                    work_days INTEGER DEFAULT 0,
                    present_days INTEGER DEFAULT 0,
                    absent_days INTEGER DEFAULT 0,
                    leave_days INTEGER DEFAULT 0,
                    late_days INTEGER DEFAULT 0,
                    overtime_hours DECIMAL(5,2) DEFAULT 0,
                    status VARCHAR(20) DEFAULT 'draft',
                    payment_date DATE,
                    payment_method VARCHAR(50),
                    notes TEXT,
                    created_by INTEGER REFERENCES outlet_users(id),
                    approved_by INTEGER REFERENCES outlet_users(id),
                    approved_at TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(user_id, period_month, period_year)
                )
            ");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_payrolls_user ON payrolls(user_id)");
            DB::statement("CREATE INDEX IF NOT EXISTS idx_payrolls_period ON payrolls(period_year, period_month)");

            DB::statement("
                CREATE TABLE IF NOT EXISTS payroll_details (
                    id SERIAL PRIMARY KEY,
                    payroll_id INTEGER NOT NULL REFERENCES payrolls(id) ON DELETE CASCADE,
                    type VARCHAR(20) NOT NULL,
                    description VARCHAR(255) NOT NULL,
                    amount DECIMAL(15,2) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $settingsExisted = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_schema = current_schema()
                    AND table_name = 'payroll_settings'
                ) AS exists
            ")->exists;

            DB::statement("
                CREATE TABLE IF NOT EXISTS payroll_settings (
                    id SERIAL PRIMARY KEY,
                    work_days_per_month INTEGER DEFAULT 22,
                    work_hours_per_day DECIMAL(4,1) DEFAULT 8.0,
                    overtime_multiplier DECIMAL(3,1) DEFAULT 1.5,
                    late_tolerance_minutes INTEGER DEFAULT 15,
                    annual_leave_days INTEGER DEFAULT 12,
                    sick_leave_days INTEGER DEFAULT 12,
                    tax_percentage DECIMAL(5,2) DEFAULT 0,
                    attendance_location_lat DECIMAL(10,8),
                    attendance_location_lng DECIMAL(11,8),
                    attendance_radius INTEGER DEFAULT 100,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            if (!$settingsExisted) {
                DB::statement("
                    INSERT INTO payroll_settings (work_days_per_month, work_hours_per_day, overtime_multiplier, late_tolerance_minutes, annual_leave_days, sick_leave_days, tax_percentage, attendance_radius)
                    VALUES (22, 8.0, 1.5, 15, 12, 12, 0, 100)
                ");
            }

            DB::statement("SET search_path TO public");
            return true;
        } catch (\Exception $e) {
            DB::statement("SET search_path TO public");
            \Log::error("Failed to ensure HR tables for outlet {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Drop PostgreSQL schema for this outlet
     */
    public function dropSchema()
    {
        try {
            DB::statement("DROP SCHEMA IF EXISTS {$this->schema_name} CASCADE");
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to drop schema for outlet {$this->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set the current connection to use this outlet's schema
     */
    public function useSchema()
    {
        DB::statement("SET search_path TO {$this->schema_name}, public");
    }

    /**
     * Reset to public schema
     */
    public static function resetSchema()
    {
        DB::statement("SET search_path TO public");
    }

    /**
     * Get outlet users from the outlet's schema
     */
    public function getUsers()
    {
        $this->useSchema();
        $users = DB::table('outlet_users')
            ->where('outlet_id', $this->id)
            ->whereNull('deleted_at')
            ->get();
        static::resetSchema();
        
        return $users;
    }

    /**
     * Relationships
     */
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
