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

    public function getH14Mode()
    {
        $setting = \App\Models\Setting::where('key', 'h14_mode')->first();
        return response()->json([
            'h14_mode' => $setting ? $setting->value : 'manual',
        ]);
    }

    public function updateH14Mode(Request $request)
    {
        $request->validate([
            'h14_mode' => 'required|in:manual,auto_acc,auto_cancel',
        ]);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'h14_mode'],
            ['value' => $request->h14_mode]
        );

        return response()->json([
            'message' => 'Pengaturan Mode H-14 berhasil disimpan.',
        ]);
    }
}
