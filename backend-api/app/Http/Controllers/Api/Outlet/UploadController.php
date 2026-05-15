<?php

namespace App\Http\Controllers\Api\Outlet;

use App\Http\Controllers\Concerns\AuthorizesOutletAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    use AuthorizesOutletAccess;

    /**
     * Upload generic image (e.g. receipt logo).
     */
    public function uploadImage(Request $request, $outletId)
    {
        $outlet = $this->authorizeOutlet($outletId);

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $image = $request->file('image');
            $filename = 'logo_' . $outlet->slug . '_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $path = $image->storeAs('uploads/logos/' . $outlet->slug, $filename, 'public');
            $url = url(Storage::url($path));

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => $url,
                'path' => $path,
                'filename' => $filename,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
