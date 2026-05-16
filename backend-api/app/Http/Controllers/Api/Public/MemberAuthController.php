<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Public member-side authentication for self-order flow.
 *
 *   POST /api/public/outlet/{outletSlug}/member/login
 *   POST /api/public/outlet/{outletSlug}/member/set-password
 *
 * The member identity lives in the per-outlet `members` table. Self-order
 * "login" here is intentionally lightweight: identify by card_number / email
 * / phone, optionally verify against the password column when set.
 *
 * No Sanctum token is issued — the public order page is anonymous and we only
 * need to surface a member identifier the customer can persist locally so the
 * outlet can attribute the order to the right member when it lands in the
 * Transaction page.
 */
class MemberAuthController extends Controller
{
    use PublicOrderingHelpers;

    public function login(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'identifier' => 'required|string|max:100',
            'password'   => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        try {
            $this->useSchema($outlet->schema_name);

            $identifier = trim((string) $request->input('identifier'));
            $password   = (string) $request->input('password', '');

            $member = DB::table('members')
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where(function ($q) use ($identifier) {
                    $q->where('card_number', $identifier)
                      ->orWhereRaw('LOWER(email) = ?', [strtolower($identifier)])
                      ->orWhere('phone', $identifier);
                })
                ->first();

            if (!$member) {
                $this->resetSchema();
                return response()->json([
                    'message' => 'Member tidak ditemukan. Periksa nomor member / email / nomor HP, atau daftar terlebih dahulu.',
                ], 404);
            }

            // Password is optional on members table. Two flows:
            //   1) Member has no password set yet → allow first-time login,
            //      client can set a password later via set-password endpoint.
            //   2) Member has password → must match.
            if (!empty($member->password)) {
                if ($password === '' || !Hash::check($password, $member->password)) {
                    $this->resetSchema();
                    return response()->json(['message' => 'Password salah'], 401);
                }
            }

            $payload = [
                'id'          => $member->id,
                'card_number' => $member->card_number,
                'nama'        => $member->nama,
                'email'       => $member->email,
                'phone'       => $member->phone,
                'tier'        => $member->tier ?? 'Silver',
                'points'      => (int) ($member->points ?? 0),
                'has_password'=> !empty($member->password),
            ];

            $this->resetSchema();

            return response()->json([
                'message' => 'Login berhasil',
                'member'  => $payload,
            ]);
        } catch (\Exception $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Optional convenience: allow a member to set a password on their account
     * once they've identified themselves (e.g. first-time login flow).
     * Requires `card_number` + at least one of email/phone matching to avoid
     * letting anyone overwrite a password by knowing only a card number.
     */
    public function setPassword(Request $request, string $outletSlug)
    {
        $outlet = $this->resolveOutletBySlug($outletSlug);
        if (!$outlet) {
            return response()->json(['message' => 'Outlet tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'card_number' => 'required|string|max:50',
            'email'       => 'nullable|email|max:100',
            'phone'       => 'nullable|string|max:50',
            'password'    => 'required|string|min:6|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }
        if (empty($request->email) && empty($request->phone)) {
            return response()->json(['message' => 'Email atau nomor HP wajib diisi'], 422);
        }

        try {
            $this->useSchema($outlet->schema_name);

            $q = DB::table('members')
                ->whereNull('deleted_at')
                ->where('card_number', $request->card_number);
            if (!empty($request->email)) {
                $q->whereRaw('LOWER(email) = ?', [strtolower($request->email)]);
            } elseif (!empty($request->phone)) {
                $q->where('phone', $request->phone);
            }
            $member = $q->first();

            if (!$member) {
                $this->resetSchema();
                return response()->json(['message' => 'Member tidak ditemukan'], 404);
            }

            DB::table('members')->where('id', $member->id)->update([
                'password'   => Hash::make($request->password),
                'updated_at' => now(),
            ]);

            $this->resetSchema();
            return response()->json(['message' => 'Password disimpan']);
        } catch (\Exception $e) {
            $this->resetSchema();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
