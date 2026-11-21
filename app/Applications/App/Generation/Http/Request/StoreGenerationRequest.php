<?php

namespace App\Applications\App\Generation\Http\Request;

use App\Enums\ImageQuality;
use App\Enums\ImageSize;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return route('generations.create');
    }

    protected function prepareForValidation(): void
    {
        $images = $this->input('reference_images', []);

        if (! is_array($images)) {
            return;
        }

        $this->merge([
            'reference_images' => array_values(array_filter(
                $images,
                fn (mixed $url): bool => is_string($url) && $url !== '',
            )),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', 'string', Rule::enum(ImageSize::class)],
            'quality' => ['required', 'string', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
        ];
    }
}
