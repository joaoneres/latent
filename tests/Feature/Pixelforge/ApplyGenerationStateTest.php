<?php

use App\Enums\GenerationStatus;
use App\Models\User;
use App\Services\Pixelforge\ApplyGenerationState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

it('keeps a succeeded generation when a late processing event arrives', function () {
    $generation = User::factory()->create()->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1536x1024',
        'quality' => 'high',
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 1,
    ]);

    $rates = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    $state = app(ApplyGenerationState::class);
    $state->fromProvider($generation, [
        'id' => 'gen_done',
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'completed_at' => '2026-11-04T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
            'total_tokens' => 16095,
        ],
        'rates' => $rates,
    ]);

    $generation->refresh();
    $charge = $generation->charge_nano;

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost_nano)->toBe(373875000)
        ->and($charge)->toBe(467343750)
        ->and($generation->usage)->toBe([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ])
        ->and($generation->image_url_expires_at?->equalTo(Carbon::parse('2026-11-05T14:03:29Z')))->toBeTrue();

    $state->fromProvider($generation, [
        'id' => 'gen_done',
        'status' => 'processing',
        'rates' => $rates,
    ]);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->charge_nano)->toBe($charge);

    $state->fromProvider($generation, [
        'id' => 'gen_done',
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'completed_at' => '2026-11-04T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ],
        'rates' => $rates,
    ]);

    expect($generation->fresh()->charge_nano)->toBe($charge);
});

it('charges a succeeded generation from the rates saved when it was accepted', function () {
    $generation = User::factory()->create()->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1536x1024',
        'quality' => 'high',
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 1,
    ]);
    $rates = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];
    $state = app(ApplyGenerationState::class);

    $state->fromProvider($generation, [
        'id' => 'gen_done',
        'status' => 'queued',
        'rates' => $rates,
    ]);

    $generation->refresh();

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->rates)->toBe($rates)
        ->and($generation->charge_nano)->toBeNull();

    $state->fromProvider($generation, [
        'id' => 'gen_done',
        'status' => 'succeeded',
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'completed_at' => '2026-11-04T14:03:29Z',
        'usage' => [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ],
    ]);

    $generation->refresh();

    expect($generation->charge_nano)->toBe(467343750)
        ->and($generation->provider_cost_nano)->toBe(373875000)
        ->and($generation->rates)->toBe($rates)
        ->and($generation->usage)->toBe([
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ]);
});
