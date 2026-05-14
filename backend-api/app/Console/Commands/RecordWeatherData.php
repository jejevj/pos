<?php

namespace App\Console\Commands;

use App\Models\Outlet;
use App\Models\WeatherData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecordWeatherData extends Command
{
    protected $signature = 'weather:record {--outlet-id=} {--backfill-days=} {--date=}';
    protected $description = 'Record weather data for all outlets or specific outlet. Supports backfilling historical data.';

    public function handle()
    {
        $outletId = $this->option('outlet-id');
        $backfillDays = $this->option('backfill-days');
        $specificDate = $this->option('date');
        
        if ($outletId) {
            $outlets = Outlet::where('id', $outletId)->get();
        } else {
            $outlets = Outlet::all();
        }

        if ($outlets->isEmpty()) {
            $this->warn('No outlets found.');
            return 0;
        }

        // Backfill mode: fetch historical data
        if ($backfillDays || $specificDate) {
            return $this->backfillHistoricalData($outlets, $backfillDays, $specificDate);
        }

        // Normal mode: record current weather
        $this->info("Recording current weather for {$outlets->count()} outlet(s)...");

        foreach ($outlets as $outlet) {
            try {
                $this->recordCurrentWeather($outlet);
                $this->info("✓ Recorded weather for outlet: {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("✗ Failed for outlet {$outlet->name}: " . $e->getMessage());
                Log::error("Weather recording failed for outlet {$outlet->id}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        $this->info('Weather recording completed!');
        return 0;
    }

    private function backfillHistoricalData($outlets, $backfillDays, $specificDate)
    {
        if ($specificDate) {
            $startDate = \Carbon\Carbon::parse($specificDate)->startOfDay();
            $endDate = $startDate->copy()->endOfDay();
            $this->info("Backfilling weather data for date: {$specificDate}");
        } else {
            $days = intval($backfillDays);
            if ($days < 1 || $days > 90) {
                $this->error('Backfill days must be between 1 and 90');
                return 1;
            }
            $endDate = now()->startOfDay();
            $startDate = $endDate->copy()->subDays($days);
            $this->info("Backfilling weather data for last {$days} days...");
        }

        $totalRecords = 0;

        foreach ($outlets as $outlet) {
            $this->info("Processing outlet: {$outlet->name}");
            
            try {
                $records = $this->fetchHistoricalWeather($outlet, $startDate, $endDate);
                $totalRecords += $records;
                $this->info("✓ Recorded {$records} hourly records for {$outlet->name}");
            } catch (\Exception $e) {
                $this->error("✗ Failed for outlet {$outlet->name}: " . $e->getMessage());
                Log::error("Historical weather fetch failed for outlet {$outlet->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("Backfill completed! Total records: {$totalRecords}");
        return 0;
    }

    private function fetchHistoricalWeather(Outlet $outlet, $startDate, $endDate)
    {
        $latitude = $outlet->latitude ?? -6.2088;
        $longitude = $outlet->longitude ?? 106.8456;

        // Format dates for API
        $start = $startDate->format('Y-m-d');
        $end = $endDate->format('Y-m-d');

        // Fetch hourly historical data from Open-Meteo
        $response = Http::timeout(60)->get('https://archive-api.open-meteo.com/v1/archive', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'start_date' => $start,
            'end_date' => $end,
            'hourly' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m',
            'timezone' => 'Asia/Jakarta'
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch historical weather data from API');
        }

        $data = $response->json();
        $hourly = $data['hourly'];

        // Get city name once (cache it)
        $cityName = $this->getCityName($latitude, $longitude);

        $recordCount = 0;

        // Process each hour
        foreach ($hourly['time'] as $index => $time) {
            $weatherCode = $hourly['weather_code'][$index];
            $weatherInfo = $this->getWeatherDescription($weatherCode);

            WeatherData::updateOrCreate(
                [
                    'outlet_id' => $outlet->id,
                    'recorded_at' => \Carbon\Carbon::parse($time)->startOfHour(),
                ],
                [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'city_name' => $cityName,
                    'temperature' => $hourly['temperature_2m'][$index],
                    'feels_like' => $hourly['apparent_temperature'][$index],
                    'humidity' => $hourly['relative_humidity_2m'][$index],
                    'pressure' => $hourly['pressure_msl'][$index],
                    'wind_speed' => $hourly['wind_speed_10m'][$index],
                    'wind_direction' => $hourly['wind_direction_10m'][$index],
                    'cloud_cover' => $hourly['cloud_cover'][$index],
                    'weather_code' => $weatherCode,
                    'weather_description' => $weatherInfo['desc'],
                    'weather_icon' => $weatherInfo['icon'],
                    'visibility' => 10000,
                ]
            );

            $recordCount++;
        }

        return $recordCount;
    }

    private function recordCurrentWeather(Outlet $outlet)
    {
        // Get outlet location (you may want to add lat/lon columns to outlets table)
        // For now, we'll use a default location or outlet address
        // You should add latitude and longitude columns to outlets table
        
        // Default to Jakarta if outlet doesn't have coordinates
        $latitude = $outlet->latitude ?? -6.2088;
        $longitude = $outlet->longitude ?? 106.8456;

        // Fetch weather data from Open-Meteo API
        $response = Http::timeout(30)->get('https://api.open-meteo.com/v1/forecast', [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,weather_code,cloud_cover,pressure_msl,wind_speed_10m,wind_direction_10m',
            'timezone' => 'Asia/Jakarta'
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch weather data from API');
        }

        $data = $response->json();
        $current = $data['current'];

        // Get city name from Nominatim (with rate limiting)
        $cityName = $this->getCityName($latitude, $longitude);

        // Map weather code to description
        $weatherInfo = $this->getWeatherDescription($current['weather_code']);

        // Record weather data (will update if record for this hour already exists)
        WeatherData::updateOrCreate(
            [
                'outlet_id' => $outlet->id,
                'recorded_at' => now()->startOfHour(), // Round to hour
            ],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'city_name' => $cityName,
                'temperature' => $current['temperature_2m'],
                'feels_like' => $current['apparent_temperature'],
                'humidity' => $current['relative_humidity_2m'],
                'pressure' => $current['pressure_msl'],
                'wind_speed' => $current['wind_speed_10m'],
                'wind_direction' => $current['wind_direction_10m'],
                'cloud_cover' => $current['cloud_cover'],
                'weather_code' => $current['weather_code'],
                'weather_description' => $weatherInfo['desc'],
                'weather_icon' => $weatherInfo['icon'],
                'visibility' => 10000, // Open-Meteo doesn't provide this
            ]
        );
    }

    private function getCityName($latitude, $longitude)
    {
        try {
            // Add delay to respect Nominatim usage policy (max 1 request per second)
            sleep(1);
            
            $response = Http::timeout(10)
                ->withHeaders(['Accept-Language' => 'id'])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'json',
                    'lat' => $latitude,
                    'lon' => $longitude,
                    'addressdetails' => 1
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $address = $data['address'] ?? [];
                
                return $address['city'] 
                    ?? $address['town'] 
                    ?? $address['village'] 
                    ?? $address['county'] 
                    ?? $address['state'] 
                    ?? 'Unknown';
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get city name from Nominatim', [
                'error' => $e->getMessage()
            ]);
        }

        return 'Unknown';
    }

    private function getWeatherDescription($code)
    {
        $weatherCodes = [
            0 => ['desc' => 'Cerah', 'icon' => '01d'],
            1 => ['desc' => 'Sebagian Cerah', 'icon' => '02d'],
            2 => ['desc' => 'Berawan Sebagian', 'icon' => '03d'],
            3 => ['desc' => 'Berawan', 'icon' => '04d'],
            45 => ['desc' => 'Berkabut', 'icon' => '50d'],
            48 => ['desc' => 'Kabut Tebal', 'icon' => '50d'],
            51 => ['desc' => 'Gerimis Ringan', 'icon' => '09d'],
            53 => ['desc' => 'Gerimis', 'icon' => '09d'],
            55 => ['desc' => 'Gerimis Lebat', 'icon' => '09d'],
            61 => ['desc' => 'Hujan Ringan', 'icon' => '10d'],
            63 => ['desc' => 'Hujan', 'icon' => '10d'],
            65 => ['desc' => 'Hujan Lebat', 'icon' => '10d'],
            71 => ['desc' => 'Salju Ringan', 'icon' => '13d'],
            73 => ['desc' => 'Salju', 'icon' => '13d'],
            75 => ['desc' => 'Salju Lebat', 'icon' => '13d'],
            77 => ['desc' => 'Butiran Salju', 'icon' => '13d'],
            80 => ['desc' => 'Hujan Ringan', 'icon' => '09d'],
            81 => ['desc' => 'Hujan Sedang', 'icon' => '09d'],
            82 => ['desc' => 'Hujan Deras', 'icon' => '09d'],
            85 => ['desc' => 'Hujan Salju Ringan', 'icon' => '13d'],
            86 => ['desc' => 'Hujan Salju Lebat', 'icon' => '13d'],
            95 => ['desc' => 'Badai Petir', 'icon' => '11d'],
            96 => ['desc' => 'Badai Petir dengan Hujan Es', 'icon' => '11d'],
            99 => ['desc' => 'Badai Petir dengan Hujan Es Lebat', 'icon' => '11d'],
        ];

        return $weatherCodes[$code] ?? ['desc' => 'Tidak Diketahui', 'icon' => '01d'];
    }
}
