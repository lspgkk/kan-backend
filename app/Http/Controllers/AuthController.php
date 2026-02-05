<?php

namespace App\Http\Controllers;

use App\Models\AsesiJadwal;
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
                'role_id' => auth()->user()->role_id,
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
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
        if (! $token = auth()->attempt($credentials)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Akun atau password salah.',
            ], 401);
        }

        // 4️⃣ Return token
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
