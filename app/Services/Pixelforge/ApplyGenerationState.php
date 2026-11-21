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
            $locked = $this->lock($generation);
            $incoming = GenerationStatus::from((string) $object['status']);

            if (! $this->accepts($locked, $incoming)) {
                return;
            }

            $this->rememberIdentity($locked, $object);
            $this->rememberRates($locked, $object);
            $this->markOpen($locked, $incoming);
            $this->recordOutcome($locked, $object, $incoming);
            $locked->save();
            $this->settle($locked, $incoming);
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

    private function lock(Generation $generation): Generation
    {
        return Generation::query()->whereKey($generation->getKey())->lockForUpdate()->firstOrFail();
    }

    private function accepts(Generation $locked, GenerationStatus $incoming): bool
    {
        return $incoming->rank() >= $locked->status->rank() && ! $locked->status->isFinal();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function rememberIdentity(Generation $locked, array $object): void
    {
        if ($locked->pixelforge_id === null && isset($object['id'])) {
            $locked->pixelforge_id = (string) $object['id'];
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function rememberRates(Generation $locked, array $object): void
    {
        $locked->rates = $this->rates($locked, $object);
    }

    private function markOpen(Generation $locked, GenerationStatus $incoming): void
    {
        $locked->status = $incoming;

        if ($locked->submitted_at === null) {
            $locked->submitted_at = now();
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function recordOutcome(Generation $locked, array $object, GenerationStatus $incoming): void
    {
        if ($incoming === GenerationStatus::Succeeded) {
            $this->recordSucceeded($locked, $object);

            return;
        }

        if ($incoming === GenerationStatus::Failed) {
            $this->recordFailed($locked, $object);
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function recordSucceeded(Generation $locked, array $object): void
    {
        $usage = $this->usage($object);
        /** @var array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string} $rates */
        $rates = $locked->rates;
        $providerCost = $this->calculator->providerCostNano($usage, $rates);
        $locked->usage = $usage;
        $locked->provider_cost_nano = $providerCost;
        $locked->charge_nano = $this->calculator->userChargeNano($providerCost);
        $locked->image_url = isset($object['image_url']) ? (string) $object['image_url'] : null;
        $completedAt = isset($object['completed_at']) && is_string($object['completed_at'])
            ? Carbon::parse($object['completed_at'])
            : now();
        $locked->completed_at = $completedAt;
        $locked->image_url_expires_at = $completedAt->copy()->addHours(24);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function recordFailed(Generation $locked, array $object): void
    {
        $error = is_array($object['error'] ?? null) ? $object['error'] : [];
        $locked->provider_cost_nano = 0;
        $locked->charge_nano = 0;
        $locked->error_code = isset($error['code']) ? (string) $error['code'] : null;
        $locked->error_message = isset($error['message']) ? (string) $error['message'] : null;
        $locked->completed_at = isset($object['completed_at']) && is_string($object['completed_at'])
            ? Carbon::parse($object['completed_at'])
            : now();
    }

    private function settle(Generation $locked, GenerationStatus $incoming): void
    {
        if ($incoming === GenerationStatus::Succeeded) {
            $this->lockUser($locked);
            $this->ledger->release($locked);
            $this->ledger->charge($locked, $locked->charge_nano ?? 0);

            return;
        }

        if ($incoming === GenerationStatus::Failed) {
            $this->lockUser($locked);
            $this->ledger->release($locked);
        }
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
    private function rates(Generation $locked, array $object): array
    {
        $payload = is_array($object['rates'] ?? null) ? $object['rates'] : [];
        $stored = is_array($locked->rates) ? $locked->rates : [];
        $rates = [];

        foreach ([
            'input_text_tokens',
            'input_image_tokens',
            'output_tokens',
            'reasoning_tokens',
        ] as $type) {
            $rates[$type] = $this->rate($payload[$type] ?? null, $stored[$type] ?? null);
        }

        return $rates;
    }

    private function rate(mixed $payload, mixed $stored): string
    {
        $incoming = $this->rateValue($payload);

        if ($incoming !== null) {
            return $incoming;
        }

        $kept = $this->rateValue($stored);

        return $kept ?? '0';
    }

    private function rateValue(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }
}
