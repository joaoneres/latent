<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
    ]);

    Http::preventStrayRequests();
});

it('formats the pending estimate and the waiting label', function () {
    $user = User::factory()->create();
    $generation = makeListedGeneration($user, [
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 57400000,
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Generations/Index')
            ->where('generations.0.id', $generation->id)
            ->where('generations.0.amount', '$0,0574')
            ->where('generations.0.status_label', 'Sending'));
});

it('formats the succeeded charge', function () {
    $user = User::factory()->create();
    $generation = makeListedGeneration($user, [
        'status' => GenerationStatus::Succeeded,
        'estimated_charge_nano' => 1,
        'charge_nano' => 467343750,
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.id', $generation->id)
            ->where('generations.0.amount', '$0,467344'));
});

it('formats a failed generation as zero without an image', function () {
    $user = User::factory()->create();
    makeListedGeneration($user, [
        'status' => GenerationStatus::Failed,
        'charge_nano' => 0,
        'image_url' => 'https://pixelforge.test/images/failed.png',
        'image_url_expires_at' => now()->addHour(),
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.amount', '$0,0000')
            ->where('generations.0.image_url', null));
});

it('includes a succeeded image url that has not expired', function () {
    $user = User::factory()->create();
    $url = 'https://pixelforge.test/images/gen_done.png';
    makeListedGeneration($user, [
        'status' => GenerationStatus::Succeeded,
        'charge_nano' => 467343750,
        'image_url' => $url,
        'image_url_expires_at' => now()->addHour(),
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.image_url', $url));
});

it('hides a succeeded image url that has expired', function () {
    $user = User::factory()->create();
    makeListedGeneration($user, [
        'status' => GenerationStatus::Succeeded,
        'charge_nano' => 467343750,
        'image_url' => 'https://pixelforge.test/images/gen_done.png',
        'image_url_expires_at' => now()->subMinute(),
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.image_url', null));
});

it('refreshes a pending generation into the succeeded charge and image', function () {
    $user = User::factory()->create();
    $generation = makeListedGeneration($user, [
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 57400000,
    ]);
    makeListedGeneration(User::factory()->create(), [
        'prompt' => 'someone else',
        'estimated_charge_nano' => 1,
    ]);
    $url = 'https://pixelforge.test/images/gen_done.png';

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $generation->id)
            ->where('generations.0.status', 'pending')
            ->where('generations.0.status_label', 'Sending')
            ->where('generations.0.amount', '$0,0574')
            ->where('generations.0.image_url', null));

    $generation->status = GenerationStatus::Succeeded;
    $generation->charge_nano = 467343750;
    $generation->image_url = $url;
    $generation->image_url_expires_at = now()->addHour();
    $generation->save();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $generation->id)
            ->where('generations.0.status', 'succeeded')
            ->where('generations.0.amount', '$0,467344')
            ->where('generations.0.image_url', $url));
});

it('refreshes a generation into a failed charge without an image', function () {
    $user = User::factory()->create();
    $generation = makeListedGeneration($user, [
        'status' => GenerationStatus::Pending,
        'estimated_charge_nano' => 57400000,
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.status', 'pending'));

    $generation->status = GenerationStatus::Failed;
    $generation->charge_nano = 0;
    $generation->image_url = 'https://pixelforge.test/images/failed.png';
    $generation->image_url_expires_at = now()->addHour();
    $generation->save();

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('generations.0.amount', '$0,0000')
            ->where('generations.0.image_url', null));
});

it('lists only the authenticated user generations', function () {
    $user = User::factory()->create();
    $own = makeListedGeneration($user, [
        'prompt' => 'mine',
        'estimated_charge_nano' => 57400000,
    ]);
    makeListedGeneration(User::factory()->create(), [
        'prompt' => 'someone else',
        'estimated_charge_nano' => 57400000,
    ]);

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $own->id));
});

it('pages generations with a cursor', function () {
    $user = User::factory()->create();
    $older = makeListedGeneration($user, [
        'prompt' => 'older',
        'created_at' => now()->subMinute(),
    ]);
    $newer = makeListedGeneration($user, [
        'prompt' => 'newer',
        'created_at' => now(),
    ]);

    $first = $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index', ['per_page' => 1]));

    $first->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $newer->id)
            ->where('pagination.type', 'cursor')
            ->where('pagination.total', 2)
            ->where('pagination.per_page', 1)
            ->where('pagination.prev_cursor', null));

    $nextCursor = $first->viewData('page')['props']['pagination']['next_cursor'];

    expect($nextCursor)->toBeString()->not->toBe('');

    $this->withoutVite()
        ->actingAs($user)
        ->get(route('generations.index', [
            'per_page' => 1,
            'cursor' => $nextCursor,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('generations', 1)
            ->where('generations.0.id', $older->id)
            ->where('pagination.total', 2)
            ->where('pagination.next_cursor', null));
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeListedGeneration(User $user, array $attributes = []): Generation
{
    $generation = $user->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'low',
        'idempotency_key' => (string) Str::uuid(),
        'status' => $attributes['status'] ?? GenerationStatus::Pending,
        'estimated_charge_nano' => $attributes['estimated_charge_nano'] ?? 1,
    ]);

    unset($attributes['status'], $attributes['estimated_charge_nano']);

    foreach ($attributes as $key => $value) {
        $generation->setAttribute($key, $value);
    }

    $generation->save();

    return $generation->fresh() ?? $generation;
}
