<?php

namespace App\Http\Controllers;

use App\Applications\App\Dashboard\Http\Handlers\ShowDashboardHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, ShowDashboardHandler $handler): Response
    {
        $handler->handle($request);

        return Inertia::render('Dashboard', [
            'dashboard' => $request->attributes->get('dashboard'),
        ]);
    }
}
