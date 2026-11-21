<?php

namespace App\Http\Controllers;

use App\Applications\App\Credit\Http\Handlers\PurchaseCreditsHandler;
use Illuminate\Http\RedirectResponse;

class CreditController extends Controller
{
    public function store(PurchaseCreditsHandler $handler): RedirectResponse
    {
        $handler->handle();

        return redirect()->route('generations.create');
    }
}
