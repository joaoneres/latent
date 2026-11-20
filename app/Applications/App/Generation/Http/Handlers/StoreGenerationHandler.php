<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\App\Generation\Http\Request\StoreGenerationRequest;
use App\Enums\GenerationStatus;
use App\Jobs\Pixelforge\SubmitGeneration;
use App\Models\User;
use App\Services\Pixelforge\PricingCalculator;
use App\Services\Pixelforge\PricingCatalog;
use Illuminate\Support\Str;

class StoreGenerationHandler
{
    public function __construct(
        private PricingCatalog $pricing,
        private PricingCalculator $calculator,
    ) {}

    public function handle(StoreGenerationRequest $request): void
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

        $generation = $user->generations()->create([
            'prompt' => $validated['prompt'],
            'size' => $validated['size'],
            'quality' => $validated['quality'],
            'reference_images' => $referenceImages,
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Pending,
            'estimated_charge_nano' => $estimate['max'],
        ]);

        SubmitGeneration::dispatch($generation->id);
    }
}
