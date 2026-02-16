<?php
// app/Http/Controllers/Api/Qa/QaDashboardController.php

namespace App\Http\Controllers\Api\Qa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use Illuminate\Http\Request;

class QaDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Debug: Log what we're looking for
        \Log::info('QA Dashboard - Looking for orders with status: ' . Order::STATUS_QA_REVIEW);
        
        // Get orders with QA_REVIEW status - simplified query first
        $qaReviewOrders = Order::where('status', Order::STATUS_QA_REVIEW)
            ->with(['assignments.user']) // Only load what we need
            ->latest()
            ->get();

        // Log what we found
        \Log::info('QA Dashboard - Found ' . $qaReviewOrders->count() . ' orders with QA_REVIEW status');
        
        // If no orders found, check what statuses exist in the database
        if ($qaReviewOrders->isEmpty()) {
            $allStatuses = Order::select('status')->distinct()->get()->pluck('status');
            \Log::info('Available statuses in database: ', $allStatuses->toArray());
        }

        // Calculate stats carefully - check if fields exist before using them
        $today = now()->startOfDay();
        
        // Count completed today
        $completedToday = Order::where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('qa_completed_at')
            ->whereDate('qa_completed_at', $today)
            ->count();
        
        // Count rejected today
        $rejectedToday = OrderReview::where('approved', false)
            ->whereDate('reviewed_at', $today)
            ->count();
        
        // Count total completed
        $totalCompleted = Order::where('status', Order::STATUS_COMPLETED)->count();
        
        // Count high priority in QA review
        $highPriority = Order::where('status', Order::STATUS_QA_REVIEW)
            ->where('priority', 'high')
            ->count();
        
        // Count overdue orders (due date passed and not completed)
        $overdue = Order::where('due_at', '<', now())
            ->where('status', '!=', Order::STATUS_COMPLETED)
            ->count();

        // Count orders in review (started but not completed) - FIXED: Added missing closing quote
        $inReview = Order::where('status', Order::STATUS_QA_REVIEW)
            ->whereNotNull('qa_started_at')
            ->whereNull('qa_completed_at')  // Fixed: Added missing closing quote
            ->count();

        $stats = [
            'pending_qa' => $qaReviewOrders->count(),
            'in_review' => $inReview,
            'completed_today' => $completedToday,
            'rejected_today' => $rejectedToday,
            'total_completed' => $totalCompleted,
            'high_priority' => $highPriority,
            'overdue' => $overdue
        ];

        // Format orders for frontend
        $formattedOrders = $qaReviewOrders->map(function($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'project_id' => $order->project_id,
                'address' => $order->address,
                'status' => $order->status,
                'priority' => $order->priority,
                'due_at' => $order->due_at,
                'qa_started_at' => $order->qa_started_at,
                'qa_completed_at' => $order->qa_completed_at,
                'drawer_completed_at' => $order->drawer_completed_at,
                'checker_completed_at' => $order->checker_completed_at,
                'created_at' => $order->created_at,
                'assignments' => $order->assignments->map(function($assignment) {
                    return [
                        'role' => $assignment->role,
                        'user' => $assignment->user ? [
                            'id' => $assignment->user->id,
                            'name' => $assignment->user->name
                        ] : null
                    ];
                }),
                'reviews' => [] // We'll load these separately if needed
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'orders' => $formattedOrders,
            'pending_qa' => $qaReviewOrders->count()
        ]);
    }
}