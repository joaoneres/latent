<?php

namespace App\Services\Credits;

use App\Models\CreditTransaction;
use App\Models\Generation;
use App\Models\User;
use InvalidArgumentException;

final class CreditLedger
{
    private const PURCHASE = 'purchase';

    private const HOLD = 'hold';

    private const RELEASE = 'release';

    private const CHARGE = 'charge';

    public function balanceNano(User $user): int
    {
        $balance = 0;

        foreach (CreditTransaction::query()->whereBelongsTo($user)->get() as $transaction) {
            $balance += $transaction->amount_nano;
        }

        return $balance;
    }

    public function purchase(User $user, int $amountNano): void
    {
        if ($amountNano !== 1_000_000_000 && $amountNano !== 5_000_000_000) {
            throw new InvalidArgumentException('The purchase amount is invalid.');
        }

        CreditTransaction::query()->create([
            'user_id' => $user->id,
            'generation_id' => null,
            'type' => self::PURCHASE,
            'amount_nano' => $amountNano,
        ]);
    }

    public function hold(User $user, Generation $generation, int $amountNano): void
    {
        CreditTransaction::query()->create([
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'type' => self::HOLD,
            'amount_nano' => -$amountNano,
        ]);
    }

    public function release(Generation $generation): void
    {
        if ($this->exists($generation, self::RELEASE)) {
            return;
        }

        $hold = CreditTransaction::query()
            ->whereBelongsTo($generation)
            ->where('type', self::HOLD)
            ->first();

        if ($hold === null) {
            return;
        }

        CreditTransaction::query()->create([
            'user_id' => $generation->user_id,
            'generation_id' => $generation->id,
            'type' => self::RELEASE,
            'amount_nano' => -$hold->amount_nano,
        ]);
    }

    public function charge(Generation $generation, int $chargeNano): void
    {
        if ($this->exists($generation, self::CHARGE)) {
            return;
        }

        CreditTransaction::query()->create([
            'user_id' => $generation->user_id,
            'generation_id' => $generation->id,
            'type' => self::CHARGE,
            'amount_nano' => -$chargeNano,
        ]);
    }

    private function exists(Generation $generation, string $type): bool
    {
        return CreditTransaction::query()
            ->whereBelongsTo($generation)
            ->where('type', $type)
            ->exists();
    }
}
