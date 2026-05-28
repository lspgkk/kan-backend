<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('all')) {
            $query = User::with('role')->where('is_deleted', false)
                ->whereDoesntHave('role', function ($q) {
                    $q->where('name', 'Asesi');
                });
            if ($request->filled('role')) {
                $query->whereHas('role', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }
            if ($request->filled('role_id')) {
                $query->where('role_id', $request->role_id);
            }

            $search = $request->input('search');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('role', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%");
                    });
                });
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $users,
                'meta' => [
                    'all' => true,
                    'total' => $users->count(),
                ],
            ]);
        }
        // 🔍 Optional search query (by name, email, or username)
        $search = $request->input('search');

        // 📄 Per page (default: 10)
        $perPage = $request->input('per_page', 10);

        $query = User::with('role')->where('is_deleted', false)
            ->whereDoesntHave('role', function ($q) {
                $q->where('name', 'Asesi');
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                // 🔍 Search by role name
                ->orWhereHas('role', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        // Optional: sort newest first
        $query->orderBy('created_at', 'desc');

        $users = $query->paginate($perPage);

        return response()->json([
            'message' => 'Users retrieved successfully',
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20',
            'role_id'  => 'required|exists:roles,id',
            'password' => 'required|string|min:6|confirmed',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address'  => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $data = [
                'name'     => $validated['name'],
                'email'    => $validated['email'],
                'phone'    => $validated['phone'] ?? null,
                'role_id'  => $validated['role_id'],
                'password' => $validated['password'],
                'address'  => $validated['address'] ?? null,
            ];

            if ($request->hasFile('avatar')) {
                $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
            }

            $user = User::create($data);
            $user->assignRole($user->role->name);

            DB::commit();
            return response()->json([
                'message' => 'User created successfully',
                'data'    => $user->load('role'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($data['avatar'])) {
                Storage::disk('public')->delete($data['avatar']);
            }
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
