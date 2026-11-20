<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Exceptions\ClientException;
use App\Models\Generation;
use App\Services\Pixelforge\MoneyFormatter;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ListAdminReportHandler
{
    public function __construct(
        private MoneyFormatter $formatter,
        private PixelforgeClient $client,
    ) {}

    /**
     * @return list<array{date: string, charged: string, provider_cost: string, profit: string, matches: bool|null, usage_cost: string|null, users: list<array{name: string, charged: string, provider_cost: string, profit: string}>}>
     */
    public function handle(Request $request): array
    {
        /** @var array<string, array{charge: int, cost: int, users: array<int, array{name: string, charge: int, cost: int}>}> $grouped */
        $grouped = [];

        foreach (Generation::query()->with('user')->whereNotNull('completed_at')->get() as $generation) {
            $completedAt = $generation->completed_at;
            $user = $generation->user;

            if ($completedAt === null || $user === null) {
                continue;
            }

            $date = $completedAt->utc()->toDateString();
            $charge = $generation->charge_nano ?? 0;
            $cost = $generation->provider_cost_nano ?? 0;

            if (! isset($grouped[$date])) {
                $grouped[$date] = [
                    'charge' => 0,
                    'cost' => 0,
                    'users' => [],
                ];
            }

            $grouped[$date]['charge'] += $charge;
            $grouped[$date]['cost'] += $cost;

            if (! isset($grouped[$date]['users'][$user->id])) {
                $grouped[$date]['users'][$user->id] = [
                    'name' => $user->name,
                    'charge' => 0,
                    'cost' => 0,
                ];
            }

            $grouped[$date]['users'][$user->id]['charge'] += $charge;
            $grouped[$date]['users'][$user->id]['cost'] += $cost;
        }

        krsort($grouped);

        /** @var list<string> $dates */
        $dates = array_keys($grouped);
        $usage = $this->usageByDate($dates);
        $days = [];

        foreach ($grouped as $date => $day) {
            $userRows = array_values($day['users']);
            usort($userRows, fn (array $left, array $right): int => $left['name'] <=> $right['name']);

            $users = [];

            foreach ($userRows as $userRow) {
                $users[] = [
                    'name' => $userRow['name'],
                    'charged' => $this->formatter->format($userRow['charge']),
                    'provider_cost' => $this->formatter->format($userRow['cost']),
                    'profit' => $this->formatter->format($userRow['charge'] - $userRow['cost']),
                ];
            }

            $reported = $usage['by_date'][$date] ?? null;
            $matches = $usage['available']
                ? $reported === $day['cost']
                : null;

            $days[] = [
                'date' => $date,
                'charged' => $this->formatter->format($day['charge']),
                'provider_cost' => $this->formatter->format($day['cost']),
                'profit' => $this->formatter->format($day['charge'] - $day['cost']),
                'matches' => $matches,
                'usage_cost' => $matches === true ? $this->formatter->format($day['cost']) : null,
                'users' => $users,
            ];
        }

        return $days;
    }

    /**
     * @param  list<string>  $dates
     * @return array{available: bool, by_date: array<string, int>}
     */
    private function usageByDate(array $dates): array
    {
        if ($dates === []) {
            return ['available' => true, 'by_date' => []];
        }

        $startDate = min($dates);
        $endDate = max($dates);

        if ($this->exceedsUsageRange($startDate, $endDate)) {
            return ['available' => false, 'by_date' => []];
        }

        try {
            $payload = $this->client->usage($startDate, $endDate);
        } catch (ClientException) {
            return ['available' => false, 'by_date' => []];
        }

        $rows = $payload['data'] ?? null;

        if (! is_array($rows)) {
            return ['available' => true, 'by_date' => []];
        }

        $byDate = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $date = $row['date'] ?? null;
            $costUsd = $row['cost_usd'] ?? null;

            if (! is_string($date) || ! is_string($costUsd)) {
                continue;
            }

            $byDate[$date] = BigDecimal::of($costUsd)->multipliedBy('1000000000')->toInt();
        }

        return ['available' => true, 'by_date' => $byDate];
    }

    private function exceedsUsageRange(string $startDate, string $endDate): bool
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        return $start->diffInDays($end, absolute: true) >= 92;
    }
}
