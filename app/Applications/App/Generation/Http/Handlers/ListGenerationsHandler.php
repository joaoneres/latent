<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Http\Request;

class ListGenerationsHandler
{
    public function __construct(private MoneyFormatter $formatter) {}

    public function handle(Request $request): void
    {
        $request->attributes->set('generations', $this->rows($request));
    }

    /**
     * @return list<array{id: int, prompt: string, status: string, status_label: string, amount: string, image_url: string|null}>
     */
    private function rows(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $generations = [];

        foreach ($user->generations()->latest()->get() as $generation) {
            $generations[] = $this->present($generation);
        }

        return $generations;
    }

    /**
     * @return array{id: int, prompt: string, status: string, status_label: string, amount: string, image_url: string|null}
     */
    private function present(Generation $generation): array
    {
        return [
            'id' => $generation->id,
            'prompt' => $generation->prompt,
            'status' => $generation->status->value,
            'status_label' => $this->statusLabel($generation->status),
            'amount' => $this->formatter->format($this->amountNano($generation)),
            'image_url' => $this->imageUrl($generation),
        ];
    }

    private function statusLabel(GenerationStatus $status): string
    {
        if ($status === GenerationStatus::Pending) {
            return 'Enviando / aguardando vaga';
        }

        return $status->value;
    }

    private function amountNano(Generation $generation): int
    {
        if ($generation->status === GenerationStatus::Succeeded || $generation->status === GenerationStatus::Failed) {
            return $generation->charge_nano ?? 0;
        }

        return $generation->estimated_charge_nano;
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
}
