<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Models\Generation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class AdminReportLines
{
    public function __construct(private MoneyFormatter $formatter) {}

    /**
     * @param  array{from: string|null, to: string|null, user_id: int|null, status: string|null}  $filters
     * @return Collection<int, Generation>
     */
    public function completed(array $filters): Collection
    {
        $query = Generation::query()
            ->with('user')
            ->whereNotNull('completed_at');

        if (is_string($filters['from'])) {
            $query->where('completed_at', '>=', CarbonImmutable::parse($filters['from'])->startOfDay());
        }

        if (is_string($filters['to'])) {
            $query->where('completed_at', '<=', CarbonImmutable::parse($filters['to'])->endOfDay());
        }

        if (is_int($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if ($filters['status'] === GenerationStatus::Succeeded->value || $filters['status'] === GenerationStatus::Failed->value) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  Collection<int, Generation>  $generations
     * @return list<array{id: int, completed_at: string, user: string, email: string, status: string, prompt: string, size: string, quality: string, charged: string, provider_cost: string, profit: string, error_code: string, error_message: string, pixelforge_id: string}>
     */
    public function present(Collection $generations): array
    {
        $lines = [];

        foreach ($generations as $generation) {
            $user = $generation->user;
            $completedAt = $generation->completed_at;
            $charge = $generation->charge_nano ?? 0;
            $cost = $generation->provider_cost_nano ?? 0;

            $lines[] = [
                'id' => $generation->id,
                'completed_at' => $completedAt === null ? '' : $completedAt->utc()->format('Y-m-d H:i:s'),
                'user' => $user === null ? '' : $user->name,
                'email' => $user === null ? '' : $user->email,
                'status' => $generation->status->value,
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $this->qualityLabel($generation->quality),
                'charged' => $this->formatter->format($charge),
                'provider_cost' => $this->formatter->format($cost),
                'profit' => $this->formatter->format($charge - $cost),
                'error_code' => $generation->error_code ?? '',
                'error_message' => $generation->error_message ?? '',
                'pixelforge_id' => $generation->pixelforge_id ?? '',
            ];
        }

        return $lines;
    }

    private function qualityLabel(string $quality): string
    {
        return ImageQuality::tryFrom($quality)?->label() ?? $quality;
    }
}
