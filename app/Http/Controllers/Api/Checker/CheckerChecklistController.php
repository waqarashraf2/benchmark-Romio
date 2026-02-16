<?php
// app/Http/Controllers/Api/Checker/CheckerChecklistController.php

namespace App\Http\Controllers\Api\Checker;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderChecklist;
use Illuminate\Http\Request;

class CheckerChecklistController extends Controller
{
    public function index(Order $order)
    {
        $checklist = $order->checklistItems()
            ->with(['checklist', 'user'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->checklist->title,
                    'description' => $item->checklist->description ?? null,
                    'checked' => $item->checked,
                    'checked_at' => $item->checked_at,
                    'checked_by' => $item->user?->name
                ];
            });

        return response()->json($checklist);
    }

    public function toggle(OrderChecklist $checklistItem, Request $request)
    {
        $request->validate(['checked' => 'required|boolean']);

        $checklistItem->update([
            'checked' => $request->checked,
            'checked_at' => $request->checked ? now() : null,
            'user_id' => $request->checked ? $request->user()->id : null
        ]);

        return response()->json(['success' => true]);
    }

    public function complete(Order $order, Request $request)
    {
        // Verify all checklist items are checked
        $allChecked = $order->checklistItems()
            ->where('checked', false)
            ->doesntExist();

        if (!$allChecked) {
            return response()->json(['message' => 'All checklist items must be completed'], 422);
        }

        // Mark order as started for checker
        $order->update([
            'checker_started_at' => now()
        ]);

        return response()->json(['message' => 'Checklist completed, you may now review']);
    }
}