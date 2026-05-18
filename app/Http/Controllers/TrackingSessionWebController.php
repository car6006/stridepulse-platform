<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
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
            'sports' => Sport::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
        ]);

        $sport = Sport::query()->findOrFail($validated['sport_id']);

        $trackingSession = TrackingSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'session_token' => Str::random(48),
            'athlete_id' => $validated['athlete_id'],
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
