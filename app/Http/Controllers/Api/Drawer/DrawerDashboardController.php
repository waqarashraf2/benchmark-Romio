<?php

namespace App\Http\Controllers\Api\Drawer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Role;
use Illuminate\Http\Request;

class DrawerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get the drawer role ID
        $drawerRole = Role::where('slug', 'drawer')->first();
        
        if (!$drawerRole) {
            return response()->json([
                'stats' => [
                    'assigned' => 0,
                    'in_progress' => 0,
                    'completed' => 0,
                    'completed_today' => 0
                ],
                'assigned_orders' => [],
                'completed_today' => []
            ]);
        }

        // Get all orders assigned to this drawer
        $orders = Order::whereHas('assignments', function ($q) use ($user, $drawerRole) {
                $q->where('user_id', $user->id)
                  ->where('role_id', $drawerRole->id)
                  ->where('is_current', true);
            })
            ->with(['assignments' => function ($q) use ($user, $drawerRole) {
                $q->where('user_id', $user->id)
                  ->where('role_id', $drawerRole->id)
                  ->where('is_current', true);
            }])
            ->latest()
            ->get();

        // Separate orders based on status and timestamps
        $assignedOrders = $orders->filter(function ($order) {
            // Assigned but not started yet
            return $order->status === Order::STATUS_ASSIGNED && 
                   !$order->drawer_started_at;
        })->values();

        $activeOrder = $orders->first(function ($order) {
            // Started but not completed
            return $order->status === Order::STATUS_ASSIGNED && 
                   $order->drawer_started_at && 
                   !$order->drawer_completed_at;
        });

        // Get completed orders for today
        $completedToday = Order::whereHas('assignments', function ($q) use ($user, $drawerRole) {
                $q->where('user_id', $user->id)
                  ->where('role_id', $drawerRole->id);
            })
            ->where('status', Order::STATUS_DRAWER_DONE)
            ->whereDate('drawer_completed_at', today())
            ->latest('drawer_completed_at')
            ->get();

        // Prepare response
        $response = [
            'assigned_orders' => $assignedOrders,
            'completed_today' => $completedToday,
            'stats' => [
                'assigned' => $assignedOrders->count(),
                'in_progress' => $activeOrder ? 1 : 0,
                'completed' => $completedToday->count(),
                'total_today' => $assignedOrders->count() + ($activeOrder ? 1 : 0) + $completedToday->count()
            ]
        ];

        // Add active order if exists
        if ($activeOrder) {
            $response['active_order'] = $activeOrder;
        }

        return response()->json($response);
    }
}