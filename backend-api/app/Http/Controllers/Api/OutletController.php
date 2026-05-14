<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\OutletAccess;
use App\Services\OutletProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Outlets",
 *     description="Outlet management endpoints"
 * )
 */
class OutletController extends Controller
{
    use AuthorizesOutletAccess;

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
            return response()->json($outlets);
        }

        // Regular users see outlets they OWN plus outlets where they are
        // mapped as an outlet_user (matched by email).
        $ownedIds = Outlet::where('user_id', $user->id)->pluck('id')->all();
        $mappedIds = $this->outletsMappedToUserByEmail($user->email);
        $allIds = array_values(array_unique(array_merge($ownedIds, $mappedIds)));

        $outlets = Outlet::whereIn('id', $allIds)
            ->with('owner:id,name,email')
            ->get();

        return response()->json($outlets);
    }

    /**
     * For non-superadmin users, look at each outlet's schema and find
     * which ones contain an active outlet_users row matching the global
     * user's email. This keeps "your outlets" honest — staff see the
     * outlets they actually work at, not just outlets they own.
     */
    private function outletsMappedToUserByEmail(string $email): array
    {
        $mapped = [];
        $outlets = Outlet::where('is_active', true)->get(['id', 'schema_name']);
        foreach ($outlets as $o) {
            try {
                DB::statement("SET search_path TO {$o->schema_name}, public");
                $hit = DB::table('outlet_users')
                    ->where('email', $email)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($hit) {
                    $mapped[] = (int) $o->id;
                }
            } catch (\Throwable $e) {
                // Schema may be incomplete; skip rather than 500.
            }
        }
        DB::statement("SET search_path TO public");
        return $mapped;
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

            // The `created` model event already provisions schema + tables +
            // RBAC and maps the creating global user as an owner outlet_user.
            // Calling provisioner again here is idempotent and acts as a
            // safety net in case the event was suppressed.
            $provisioner = app(OutletProvisioner::class);
            $schemaCreated = $provisioner->provision($outlet);

            if (!$schemaCreated) {
                $outlet->delete();
                return response()->json([
                    'message' => 'Failed to create outlet schema',
                    'error' => 'Schema creation failed'
                ], 500);
            }

            // Ensure the creating global user is mapped as the outlet owner
            // so they can clock-in / manage immediately. Only the creator is
            // mapped — this preserves the rule that superadmin is NOT a
            // universal employee of every outlet, only of outlets they
            // personally created (or were explicitly added to).
            $creator = Auth::user();
            if ($creator) {
                $provisioner->mapOwner($outlet, $creator);
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
        // Authorize: superadmin, owner, or mapped outlet_user can read.
        $this->authorizeOutlet($id, ['setSchema' => false]);

        $outlet = Outlet::with('owner:id,name,email')->find($id);
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
