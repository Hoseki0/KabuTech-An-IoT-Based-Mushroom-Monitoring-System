<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\HarvestLog;
use App\Models\SensorData;
use App\Models\User;
use App\Services\MlHarvestPredictionModel;
use App\Services\MushroomSpeciesCatalog;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        $users = User::query()->orderBy('id')->paginate(20);
        $harvestInventory = HarvestLog::orderBy('harvested_at', 'desc')->orderBy('id', 'desc')->get();

        $whiteTotalGrams = (float) $harvestInventory->where('mushroom_type', 'oyster_white')->sum('weight_grams');
        $brownTotalGrams = (float) $harvestInventory->where('mushroom_type', 'oyster_brown')->sum('weight_grams');
        $totalGrams      = (float) $harvestInventory->sum('weight_grams');

        $inventorySummary = [
            'white_grams' => round($whiteTotalGrams, 1),
            'white_kg'    => round($whiteTotalGrams / 1000, 2),
            'brown_grams' => round($brownTotalGrams, 1),
            'brown_kg'    => round($brownTotalGrams / 1000, 2),
            'total_grams' => round($totalGrams, 1),
            'total_kg'    => round($totalGrams / 1000, 2),
            'count'       => $harvestInventory->count(),
        ];

        return view('admin.index', compact('users', 'harvestInventory', 'inventorySummary'));
    }

    /** Add a harvested mushroom entry to the inventory (stored in MySQL harvest_logs). */
    public function storeInventory(Request $request)
    {
        $validated = $request->validate([
            'mushroom_type' => 'required|string|in:oyster_white,oyster_brown',
            'batch_name'    => 'required|string|max:128',
            'flush_number'  => 'required|integer|min:1|max:10',
            'qty'           => 'required|numeric|min:0.1',
            'unit'          => 'required|string|in:g,kg',
            'quality'       => 'nullable|string|max:32',
        ]);

        $weightGrams = $validated['unit'] === 'kg'
            ? round((float) $validated['qty'] * 1000, 2)
            : round((float) $validated['qty'], 2);

        // Default 4-bag box substrate (4000g) for BE% calculation
        $substrateGrams = 4000.00;
        $bePercentage   = round(($weightGrams / $substrateGrams) * 100, 2);

        $latestSensor = SensorData::latest('recorded_at')->first();

        HarvestLog::create([
            'batch_id'               => 'BATCH-' . strtoupper(substr(uniqid(), -5)),
            'batch_name'             => $validated['batch_name'],
            'mushroom_type'          => MushroomSpeciesCatalog::normalizeKey($validated['mushroom_type']),
            'incubator'              => 'box_a',
            'flush_number'           => (int) $validated['flush_number'],
            'weight_grams'           => $weightGrams,
            'substrate_weight_grams' => $substrateGrams,
            'biological_efficiency'  => $bePercentage,
            'temperature'            => $latestSensor && $latestSensor->temperature !== null ? round((float) $latestSensor->temperature, 1) : null,
            'humidity'               => $latestSensor && $latestSensor->humidity !== null ? round((float) $latestSensor->humidity, 1) : null,
            'quality'                => $validated['quality'] ?? 'Grade A',
            'notes'                  => 'Logged via Admin Harvested Mushroom Inventory.',
            'harvested_at'           => now(),
        ]);

        MlHarvestPredictionModel::clearCache();

        $speciesLabel = MushroomSpeciesCatalog::profile($validated['mushroom_type'])['label'] ?? 'Mushroom';
        return back()->with('status', "Added harvested {$speciesLabel} ({$weightGrams} g) to inventory.");
    }

    /** Remove a harvested mushroom entry from the inventory. */
    public function destroyInventory(string $id)
    {
        $log = HarvestLog::find($id);
        if ($log) {
            $log->delete();
            MlHarvestPredictionModel::clearCache();
        }

        return back()->with('status', 'Harvested mushroom entry removed from inventory.');
    }

    /** Verify / unverify a regular user. */
    public function verify(User $user)
    {
        $user->update(['is_verified' => ! $user->is_verified]);

        $label = $user->is_verified ? 'verified' : 'unverified';
        return back()->with('status', "User \"{$user->name}\" has been {$label}.");
    }

    /** Permanently delete a regular user. */
    public function destroyUser(User $user)
    {
        $user->delete();
        return back()->with('status', 'User removed successfully.');
    }
}
