<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use SoftDeletes;

    protected $table = 'tables';

    protected $fillable = [
        'table_number',
        'capacity',
        'area',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'integer',
    ];

    protected $appends = ['status_label'];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByArea($query, $area)
    {
        return $query->where('area', $area);
    }

    /**
     * Accessors
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'available' => 'Tersedia',
            'occupied' => 'Terisi',
            'reserved' => 'Dipesan',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    /**
     * Methods
     */
    public function markAsOccupied()
    {
        $this->status = 'occupied';
        $this->save();
    }

    public function markAsAvailable()
    {
        $this->status = 'available';
        $this->save();
    }

    public function markAsReserved()
    {
        $this->status = 'reserved';
        $this->save();
    }
}
