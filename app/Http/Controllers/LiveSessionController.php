<?php

namespace App\Http\Controllers;

use App\Models\TrackingSession;
use Illuminate\View\View;

class LiveSessionController extends Controller
{
    public function show(string $sessionToken): View
    {
        $trackingSession = TrackingSession::query()
            ->with(['athlete', 'sport'])
            ->where('session_token', $sessionToken)
            ->firstOrFail();

        $latestTelemetry = $trackingSession->telemetryPoints()
            ->latest('recorded_at')
            ->latest('id')
            ->first();

        return view('live.session', [
            'trackingSession' => $trackingSession,
            'latestTelemetry' => $latestTelemetry,
        ]);
    }
}
