<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Device;
use App\Models\Sport;
use App\Models\TrackingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TrackingSessionWebController extends Controller
{
    public function create(): View
    {
        return view('tracking-sessions.create', [
            'athletes' => Athlete::query()->orderBy('name')->get(),
            'devices' => Device::query()
                ->with('athlete')
                ->whereNotNull('athlete_id')
                ->whereIn('status', [
                    Device::STATUS_CLAIMED,
                    Device::STATUS_READY,
                    Device::STATUS_LIVE,
                    Device::STATUS_OFFLINE,
                    'active',
                ])
                ->orderBy('name')
                ->get(),
            'sports' => Sport::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
        ]);

        $sport = Sport::query()->findOrFail($validated['sport_id']);
        $deviceId = $validated['device_id'];

        Device::query()
            ->whereKey($deviceId)
            ->where('athlete_id', $validated['athlete_id'])
            ->whereIn('status', [
                Device::STATUS_CLAIMED,
                Device::STATUS_READY,
                Device::STATUS_LIVE,
                Device::STATUS_OFFLINE,
                'active',
            ])
            ->firstOrFail();

        $trackingSession = TrackingSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'session_token' => Str::random(48),
            'athlete_id' => $validated['athlete_id'],
            'device_id' => $deviceId,
            'sport_id' => $validated['sport_id'],
            'race_entry_id' => null,
            'device_source' => 'garmin_connect_iq',
            'activity_type' => Str::slug($sport->name, '_'),
            'status' => 'active',
            'started_at' => now(),
            'telemetry_source' => 'connect_iq',
            'metadata' => [],
        ]);

        return redirect()
            ->route('live-sessions.index')
            ->with('status', 'Tracking session started.')
            ->with('session_token', $trackingSession->session_token);
    }
}
