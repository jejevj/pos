<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateWeatherTables extends Command
{
    protected $signature = 'create:weather-tables';
    protected $description = 'Create weather data tables in public schema';

    public function handle()
    {
        try {
            // Create weather_data table in public schema
            DB::statement("
                CREATE TABLE IF NOT EXISTS public.weather_data (
                    id SERIAL PRIMARY KEY,
                    outlet_id INTEGER NOT NULL REFERENCES public.outlets(id) ON DELETE CASCADE,
                    recorded_at TIMESTAMP NOT NULL,
                    latitude DECIMAL(10, 8) NOT NULL,
                    longitude DECIMAL(11, 8) NOT NULL,
                    city_name VARCHAR(255),
                    temperature DECIMAL(5, 2) NOT NULL,
                    feels_like DECIMAL(5, 2),
                    humidity INTEGER,
                    pressure INTEGER,
                    wind_speed DECIMAL(5, 2),
                    wind_direction INTEGER,
                    cloud_cover INTEGER,
                    weather_code INTEGER,
                    weather_description VARCHAR(255),
                    weather_icon VARCHAR(10),
                    visibility INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Create index for faster queries
            DB::statement("
                CREATE INDEX IF NOT EXISTS idx_weather_outlet_recorded 
                ON public.weather_data(outlet_id, recorded_at DESC)
            ");

            DB::statement("
                CREATE INDEX IF NOT EXISTS idx_weather_recorded_at 
                ON public.weather_data(recorded_at DESC)
            ");

            // Create unique constraint to prevent duplicate records
            DB::statement("
                CREATE UNIQUE INDEX IF NOT EXISTS idx_weather_unique_outlet_hour 
                ON public.weather_data(outlet_id, DATE_TRUNC('hour', recorded_at))
            ");

            $this->info('Weather tables created successfully!');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Error creating weather tables: ' . $e->getMessage());
            return 1;
        }
    }
}
