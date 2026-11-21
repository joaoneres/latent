<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use Illuminate\Http\Request;

final class AdminReportFilters
{
    /**
     * @return array{from: string|null, to: string|null, user_id: int|null, status: string|null}
     */
    public function fromRequest(Request $request): array
    {
        $from = $this->date($request->query('from'));
        $to = $this->date($request->query('to'));

        if ($from !== null && $to !== null && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'user_id' => $this->userId($request->query('user_id')),
            'status' => $this->status($request->query('status')),
        ];
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $month = (int) substr($value, 5, 2);
        $day = (int) substr($value, 8, 2);

        if (! checkdate($month, $day, (int) substr($value, 0, 4))) {
            return null;
        }

        return $value;
    }

    private function userId(mixed $value): ?int
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        if (preg_match('/^[1-9]\d*$/', (string) $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    private function status(mixed $value): ?string
    {
        if ($value === GenerationStatus::Succeeded->value || $value === GenerationStatus::Failed->value) {
            return $value;
        }

        return null;
    }
}
