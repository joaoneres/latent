<?php

namespace App\Http\Controllers;

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

    public function create(): Response
    {
        return Inertia::render('Generations/Create');
    }

    public function store(StoreGenerationRequest $request, StoreGenerationHandler $handler): RedirectResponse
    {
        $handler->handle($request);

        return redirect()->route('generations.index');
    }
}
