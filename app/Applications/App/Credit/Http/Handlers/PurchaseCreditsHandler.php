<?php

namespace App\Applications\App\Credit\Http\Handlers;

use App\Applications\App\Credit\Http\Request\PurchaseCreditsRequest;
use App\Applications\Http\HandlesHttpRequest;
use App\Models\User;
use App\Services\Credits\CreditLedger;

class PurchaseCreditsHandler implements HandlesHttpRequest
{
    public function __construct(
        private PurchaseCreditsRequest $request,
        private CreditLedger $ledger,
    ) {}

    public function handle(): void
    {
        $this->purchase($this->request);
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
