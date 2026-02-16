<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ManagerOrderController extends Controller
{

    /**
     * List all orders for manager
     */
    public function index()
    {
        $orders = Order::with(['client','project'])
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    /**
     * Assign drawer to order
     */
public function assignDrawer(Request $request, $orderId)
{
    $request->validate([
        'drawer_id' => ['required', 'exists:users,id']
    ]);

    $order = Order::findOrFail($orderId);

    // prevent wrong state
    if ($order->status !== Order::STATUS_PENDING &&
        $order->status !== Order::STATUS_ASSIGNED) {
        return response()->json([
            'message' => 'Order cannot be assigned in current state'
        ], 422);
    }

    $user = User::find($request->drawer_id);
    
    if (!$user || !$user->hasRole('drawer')) {
        return response()->json([
            'message' => 'User is not a drawer'
        ], 422);
    }

    DB::transaction(function () use ($order, $user) {
        // First, get the role ID for 'drawer'
        $role = \App\Models\Role::where('slug', 'drawer')->first();
        
        if (!$role) {
            throw new \Exception('Drawer role not found');
        }

        // create assignment record with role_id
        $order->assignments()->create([
            'user_id' => $user->id,
            'role_id' => $role->id, // Use role_id instead of role string
            'assigned_at' => now(),
        ]);

        // update order
        $order->update([
            'status' => Order::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
    });

    return response()->json([
        'message' => 'Drawer assigned successfully',
        'assignment' => $order->assignments()->latest()->first()
    ]);
}


    /**
     * Reject order
     */
    public function reject($orderId)
    {
        $order = Order::findOrFail($orderId);

        $order->update([
            'status' => Order::STATUS_REJECTED
        ]);

        return response()->json([
            'message' => 'Order rejected'
        ]);
    }

}
