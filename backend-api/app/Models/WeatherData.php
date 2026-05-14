<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherData extends Model
{
    protected $table = 'weather_data';
    
    protected $fillable = [
        'outlet_id',
        'recorded_at',
        'latitude',
        'longitude',
        'city_name',
        'temperature',
        'feels_like',
        'humidity',
        'pressure',
        'wind_speed',
        'wind_direction',
        'cloud_cover',
        'weather_code',
        'weather_description',
        'weather_icon',
        'visibility',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'temperature' => 'decimal:2',
        'feels_like' => 'decimal:2',
        'wind_speed' => 'decimal:2',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
