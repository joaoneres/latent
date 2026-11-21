<?php

namespace App\Http\Controllers;

use App\Applications\App\Generation\Http\Handlers\EstimateGenerationHandler;
use App\Applications\App\Generation\Http\Handlers\ListGenerationsHandler;
use App\Applications\App\Generation\Http\Handlers\StoreGenerationHandler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request, ListGenerationsHandler $handler): Response
    {
        $handler->handle();

        return Inertia::render('Generations/Index', [
            'generations' => $request->attributes->get('generations'),
            'pagination' => $request->attributes->get('pagination'),
        ]);
    }

    public function create(Request $request, EstimateGenerationHandler $handler): Response
    {
        $handler->handle();

        return Inertia::render('Generations/Create', [
            'estimate' => $request->attributes->get('estimate'),
        ]);
    }

    public function store(StoreGenerationHandler $handler): RedirectResponse
    {
        $handler->handle();
        $message = $handler->request()->attributes->get('balance_error');

        if (is_string($message)) {
            return redirect()->route('generations.create')->withErrors([
                'balance' => $message,
            ]);
        }

        return redirect()->route('generations.index');
    }
}
