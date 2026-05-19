<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function index(): View
    {
        return view('devices.index', [
            'devices' => Device::query()
                ->with('athlete')
                ->withCount('trackingSessions')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function unclaimed(): View
    {
        return view('devices.unclaimed', [
            'devices' => Device::query()
                ->where('status', 'unclaimed')
                ->latest('last_seen_at')
                ->paginate(15),
            'athletes' => Athlete::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('devices.create', [
            'athletes' => Athlete::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'provider' => ['required', 'string', 'max:50'],
        ]);

        $uuid = (string) Str::uuid();

        $device = Device::query()->create([
            'uuid' => $uuid,
            'device_uuid' => $uuid,
            'athlete_id' => $validated['athlete_id'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'provider' => $validated['provider'],
            'device_secret' => Str::random(64),
            'status' => 'active',
            'last_seen_at' => null,
            'metadata' => [
                'pairing_code' => Str::upper(Str::random(8)),
            ],
        ]);

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Garmin device registered.');
    }

    public function claim(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
        ]);

        abort_unless($device->status === 'unclaimed', 404);

        $device->forceFill([
            'athlete_id' => $validated['athlete_id'],
            'status' => 'active',
        ])->save();

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device claimed.');
    }

    public function show(Device $device): View
    {
        return view('devices.show', [
            'device' => $device->load('athlete', 'trackingSessions'),
        ]);
    }
}
