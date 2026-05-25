<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->boolean('all')) {
            $query = User::with('role')->where('is_deleted', false);
            if ($request->filled('role')) {
                $query->whereHas('role', function ($q) use ($request) {
                    $q->where('name', $request->role);
                });
            }
            if ($request->filled('role_id')) {
                $query->where('role_id', $request->role_id);
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

        $query = User::with('role')->where('is_deleted', false);

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
}
