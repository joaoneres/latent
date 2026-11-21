<?php

namespace App\Http\Controllers;

use App\Applications\App\Credit\Http\Handlers\PurchaseCreditsHandler;
use App\Applications\App\Credit\Http\Request\PurchaseCreditsRequest;
use Illuminate\Http\RedirectResponse;

class CreditController extends Controller
{
    public function store(PurchaseCreditsRequest $request, PurchaseCreditsHandler $handler): RedirectResponse
    {
        $handler->handle($request);

        return back();
    }
}
