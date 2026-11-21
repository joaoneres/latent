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
            ->has('days', 0)
            ->has('generations', 0)
            ->where('summary.count', 0)
            ->where('summary.charged', '$0,0000')
            ->where('filters.from', '')
            ->where('filters.to', '')
            ->where('filters.user_id', '')
            ->where('filters.status', '')
            ->has('users', 1));
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
            ->where('days.1.matches', true)
            ->has('generations', 3)
            ->where('generations.0.completed_at', '2026-11-21 00:30:00')
            ->where('generations.0.user', 'Ada')
            ->where('generations.0.prompt', 'A lighthouse')
            ->where('generations.0.charged', '$0,000009')
            ->where('summary.count', 3)
            ->where('summary.charged', '$0,000012')
            ->where('summary.provider_cost', '$0,000011')
            ->where('summary.profit', '$0,000001'));
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

it('forbids the report download for a user who is not an admin', function () {
    $user = User::factory()->create()->fresh();

    $this->actingAs($user)
        ->get(route('reports.download'))
        ->assertForbidden();
});

it('downloads every completed generation as csv', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Ada',
        'email' => 'ada@example.com',
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
        'prompt' => 'A red, tall lighthouse',
        'pixelforge_id' => 'gen_123',
        'error_code' => null,
        'error_message' => null,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => null,
        'charge_nano' => 5000,
        'provider_cost_nano' => 5000,
        'prompt' => 'Still running',
    ]);

    $response = $this->actingAs($admin)->get(route('reports.download'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('latent-report.csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Completed at (UTC)')
        ->toContain('A red, tall lighthouse')
        ->toContain('ada@example.com')
        ->toContain('$0,000002')
        ->toContain('gen_123')
        ->not->toContain('Still running');
});

it('filters the report by utc day, user, and status', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'name' => 'Ada',
    ]);
    $bea = User::factory()->create([
        'name' => 'Bea',
        'email' => 'bea@example.com',
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
        'prompt' => 'Ada succeeded',
    ]);
    makeReportedGeneration($bea, [
        'completed_at' => '2026-11-21 00:30:00',
        'charge_nano' => 3000,
        'provider_cost_nano' => 2000,
        'prompt' => 'Bea succeeded',
        'status' => GenerationStatus::Succeeded,
    ]);
    makeReportedGeneration($bea, [
        'completed_at' => '2026-11-21 02:00:00',
        'charge_nano' => 0,
        'provider_cost_nano' => 0,
        'prompt' => 'Bea failed',
        'status' => GenerationStatus::Failed,
    ]);

    Http::fake([
        'https://pixelforge.test/v1/usage*' => Http::response([
            'data' => [
                ['date' => '2026-11-21', 'cost_usd' => '0.000002000'],
            ],
        ]),
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index', [
            'from' => '2026-11-21',
            'to' => '2026-11-20',
            'user_id' => $bea->public_id,
            'status' => 'succeeded',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', '2026-11-20')
            ->where('filters.to', '2026-11-21')
            ->where('filters.user_id', $bea->public_id)
            ->where('filters.status', 'succeeded')
            ->has('generations', 1)
            ->where('generations.0.prompt', 'Bea succeeded')
            ->where('generations.0.user', 'Bea')
            ->where('summary.count', 1)
            ->where('summary.charged', '$0,000003')
            ->has('days', 1)
            ->where('days.0.date', '2026-11-21')
            ->has('users', 2));
});

it('ignores a numeric user id on the report', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $bea = User::factory()->create(['name' => 'Bea']);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-21 01:00:00',
        'prompt' => 'Ada succeeded',
    ]);
    makeReportedGeneration($bea, [
        'completed_at' => '2026-11-21 00:30:00',
        'prompt' => 'Bea succeeded',
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get(route('reports.index', [
            'user_id' => $bea->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.user_id', '')
            ->has('generations', 2));
});

it('downloads only the generations that match the filters', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 15:00:00',
        'charge_nano' => 2000,
        'provider_cost_nano' => 1000,
        'prompt' => 'Keep this row',
        'status' => GenerationStatus::Succeeded,
    ]);
    makeReportedGeneration($admin, [
        'completed_at' => '2026-11-20 16:00:00',
        'charge_nano' => 0,
        'provider_cost_nano' => 0,
        'prompt' => 'Drop this row',
        'status' => GenerationStatus::Failed,
    ]);

    $response = $this->actingAs($admin)->get(route('reports.download', [
        'status' => 'succeeded',
    ]));

    $response->assertOk();

    expect($response->streamedContent())
        ->toContain('Keep this row')
        ->not->toContain('Drop this row');
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
