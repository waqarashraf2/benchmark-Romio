<?php

namespace App\Http\Controllers\Api\Drawer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DrawerOrderController extends Controller
{
    /* Start Drawing */
    public function start(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'drawer_started_at' => now(),
        ]);

        return response()->json(['message' => 'Drawing started']);
    }

    /* Submit to Checker */
    public function complete(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $order->update([
            'status' => Order::STATUS_CHECKER_REVIEW,
            'drawer_completed_at' => now(),
        ]);

        return response()->json(['message' => 'Sent to checker']);
    }
}
