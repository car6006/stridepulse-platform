@php
    $distanceKm = $latestTelemetry?->distance_m !== null
        ? number_format(((float) $latestTelemetry->distance_m) / 1000, 2)
        : '--';

    $pace = '--';
    if ($latestTelemetry?->pace_sec_per_km !== null) {
        $minutes = intdiv((int) $latestTelemetry->pace_sec_per_km, 60);
        $seconds = ((int) $latestTelemetry->pace_sec_per_km) % 60;
        $pace = sprintf('%d:%02d /km', $minutes, $seconds);
    }

    $averagePace = '--';
    if ($latestTelemetry?->average_pace_sec_per_km !== null) {
        $minutes = intdiv((int) $latestTelemetry->average_pace_sec_per_km, 60);
        $seconds = ((int) $latestTelemetry->average_pace_sec_per_km) % 60;
        $averagePace = sprintf('%d:%02d /km', $minutes, $seconds);
    }

    $elapsedTime = '--';
    $elapsedSeconds = $latestTelemetry?->elapsed_time_seconds ?? $latestTelemetry?->elapsed_seconds;
    if ($elapsedSeconds !== null) {
        $hours = intdiv((int) $elapsedSeconds, 3600);
        $minutes = intdiv(((int) $elapsedSeconds) % 3600, 60);
        $seconds = ((int) $elapsedSeconds) % 60;
        $elapsedTime = $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    $currentSpeed = $latestTelemetry?->current_speed_mps !== null
        ? number_format(((float) $latestTelemetry->current_speed_mps) * 3.6, 1)
        : '--';

    $lastSeen = $trackingSession->last_seen_at?->diffForHumans() ?? '--';
    $hasLocation = $latestTelemetry?->latitude !== null && $latestTelemetry?->longitude !== null;
    $mapCenter = $hasLocation
        ? [(float) $latestTelemetry->latitude, (float) $latestTelemetry->longitude]
        : null;
    $trailPoints = ($breadcrumbTrail ?? collect())->values();
    $isEnded = $trackingSession->ended_at !== null || $trackingSession->status === 'ended';
    $isStale = ! $isEnded && (
        $trackingSession->last_seen_at === null ||
        $trackingSession->last_seen_at->lt(now()->subMinutes(3))
    );
    $statusLabel = $isEnded ? 'Ended' : ($isStale ? 'Offline' : 'Live');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="refresh" content="60">
        <title>Live Session - {{ config('app.name', 'StridePulse') }}</title>
        <link
            rel="stylesheet"
            href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
            integrity="sha256-p4NxAoJBhIINfQPD9eMQpQmHTVwRIsjD5fEnwV1b3r0="
            crossorigin=""
        >
        <style>
            :root {
                color-scheme: light;
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                background: #f6f8fb;
                color: #101828;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: #f6f8fb;
            }

            .page {
                width: min(1120px, calc(100% - 32px));
                margin: 0 auto;
                padding: 32px 0;
            }

            .header {
                display: flex;
                justify-content: space-between;
                gap: 24px;
                align-items: flex-start;
                padding-bottom: 24px;
                border-bottom: 1px solid #d9e2ec;
            }

            .brand-row {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 8px;
            }

            .eyebrow {
                margin: 0;
                color: #526071;
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            h1 {
                margin: 0;
                font-size: clamp(28px, 4vw, 44px);
                line-height: 1.05;
            }

            .status {
                display: inline-flex;
                align-items: center;
                border-radius: 999px;
                padding: 8px 12px;
                background: #d9fbe8;
                color: #087443;
                font-size: 14px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .status.offline {
                background: #fff2cc;
                color: #8a5a00;
            }

            .status.ended {
                background: #e7edf4;
                color: #344054;
            }

            .notice {
                margin-top: 16px;
                border-radius: 8px;
                border: 1px solid #f3d18a;
                background: #fff8e6;
                padding: 12px 14px;
                color: #664400;
                font-size: 14px;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 12px;
                margin-top: 24px;
            }

            .panel {
                background: #ffffff;
                border: 1px solid #d9e2ec;
                border-radius: 8px;
                padding: 18px;
                box-shadow: 0 1px 2px rgba(16, 24, 40, 0.05);
            }

            .panel.wide {
                grid-column: span 2;
            }

            .label {
                margin: 0 0 8px;
                color: #697586;
                font-size: 13px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .value {
                margin: 0;
                color: #101828;
                font-size: 28px;
                font-weight: 800;
                line-height: 1.1;
            }

            .subvalue {
                margin: 8px 0 0;
                color: #526071;
                font-size: 15px;
            }

            .map {
                min-height: 220px;
                border-radius: 8px;
                border: 1px solid #cbd5e1;
                background:
                    linear-gradient(90deg, rgba(15, 118, 110, 0.08) 1px, transparent 1px),
                    linear-gradient(rgba(15, 118, 110, 0.08) 1px, transparent 1px),
                    #eef7f4;
                background-size: 28px 28px;
                color: #24463f;
                text-align: center;
                padding: 16px;
            }

            #live-map {
                min-height: 360px;
                padding: 0;
                overflow: hidden;
            }

            .map-empty {
                min-height: 220px;
                display: grid;
                place-items: center;
                padding: 16px;
            }

            .athlete-marker {
                align-items: center;
                background: #0f766e;
                border: 3px solid #ffffff;
                border-radius: 999px;
                box-shadow: 0 4px 12px rgba(15, 23, 42, 0.28);
                color: #ffffff;
                display: flex;
                font-size: 13px;
                font-weight: 800;
                height: 28px;
                justify-content: center;
                width: 28px;
            }

            .link {
                color: #0f5d9c;
                font-weight: 700;
                overflow-wrap: anywhere;
            }

            @media (max-width: 820px) {
                .header {
                    flex-direction: column;
                }

                .grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }

                .panel.wide {
                    grid-column: span 2;
                }
            }

            @media (max-width: 520px) {
                .page {
                    width: min(100% - 20px, 1120px);
                    padding: 20px 0;
                }

                .grid {
                    grid-template-columns: 1fr;
                }

                .panel.wide {
                    grid-column: span 1;
                }
            }
        </style>
    </head>
    <body>
        <main class="page">
            <section class="header">
                <div>
                    <div class="brand-row">
                        <p class="eyebrow">Future Fit Sports</p>
                        <span aria-hidden="true">/</span>
                        <p class="eyebrow">StridePulse Live</p>
                    </div>
                    <h1>{{ $trackingSession->athlete?->name ?? 'Athlete' }}</h1>
                    <p class="subvalue">
                        {{ $trackingSession->sport?->name ?? ucfirst((string) ($trackingSession->activity_type ?? 'session')) }}
                        @if ($trackingSession->started_at)
                            started {{ $trackingSession->started_at->diffForHumans() }}
                        @endif
                    </p>
                    <p class="subvalue">Session status: {{ $trackingSession->status ?? 'active' }}</p>
                    @if ($isStale)
                        <div class="notice">
                            This session is offline or stale. The page refreshes every 60 seconds and will update when new Garmin telemetry arrives.
                        </div>
                    @endif
                </div>
                <div class="status {{ $isStale ? 'offline' : '' }} {{ $isEnded ? 'ended' : '' }}">{{ $statusLabel }}</div>
            </section>

            <section class="grid" aria-label="Live session metrics">
                <article class="panel">
                    <p class="label">Last seen</p>
                    <p class="value">{{ $lastSeen }}</p>
                </article>
                <article class="panel">
                    <p class="label">Distance</p>
                    <p class="value">{{ $distanceKm }}</p>
                    <p class="subvalue">km</p>
                </article>
                <article class="panel">
                    <p class="label">Pace</p>
                    <p class="value">{{ $pace }}</p>
                </article>
                <article class="panel">
                    <p class="label">Average pace</p>
                    <p class="value">{{ $averagePace }}</p>
                </article>
                <article class="panel">
                    <p class="label">Elapsed time</p>
                    <p class="value">{{ $elapsedTime }}</p>
                </article>
                <article class="panel">
                    <p class="label">Current speed</p>
                    <p class="value">{{ $currentSpeed }}</p>
                    <p class="subvalue">km/h</p>
                </article>
                <article class="panel">
                    <p class="label">Heart rate</p>
                    <p class="value">{{ $latestTelemetry?->heart_rate_bpm ?? '--' }}</p>
                    <p class="subvalue">bpm</p>
                </article>
                <article class="panel">
                    <p class="label">Cadence</p>
                    <p class="value">{{ $latestTelemetry?->cadence ?? '--' }}</p>
                    <p class="subvalue">spm</p>
                </article>
                <article class="panel">
                    <p class="label">GPS</p>
                    <p class="value">{{ $latestTelemetry?->gps_status ?? '--' }}</p>
                </article>
                <article class="panel">
                    <p class="label">Battery</p>
                    <p class="value">{{ $latestTelemetry?->battery_percent !== null ? $latestTelemetry->battery_percent.'%' : '--' }}</p>
                </article>
                <article class="panel">
                    <p class="label">Altitude</p>
                    <p class="value">{{ $latestTelemetry?->altitude_m !== null ? number_format((float) $latestTelemetry->altitude_m, 0) : '--' }}</p>
                    <p class="subvalue">m</p>
                </article>
                <article class="panel">
                    <p class="label">Heading</p>
                    <p class="value">{{ $latestTelemetry?->heading_degrees !== null ? number_format((float) $latestTelemetry->heading_degrees, 0).'°' : '--' }}</p>
                </article>
                <article class="panel">
                    <p class="label">Ascent / descent</p>
                    <p class="value">
                        {{ $latestTelemetry?->ascent_m !== null ? number_format((float) $latestTelemetry->ascent_m, 0) : '--' }}
                        /
                        {{ $latestTelemetry?->descent_m !== null ? number_format((float) $latestTelemetry->descent_m, 0) : '--' }}
                    </p>
                    <p class="subvalue">m</p>
                </article>
                <article class="panel">
                    <p class="label">Calories</p>
                    <p class="value">{{ $latestTelemetry?->calories ?? '--' }}</p>
                    <p class="subvalue">kcal</p>
                </article>
                <article class="panel">
                    <p class="label">Lap</p>
                    <p class="value">{{ $latestTelemetry?->lap_number ?? '--' }}</p>
                </article>
                <article class="panel">
                    <p class="label">Source</p>
                    <p class="value">{{ $trackingSession->telemetry_source ?? '--' }}</p>
                </article>

                <article class="panel wide">
                    <p class="label">Map</p>
                    <div id="live-map" class="map">
                        @if ($hasLocation)
                            <noscript>
                                <div class="map-empty">
                                    <div>
                                        <strong>Location received</strong>
                                        <p class="subvalue">
                                            {{ $latestTelemetry->latitude }}, {{ $latestTelemetry->longitude }}
                                        </p>
                                    </div>
                                </div>
                            </noscript>
                        @else
                            <div class="map-empty">
                                <strong>No location yet</strong>
                                <p class="subvalue">Map appears after Garmin telemetry includes latitude and longitude.</p>
                            </div>
                        @endif
                    </div>
                </article>

                <article class="panel wide">
                    <p class="label">Garmin LiveTrack</p>
                    @if ($trackingSession->livetrack_url)
                        <p class="value">Available</p>
                        <p class="subvalue">
                            <a class="link" href="{{ $trackingSession->livetrack_url }}" target="_blank" rel="noopener noreferrer">
                                Open LiveTrack
                            </a>
                        </p>
                    @else
                        <p class="value">--</p>
                        <p class="subvalue">No LiveTrack link has been received for this session.</p>
                    @endif
                </article>
            </section>
        </main>
        @if ($hasLocation)
            <script
                src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""
            ></script>
            <script>
                const center = @json($mapCenter);
                const trail = @json($trailPoints);
                const map = L.map('live-map', {
                    scrollWheelZoom: false,
                }).setView(center, 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);

                const markerIcon = L.divIcon({
                    className: '',
                    html: '<div class="athlete-marker">●</div>',
                    iconAnchor: [14, 14],
                    iconSize: [28, 28],
                });

                const latLngs = trail.map((point) => [point.lat, point.lng]);
                if (latLngs.length > 1) {
                    L.polyline(latLngs, {
                        color: '#0f766e',
                        opacity: 0.85,
                        weight: 4,
                    }).addTo(map);
                    map.fitBounds(latLngs, { padding: [28, 28], maxZoom: 16 });
                }

                L.marker(center, { icon: markerIcon })
                    .addTo(map)
                    .bindPopup('Latest Garmin telemetry');
            </script>
        @endif
    </body>
</html>
