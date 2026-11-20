<?php

namespace App\Http\Controllers;

use App\Applications\App\Generation\Http\Handlers\EstimateGenerationHandler;
use App\Applications\App\Generation\Http\Handlers\ListGenerationsHandler;
use App\Applications\App\Generation\Http\Handlers\StoreGenerationHandler;
use App\Applications\App\Generation\Http\Request\StoreGenerationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request, ListGenerationsHandler $handler): Response
    {
        return Inertia::render('Generations/Index', [
            'generations' => $handler->handle($request),
        ]);
    }

    public function create(Request $request, EstimateGenerationHandler $handler): Response
    {
        return Inertia::render('Generations/Create', [
            'estimate' => $handler->handle($request),
        ]);
    }

    public function store(StoreGenerationRequest $request, StoreGenerationHandler $handler): RedirectResponse
    {
        $message = $handler->handle($request);

        if (is_string($message)) {
            return back()->withErrors([
                'balance' => $message,
            ]);
        }

        return redirect()->route('generations.index');
    }
}
