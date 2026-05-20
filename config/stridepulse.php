<?php

return [
    'tracking' => [
        'stale_after_seconds' => env('STRIDEPULSE_STALE_AFTER_SECONDS', 120),
        'offline_after_seconds' => env('STRIDEPULSE_OFFLINE_AFTER_SECONDS', 300),
        'stationary_after_seconds' => env('STRIDEPULSE_STATIONARY_AFTER_SECONDS', 300),
        'stationary_distance_threshold_m' => env('STRIDEPULSE_STATIONARY_DISTANCE_THRESHOLD_M', 10),
        'stationary_gps_threshold_m' => env('STRIDEPULSE_STATIONARY_GPS_THRESHOLD_M', 25),
        'abandon_after_seconds' => env('STRIDEPULSE_ABANDON_AFTER_SECONDS', 3600),
        'notification_cooldown_seconds' => env('STRIDEPULSE_NOTIFICATION_COOLDOWN_SECONDS', 900),
    ],
];
