<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Outlets",
 *     description="Outlet management endpoints"
 * )
 */
class OutletController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/outlets",
     *     summary="Get all outlets",
     *     tags={"Outlets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index()
    {
        $user = Auth::user();
        
        // Superadmin can see all outlets
        if ($user->isSuperAdmin()) {
            $outlets = Outlet::with('owner:id,name,email')->get();
        } else {
            // Regular users only see their own outlets
            $outlets = Outlet::where('user_id', $user->id)
                ->with('owner:id,name,email')
                ->get();
        }

        return response()->json($outlets);
    }

    /**
     * @OA\Post(
     *     path="/api/outlets",
     *     summary="Create new outlet",
     *     tags={"Outlets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:outlets,slug',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'fixed_cost_type' => 'nullable|in:percentage,nominal',
            'fixed_cost_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_cost_nominal' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = Auth::id();

        try {
            $outlet = Outlet::create($data);
            
            // Manually create schema (in case event doesn't fire)
            $schemaCreated = $outlet->createSchema();
            
            if (!$schemaCreated) {
                // Rollback if schema creation failed
                $outlet->delete();
                return response()->json([
                    'message' => 'Failed to create outlet schema',
                    'error' => 'Schema creation failed'
                ], 500);
            }
            
            $outlet->load('owner:id,name,email');

            return response()->json([
                'message' => 'Outlet created successfully',
                'outlet' => $outlet
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create outlet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/outlets/{id}",
     *     summary="Get outlet by ID",
     *     tags={"Outlets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function show($id)
    {
        $user = Auth::user();
        $outlet = Outlet::with('owner:id,name,email')->find($id);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        // Check permission
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($outlet);
    }

    /**
     * @OA\Put(
     *     path="/api/outlets/{id}",
     *     summary="Update outlet",
     *     tags={"Outlets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $outlet = Outlet::find($id);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        // Check permission
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:outlets,slug,' . $id,
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255', // Changed from url to string
            'logo' => 'nullable|string', // base64 image - no max length
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'business_hours' => 'nullable|string',
            'social_media' => 'nullable|array',
            'social_media.facebook' => 'nullable|string|max:255',
            'social_media.instagram' => 'nullable|string|max:255',
            'social_media.twitter' => 'nullable|string|max:255',
            'social_media.whatsapp' => 'nullable|string|max:50',
            'fixed_cost_type' => 'nullable|in:percentage,nominal',
            'fixed_cost_percentage' => 'nullable|numeric|min:0|max:100',
            'fixed_cost_nominal' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $outlet->update($validator->validated());
            $outlet->load('owner:id,name,email');

            return response()->json([
                'message' => 'Outlet updated successfully',
                'outlet' => $outlet
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update outlet',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/outlets/{id}",
     *     summary="Delete outlet",
     *     tags={"Outlets"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $outlet = Outlet::find($id);

        if (!$outlet) {
            return response()->json(['message' => 'Outlet not found'], 404);
        }

        // Check permission
        if (!$user->isSuperAdmin() && $outlet->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $outlet->delete(); // Soft delete

            return response()->json([
                'message' => 'Outlet deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete outlet',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
