<?php

namespace App\Http\Controllers;

use App\Models\BoxSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoxSettingController extends Controller
{
    const DEFAULT_BOXES = [
        'box_a' => 'Incubator Box A',
        'box_b' => 'Incubator Box B',
        'box_c' => 'Incubator Box C',
    ];

    /** Get box configuration settings */
    public function index(): JsonResponse
    {
        $settings = BoxSetting::all()->keyBy('box_id');
        $result = [];

        // Always include default boxes box_a, box_b, box_c
        foreach (self::DEFAULT_BOXES as $boxId => $defaultName) {
            if (isset($settings[$boxId])) {
                $result[$boxId] = $settings[$boxId];
            } else {
                $result[$boxId] = [
                    'box_id'        => $boxId,
                    'name'          => $defaultName,
                    'mushroom_type' => 'oyster_mushroom',
                    'stage'         => 'fruiting',
                    'temp_min'      => 20.00,
                    'temp_max'      => 28.00,
                    'hum_min'       => 80.00,
                    'hum_max'       => 95.00,
                    'misting_mode'  => 'auto',
                ];
            }
        }

        // Include any additional custom created boxes from DB
        foreach ($settings as $boxId => $setting) {
            if (!isset($result[$boxId])) {
                $result[$boxId] = $setting;
            }
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /** Save or update box custom settings */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'box_id'        => 'required|string|max:32',
            'name'          => 'required|string|max:128',
            'mushroom_type' => 'nullable|string|max:64',
            'stage'         => 'nullable|string|in:incubation,fruiting',
            'temp_min'      => 'nullable|numeric|between:0,60',
            'temp_max'      => 'nullable|numeric|between:0,60',
            'hum_min'       => 'nullable|numeric|between:0,100',
            'hum_max'       => 'nullable|numeric|between:0,100',
            'misting_mode'  => 'nullable|string|in:auto,manual',
        ]);

        $boxSetting = BoxSetting::updateOrCreate(
            ['box_id' => strtolower(str_replace(' ', '_', $validated['box_id']))],
            [
                'name'          => $validated['name'],
                'mushroom_type' => $validated['mushroom_type'] ?? 'oyster_mushroom',
                'stage'         => $validated['stage'] ?? 'fruiting',
                'temp_min'      => $validated['temp_min'] ?? null,
                'temp_max'      => $validated['temp_max'] ?? null,
                'hum_min'       => $validated['hum_min'] ?? null,
                'hum_max'       => $validated['hum_max'] ?? null,
                'misting_mode'  => $validated['misting_mode'] ?? 'auto',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Settings updated for {$boxSetting->name}",
            'data'    => $boxSetting,
        ]);
    }

    /** Delete a custom incubator box */
    public function destroy(string $boxId): JsonResponse
    {
        if (in_array($boxId, array_keys(self::DEFAULT_BOXES))) {
            return response()->json(['success' => false, 'message' => 'Default system boxes cannot be deleted.'], 422);
        }

        $setting = BoxSetting::where('box_id', $boxId)->first();
        if ($setting) {
            $setting->delete();
        }

        return response()->json(['success' => true, 'message' => 'Incubator box removed']);
    }
}
