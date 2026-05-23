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
    $device = $trackingSession->device;
    $deviceLastTelemetryAt = $device?->last_telemetry_at ?? $trackingSession->last_direct_telemetry_at ?? $trackingSession->last_seen_at;
    $deviceLastTelemetryAge = $deviceLastTelemetryAt?->diffForHumans() ?? 'not yet';
    $hasLocation = $latestTelemetry?->latitude !== null && $latestTelemetry?->longitude !== null;
    $mapCenter = $hasLocation
        ? [(float) $latestTelemetry->latitude, (float) $latestTelemetry->longitude]
        : null;
    $trailPoints = ($breadcrumbTrail ?? collect())->values();
    $finalStatuses = ['stopped', 'completed', 'discarded', 'abandoned', 'ended'];
    $isEnded = $trackingSession->ended_at !== null || in_array((string) $trackingSession->status, $finalStatuses, true);
    $isCompleted = $trackingSession->status === 'completed';
    $isStopped = $trackingSession->status === 'stopped';
    $isStationary = $trackingSession->status === 'stationary';
    $isAbandoned = $trackingSession->status === 'abandoned';
    $isDiscarded = $trackingSession->status === 'discarded';
    $isStale = ! $isEnded && (
        $trackingSession->last_seen_at === null ||
        $trackingSession->last_seen_at->lt(now()->subSeconds((int) config('stridepulse.tracking.offline_after_seconds', 300)))
    );
    $statusLabel = match ((string) $trackingSession->status) {
        'stationary' => 'STATIONARY',
        'stopped' => 'STOPPED',
        'completed' => 'COMPLETED',
        'discarded' => 'DISCARDED',
        'abandoned' => 'ABANDONED',
        default => $isStale ? 'Offline' : 'Live',
    };
    $isTelemetryLive = $latestTelemetry !== null && ! $isStale && ! $isEnded;
    $hasMovementMetrics = $latestTelemetry?->distance_m !== null || $latestTelemetry?->pace_sec_per_km !== null;
    $gpsReady = $hasLocation || filled($latestTelemetry?->gps_status);
    $heartRateReady = $latestTelemetry?->heart_rate_bpm !== null;
    $batteryLabel = $latestTelemetry?->battery_percent !== null ? $latestTelemetry->battery_percent.'%' : null;
    $deviceStatusLabel = $device
        ? ($device->status === \App\Models\Device::STATUS_LIVE || $isTelemetryLive ? 'Online' : Str::headline((string) $device->status))
        : 'Unassigned';
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
        <link
            rel="stylesheet"
            href="https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.css"
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

            .status.stationary {
                background: #e0f2fe;
                color: #075985;
            }

            .ribbon {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 18px;
            }

            .ribbon-item {
                border: 1px solid #cbd5e1;
                border-radius: 999px;
                background: #ffffff;
                color: #344054;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                font-weight: 800;
                padding: 8px 11px;
                text-transform: uppercase;
            }

            .ribbon-item.on {
                border-color: #86efac;
                background: #dcfce7;
                color: #166534;
            }

            .ribbon-item.warn {
                border-color: #fde68a;
                background: #fef3c7;
                color: #92400e;
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

            .notice.final {
                border-color: #b7e4c7;
                background: #effdf4;
                color: #166534;
            }

            .notice.motion {
                border-color: #bae6fd;
                background: #f0f9ff;
                color: #075985;
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
                background: #dce8ee;
                background-size: 28px 28px;
                color: #24463f;
                text-align: center;
                padding: 16px;
                position: relative;
            }

            #live-map {
                min-height: 460px;
                padding: 0;
                overflow: hidden;
                text-align: left;
            }

            #live-map::after {
                content: "";
                pointer-events: none;
                position: absolute;
                inset: 0;
                background: linear-gradient(180deg, rgba(15, 23, 42, 0.04), rgba(15, 23, 42, 0));
                z-index: 1;
            }

            .map-loading {
                min-height: 460px;
                display: grid;
                place-items: center;
                padding: 16px;
                text-align: center;
            }

            .map-empty {
                min-height: 220px;
                display: grid;
                place-items: center;
                padding: 16px;
            }

            .athlete-marker {
                align-items: center;
                background: linear-gradient(135deg, #10b981, #0284c7);
                border: 4px solid #ffffff;
                border-radius: 999px;
                box-shadow: 0 14px 30px rgba(2, 132, 199, 0.34), 0 0 0 10px rgba(16, 185, 129, 0.18);
                color: #ffffff;
                display: flex;
                font-size: 13px;
                font-weight: 800;
                height: 34px;
                justify-content: center;
                transform: translateZ(0);
                transition: transform 400ms ease;
                width: 34px;
            }

            .athlete-marker::after {
                content: "";
                width: 10px;
                height: 10px;
                border-radius: 999px;
                background: #ffffff;
            }

            .maplibregl-ctrl-attrib,
            .leaflet-control-attribution {
                font-size: 10px;
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
                    <div class="ribbon" aria-label="Live telemetry status">
                        <span class="ribbon-item {{ $isTelemetryLive ? 'on' : 'warn' }}">{{ $isTelemetryLive ? 'LIVE' : 'WAITING' }}</span>
                        <span class="ribbon-item {{ $gpsReady ? 'on' : 'warn' }}">GPS {{ $gpsReady ? 'READY' : 'WAITING' }}</span>
                        <span class="ribbon-item {{ $heartRateReady ? 'on' : 'warn' }}">HR {{ $heartRateReady ? $latestTelemetry->heart_rate_bpm.' BPM' : 'WAITING' }}</span>
                        @if ($batteryLabel)
                            <span class="ribbon-item">Battery {{ $batteryLabel }}</span>
                        @endif
                    </div>
                    @if ($isStale)
                        <div class="notice">
                            This session is offline or stale. The page refreshes every 60 seconds and will update when new Garmin telemetry arrives.
                        </div>
                    @endif
                    @if ($isEnded)
                        <div class="notice final">
                            @if ($isCompleted)
                                This activity is complete. Final Garmin telemetry has been received and the activity summary has been saved.
                            @elseif ($isStopped)
                                This activity has stopped. Final telemetry remains visible for review.
                            @elseif ($isAbandoned)
                                This tracking session was marked abandoned after telemetry stopped arriving. Final telemetry remains visible for review.
                            @elseif ($isDiscarded)
                                This tracking session was discarded. Final telemetry remains visible for review.
                            @else
                                This tracking session has ended. Final telemetry remains visible for review.
                            @endif
                        </div>
                    @endif
                    @if ($isTelemetryLive && ! $hasMovementMetrics)
                        <div class="notice motion">
                            Waiting for movement. Telemetry is live and distance or pace will appear once the activity starts moving.
                        </div>
                    @endif
                </div>
                <div class="status {{ $isStale ? 'offline' : '' }} {{ $isEnded ? 'ended' : '' }} {{ $isStationary ? 'stationary' : '' }}">{{ $statusLabel }}</div>
            </section>

            <section class="grid" aria-label="Live session metrics">
                <article class="panel">
                    <p class="label">Last seen</p>
                    <p class="value">{{ $lastSeen }}</p>
                </article>
                <article class="panel">
                    <p class="label">Distance</p>
                    @if ($latestTelemetry?->distance_m !== null)
                        <p class="value">{{ $distanceKm }}</p>
                        <p class="subvalue">km</p>
                    @else
                        <p class="value">Waiting</p>
                        <p class="subvalue">Movement not detected yet</p>
                    @endif
                </article>
                <article class="panel">
                    <p class="label">Pace</p>
                    @if ($latestTelemetry?->pace_sec_per_km !== null)
                        <p class="value">{{ $pace }}</p>
                    @else
                        <p class="value">Waiting</p>
                        <p class="subvalue">Pace appears after movement</p>
                    @endif
                </article>
                @if ($latestTelemetry?->average_pace_sec_per_km !== null)
                    <article class="panel">
                        <p class="label">Average pace</p>
                        <p class="value">{{ $averagePace }}</p>
                    </article>
                @endif
                @if ($elapsedSeconds !== null)
                    <article class="panel">
                        <p class="label">Elapsed time</p>
                        <p class="value">{{ $elapsedTime }}</p>
                    </article>
                @endif
                @if ($latestTelemetry?->current_speed_mps !== null)
                    <article class="panel">
                        <p class="label">Current speed</p>
                        <p class="value">{{ $currentSpeed }}</p>
                        <p class="subvalue">km/h</p>
                    </article>
                @endif
                @if ($latestTelemetry?->heart_rate_bpm !== null)
                    <article class="panel">
                        <p class="label">Heart rate</p>
                        <p class="value">{{ $latestTelemetry->heart_rate_bpm }}</p>
                        <p class="subvalue">bpm</p>
                    </article>
                @endif
                @if ($latestTelemetry?->cadence !== null)
                    <article class="panel">
                        <p class="label">Cadence</p>
                        <p class="value">{{ $latestTelemetry->cadence }}</p>
                        <p class="subvalue">spm</p>
                    </article>
                @endif
                @if ($latestTelemetry?->gps_status !== null)
                    <article class="panel">
                        <p class="label">GPS</p>
                        <p class="value">{{ $latestTelemetry->gps_status }}</p>
                    </article>
                @endif
                @if ($latestTelemetry?->battery_percent !== null)
                    <article class="panel">
                        <p class="label">Battery</p>
                        <p class="value">{{ $latestTelemetry->battery_percent }}%</p>
                    </article>
                @endif
                @if ($latestTelemetry?->altitude_m !== null)
                    <article class="panel">
                        <p class="label">Altitude</p>
                        <p class="value">{{ number_format((float) $latestTelemetry->altitude_m, 0) }}</p>
                        <p class="subvalue">m</p>
                    </article>
                @endif
                @if ($latestTelemetry?->heading_degrees !== null)
                    <article class="panel">
                        <p class="label">Heading</p>
                        <p class="value">{{ number_format((float) $latestTelemetry->heading_degrees, 0) }}°</p>
                    </article>
                @endif
                @if ($latestTelemetry?->ascent_m !== null || $latestTelemetry?->descent_m !== null)
                    <article class="panel">
                        <p class="label">Ascent / descent</p>
                        <p class="value">
                            {{ $latestTelemetry?->ascent_m !== null ? number_format((float) $latestTelemetry->ascent_m, 0) : '0' }}
                            /
                            {{ $latestTelemetry?->descent_m !== null ? number_format((float) $latestTelemetry->descent_m, 0) : '0' }}
                        </p>
                        <p class="subvalue">m</p>
                    </article>
                @endif
                @if ($latestTelemetry?->calories !== null)
                    <article class="panel">
                        <p class="label">Calories</p>
                        <p class="value">{{ $latestTelemetry->calories }}</p>
                        <p class="subvalue">kcal</p>
                    </article>
                @endif
                @if ($latestTelemetry?->lap_number !== null)
                    <article class="panel">
                        <p class="label">Lap</p>
                        <p class="value">{{ $latestTelemetry->lap_number }}</p>
                    </article>
                @endif
                <article class="panel">
                    <p class="label">Source</p>
                    <p class="value">{{ $trackingSession->telemetry_source ?? '--' }}</p>
                </article>

                <article class="panel wide">
                    <p class="label">Map</p>
                    <div id="live-map" class="map">
                        @if ($hasLocation)
                            <div class="map-loading" id="map-loading">
                                <div>
                                    <strong>Loading live map</strong>
                                    <p class="subvalue">Rendering athlete position and breadcrumb trail.</p>
                                </div>
                            </div>
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
                    <p class="label">Device Status</p>
                    <p class="value">{{ $device?->name ?? 'No assigned device' }}</p>
                    <p class="subvalue">
                        {{ $deviceStatusLabel }}
                        @if ($device)
                            · Last telemetry {{ $deviceLastTelemetryAge }}
                        @endif
                    </p>
                    @if ($trackingSession->livetrack_url)
                        <p class="subvalue">
                            <a class="link" href="{{ $trackingSession->livetrack_url }}" target="_blank" rel="noopener noreferrer">
                                Open LiveTrack
                            </a>
                        </p>
                    @endif
                </article>
            </section>
        </main>
        @if ($hasLocation)
            <script src="https://unpkg.com/maplibre-gl@5.24.0/dist/maplibre-gl.js"></script>
            <script
                src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""
            ></script>
            <script>
                const center = @json($mapCenter);
                const trail = @json($trailPoints);
                const mapProvider = @json($mapProvider ?? 'maplibre');
                const mapStyleUrl = @json($mapStyleUrl ?? 'https://tiles.openfreemap.org/styles/liberty');
                const mapElement = document.getElementById('live-map');
                const coordinates = trail.map((point) => [Number(point.lng), Number(point.lat)]);
                const latestCoordinate = [Number(center[1]), Number(center[0])];
                let fallbackStarted = false;
                let maplibreLoaded = false;

                function resetMapContainer() {
                    mapElement.innerHTML = '';
                }

                function athleteMarkerElement() {
                    const marker = document.createElement('div');
                    marker.className = 'athlete-marker';
                    marker.setAttribute('aria-label', 'Athlete current position');
                    return marker;
                }

                function boundsForMapLibre(points) {
                    const bounds = new maplibregl.LngLatBounds(points[0], points[0]);
                    points.forEach((point) => bounds.extend(point));
                    return bounds;
                }

                function initMapLibre() {
                    if (mapProvider !== 'maplibre' || !window.maplibregl || !mapStyleUrl) {
                        throw new Error('MapLibre unavailable');
                    }

                    resetMapContainer();

                    const map = new maplibregl.Map({
                        container: 'live-map',
                        style: mapStyleUrl,
                        center: latestCoordinate,
                        zoom: 15,
                        attributionControl: false,
                    });

                    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
                    map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');

                    map.once('load', () => {
                        maplibreLoaded = true;

                        map.addSource('route', {
                            type: 'geojson',
                            data: {
                                type: 'Feature',
                                geometry: {
                                    type: 'LineString',
                                    coordinates: coordinates.length > 1 ? coordinates : [latestCoordinate],
                                },
                                properties: {},
                            },
                        });

                        map.addLayer({
                            id: 'route-casing',
                            type: 'line',
                            source: 'route',
                            paint: {
                                'line-color': '#ffffff',
                                'line-opacity': 0.9,
                                'line-width': 8,
                            },
                        });

                        map.addLayer({
                            id: 'route-line',
                            type: 'line',
                            source: 'route',
                            paint: {
                                'line-color': '#00a3ff',
                                'line-opacity': 0.95,
                                'line-width': 4,
                            },
                        });

                        new maplibregl.Marker({ element: athleteMarkerElement(), anchor: 'center' })
                            .setLngLat(latestCoordinate)
                            .setPopup(new maplibregl.Popup({ offset: 18 }).setText('Latest Garmin telemetry'))
                            .addTo(map);

                        if (coordinates.length > 1) {
                            map.fitBounds(boundsForMapLibre(coordinates), {
                                padding: 44,
                                maxZoom: 16,
                                duration: 900,
                            });
                        } else {
                            map.easeTo({ center: latestCoordinate, zoom: 15, duration: 700 });
                        }
                    });

                    map.once('error', () => {
                        if (!maplibreLoaded) {
                            initLeafletFallback();
                        }
                    });

                    window.setTimeout(() => {
                        if (!maplibreLoaded) {
                            initLeafletFallback();
                        }
                    }, 4500);
                }

                function initLeafletFallback() {
                    if (fallbackStarted || !window.L) {
                        return;
                    }

                    fallbackStarted = true;
                    resetMapContainer();

                    const map = L.map('live-map', {
                        scrollWheelZoom: false,
                    }).setView(center, 15);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(map);

                    const markerIcon = L.divIcon({
                        className: '',
                        html: '<div class="athlete-marker"></div>',
                        iconAnchor: [15, 15],
                        iconSize: [34, 34],
                    });

                    const latLngs = trail.map((point) => [point.lat, point.lng]);
                    if (latLngs.length > 1) {
                        L.polyline(latLngs, {
                            color: '#00a3ff',
                            opacity: 0.92,
                            weight: 5,
                        }).addTo(map);
                        map.fitBounds(latLngs, { padding: [32, 32], maxZoom: 16 });
                    }

                    L.marker(center, { icon: markerIcon })
                        .addTo(map)
                        .bindPopup('Latest Garmin telemetry');
                }

                try {
                    initMapLibre();
                } catch (error) {
                    initLeafletFallback();
                }
            </script>
        @endif
    </body>
</html>
