<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AuthLog;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    protected function respondWithToken($token)
    {
        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'id_user' => auth()->user()->id,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
            ]
        ]);
    }

    public function login(Request $request)
    {
        // 1️⃣ Authenticate credentials
        if (! $token = auth()->attempt([
            'email' => $request->email,
            'password' => $request->password,
        ])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // 2️⃣ Fetch user with soft-delete check
        $user = User::where('email', $request->email)
            ->where('is_deleted', false)
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Akun telah dinonaktifkan.',
            ], 401);
        }

        // 4️⃣ Log login activity
        // AuthLog::create([
        //     'user_id' => $user->id,
        //     'description' => 'User logged in',
        //     'login_ip' => $request->ip(),
        // ]);

        // 5️⃣ Return the token
        return $this->respondWithToken($token);
    }

    public function me()
    {
        $user = auth()->user();
        $role = Role::with('permissions')->findOrFail($user->role_id);
        return response()->json([
            'data' => [
                'user' => $user,
                'role' => $role
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $user = auth()->user();

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
}
