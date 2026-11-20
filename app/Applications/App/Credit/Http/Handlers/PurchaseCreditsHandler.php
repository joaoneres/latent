<?php

namespace App\Applications\App\Credit\Http\Handlers;

use App\Models\User;
use App\Services\Credits\CreditLedger;

class PurchaseCreditsHandler
{
    public function __construct(private CreditLedger $ledger) {}

    public function handle(User $user, int $amountNano): void
    {
        $this->ledger->purchase($user, $amountNano);
    }
}
