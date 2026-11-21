<?php

namespace App\Applications\App\Credit\Http\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_nano' => ['required', 'integer', Rule::in([1_000_000_000, 5_000_000_000])],
        ];
    }
}
