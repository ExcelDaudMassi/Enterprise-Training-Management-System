<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        return inertia('Admin/Settings');
    }

    public function getPreparationAlertSettings()
    {
        $mode = \App\Models\Setting::where('key', 'preparation_alert_mode')->first();
        $days = \App\Models\Setting::where('key', 'preparation_alert_days')->first();

        return response()->json([
            'preparation_alert_mode' => $mode ? $mode->value : 'manual',
            'preparation_alert_days' => $days ? (int) $days->value : 14,
        ]);
    }

    public function updatePreparationAlertSettings(Request $request)
    {
        $request->validate([
            'preparation_alert_mode' => 'required|in:manual,auto_acc,auto_cancel',
            'preparation_alert_days' => 'required|integer|min:1|max:365',
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'preparation_alert_mode'],
            ['value' => $request->preparation_alert_mode]
        );

        \App\Models\Setting::updateOrCreate(
            ['key' => 'preparation_alert_days'],
            ['value' => $request->preparation_alert_days]
        );

        return response()->json([
            'message' => 'Pengaturan Preparation Alert berhasil disimpan.',
        ]);
    }
}
