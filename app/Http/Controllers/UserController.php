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
            'tuk_id'   => 'nullable|exists:mytuk,id',
            'noreg'    => 'nullable|string|max:255',
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
                'tuk_id'   => $validated['tuk_id'] ?? null,
                'noreg'    => in_array($validated['role_id'], [5, 12]) ? ($validated['noreg'] ?? null) : null,
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

    public function show($id)
    {
        $user = User::with('role', 'tuk')->where('is_deleted', false)->findOrFail($id);

        return response()->json([
            'message' => 'User retrieved successfully',
            'data'    => $user,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::where('is_deleted', false)->findOrFail($id);

        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email|unique:users,email,' . $user->id,
            'phone'    => 'nullable|string|max:20',
            'role_id'  => 'nullable|exists:roles,id',
            'tuk_id'   => 'nullable|exists:mytuk,id',
            'noreg'    => 'nullable|string|max:255',
            'password' => 'nullable|string|min:6|confirmed',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'address'  => 'nullable|string',
        ]);

        $roleId = $validated['role_id'] ?? $user->role_id;
        $data = array_filter([
            'name'    => $validated['name'] ?? null,
            'email'   => $validated['email'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'role_id' => $validated['role_id'] ?? null,
            'tuk_id'  => $validated['tuk_id'] ?? null,
            'noreg'   => in_array($roleId, [5, 12]) ? ($validated['noreg'] ?? null) : null,
            'address' => $validated['address'] ?? null,
        ], fn ($v) => !is_null($v));

        if (!empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $user->fresh()->load('role'),
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_deleted' => true, 'deleted_at' => now()]);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
