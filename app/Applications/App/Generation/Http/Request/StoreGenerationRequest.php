<?php

namespace App\Applications\App\Generation\Http\Request;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'size' => ['required', 'string', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['nullable', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https'],
        ];
    }
}
