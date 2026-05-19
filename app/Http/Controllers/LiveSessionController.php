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

        $breadcrumbTrail = $trackingSession->telemetryPoints()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['latitude', 'longitude', 'recorded_at'])
            ->map(fn ($point) => [
                'lat' => (float) $point->latitude,
                'lng' => (float) $point->longitude,
                'recorded_at' => $point->recorded_at?->toIso8601String(),
            ])
            ->values();

        return view('live.session', [
            'trackingSession' => $trackingSession,
            'latestTelemetry' => $latestTelemetry,
            'breadcrumbTrail' => $breadcrumbTrail,
            'mapProvider' => config('maps.provider', 'maplibre'),
            'mapStyleUrl' => config('maps.style_url'),
        ]);
    }
}
