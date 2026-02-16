<?php
// app/Http/Controllers/Api/Checker/CheckerIssueController.php

namespace App\Http\Controllers\Api\Checker;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderIssue;
use App\Models\OrderReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CheckerIssueController extends Controller
{
    /**
     * Get all issues for a specific order
     */
    public function index(Order $order)
    {
        $issues = $order->issues()
            ->with('review.reviewer')
            ->latest()
            ->get()
            ->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'severity' => $issue->severity,
                    'description' => $issue->description,
                    'created_at' => $issue->created_at?->format('Y-m-d H:i:s'),
                    'review' => $issue->review ? [
                        'id' => $issue->review->id,
                        'comment' => $issue->review->comment,
                        'approved' => $issue->review->approved,
                        'reviewer_name' => $issue->review->reviewer?->name
                    ] : null
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $issues
        ]);
    }

    /**
     * Create a new issue for an order
     */
    public function store(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'required|in:critical,major,minor,suggestion',
            'description' => 'required|string|min:3|max:1000',
            'order_review_id' => 'nullable|exists:order_reviews,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $issue = OrderIssue::create([
            'order_id' => $order->id,
            'order_review_id' => $request->order_review_id,
            'severity' => $request->severity,
            'description' => $request->description
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Issue created successfully',
            'data' => [
                'id' => $issue->id,
                'severity' => $issue->severity,
                'description' => $issue->description,
                'created_at' => $issue->created_at?->format('Y-m-d H:i:s')
            ]
        ], 201);
    }

    /**
     * Update an issue
     */
    public function update(Request $request, OrderIssue $issue)
    {
        $validator = Validator::make($request->all(), [
            'severity' => 'sometimes|in:critical,major,minor,suggestion',
            'description' => 'sometimes|string|min:3|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->has('severity')) {
            $issue->severity = $request->severity;
        }
        if ($request->has('description')) {
            $issue->description = $request->description;
        }
        
        $issue->save();

        return response()->json([
            'success' => true,
            'message' => 'Issue updated successfully',
            'data' => [
                'id' => $issue->id,
                'severity' => $issue->severity,
                'description' => $issue->description,
                'updated_at' => $issue->updated_at?->format('Y-m-d H:i:s')
            ]
        ]);
    }

    /**
     * Delete an issue
     */
    public function destroy(OrderIssue $issue)
    {
        $issue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Issue deleted successfully'
        ]);
    }

    /**
     * Get issues for a specific review
     */
    public function getByReview(OrderReview $review)
    {
        $issues = $review->issues()
            ->get()
            ->map(function ($issue) {
                return [
                    'id' => $issue->id,
                    'severity' => $issue->severity,
                    'description' => $issue->description,
                    'created_at' => $issue->created_at?->format('Y-m-d H:i:s')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $issues
        ]);
    }

    /**
     * Get issue statistics for an order
     */
    public function stats(Order $order)
    {
        $issues = $order->issues;
        $total = $issues->count();

        $stats = [
            'total' => $total,
            'by_severity' => [
                'critical' => $issues->where('severity', 'critical')->count(),
                'major' => $issues->where('severity', 'major')->count(),
                'minor' => $issues->where('severity', 'minor')->count(),
                'suggestion' => $issues->where('severity', 'suggestion')->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}