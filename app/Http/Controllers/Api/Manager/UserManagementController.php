<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    /**
     * Get all users
     */
    public function index()
    {
        $users = User::with('role')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'slug' => $user->role->slug
                    ] : null,
                    'active' => (bool) $user->active,
                    'created_at' => $user->created_at?->toISOString(),
                    'last_login_at' => $user->last_login_at?->toISOString(),
                    'working_orders' => $this->getWorkingOrdersCount($user)
                ];
            });

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|string|in:drawer,checker,qa,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get role ID based on slug
        $role = Role::where('slug', $request->role)->first();
        
        if (!$role) {
            return response()->json([
                'message' => 'Invalid role specified. Available roles: drawer, checker, qa, admin'
            ], 400);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $role->id, // Set the role_id directly
                'active' => true // New users are active by default
            ]);

            // Load the role relationship
            $user->load('role');

            return response()->json([
                'message' => 'User created successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'name' => $user->role->name,
                        'slug' => $user->role->slug
                    ] : null,
                    'active' => (bool) $user->active,
                    'created_at' => $user->created_at?->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user role
     */
    public function changeRole(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:drawer,checker,qa,admin'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $role = Role::where('slug', $request->role)->first();
        
        if (!$role) {
            return response()->json([
                'message' => 'Invalid role specified'
            ], 400);
        }

        $user->role_id = $role->id;
        $user->save();
        
        // Load the updated role relationship
        $user->load('role');

        return response()->json([
            'message' => 'User role updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'name' => $user->role->name,
                    'slug' => $user->role->slug
                ] : null
            ]
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggle(User $user)
    {
        // Don't allow deactivating yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot deactivate your own account'
            ], 403);
        }

        $user->active = !$user->active;
        $user->save();

        return response()->json([
            'message' => $user->active ? 'User activated' : 'User deactivated',
            'active' => (bool) $user->active
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'confirmed', Password::min(8)]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        // Don't allow deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        // Check if user has active orders
        $activeOrdersCount = $this->getWorkingOrdersCount($user);
        
        if ($activeOrdersCount > 0) {
            return response()->json([
                'message' => 'Cannot delete user with active orders. Please reassign or complete the orders first.'
            ], 400);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Get count of working orders for a user
     */
    private function getWorkingOrdersCount($user)
    {
        if (!$user->role) {
            return 0;
        }

        // Count based on role
        if ($user->role->slug === 'drawer') {
            return $user->drawerAssignments()
                ->whereHas('order', function($q) {
                    $q->whereNotIn('status', ['completed', 'rejected']);
                })
                ->count();
        }

        if ($user->role->slug === 'checker') {
            return $user->checkerAssignments()
                ->whereHas('order', function($q) {
                    $q->whereNotIn('status', ['completed', 'rejected']);
                })
                ->count();
        }

        if ($user->role->slug === 'qa') {
            return $user->qaAssignments()
                ->whereHas('order', function($q) {
                    $q->whereNotIn('status', ['completed', 'rejected']);
                })
                ->count();
        }

        return 0;
    }
}