<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\App\Generation\Http\Request\StoreGenerationRequest;
use App\Applications\Http\HandlesHttpRequest;
use App\Enums\GenerationStatus;
use App\Jobs\Pixelforge\SubmitGeneration;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\PricingCalculator;
use App\Services\Pixelforge\PricingCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreGenerationHandler implements HandlesHttpRequest
{
    public function __construct(
        private StoreGenerationRequest $request,
        private PricingCatalog $pricing,
        private PricingCalculator $calculator,
        private CreditLedger $ledger,
        private MoneyFormatter $formatter,
    ) {}

    public function handle(): void
    {
        $this->resolveEstimate($this->request);
        $this->reserve($this->request);
        $this->dispatchJob($this->request);
    }

    public function request(): StoreGenerationRequest
    {
        return $this->request;
    }

    private function resolveEstimate(StoreGenerationRequest $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $referenceImages = is_array($validated['reference_images'] ?? null) ? $validated['reference_images'] : [];
        /** @var array{rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}, tokens: array{characters_per_text_token: int, text_token_variance: float|int|string, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $pricing */
        $pricing = $this->pricing->current();
        $estimate = $this->calculator->estimateChargeNano(
            (string) $validated['prompt'],
            (string) $validated['quality'],
            (string) $validated['size'],
            count($referenceImages),
            $pricing,
        );

        $request->attributes->set('generation_input', [
            'user_id' => $user->getKey(),
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_images' => $referenceImages,
            'maximum' => $estimate['max'],
        ]);
    }

    private function reserve(StoreGenerationRequest $request): void
    {
        /** @var array{user_id: int, prompt: string, size: string, quality: string, reference_images: list<string>, maximum: int} $input */
        $input = $request->attributes->get('generation_input');

        $generationId = DB::transaction(function () use ($input): ?int {
            $locked = User::query()->whereKey($input['user_id'])->lockForUpdate()->firstOrFail();

            if ($this->ledger->balanceNano($locked) < $input['maximum']) {
                return null;
            }

            $generation = $locked->generations()->create([
                'prompt' => $input['prompt'],
                'size' => $input['size'],
                'quality' => $input['quality'],
                'reference_images' => $input['reference_images'],
                'idempotency_key' => (string) Str::uuid(),
                'status' => GenerationStatus::Pending,
                'estimated_charge_nano' => $input['maximum'],
            ]);

            $this->ledger->hold($locked, $generation, $input['maximum']);

            return $generation->id;
        });

        if ($generationId === null) {
            $request->attributes->set(
                'balance_error',
                'Not enough credit. This generation costs up to '.$this->formatter->format($input['maximum']).'.',
            );

            return;
        }

        $request->attributes->set('generation_id', $generationId);
    }

    private function dispatchJob(StoreGenerationRequest $request): void
    {
        if (is_string($request->attributes->get('balance_error'))) {
            return;
        }

        $generationId = $request->attributes->get('generation_id');

        if (is_int($generationId)) {
            SubmitGeneration::dispatch($generationId);
        }
    }
}
