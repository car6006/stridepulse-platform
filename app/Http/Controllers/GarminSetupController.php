<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GarminSetupController extends Controller
{
    public function index(): View
    {
        return view('garmin-setup.index', [
            'telemetryEndpoint' => url('/api/garmin/telemetry'),
            'setupToken' => session('garmin_setup_token'),
        ]);
    }

    public function generate(): RedirectResponse
    {
        session(['garmin_setup_token' => Str::random(48)]);

        return redirect()
            ->route('garmin-setup.index')
            ->with('status', 'Garmin setup token generated.');
    }
}
