<?php

namespace App\Http\Controllers;

use App\Applications\App\Generation\Http\Handlers\StoreGenerationHandler;
use App\Applications\App\Generation\Http\Request\StoreGenerationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Generations/Index', [
            'generations' => $user->generations()->latest()->get(),
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
