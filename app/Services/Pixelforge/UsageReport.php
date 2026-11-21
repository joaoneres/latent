<?php

namespace App\Services\Pixelforge;

use App\Clients\Pixelforge\PixelforgeClient;
use App\Exceptions\ClientException;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

final class UsageReport
{
    public function __construct(private PixelforgeClient $client) {}

    /**
     * @param  list<string>  $dates
     * @return array{available: bool, by_date: array<string, int>}
     */
    public function forDates(array $dates): array
    {
        if ($dates === []) {
            return ['available' => true, 'by_date' => []];
        }

        $startDate = min($dates);
        $endDate = max($dates);

        if ($this->exceedsRange($startDate, $endDate)) {
            return ['available' => false, 'by_date' => []];
        }

        try {
            $payload = $this->client->usage($startDate, $endDate);
        } catch (ClientException) {
            return ['available' => false, 'by_date' => []];
        }

        return [
            'available' => true,
            'by_date' => $this->nanoByDate($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, int>
     */
    private function nanoByDate(array $payload): array
    {
        $rows = $payload['data'] ?? null;

        if (! is_array($rows)) {
            return [];
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

        return $byDate;
    }

    private function exceedsRange(string $startDate, string $endDate): bool
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        return $start->diffInDays($end, absolute: true) >= 92;
    }
}
