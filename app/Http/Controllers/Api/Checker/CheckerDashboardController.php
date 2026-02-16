<?php

namespace App\Http\Controllers\Api\Checker;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class CheckerDashboardController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('status', Order::STATUS_CHECKER_REVIEW)->get();

        return response()->json([
            'total_pending_check' => $orders->count(),
            'orders' => $orders
        ]);
    }
}
