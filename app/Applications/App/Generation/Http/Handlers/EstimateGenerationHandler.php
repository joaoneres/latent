<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\PricingCalculator;
use App\Services\Pixelforge\PricingCatalog;
use Illuminate\Http\Request;

class EstimateGenerationHandler implements HandlesHttpRequest
{
    public function __construct(
        private Request $request,
        private PricingCatalog $pricing,
        private PricingCalculator $calculator,
        private MoneyFormatter $formatter,
    ) {}

    public function handle(): void
    {
        $this->publish($this->request);
    }

    private function publish(Request $request): void
    {
        $request->attributes->set('estimate', $this->estimate($request));
    }

    /**
     * @return array{min: string, max: string}
     */
    private function estimate(Request $request): array
    {
        /** @var array{rates: array{input_text_tokens: string, input_image_tokens: string, output_tokens: string, reasoning_tokens: string}, tokens: array{characters_per_text_token: int, text_token_variance: float|int|string, per_reference_image: int, output: array<string, array<string, int>>, reasoning: array<string, array{min: int, max: int}>}} $pricing */
        $pricing = $this->pricing->current();
        $estimate = $this->calculator->estimateChargeNano(
            $this->prompt($request),
            $this->quality($request),
            $this->size($request),
            $this->referenceImageCount($request),
            $pricing,
        );

        return $this->format($estimate);
    }

    /**
     * @param  array{min: int, max: int}  $estimate
     * @return array{min: string, max: string}
     */
    private function format(array $estimate): array
    {
        return [
            'min' => $this->formatter->format($estimate['min']),
            'max' => $this->formatter->format($estimate['max']),
        ];
    }

    private function prompt(Request $request): string
    {
        $prompt = $request->query('prompt', '');

        return is_string($prompt) ? $prompt : '';
    }

    private function size(Request $request): string
    {
        $size = $request->query('size');

        if (! is_string($size) || ! in_array($size, ['1024x1024', '1536x1024', '1024x1536'], true)) {
            return '1024x1024';
        }

        return $size;
    }

    private function quality(Request $request): string
    {
        $quality = $request->query('quality');

        if (! is_string($quality) || ! in_array($quality, ['low', 'medium', 'high'], true)) {
            return 'medium';
        }

        return $quality;
    }

    private function referenceImageCount(Request $request): int
    {
        $images = $request->query('reference_images', []);

        if (! is_array($images)) {
            $images = [$images];
        }

        $count = 0;

        foreach ($images as $url) {
            if (is_string($url) && trim($url) !== '') {
                $count++;
            }
        }

        return $count;
    }
}
