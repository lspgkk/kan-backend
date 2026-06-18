<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuthLog;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected function respondWithToken($token)
    {
        $ttl = auth()->factory()->getTTL();

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'id_user' => auth()->user()->id,
                'role_id' => auth()->user()->role_id,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $ttl * 60,
                'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            ]
        ]);
    }

    public function login(Request $request)
    {
        // Normalize account to email
        $account = $request->account;

        // 1️⃣ Find user by email
        $user = User::where('email', $account)
            ->where('is_deleted', false)
            ->first();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Akun tidak ditemukan atau dinonaktifkan.',
            ], 401);
        }

        // 2️⃣ Credentials
        $credentials = [
            'email'    => $account,
            'password' => $request->password,
        ];

        // 3️⃣ Attempt authentication
        if (! auth()->attempt($credentials)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Akun atau password salah.',
            ], 401);
        }

        // 4️⃣ Check if user already has an active session
        $user = auth()->user();

        if ($user->token_version > 0 && $user->token_expires_at && now()->lt($user->token_expires_at)) {
            // Rollback the authentication — user is already logged in elsewhere
            auth()->logout();

            Log::info('LOGIN REJECTED (active session exists)', [
                'user_id' => $user->id,
                'email' => $user->email,
                'token_expires_at' => $user->token_expires_at,
            ]);

            return response()->json([
                'error' => 'Session Active',
                'message' => 'Sesi masih aktif. Silakan logout terlebih dahulu sebelum login dari perangkat lain.',
            ], 409);
        }

        Log::debug('LOGIN: Before increment', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token_version' => $user->token_version,
        ]);

        // Invalidate any stale tokens and issue a fresh one
        $user->increment('token_version');
        $user->refresh();

        Log::debug('LOGIN: After increment', [
            'user_id' => $user->id,
            'email' => $user->email,
            'token_version' => $user->token_version,
        ]);

        // 5️⃣ Generate a fresh token with the updated token_version claim
        //    using login() which both sets the claims AND authenticates the user
        //    on the guard (needed by respondWithToken).
        $token = auth()->claims([
            'token_version' => (int) $user->token_version,
        ])->login($user);

        // Verify the token_version is actually in the new token's payload
        $payloadVersion = auth()->payload()->get('token_version');
        Log::debug('LOGIN: New token payload', [
            'user_id' => $user->id,
            'token_version_in_claim' => $payloadVersion,
        ]);

        // Record when this token expires
        $ttl = auth()->factory()->getTTL();
        $user->update(['token_expires_at' => now()->addMinutes($ttl)]);

        // 6️⃣ Log auth activity
        AuthLog::create([
            'user_id'     => $user->id,
            'description' => 'Login',
            'login_ip'    => $request->ip(),
        ]);

        // 7️⃣ Return token
        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = auth()->user();

        $role = Role::with('permissions')->findOrFail($user->role_id);

        $response = [
            'user' => $user,
            'role' => $role,
        ];

        if ($role->name === 'Asesi') {
            $idIzin = AsesiJadwal::where('account', $user->email)
                ->value('id_izin');

            $response['id_izin'] = $idIzin;
        }

        return response()->json([
            'data' => $response
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

        if ($user) {
            // Increment token_version to invalidate all active sessions
            $user->increment('token_version');
            // Clear token_expires_at so a new login is allowed
            $user->update(['token_expires_at' => null]);

            // Log auth activity before invalidating
            AuthLog::create([
                'user_id'     => $user->id,
                'description' => 'Logout',
                'login_ip'    => $request->ip(),
            ]);
        }

        // Invalidate token (for sanctuary or JWT)
        if (method_exists(auth()->guard(), 'logout')) {
            auth()->logout();
        }

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $userId = auth()->id();
        $user = User::find($userId);

        if (! Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Incorrect old password',
            ], 422);
        } else {
            $user->update([
                'password' => bcrypt($request->password),
            ]);
            return response()->json([
                'message' => 'Password changed successfully',
            ]);
        }
    }

    public function logs(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $query = AuthLog::with('user:id,name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('login_ip', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'message' => 'Auth logs retrieved successfully',
            'data' => $logs,
        ]);
    }
}
