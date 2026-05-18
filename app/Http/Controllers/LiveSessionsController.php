<?php

namespace App\Http\Controllers;

use App\Models\TrackingSession;
use Illuminate\View\View;

class LiveSessionsController extends Controller
{
    public function index(): View
    {
        return view('live-sessions.index', [
            'trackingSessions' => TrackingSession::query()
                ->with(['athlete', 'sport'])
                ->where(function ($query) {
                    $query->where('status', 'active')
                        ->orWhereNull('ended_at');
                })
                ->latest('last_seen_at')
                ->latest('started_at')
                ->paginate(20),
        ]);
    }
}
