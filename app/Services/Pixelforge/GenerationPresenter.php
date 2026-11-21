<?php

namespace App\Services\Pixelforge;

use App\Enums\GenerationStatus;
use App\Enums\ImageQuality;
use App\Models\Generation;

final class GenerationPresenter
{
    public function __construct(private MoneyFormatter $formatter) {}

    /**
     * @return array{id: int, prompt: string, status: string, status_label: string, estimate: string, charge: string|null, image_url: string|null, size: string, quality: string, created_at: string, error_message: string|null}
     */
    public function present(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'status' => $generation->status->value,
            'status_label' => $this->statusLabel($generation->status),
            'estimate' => $this->formatter->format($generation->estimated_charge_nano),
            'charge' => $this->charge($generation),
            'image_url' => $this->imageUrl($generation),
            'size' => $generation->size,
            'quality' => $this->qualityLabel($generation->quality),
            'created_at' => $this->createdAt($generation),
            'error_message' => $generation->error_message,
        ];
    }

    private function statusLabel(GenerationStatus $status): string
    {
        return match ($status) {
            GenerationStatus::Pending => 'Sending',
            GenerationStatus::Queued => 'Queued',
            GenerationStatus::Processing => 'Running',
            GenerationStatus::Succeeded => 'Succeeded',
            GenerationStatus::Failed => 'Failed',
        };
    }

    private function qualityLabel(string $quality): string
    {
        return ImageQuality::tryFrom($quality)?->label() ?? $quality;
    }

    private function charge(Generation $generation): ?string
    {
        if ($generation->status !== GenerationStatus::Succeeded && $generation->status !== GenerationStatus::Failed) {
            return null;
        }

        return $this->formatter->format($generation->charge_nano ?? 0);
    }

    private function imageUrl(Generation $generation): ?string
    {
        if ($generation->status !== GenerationStatus::Succeeded) {
            return null;
        }

        $url = $generation->image_url;
        $expiresAt = $generation->image_url_expires_at;

        if (! is_string($url) || $url === '' || $expiresAt === null || ! $expiresAt->isFuture()) {
            return null;
        }

        return $url;
    }

    private function createdAt(Generation $generation): string
    {
        $createdAt = $generation->created_at;

        if ($createdAt === null) {
            return '';
        }

        return $createdAt->timezone('UTC')->format('M j, Y H:i');
    }
}
