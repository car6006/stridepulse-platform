<?php

return [
    'provider' => env('MAP_PROVIDER', 'maplibre'),
    'style_url' => env('MAP_STYLE_URL') ?: 'https://tiles.openfreemap.org/styles/liberty',
];
