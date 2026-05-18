<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AthleteController extends Controller
{
    public function index(): View
    {
        return view('athletes.index', [
            'athletes' => Athlete::query()
                ->withCount('trackingSessions')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('athletes.create', [
            'athlete' => new Athlete(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $athlete = Athlete::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $validated['name'],
            'metadata' => [],
        ]);

        return redirect()
            ->route('athletes.edit', $athlete)
            ->with('status', 'Athlete created.');
    }

    public function edit(Athlete $athlete): View
    {
        return view('athletes.edit', [
            'athlete' => $athlete,
        ]);
    }

    public function update(Request $request, Athlete $athlete): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $athlete->update($validated);

        return redirect()
            ->route('athletes.edit', $athlete)
            ->with('status', 'Athlete updated.');
    }

    public function destroy(Athlete $athlete): RedirectResponse
    {
        $athlete->delete();

        return redirect()
            ->route('athletes.index')
            ->with('status', 'Athlete deleted.');
    }
}
