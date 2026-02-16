<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManagerDashboardController extends Controller
{
    public function index()
    {
        /* ---------------- ORDER COUNTS ---------------- */
        $stats = [
            'pending' => Order::where('status', Order::STATUS_PENDING)->count(),
            'assigned' => Order::where('status', Order::STATUS_ASSIGNED)->count(),
            'drawing' => Order::where('status', Order::STATUS_DRAWER_DONE)->count(),
            'checking' => Order::where('status', Order::STATUS_CHECKER_REVIEW)->count(),
            'qa' => Order::where('status', Order::STATUS_QA_REVIEW)->count(),
            'completed' => Order::where('status', Order::STATUS_COMPLETED)->count(),
            'rejected' => Order::where('status', Order::STATUS_REJECTED)->count(),
        ];

        /* ---------------- ORDERS READY TO ASSIGN ---------------- */
        $pendingOrders = Order::where('status', Order::STATUS_PENDING)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function($order) {
                return $this->formatOrderForFrontend($order);
            });

        /* ---------------- ACTIVE WORK ORDERS ---------------- */
        $activeOrders = Order::whereNotIn('status', [
                Order::STATUS_PENDING,
                Order::STATUS_COMPLETED,
                Order::STATUS_REJECTED
            ])
            ->latest()
            ->limit(20)
            ->get()
            ->map(function($order) {
                return $this->formatOrderForFrontend($order);
            });

        /* ---------------- DRAWER WORKLOAD ---------------- */
        $drawers = User::whereHas('role', fn($q) => $q->where('slug','drawer'))
            ->where('active',true)
            ->get()
            ->map(function($drawer){
                $working = Order::whereHas('assignments', function($q) use ($drawer){
                    $q->where('user_id',$drawer->id)
                      ->where('role','drawer')
                      ->where('is_current', true);
                })
                ->whereNotIn('status', ['completed', 'rejected'])
                ->count();

                return [
                    'id' => $drawer->id,
                    'name' => $drawer->name,
                    'email' => $drawer->email,
                    'working_orders' => $working
                ];
            });

        /* ---------------- RECENT ORDERS ---------------- */
        $recentOrders = Order::latest()
            ->limit(50)
            ->get()
            ->map(function($order) {
                return $this->formatOrderForFrontend($order);
            });

        return response()->json([
            'stats' => $stats,
            'pending_orders' => $pendingOrders,
            'active_orders' => $activeOrders,
            'drawers' => $drawers,
            'recent_orders' => $recentOrders
        ]);
    }

    /**
     * Format order data to match frontend expectations
     */
    private function formatOrderForFrontend($order)
    {
        // Use the actual order_id column value (with # prefix if needed)
        $orderId = $order->order_id;
        // If order_id doesn't have # prefix, add it
        if ($orderId && !str_starts_with($orderId, '#')) {
            $orderId = '#' . $orderId;
        }

        // Determine priority based on due_in string, due_at, or ausDatein
        $priority = $this->calculatePriority($order);

        // Get current assignment if any
        $currentAssignment = $order->currentAssignments()->with('user')->first();

        // Determine source
        $source = 'client_portal';
        if ($order->external_order_id) {
            $source = 'external_api';
        } elseif ($order->created_from_api_at) {
            $source = 'api';
        }

        // Get address/description - use property if available, otherwise instruction
        $address = $order->property 
            ? $order->property
            : ($order->instruction 
                ? substr($order->instruction, 0, 50) . (strlen($order->instruction) > 50 ? '...' : '')
                : 'No address provided');

        return [
            'id' => $order->id,
            'order_id' => $orderId,
            'order_number' => $order->order_number,
            'external_order_id' => $order->external_order_id,
            
            // Client info
            'client' => $this->getClientInfo($order, $currentAssignment),
            
            // Project info
            'project' => [
                'id' => $order->project_id,
                'name' => $this->getProjectName($order->project_id)
            ],
            
            // Address field (using property as primary)
            'address' => $address,
            
            // Property field - ADDED
            'property' => $order->property,
            
            // Instruction/description
            'instruction' => $order->instruction,
            
            // Status information
            'status' => $order->status,
            'status_display' => $this->getStatusDisplayName($order->status),
            
            // Priority
            'priority' => $priority,
            
            // Source
            'source' => $source,
            
            // Timestamps
            'created_at' => $order->created_at ? $order->created_at->toISOString() : null,
            'ausDatein' => $order->ausDatein, // ADDED - Australian date in field
            'due_at' => $order->due_at ? $order->due_at->toISOString() : null,
            'due_in' => $this->calculateDueIn($order), // Enhanced to use ausDatein
            'assigned_at' => $order->assigned_at ? $order->assigned_at->toISOString() : null,
            
            // Assignment info
            'assigned_to' => $currentAssignment && $currentAssignment->user ? [
                'id' => $currentAssignment->user_id,
                'name' => $currentAssignment->user->name ?? 'Unknown',
                'role' => $currentAssignment->role,
                'email' => $currentAssignment->user->email ?? null
            ] : null,
            
            // QA flags
            'd_live_qa' => $order->d_live_qa ?? false,
            'c_live_qa' => $order->c_live_qa ?? false,
            'qa_live_qa' => $order->qa_live_qa ?? false,
            
            // Batch info
            'batch' => $order->batch,
        ];
    }

    /**
     * Calculate priority based on due_in string, due_at, or ausDatein
     */
    private function calculatePriority($order)
    {
        // Default priority
        $priority = 'normal';
        
        // First check if we have due_at timestamp
        if ($order->due_at) {
            $hoursUntilDue = now()->diffInHours($order->due_at, false);
            if ($hoursUntilDue < 24) {
                return 'high';
            } elseif ($hoursUntilDue < 72) {
                return 'medium';
            }
        }
        
        // Check ausDatein if available (Australian date format)
        if ($order->ausDatein) {
            try {
                // Try to parse ausDatein as a date (assuming it's in a standard format)
                $ausDate = Carbon::parse($order->ausDatein);
                $hoursUntilDue = now()->diffInHours($ausDate, false);
                
                if ($hoursUntilDue < 24) {
                    return 'high';
                } elseif ($hoursUntilDue < 72) {
                    return 'medium';
                }
            } catch (\Exception $e) {
                // If ausDatein can't be parsed as a date, ignore it
            }
        }
        
        // If no due_at or ausDatein, try to parse due_in string
        if ($order->due_in) {
            $dueInLower = strtolower($order->due_in);
            
            // Extract hours from strings like "About 14 hours", "About 2 days", etc.
            if (preg_match('/(\d+)\s*(hour|day)/i', $dueInLower, $matches)) {
                $value = (int)$matches[1];
                $unit = $matches[2];
                
                if ($unit === 'day') {
                    $value = $value * 24; // Convert days to hours
                }
                
                if ($value < 24) {
                    return 'high';
                } elseif ($value < 72) {
                    return 'medium';
                }
            }
        }
        
        return $priority;
    }

    /**
     * Calculate due_in display string
     * This uses the stored due_in value from client portal or calculates from ausDatein/due_at
     */
    private function calculateDueIn($order)
    {
        // If we have due_in value from client portal, use it directly
        if ($order->due_in) {
            return $order->due_in;
        }
        
        // If we have ausDatein (Australian date in), calculate from that
        if ($order->ausDatein) {
            try {
                // Try to parse ausDatein as a date
                $ausDate = Carbon::parse($order->ausDatein);
                $now = now();
                
                if ($ausDate->isPast()) {
                    $diff = $ausDate->diff($now);
                    if ($diff->days > 0) {
                        return 'About ' . $diff->days . ' days overdue';
                    } elseif ($diff->h > 0) {
                        return 'About ' . $diff->h . ' hours overdue';
                    } else {
                        return 'About ' . $diff->i . ' minutes overdue';
                    }
                } else {
                    $diff = $now->diff($ausDate);
                    if ($diff->days > 0) {
                        return 'About ' . $diff->days . ' days remaining';
                    } elseif ($diff->h > 0) {
                        return 'About ' . $diff->h . ' hours remaining';
                    } else {
                        return 'About ' . $diff->i . ' minutes remaining';
                    }
                }
            } catch (\Exception $e) {
                // If ausDatein can't be parsed as a date, treat it as a string
                return $order->ausDatein;
            }
        }
        
        // Fallback to calculate from due_at if due_in is not available
        if ($order->due_at) {
            $now = now();
            $dueDate = $order->due_at;
            
            if ($dueDate->isPast()) {
                $diff = $dueDate->diff($now);
                if ($diff->days > 0) {
                    return 'About ' . $diff->days . ' days overdue';
                } elseif ($diff->h > 0) {
                    return 'About ' . $diff->h . ' hours overdue';
                } else {
                    return 'About ' . $diff->i . ' minutes overdue';
                }
            } else {
                $diff = $now->diff($dueDate);
                if ($diff->days > 0) {
                    return 'About ' . $diff->days . ' days remaining';
                } elseif ($diff->h > 0) {
                    return 'About ' . $diff->h . ' hours remaining';
                } else {
                    return 'About ' . $diff->i . ' minutes remaining';
                }
            }
        }
        
        return 'Not set';
    }

    /**
     * Get client information
     */
    private function getClientInfo($order, $currentAssignment = null)
    {
        // Try to get client from relationship if it exists
        if (method_exists($order, 'client') && $order->client) {
            return [
                'id' => $order->client->id,
                'name' => $order->client->name,
                'email' => $order->client->email ?? null
            ];
        }
        
        // Try to get from assignment
        if ($currentAssignment && $currentAssignment->user) {
            return [
                'id' => $currentAssignment->user_id,
                'name' => $currentAssignment->user->name ?? 'Client',
                'email' => $currentAssignment->user->email ?? null
            ];
        }
        
        // Try to get from client_id if available
        if ($order->client_id) {
            return [
                'id' => $order->client_id,
                'name' => 'Client #' . $order->client_id,
                'email' => null
            ];
        }
        
        // Default fallback
        return [
            'id' => null,
            'name' => 'Unknown Client',
            'email' => null
        ];
    }

    /**
     * Get project name
     */
    private function getProjectName($projectId)
    {
        if (!$projectId) {
            return 'Default Project';
        }
        
        // You can implement project name lookup here if you have a Project model
        // For now, return a default
        return 'Project #' . $projectId;
    }

    /**
     * Get human-readable status display name
     */
    private function getStatusDisplayName($status)
    {
        $statusNames = [
            Order::STATUS_PENDING => 'Pending',
            Order::STATUS_ASSIGNED => 'Assigned to Drawer',
            Order::STATUS_DRAWER_DONE => 'Drawer Completed',
            Order::STATUS_CHECKER_REVIEW => 'Checker Review',
            Order::STATUS_CHECKER_DONE => 'Checker Completed',
            Order::STATUS_QA_REVIEW => 'QA Review',
            Order::STATUS_QA_DONE => 'QA Completed',
            Order::STATUS_COMPLETED => 'Completed',
            Order::STATUS_REJECTED => 'Rejected',
        ];

        return $statusNames[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Parse hours from due_in string
     * Helper method to extract hours from strings like "About 14 hours"
     */
    private function parseHoursFromDueIn($dueIn)
    {
        if (!$dueIn) {
            return null;
        }
        
        $dueInLower = strtolower($dueIn);
        
        // Pattern to match numbers followed by hours/days
        if (preg_match('/(\d+)\s*(hour|day)/i', $dueInLower, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2];
            
            if ($unit === 'day') {
                return $value * 24; // Convert days to hours
            }
            
            return $value; // Hours
        }
        
        return null;
    }

    /**
     * Get order by ID for details view
     */
    public function show($id)
    {
        $order = Order::with(['assignments.user', 'statusLogs'])->findOrFail($id);
        
        return response()->json([
            'order' => $this->formatOrderForFrontend($order)
        ]);
    }

    /**
     * Get orders filtered by various criteria
     */
    public function filter()
    {
        $query = Order::query();
        
        // Filter by status
        if (request()->has('status')) {
            $query->where('status', request('status'));
        }
        
        // Filter by priority
        if (request()->has('priority')) {
            $query->where('priority', request('priority'));
        }
        
        // Filter by source
        if (request()->has('source')) {
            $query->where('source', request('source'));
        }
        
        // Filter by date range
        if (request()->has('from_date')) {
            $query->whereDate('created_at', '>=', request('from_date'));
        }
        
        if (request()->has('to_date')) {
            $query->whereDate('created_at', '<=', request('to_date'));
        }
        
        // Filter by ausDatein (Australian date)
        if (request()->has('ausDatein_from')) {
            $query->whereDate('ausDatein', '>=', request('ausDatein_from'));
        }
        
        if (request()->has('ausDatein_to')) {
            $query->whereDate('ausDatein', '<=', request('ausDatein_to'));
        }
        
        // Search by property
        if (request()->has('search')) {
            $search = request('search');
            $query->where(function($q) use ($search) {
                $q->where('property', 'LIKE', "%{$search}%")
                  ->orWhere('order_id', 'LIKE', "%{$search}%")
                  ->orWhere('order_number', 'LIKE', "%{$search}%")
                  ->orWhere('instruction', 'LIKE', "%{$search}%");
            });
        }
        
        $orders = $query->latest()
            ->limit(100)
            ->get()
            ->map(function($order) {
                return $this->formatOrderForFrontend($order);
            });
        
        return response()->json([
            'orders' => $orders,
            'total' => $orders->count()
        ]);
    }
}