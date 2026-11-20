<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $prompt
 * @property string $size
 * @property string $quality
 * @property array<int, string>|null $reference_images
 * @property string $idempotency_key
 * @property string|null $pixelforge_id
 * @property GenerationStatus $status
 * @property array<string, mixed>|null $rates
 * @property array<string, mixed>|null $usage
 * @property int $estimated_charge_nano
 * @property int|null $provider_cost_nano
 * @property int|null $charge_nano
 * @property string|null $image_url
 * @property CarbonInterface|null $image_url_expires_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property CarbonInterface|null $submitted_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'prompt',
    'size',
    'quality',
    'reference_images',
    'idempotency_key',
    'status',
    'estimated_charge_nano',
])]
class Generation extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'reference_images' => 'array',
            'rates' => 'array',
            'usage' => 'array',
            'estimated_charge_nano' => 'integer',
            'provider_cost_nano' => 'integer',
            'charge_nano' => 'integer',
            'image_url_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
