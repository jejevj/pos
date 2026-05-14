<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

class SetOutletSchema
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get outlet_id from route parameter or request
        $outletId = $request->route('outlet') ?? $request->input('outlet_id');

        if ($outletId) {
            $outlet = Outlet::find($outletId);
            
            if ($outlet && $outlet->is_active) {
                // Set the schema for this request
                DB::statement("SET search_path TO {$outlet->schema_name}, public");
                
                // Store outlet in request for later use
                $request->merge(['current_outlet' => $outlet]);
            }
        }

        $response = $next($request);

        // Reset to public schema after request
        DB::statement("SET search_path TO public");

        return $response;
    }
}
