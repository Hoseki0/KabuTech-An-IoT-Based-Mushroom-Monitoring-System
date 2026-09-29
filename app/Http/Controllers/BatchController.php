<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    /** List active and recent substrate batches */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');
        $query = Batch::orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $batches = $query->get()->map(function ($batch) {
            $daysInStage = 0;
            if ($batch->stage === 'inoculation' && $batch->inoculation_date) {
                $daysInStage = now()->diffInDays($batch->inoculation_date);
            } elseif ($batch->stage === 'fruiting' && $batch->fruiting_start_date) {
                $daysInStage = now()->diffInDays($batch->fruiting_start_date);
            } else {
                $daysInStage = now()->diffInDays($batch->created_at);
            }

            return [
                'id'                    => $batch->id,
                'batch_code'            => $batch->batch_code,
                'box_id'                => $batch->box_id,
                'mushroom_type'         => $batch->mushroom_type,
                'stage'                 => $batch->stage,
                'bag_count'             => $batch->bag_count,
                'inoculation_date'      => $batch->inoculation_date ? $batch->inoculation_date->toDateString() : null,
                'fruiting_start_date'   => $batch->fruiting_start_date ? $batch->fruiting_start_date->toDateString() : null,
                'expected_harvest_date' => $batch->expected_harvest_date ? $batch->expected_harvest_date->toDateString() : null,
                'notes'                 => $batch->notes,
                'status'                => $batch->status,
                'days_in_stage'         => $daysInStage,
            ];
        });

        return response()->json(['success' => true, 'data' => $batches]);
    }

    /** Create a new batch */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_code'            => 'required|string|max:64|unique:batches,batch_code',
            'box_id'                => 'nullable|string|max:32',
            'mushroom_type'         => 'required|string|max:64',
            'stage'                 => 'required|string|in:inoculation,incubation,fruiting,spent',
            'bag_count'             => 'required|integer|min:1',
            'inoculation_date'      => 'nullable|date',
            'fruiting_start_date'   => 'nullable|date',
            'expected_harvest_date' => 'nullable|date',
            'notes'                 => 'nullable|string',
        ]);

        $batch = Batch::create([
            'batch_code'            => $validated['batch_code'],
            'box_id'                => $validated['box_id'] ?? 'box_a',
            'mushroom_type'         => $validated['mushroom_type'],
            'stage'                 => $validated['stage'],
            'bag_count'             => $validated['bag_count'],
            'inoculation_date'      => $validated['inoculation_date'] ?? now()->toDateString(),
            'fruiting_start_date'   => $validated['fruiting_start_date'] ?? null,
            'expected_harvest_date' => $validated['expected_harvest_date'] ?? null,
            'notes'                 => $validated['notes'] ?? null,
            'status'                => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Substrate batch created successfully', 'data' => $batch], 201);
    }

    /** Update batch details or growth stage */
    public function update(Request $request, $id): JsonResponse
    {
        $batch = Batch::find($id);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        $validated = $request->validate([
            'box_id'                => 'nullable|string|max:32',
            'mushroom_type'         => 'nullable|string|max:64',
            'stage'                 => 'nullable|string|in:inoculation,incubation,fruiting,spent',
            'bag_count'             => 'nullable|integer|min:1',
            'inoculation_date'      => 'nullable|date',
            'fruiting_start_date'   => 'nullable|date',
            'expected_harvest_date' => 'nullable|date',
            'notes'                 => 'nullable|string',
            'status'                => 'nullable|string|in:active,archived,completed',
        ]);

        $batch->update(array_filter($validated, fn ($val) => $val !== null));

        return response()->json(['success' => true, 'message' => 'Substrate batch updated', 'data' => $batch]);
    }

    /** Delete or archive a batch */
    public function destroy($id): JsonResponse
    {
        $batch = Batch::find($id);
        if (!$batch) {
            return response()->json(['success' => false, 'message' => 'Batch not found'], 404);
        }

        $batch->delete();

        return response()->json(['success' => true, 'message' => 'Batch removed']);
    }
}
