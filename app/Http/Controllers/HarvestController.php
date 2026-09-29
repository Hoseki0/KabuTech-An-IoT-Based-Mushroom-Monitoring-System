<?php

namespace App\Http\Controllers;

use App\Models\HarvestLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HarvestController extends Controller
{
    /** Get all harvest logs + summary statistics */
    public function index(): JsonResponse
    {
        $logs = HarvestLog::orderBy('harvested_at', 'desc')->get();

        $totalWeightGrams = $logs->sum('weight_grams');
        $totalWeightKg    = round($totalWeightGrams / 1000, 2);
        $totalFlushes     = $logs->count();

        // Calculate average biological efficiency
        $logsWithBe = $logs->whereNotNull('biological_efficiency');
        $avgBe      = $logsWithBe->count() > 0 ? round($logsWithBe->avg('biological_efficiency'), 1) : 0;

        // Species harvest breakdown
        $speciesBreakdown = $logs->groupBy('mushroom_type')->map(function ($group) {
            return [
                'total_weight_grams' => $group->sum('weight_grams'),
                'total_weight_kg'    => round($group->sum('weight_grams') / 1000, 2),
                'harvest_count'      => $group->count(),
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_weight_grams' => $totalWeightGrams,
                'total_weight_kg'    => $totalWeightKg,
                'total_flushes'     => $totalFlushes,
                'avg_be_percentage'  => $avgBe,
                'species_breakdown'  => $speciesBreakdown,
            ],
            'logs' => $logs->map(function ($log) {
                return [
                    'id'                     => $log->id,
                    'batch_id'               => $log->batch_id,
                    'batch_name'             => $log->batch_name,
                    'mushroom_type'          => $log->mushroom_type,
                    'incubator'              => $log->incubator,
                    'flush_number'           => $log->flush_number,
                    'weight_grams'           => $log->weight_grams,
                    'weight_kg'              => round($log->weight_grams / 1000, 2),
                    'substrate_weight_grams' => $log->substrate_weight_grams,
                    'biological_efficiency'  => $log->biological_efficiency,
                    'quality'                => $log->quality,
                    'notes'                  => $log->notes,
                    'harvested_at'           => $log->harvested_at ? $log->harvested_at->toIso8601String() : null,
                    'harvested_date_formatted' => $log->harvested_at ? $log->harvested_at->format('M d, Y') : null,
                ];
            }),
        ]);
    }

    /** Record a new harvest entry */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'batch_id'               => 'nullable|string|max:64',
            'batch_name'             => 'required|string|max:128',
            'mushroom_type'          => 'required|string|max:64',
            'incubator'              => 'nullable|string|max:32',
            'flush_number'           => 'required|integer|min:1|max:20',
            'weight_grams'           => 'required|numeric|min:0.1',
            'substrate_weight_grams' => 'nullable|numeric|min:0',
            'quality'                => 'nullable|string|max:32',
            'notes'                  => 'nullable|string|max:1000',
            'harvested_at'           => 'nullable|date',
        ]);

        $weightGrams    = (float) $validated['weight_grams'];
        $substrateGrams = isset($validated['substrate_weight_grams']) && $validated['substrate_weight_grams'] > 0
            ? (float) $validated['substrate_weight_grams']
            : null;

        // Auto-calculate Biological Efficiency (% BE = Fresh Mushroom Weight / Substrate Weight * 100)
        $bePercentage = null;
        if ($substrateGrams && $substrateGrams > 0) {
            $bePercentage = round(($weightGrams / $substrateGrams) * 100, 2);
        }

        $log = HarvestLog::create([
            'batch_id'               => $validated['batch_id'] ?? null,
            'batch_name'             => $validated['batch_name'],
            'mushroom_type'          => $validated['mushroom_type'],
            'incubator'              => $validated['incubator'] ?? 'incubator_a',
            'flush_number'           => $validated['flush_number'],
            'weight_grams'           => $weightGrams,
            'substrate_weight_grams' => $substrateGrams,
            'biological_efficiency'  => $bePercentage,
            'quality'                => $validated['quality'] ?? 'Grade A',
            'notes'                  => $validated['notes'] ?? null,
            'harvested_at'           => isset($validated['harvested_at']) ? $validated['harvested_at'] : now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Harvest entry logged successfully',
            'data'    => $log,
        ], 201);
    }

    /** Delete a harvest log entry */
    public function destroy($id): JsonResponse
    {
        $log = HarvestLog::find($id);
        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Harvest entry not found'], 404);
        }

        $log->delete();

        return response()->json(['success' => true, 'message' => 'Harvest entry removed']);
    }
}
