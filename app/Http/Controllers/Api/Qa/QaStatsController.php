<?php
// app/Http/Controllers/Api/Qa/QaStatsController.php

namespace App\Http\Controllers\Api\Qa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderReview;
use Illuminate\Http\Request;

class QaStatsController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $stats = [
            'total_orders' => $query->count(),
            'qa_review' => (clone $query)->where('status', Order::STATUS_QA_REVIEW)->count(),
            'completed' => (clone $query)->where('status', Order::STATUS_COMPLETED)->count(),
            'rejected' => OrderReview::where('approved', false)
                ->whereIn('order_id', (clone $query)->pluck('id'))
                ->count(),
            
            'by_priority' => [
                'high' => (clone $query)->where('priority', 'high')->count(),
                'medium' => (clone $query)->where('priority', 'medium')->count(),
                'low' => (clone $query)->where('priority', 'low')->count()
            ],
            
            'average_completion_time' => $this->getAverageCompletionTime($query),
            
            'daily_stats' => $this->getDailyStats($request)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function dailyProgress()
    {
        $today = now()->startOfDay();
        
        $stats = [
            'completed_today' => Order::where('status', Order::STATUS_COMPLETED)
                ->whereDate('qa_completed_at', $today)
                ->count(),
            'rejected_today' => OrderReview::where('approved', false)
                ->whereDate('reviewed_at', $today)
                ->count(),
            'pending' => Order::where('status', Order::STATUS_QA_REVIEW)
                ->count(),
            'in_progress' => Order::where('status', Order::STATUS_QA_REVIEW)
                ->whereNotNull('qa_started_at')
                ->whereNull('qa_completed_at')
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    private function getAverageCompletionTime($query)
    {
        $completed = (clone $query)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('qa_started_at')
            ->whereNotNull('qa_completed_at')
            ->get();

        if ($completed->isEmpty()) {
            return 0;
        }

        $totalHours = $completed->sum(function($order) {
            return $order->qa_started_at->diffInHours($order->qa_completed_at);
        });

        return round($totalHours / $completed->count(), 2);
    }

    private function getDailyStats($request)
    {
        $days = $request->get('days', 7);
        $stats = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            
            $stats[] = [
                'date' => $date->format('Y-m-d'),
                'completed' => Order::where('status', Order::STATUS_COMPLETED)
                    ->whereDate('qa_completed_at', $date)
                    ->count(),
                'rejected' => OrderReview::where('approved', false)
                    ->whereDate('reviewed_at', $date)
                    ->count()
            ];
        }

        return $stats;
    }
}