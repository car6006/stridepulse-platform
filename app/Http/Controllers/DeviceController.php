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
                ->where('status', '!=', Device::STATUS_ARCHIVED)
                ->latest()
                ->paginate(15),
        ]);
    }

    public function unclaimed(): View
    {
        return view('devices.unclaimed', [
            'devices' => Device::query()
                ->where('status', Device::STATUS_UNCLAIMED)
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
            'pairing_code' => Device::derivePairingCode($uuid),
            'athlete_id' => $validated['athlete_id'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'provider' => $validated['provider'],
            'device_secret' => Str::random(64),
            'status' => Device::STATUS_CLAIMED,
            'last_seen_at' => null,
            'last_claimed_at' => now(),
            'metadata' => [
                'pairing_code' => Device::derivePairingCode($uuid),
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

        abort_unless($device->status === Device::STATUS_UNCLAIMED, 404);

        $device->forceFill([
            'athlete_id' => $validated['athlete_id'],
            'status' => Device::STATUS_CLAIMED,
            'last_claimed_at' => now(),
        ])->save();

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device claimed.');
    }

    public function show(Device $device): View
    {
        return view('devices.show', [
            'device' => $device->load('athlete', 'trackingSessions'),
            'athletes' => Athlete::query()->orderBy('name')->get(),
        ]);
    }

    public function archive(Device $device): RedirectResponse
    {
        $device->forceFill([
            'status' => Device::STATUS_ARCHIVED,
            'archived_at' => now(),
        ])->save();

        return redirect()
            ->route('devices.index')
            ->with('status', 'Device archived.');
    }

    public function rePair(Device $device): RedirectResponse
    {
        $device->forceFill([
            'athlete_id' => null,
            'status' => Device::STATUS_UNCLAIMED,
            'last_claimed_at' => null,
        ])->save();

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device is ready to be paired again.');
    }

    public function transfer(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'athlete_id' => ['required', 'integer', 'exists:athletes,id'],
        ]);

        abort_if($device->isArchived(), 404);

        $device->forceFill([
            'athlete_id' => $validated['athlete_id'],
            'status' => Device::STATUS_CLAIMED,
            'last_claimed_at' => now(),
        ])->save();

        return redirect()
            ->route('devices.show', $device)
            ->with('status', 'Device ownership transferred.');
    }
}
