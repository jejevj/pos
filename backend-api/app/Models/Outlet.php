<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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

        // Create PostgreSQL schema after outlet is created
        static::created(function ($outlet) {
            $outlet->createSchema();
        });

        // Drop PostgreSQL schema when outlet is deleted
        static::deleted(function ($outlet) {
            if ($outlet->isForceDeleting()) {
                $outlet->dropSchema();
            }
        });
    }

    /**
     * Create PostgreSQL schema for this outlet
     */
    public function createSchema()
    {
        try {
            \Log::info("Creating schema: {$this->schema_name} for outlet ID: {$this->id}");
            
            // Create schema
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$this->schema_name}");
            \Log::info("Schema created: {$this->schema_name}");
            
            // Create outlet_users table in the new schema
            DB::statement("
                CREATE TABLE IF NOT EXISTS {$this->schema_name}.outlet_users (
                    id SERIAL PRIMARY KEY,
                    outlet_id INTEGER NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL UNIQUE,
                    password VARCHAR(255) NOT NULL,
                    phone VARCHAR(255),
                    role VARCHAR(50) DEFAULT 'staff',
                    is_active BOOLEAN DEFAULT true,
                    settings JSONB,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    deleted_at TIMESTAMP
                )
            ");
            \Log::info("Table outlet_users created in schema: {$this->schema_name}");

            // Create index on email
            DB::statement("
                CREATE INDEX IF NOT EXISTS idx_outlet_users_email 
                ON {$this->schema_name}.outlet_users(email)
            ");
            \Log::info("Index created on outlet_users.email in schema: {$this->schema_name}");

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create schema for outlet {$this->id}: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
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
