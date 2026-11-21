<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Models\Generation;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\UsageReport;
use Illuminate\Http\Request;

class ListAdminReportHandler
{
    public function __construct(
        private MoneyFormatter $formatter,
        private UsageReport $usage,
    ) {}

    public function handle(Request $request): void
    {
        $grouped = $this->group();
        $usage = $this->usage->forDates(array_keys($grouped));

        $request->attributes->set('days', $this->present($grouped, $usage));
    }

    /**
     * @return array<string, array{charge: int, cost: int, users: array<int, array{name: string, charge: int, cost: int}>}>
     */
    private function group(): array
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

        return $grouped;
    }

    /**
     * @param  array<string, array{charge: int, cost: int, users: array<int, array{name: string, charge: int, cost: int}>}>  $grouped
     * @param  array{available: bool, by_date: array<string, int>}  $usage
     * @return list<array{date: string, charged: string, provider_cost: string, profit: string, matches: bool|null, usage_cost: string|null, users: list<array{name: string, charged: string, provider_cost: string, profit: string}>}>
     */
    private function present(array $grouped, array $usage): array
    {
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
}
