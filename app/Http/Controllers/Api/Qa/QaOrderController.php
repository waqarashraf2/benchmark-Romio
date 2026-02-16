<?php
// app/Http/Controllers/Api/Qa/QaOrderController.php

namespace App\Http\Controllers\Api\Qa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use App\Models\OrderStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QaOrderController extends Controller
{
    /**
     * Start review
     */
    public function startReview($id)
    {
        $order = Order::findOrFail($id);
        
        if ($order->status !== Order::STATUS_QA_REVIEW) {
            return response()->json([
                'success' => false,
                'message' => 'Order is not in QA review status'
            ], 422);
        }

        $order->qa_started_at = now();
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Review started successfully'
        ]);
    }

    /**
     * Approve order (complete)
     */
    public function approve($id)
    {
        $order = Order::findOrFail($id);

        if ($order->status !== Order::STATUS_QA_REVIEW) {
            return response()->json([
                'success' => false,
                'message' => 'Order is not in QA review status'
            ], 422);
        }

        // Create QA review record
        OrderReview::create([
            'order_id' => $order->id,
            'reviewer_id' => request()->user()->id,
            'role_id' => request()->user()->role_id,
            'approved' => true,
            'comment' => 'Order approved by QA',
            'reviewed_at' => now()
        ]);

        // Update order
        $order->status = Order::STATUS_COMPLETED;
        $order->qa_completed_at = now();
        $order->save();

        // Log status change
        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => Order::STATUS_QA_REVIEW,
            'to_status' => Order::STATUS_COMPLETED,
            'changed_by' => request()->user()->id,
            'note' => 'Order completed by QA'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order completed successfully'
        ]);
    }

    /**
     * Reject order (return to checker)
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::findOrFail($id);

        if ($order->status !== Order::STATUS_QA_REVIEW) {
            return response()->json([
                'success' => false,
                'message' => 'Order is not in QA review status'
            ], 422);
        }

        // Create QA rejection record
        OrderReview::create([
            'order_id' => $order->id,
            'reviewer_id' => $request->user()->id,
            'role_id' => $request->user()->role_id,
            'approved' => false,
            'comment' => $request->reason,
            'reviewed_at' => now()
        ]);

        // Update order - return to checker
        $order->status = Order::STATUS_CHECKER_REVIEW;
        $order->qa_started_at = null;
        $order->qa_completed_at = null;
        $order->save();

        // Log status change
        OrderStatusLog::create([
            'order_id' => $order->id,
            'from_status' => Order::STATUS_QA_REVIEW,
            'to_status' => Order::STATUS_CHECKER_REVIEW,
            'changed_by' => $request->user()->id,
            'note' => 'Rejected by QA: ' . $request->reason
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order returned to checker'
        ]);
    }

    /**
     * Get order details
     */
    public function details($id)
    {
        $order = Order::with([
            'assignments.user',
            'statusLogs',
            'reviews' => function($q) {
                $q->with('reviewer')->latest();
            },
            'issues'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }
}