<?php

namespace App\Http\Controllers;

use App\Applications\App\Credit\Http\Handlers\PurchaseCreditsHandler;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CreditController extends Controller
{
    public function store(Request $request, PurchaseCreditsHandler $handler): RedirectResponse
    {
        $validated = $request->validate([
            'amount_nano' => ['required', 'integer', Rule::in([1_000_000_000, 5_000_000_000])],
        ]);

        /** @var User $user */
        $user = $request->user();
        $handler->handle($user, (int) $validated['amount_nano']);

        return back();
    }
}
