<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Controller;
use App\Models\WeatherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WeatherController extends Controller
{
    /**
     * Get weather history for outlet
     */
    public function index(Request $request, $outletId)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = WeatherData::where('outlet_id', $outletId)
            ->orderBy('recorded_at', 'desc');

        if ($request->start_date) {
            $query->where('recorded_at', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->where('recorded_at', '<=', $request->end_date);
        }

        $limit = $request->limit ?? 100;
        $weatherData = $query->limit($limit)->get();

        return response()->json($weatherData);
    }

    /**
     * Get latest weather for outlet
     */
    public function latest($outletId)
    {
        $weather = WeatherData::where('outlet_id', $outletId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$weather) {
            return response()->json([
                'message' => 'No weather data available for this outlet'
            ], 404);
        }

        return response()->json($weather);
    }

    /**
     * Get weather statistics for analysis
     */
    public function statistics(Request $request, $outletId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:hour,day,week,month',
        ]);

        $groupBy = $request->group_by ?? 'day';

        // SQL for grouping
        $dateFormat = match($groupBy) {
            'hour' => "DATE_TRUNC('hour', recorded_at)",
            'day' => "DATE_TRUNC('day', recorded_at)",
            'week' => "DATE_TRUNC('week', recorded_at)",
            'month' => "DATE_TRUNC('month', recorded_at)",
        };

        $statistics = DB::table('weather_data')
            ->select(
                DB::raw("{$dateFormat} as period"),
                DB::raw('AVG(temperature) as avg_temperature'),
                DB::raw('MIN(temperature) as min_temperature'),
                DB::raw('MAX(temperature) as max_temperature'),
                DB::raw('AVG(humidity) as avg_humidity'),
                DB::raw('AVG(wind_speed) as avg_wind_speed'),
                DB::raw('AVG(cloud_cover) as avg_cloud_cover'),
                DB::raw('COUNT(*) as record_count')
            )
            ->where('outlet_id', $outletId)
            ->whereBetween('recorded_at', [$request->start_date, $request->end_date])
            ->groupBy(DB::raw($dateFormat))
            ->orderBy('period', 'asc')
            ->get();

        return response()->json($statistics);
    }

    /**
     * Get weather correlation with sales
     * This endpoint combines weather data with order data for analysis
     */
    public function salesCorrelation(Request $request, $outletId)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Get weather data grouped by hour
        $weatherData = DB::table('weather_data')
            ->select(
                DB::raw("DATE_TRUNC('hour', recorded_at) as hour"),
                DB::raw('AVG(temperature) as avg_temperature'),
                DB::raw('AVG(humidity) as avg_humidity'),
                DB::raw('AVG(wind_speed) as avg_wind_speed'),
                DB::raw('MAX(weather_description) as weather_condition')
            )
            ->where('outlet_id', $outletId)
            ->whereBetween('recorded_at', [$request->start_date, $request->end_date])
            ->groupBy(DB::raw("DATE_TRUNC('hour', recorded_at)"))
            ->get()
            ->keyBy('hour');

        // Get sales data grouped by hour from outlet schema
        $outlet = \App\Models\Outlet::findOrFail($outletId);
        $schemaName = $outlet->schema_name;

        $salesData = DB::table("{$schemaName}.orders")
            ->select(
                DB::raw("DATE_TRUNC('hour', created_at) as hour"),
                DB::raw('COUNT(*) as order_count'),
                DB::raw('SUM(total_amount) as total_sales'),
                DB::raw('AVG(total_amount) as avg_order_value')
            )
            ->where('status', 'paid')
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->groupBy(DB::raw("DATE_TRUNC('hour', created_at)"))
            ->get()
            ->keyBy('hour');

        // Combine weather and sales data
        $correlation = [];
        foreach ($weatherData as $hour => $weather) {
            $sales = $salesData->get($hour);
            
            $correlation[] = [
                'hour' => $hour,
                'temperature' => round($weather->avg_temperature, 2),
                'humidity' => round($weather->avg_humidity, 2),
                'wind_speed' => round($weather->avg_wind_speed, 2),
                'weather_condition' => $weather->weather_condition,
                'order_count' => $sales ? $sales->order_count : 0,
                'total_sales' => $sales ? floatval($sales->total_sales) : 0,
                'avg_order_value' => $sales ? round($sales->avg_order_value, 2) : 0,
            ];
        }

        return response()->json([
            'data' => $correlation,
            'summary' => [
                'total_hours' => count($correlation),
                'avg_temperature' => round(collect($correlation)->avg('temperature'), 2),
                'total_sales' => collect($correlation)->sum('total_sales'),
                'total_orders' => collect($correlation)->sum('order_count'),
            ]
        ]);
    }
}
