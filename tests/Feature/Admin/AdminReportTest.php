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
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
    ]);

    Http::preventStrayRequests();
});

it('forbids an authenticated user who is not an admin', function () {
    $user = User::factory()->create()->fresh();

    expect($user)->not->toBeNull()
        ->and($user->is_admin)->toBeFalse();

    $this->actingAs($user)
        ->get(route('reports.index'))
        ->assertForbidden();
});

it('shows the report to an admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('auth.user.is_admin', true)
            ->has('days', 0));
});

it('sums one user on the utc completion day', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Ada',
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-21 00:30:00',
        'charge_nano' => 9000,
        'provider_cost_nano' => 9000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => null,
        'charge_nano' => 5000,
        'provider_cost_nano' => 5000,
    ]);

    Http::fake([
        'https://pixelforge.test/v1/usage*' => Http::response([
            'data' => [
                ['date' => '2026-11-21', 'cost_usd' => '0.000009000'],
                ['date' => '2026-11-20', 'cost_usd' => '0.000002000'],
            ],
        ]),
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('days', 2)
            ->where('days.0.date', '2026-11-21')
            ->where('days.1.date', '2026-11-20')
            ->where('days.1.charged', '$0,000003')
            ->where('days.1.provider_cost', '$0,000002')
            ->where('days.1.profit', '$0,000001')
            ->has('days.1.users', 1)
            ->where('days.1.users.0.name', 'Ada')
            ->where('days.1.users.0.charged', '$0,000003')
            ->where('days.1.users.0.provider_cost', '$0,000002')
            ->where('days.1.users.0.profit', '$0,000001')
            ->where('days.1.matches', true));
});

it('marks the day when the provider usage cost matches', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);

    Http::fake([
        'https://pixelforge.test/v1/usage*' => Http::response([
            'data' => [
                ['date' => '2026-11-20', 'cost_usd' => '0.000002000'],
            ],
        ]),
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0.matches', true)
            ->where('days.0.usage_cost', '$0,000002'));

    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://pixelforge.test/v1/usage?start_date=2026-11-20&end_date=2026-11-20';
    });
});

it('does not mark the day when the provider usage cost differs', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);

    Http::fake([
        'https://pixelforge.test/v1/usage*' => Http::response([
            'data' => [
                ['date' => '2026-11-20', 'cost_usd' => '0.000009000'],
            ],
        ]),
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0.matches', false)
            ->where('days.0.usage_cost', null)
            ->where('days.0.charged', '$0,000003')
            ->where('days.0.provider_cost', '$0,000002')
            ->where('days.0.profit', '$0,000001'));
});

it('keeps the local totals when usage fails', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);

    Http::fake([
        'https://pixelforge.test/v1/usage*' => Http::response(['error' => 'unavailable'], 500),
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Reports/Index')
            ->where('days.0.matches', null)
            ->where('days.0.charged', '$0,000003')
            ->where('days.0.provider_cost', '$0,000002')
            ->where('days.0.profit', '$0,000001'));
});

it('skips usage when the completed range exceeds 92 days', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-01-01 12:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-04-03 12:00:00',
        'charge_nano' => 1000,
        'provider_cost_nano' => 1000,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0.matches', null)
            ->where('days.1.matches', null)
            ->where('days.0.charged', '$0,000001')
            ->where('days.1.charged', '$0,000001'));

    Http::assertNothingSent();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeReportedGeneration(User $user, array $attributes = []): Generation
{
    $generation = $user->generations()->create([
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'low',
        'idempotency_key' => (string) Str::uuid(),
        'status' => GenerationStatus::Succeeded,
        'estimated_charge_nano' => 1,
    ]);

    foreach ($attributes as $key => $value) {
        $generation->setAttribute($key, $value);
    }

    $generation->save();

    return $generation->fresh() ?? $generation;
}
