<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ApplyGenerationState
{
    public function __construct(
        private PricingCalculator $calculator,
        private CreditLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $object
     */
    public function fromProvider(Generation $generation, array $object): void
    {
        DB::transaction(function () use ($generation, $object): void {
            $locked = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();
            $incoming = GenerationStatus::from((string) $object['status']);

            if ($incoming->rank() < $locked->status->rank() || $locked->status->isFinal()) {
                return;
            }

            if ($locked->pixelforge_id === null && isset($object['id'])) {
                $locked->pixelforge_id = (string) $object['id'];
            }

            if (isset($object['rates']) && is_array($object['rates'])) {
                $locked->rates = $object['rates'];
            }

            $locked->status = $incoming;

            if ($locked->submitted_at === null) {
                $locked->submitted_at = now();
            }

            $chargeNano = null;

            if ($incoming === GenerationStatus::Succeeded) {
                $usage = $this->usage($object);
                $rates = $this->rates($object);
                $providerCost = $this->calculator->providerCostNano($usage, $rates);
                $chargeNano = $this->calculator->userChargeNano($providerCost);
                $locked->provider_cost_nano = $providerCost;
                $locked->charge_nano = $chargeNano;
                $locked->image_url = isset($object['image_url']) ? (string) $object['image_url'] : null;
                $completedAt = isset($object['completed_at']) && is_string($object['completed_at'])
                    ? Carbon::parse($object['completed_at'])
                    : now();
                $locked->completed_at = $completedAt;
                $locked->image_url_expires_at = $completedAt->copy()->addHours(24);
            }

            if ($incoming === GenerationStatus::Failed) {
                $error = is_array($object['error'] ?? null) ? $object['error'] : [];
                $locked->provider_cost_nano = 0;
                $locked->charge_nano = 0;
                $locked->error_code = isset($error['code']) ? (string) $error['code'] : null;
                $locked->error_message = isset($error['message']) ? (string) $error['message'] : null;
                $locked->completed_at = isset($object['completed_at']) && is_string($object['completed_at'])
                    ? Carbon::parse($object['completed_at'])
                    : now();
            }

            $locked->save();

            if ($chargeNano !== null) {
                $this->lockUser($locked);
                $this->ledger->release($locked);
                $this->ledger->charge($locked, $chargeNano);
            }

            if ($incoming === GenerationStatus::Failed) {
                $this->lockUser($locked);
                $this->ledger->release($locked);
            }
        });

        $generation->refresh();
    }

    public function fail(Generation $generation, string $code, string $message): void
    {
        DB::transaction(function () use ($generation, $code, $message): void {
            $locked = Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status->isFinal()) {
                return;
            }

            $locked->status = GenerationStatus::Failed;
            $locked->provider_cost_nano = 0;
            $locked->charge_nano = 0;
            $locked->error_code = $code;
            $locked->error_message = $message;
            $locked->completed_at = now();
            $locked->save();
            $this->lockUser($locked);
            $this->ledger->release($locked);
        });

        $generation->refresh();
    }

    private function lockUser(Generation $generation): void
    {
        User::query()->whereKey($generation->user_id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array{input_text_tokens: int, input_image_tokens: int, output_tokens: int, reasoning_tokens: int}
     */
    private function usage(array $object): array
    {
        $usage = is_array($object['usage'] ?? null) ? $object['usage'] : [];

        return [
            'input_text_tokens' => (int) ($usage['input_text_tokens'] ?? 0),
            'input_image_tokens' => (int) ($usage['input_image_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'reasoning_tokens' => (int) ($usage['reasoning_tokens'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}
     */
    private function rates(array $object): array
    {
        $rates = is_array($object['rates'] ?? null) ? $object['rates'] : [];

        return [
            'input_text_tokens' => (string) ($rates['input_text_tokens'] ?? '0'),
            'input_image_tokens' => (string) ($rates['input_image_tokens'] ?? '0'),
            'output_tokens' => (string) ($rates['output_tokens'] ?? '0'),
            'reasoning_tokens' => (string) ($rates['reasoning_tokens'] ?? '0'),
        ];
    }
}
