<?php

namespace App\Http\Controllers;

use App\Services\OperationsHealthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function dashboard(OperationsHealthService $health): View
    {
        return view('dashboard', [
            'health' => $health->summary(),
        ]);
    }

    public function logs(Request $request, OperationsHealthService $health): View
    {
        $filter = $request->query('filter', 'all');
        $limit = (int) $request->query('limit', 50);

        if (! in_array($filter, ['all', 'whatsapp', 'garmin', 'sessions', 'failed'], true)) {
            $filter = 'all';
        }

        if (! in_array($limit, [50, 100, 250], true)) {
            $limit = 50;
        }

        return view('operations.logs', [
            'filter' => $filter,
            'limit' => $limit,
            'logs' => $health->logs($filter, $limit),
            'diagnostics' => $health->diagnosticsText(),
            'summary' => $health->summary(),
        ]);
    }
}
