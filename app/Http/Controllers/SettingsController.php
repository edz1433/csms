<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * System Settings — administrator only (see the route group in web.php).
 */
class SettingsController extends Controller
{
    public function index()
    {
        return view('settings.index', [
            'settings' => [
                'maintenance_enabled' => Setting::bool('maintenance_enabled'),
                'maintenance_message' => Setting::get('maintenance_message'),
                'maintenance_until' => Setting::get('maintenance_until'),
                'maintenance_declared_at' => Setting::get('maintenance_declared_at'),
                'maintenance_declared_by' => Setting::get('maintenance_declared_by'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'maintenance_enabled' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
            'maintenance_until' => ['nullable', 'date'],
        ]);

        $enabled = (bool) $data['maintenance_enabled'];
        $wasEnabled = Setting::bool('maintenance_enabled');

        Setting::put([
            'maintenance_enabled' => $enabled,
            'maintenance_message' => $data['maintenance_message'] ?? null,
            'maintenance_until' => $data['maintenance_until'] ?? null,
            // Stamp who declared it, so the banner can say so.
            'maintenance_declared_at' => $enabled ? ($wasEnabled ? Setting::get('maintenance_declared_at') : now()->toDateTimeString()) : null,
            'maintenance_declared_by' => $enabled ? ($wasEnabled ? Setting::get('maintenance_declared_by') : $request->user()->name) : null,
        ]);

        return response()->json([
            'ok' => true,
            'enabled' => $enabled,
            'message' => $enabled
                ? 'Maintenance declared. Only administrators can use the system now.'
                : 'Maintenance lifted. The system is open to everyone again.',
        ]);
    }
}
