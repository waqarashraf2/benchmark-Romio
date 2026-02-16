<?php
// app/Http/Controllers/Api/Qa/QaExportController.php

namespace App\Http\Controllers\Api\Qa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class QaExportController extends Controller
{
    public function export(Request $request)
    {
        $query = Order::with(['assignments.user', 'reviews']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->get();

        // Generate CSV
        $filename = 'qa-orders-' . now()->format('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($orders) {
            $file = fopen('php://output', 'w');
            
            // UTF-8 BOM for Excel
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Headers
            fputcsv($file, [
                'Order ID',
                'Order Number',
                'Project ID',
                'Address',
                'Status',
                'Priority',
                'Drawer',
                'Checker',
                'Drawer Completed',
                'Checker Completed',
                'QA Completed',
                'Created At'
            ]);

            // Data
            foreach ($orders as $order) {
                $drawer = $order->assignments->where('role', 'drawer')->first();
                $checker = $order->assignments->where('role', 'checker')->first();

                fputcsv($file, [
                    $order->id,
                    $order->order_number,
                    $order->project_id,
                    $order->address,
                    $order->status,
                    $order->priority,
                    $drawer->user->name ?? 'N/A',
                    $checker->user->name ?? 'N/A',
                    $order->drawer_completed_at ? $order->drawer_completed_at->format('Y-m-d H:i') : 'N/A',
                    $order->checker_completed_at ? $order->checker_completed_at->format('Y-m-d H:i') : 'N/A',
                    $order->qa_completed_at ? $order->qa_completed_at->format('Y-m-d H:i') : 'N/A',
                    $order->created_at->format('Y-m-d H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}