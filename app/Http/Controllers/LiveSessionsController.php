<?php

namespace App\Http\Controllers;

use App\Models\TrackingSession;
use App\Services\TrackingSessionLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LiveSessionsController extends Controller
{
    public function index(): View
    {
        return view('live-sessions.index', [
            'trackingSessions' => TrackingSession::query()
                ->with(['athlete', 'sport', 'device'])
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhereNull('ended_at');
                })
                ->latest('last_seen_at')
                ->latest('started_at')
                ->paginate(20),
        ]);
    }

    public function complete(TrackingSession $trackingSession, TrackingSessionLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->completeManually($trackingSession);

        return redirect()
            ->route('live-sessions.index')
            ->with('status', 'Tracking session completed.');
    }

    public function discard(TrackingSession $trackingSession, TrackingSessionLifecycleService $lifecycle): RedirectResponse
    {
        $lifecycle->discardManually($trackingSession);

        return redirect()
            ->route('live-sessions.index')
            ->with('status', 'Tracking session discarded.');
    }
}
