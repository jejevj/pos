<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SiteSettingController extends Controller
{
    /**
     * Public endpoint — return all settings grouped by group.
     */
    public function index()
    {
        $settings = SiteSetting::orderBy('group')->orderBy('id')->get();

        $grouped = [];
        $flat = [];
        foreach ($settings as $s) {
            $grouped[$s->group][] = [
                'key' => $s->key,
                'value' => $s->value,
                'type' => $s->type,
                'group' => $s->group,
                'label' => $s->label,
                'description' => $s->description,
            ];
            $flat[$s->key] = $s->value;
        }

        return response()->json([
            'settings' => $flat,
            'grouped' => $grouped,
            'all' => $settings,
        ]);
    }

    /**
     * Bulk update settings. Requires superadmin.
     * Body: { settings: { key1: value1, key2: value2, ... } }
     */
    public function update(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->isSuperAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->input('settings', []) as $key => $value) {
            SiteSetting::where('key', $key)->update(['value' => $value]);
        }

        return response()->json([
            'message' => 'Site settings updated successfully',
            'settings' => SiteSetting::pluck('value', 'key'),
        ]);
    }

    /**
     * Upload an image (logo / favicon). Requires superadmin.
     * Body (multipart): { image: file, key: 'site_logo'|... }
     */
    public function uploadImage(Request $request)
    {
        $user = $request->user();
        if (!$user || !$this->isSuperAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'image' => 'required|file|image|max:5120',
            'key' => 'required|string|max:255',
        ]);

        $path = $request->file('image')->store('site', 'public');
        $url = Storage::url($path);

        SiteSetting::updateOrCreate(
            ['key' => $request->input('key')],
            ['value' => $url, 'type' => 'image']
        );

        return response()->json([
            'message' => 'Image uploaded successfully',
            'key' => $request->input('key'),
            'url' => $url,
            'path' => $path,
        ]);
    }

    private function isSuperAdmin($user): bool
    {
        if (method_exists($user, 'roles')) {
            return $user->roles()->where('name', 'superadmin')->exists();
        }
        return false;
    }
}
