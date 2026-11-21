<?php

namespace App\Applications\App\Credit\Http\Handlers;

use App\Applications\App\Credit\Http\Request\PurchaseCreditsRequest;
use App\Models\User;
use App\Services\Credits\CreditLedger;

class PurchaseCreditsHandler
{
    public function __construct(private CreditLedger $ledger) {}

    public function handle(PurchaseCreditsRequest $request): void
    {
        $this->purchase($request);
    }

    private function purchase(PurchaseCreditsRequest $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return;
        }

        $this->ledger->purchase($user, (int) $request->validated('amount_nano'));
    }
}
