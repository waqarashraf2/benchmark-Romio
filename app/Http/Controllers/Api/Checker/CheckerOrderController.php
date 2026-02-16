<?php
// app/Http/Controllers/Api/Checker/CheckerOrderController.php

namespace App\Http\Controllers\Api\Checker;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\OrderIssue;
use Illuminate\Http\Request;

class CheckerOrderController extends Controller
{
    /**
     * Start reviewing an order
     */
    public function startReview(Order $order)
    {
        // Check if order is in correct status
        if ($order->status !== Order::STATUS_CHECKER_REVIEW) {
            return response()->json([
                'message' => 'Order is not ready for checker review'
            ], 422);
        }

        $order->update([
            'checker_started_at' => now(),
            'status' => Order::STATUS_CHECKER_REVIEW // Ensure status is set
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review started successfully'
        ]);
    }

    /**
     * Submit a review (with or without issues)
     */
    public function submitReview(Request $request, Order $order)
    {
        $request->validate([
            'approved' => 'required|boolean',
            'comment' => 'required|string',
            'issues' => 'sometimes|array',
            'issues.*.severity' => 'required_with:issues|in:critical,major,minor,suggestion',
            'issues.*.description' => 'required_with:issues|string'
        ]);

        // Create review
        $review = OrderReview::create([
            'order_id' => $order->id,
            'reviewer_id' => $request->user()->id,
            'role_id' => $request->user()->role_id,
            'approved' => $request->approved,
            'comment' => $request->comment,
            'reviewed_at' => now()
        ]);

        // Create issues if any
        if (!$request->approved && $request->has('issues') && !empty($request->issues)) {
            foreach ($request->issues as $issueData) {
                OrderIssue::create([
                    'order_id' => $order->id,
                    'order_review_id' => $review->id,
                    'severity' => $issueData['severity'],
                    'description' => $issueData['description']
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review' => $review->load('issues')
        ]);
    }

    /**
     * Approve order (send to QA)
     */
    public function approve(Order $order)
    {
        // Check if order is in correct status
        if ($order->status !== Order::STATUS_CHECKER_REVIEW) {
            return response()->json([
                'message' => 'Order is not in checker review status'
            ], 422);
        }

        // Update order status
        $order->update([
            'status' => Order::STATUS_QA_REVIEW,
            'checker_completed_at' => now()
        ]);

        // Create approval review
        OrderReview::create([
            'order_id' => $order->id,
            'reviewer_id' => request()->user()->id,
            'role_id' => request()->user()->role_id,
            'approved' => true,
            'comment' => 'Order approved by checker',
            'reviewed_at' => now()
        ]);

        // Log status change
        $order->statusLogs()->create([
            'from_status' => Order::STATUS_CHECKER_REVIEW,
            'to_status' => Order::STATUS_QA_REVIEW,
            'changed_by' => request()->user()->id,
            'note' => 'Approved by checker'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order approved and sent to QA'
        ]);
    }

    /**
     * Reject order (return to drawer)
     */
    public function reject(Request $request, Order $order)
    {
        $request->validate([
            'reason' => 'required|string',
            'issues' => 'sometimes|array'
        ]);

        // Check if order is in correct status
        if ($order->status !== Order::STATUS_CHECKER_REVIEW) {
            return response()->json([
                'message' => 'Order is not in checker review status'
            ], 422);
        }

        // Create rejection review
        $review = OrderReview::create([
            'order_id' => $order->id,
            'reviewer_id' => $request->user()->id,
            'role_id' => $request->user()->role_id,
            'approved' => false,
            'comment' => $request->reason,
            'reviewed_at' => now()
        ]);

        // Create issues if provided
        if ($request->has('issues') && is_array($request->issues)) {
            foreach ($request->issues as $issueData) {
                OrderIssue::create([
                    'order_id' => $order->id,
                    'order_review_id' => $review->id,
                    'severity' => $issueData['severity'] ?? 'major',
                    'description' => $issueData['description'] ?? $issueData
                ]);
            }
        }

        // Update order status - return to drawer
        $order->update([
            'status' => Order::STATUS_ASSIGNED,
            'checker_completed_at' => null, // Reset checker completion
            'checker_started_at' => null // Reset checker start
        ]);

        // Log status change
        $order->statusLogs()->create([
            'from_status' => Order::STATUS_CHECKER_REVIEW,
            'to_status' => Order::STATUS_ASSIGNED,
            'changed_by' => $request->user()->id,
            'note' => 'Rejected by checker: ' . $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order rejected and returned to drawer'
        ]);
    }

    /**
     * Get detailed order information
     */
    public function details(Order $order)
    {
        $order->load([
            'assignments.user',
            'statusLogs.changedBy',
            'reviews' => function ($query) {
                $query->with(['issues', 'reviewer'])->latest();
            },
            'issues' => function ($query) {
                $query->latest();
            },
            'checklistItems' => function ($query) {
                $query->with(['checklist', 'user'])->orderBy('id');
            }
        ]);

        return response()->json($order);
    }

    /**
     * Get orders for checker with filters
     */
    public function index(Request $request)
    {
        $query = Order::with(['assignments.user', 'reviews']);

        // Filter by status
        if ($request->has('status')) {
            switch ($request->status) {
                case 'pending':
                    $query->where('status', Order::STATUS_CHECKER_REVIEW)
                          ->whereNull('checker_started_at');
                    break;
                case 'in-review':
                    $query->where('status', Order::STATUS_CHECKER_REVIEW)
                          ->whereNotNull('checker_started_at')
                          ->whereNull('checker_completed_at');
                    break;
                case 'completed':
                    $query->whereIn('status', [Order::STATUS_QA_REVIEW, Order::STATUS_COMPLETED]);
                    break;
                case 'rejected':
                    $query->whereHas('reviews', function ($q) {
                        $q->where('approved', false);
                    })->latest();
                    break;
                default:
                    $query->where('status', $request->status);
            }
        } else {
            $query->where('status', Order::STATUS_CHECKER_REVIEW);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('project_id', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json($orders);
    }

    /**
     * Get high priority orders
     */
    public function highPriority()
    {
        $orders = Order::where('status', Order::STATUS_CHECKER_REVIEW)
            ->where('priority', 'high')
            ->with(['assignments.user'])
            ->latest()
            ->get();

        return response()->json($orders);
    }

    /**
     * Get overdue orders
     */
    public function overdue()
    {
        $orders = Order::where('status', Order::STATUS_CHECKER_REVIEW)
            ->where('due_at', '<', now())
            ->with(['assignments.user'])
            ->latest()
            ->get();

        return response()->json($orders);
    }
}