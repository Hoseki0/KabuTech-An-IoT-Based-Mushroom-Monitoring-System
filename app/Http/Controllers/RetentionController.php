<?php

namespace App\Http\Controllers;

use App\Models\SensorData;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RetentionController extends Controller
{
    /** Show retention settings page */
    public function index()
    {
        $days      = (int) SystemSetting::get('retention_days', 90);
        $lastRun   = SystemSetting::get('retention_last_run');
        $lastCount = SystemSetting::get('retention_last_count');
        $totalRows = SensorData::count();

        // Count rows that WOULD be deleted with the current setting
        $cutoff      = Carbon::now()->subDays($days);
        $eligibleRows = SensorData::where('recorded_at', '<', $cutoff)->count();

        $oldestRow = SensorData::orderBy('recorded_at', 'asc')->value('recorded_at');

        return view('admin.retention', compact(
            'days', 'lastRun', 'lastCount', 'totalRows', 'eligibleRows', 'oldestRow'
        ));
    }

    /** Save new retention_days setting */
    public function update(Request $request)
    {
        $request->validate([
            'retention_days' => 'required|integer|min:7|max:3650',
        ]);

        SystemSetting::set('retention_days', (string) $request->integer('retention_days'));

        return back()->with('status', 'Retention policy updated to ' . $request->retention_days . ' days.');
    }

    /** Manually trigger a prune run now */
    public function prune(Request $request)
    {
        $days   = (int) SystemSetting::get('retention_days', 90);
        $cutoff = Carbon::now()->subDays($days);
        $deleted = SensorData::where('recorded_at', '<', $cutoff)->delete();

        SystemSetting::set('retention_last_run',   now()->toIso8601String());
        SystemSetting::set('retention_last_count', (string) $deleted);

        return back()->with('status', "Manual prune complete — {$deleted} row(s) deleted (older than {$days} days).");
    }
}
