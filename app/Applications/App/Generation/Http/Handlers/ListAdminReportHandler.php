<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\AdminReportFilters;
use App\Services\Pixelforge\AdminReportLines;
use App\Services\Pixelforge\MoneyFormatter;
use App\Services\Pixelforge\UsageReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ListAdminReportHandler implements HandlesHttpRequest
{
    /** @var Collection<int, Generation> */
    private Collection $completed;

    /** @var array{from: string|null, to: string|null, user_id: int|null, status: string|null} */
    private array $filters;

    public function __construct(
        private Request $request,
        private MoneyFormatter $formatter,
        private UsageReport $usage,
        private AdminReportLines $lines,
        private AdminReportFilters $filtersReader,
    ) {}

    public function handle(): void
    {
        $this->readFilters($this->request);
        $this->load();
        $this->publish($this->request);
    }

    private function readFilters(Request $request): void
    {
        $this->filters = $this->filtersReader->fromRequest($request);
    }

    private function load(): void
    {
        $this->completed = $this->lines->completed($this->filters);
    }

    private function publish(Request $request): void
    {
        $grouped = $this->group();
        $usage = $this->usage->forDates(array_keys($grouped));

        $request->attributes->set('days', $this->presentDays($grouped, $usage));
        $request->attributes->set('summary', $this->summary());
        $request->attributes->set('generations', $this->lines->present($this->completed));
        $request->attributes->set('filters', $this->presentFilters());
        $request->attributes->set('users', $this->users());
    }

    /**
     * @return array{from: string, to: string, user_id: string, status: string}
     */
    private function presentFilters(): array
    {
        return [
            'from' => $this->filters['from'] ?? '',
            'to' => $this->filters['to'] ?? '',
            'user_id' => $this->filters['user_id'] === null ? '' : (string) $this->filters['user_id'],
            'status' => $this->filters['status'] ?? '',
        ];
    }

    /**
     * @return list<array{id: int, name: string, email: string}>
     */
    private function users(): array
    {
        $users = [];

        foreach (User::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'email']) as $user) {
            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        return $users;
    }

    /**
     * @return array{count: int, charged: string, provider_cost: string, profit: string}
     */
    private function summary(): array
    {
        $charge = 0;
        $cost = 0;

        foreach ($this->completed as $generation) {
            $charge += $generation->charge_nano ?? 0;
            $cost += $generation->provider_cost_nano ?? 0;
        }

        return [
            'count' => $this->completed->count(),
            'charged' => $this->formatter->format($charge),
            'provider_cost' => $this->formatter->format($cost),
            'profit' => $this->formatter->format($charge - $cost),
        ];
    }

    /**
     * @return array<string, array{charge: int, cost: int, users: array<int, array{name: string, charge: int, cost: int}>}>
     */
    private function group(): array
    {
        /** @var array<string, array{charge: int, cost: int, users: array<int, array{name: string, charge: int, cost: int}>}> $grouped */
        $grouped = [];

        foreach ($this->completed as $generation) {
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
    private function presentDays(array $grouped, array $usage): array
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
