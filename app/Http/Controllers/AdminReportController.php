<?php

namespace App\Http\Controllers;

use App\Applications\App\Generation\Http\Handlers\ListAdminReportHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReportController extends Controller
{
    public function index(Request $request, ListAdminReportHandler $handler): Response
    {
        return Inertia::render('Reports/Index', [
            'days' => $handler->handle($request),
        ]);
    }
}
