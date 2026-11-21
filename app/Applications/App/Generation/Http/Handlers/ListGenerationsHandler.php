<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Models\User;
use App\Services\Pixelforge\GenerationPresenter;
use Illuminate\Http\Request;

class ListGenerationsHandler
{
    public function __construct(private GenerationPresenter $presenter) {}

    public function handle(Request $request): void
    {
        $this->publish($request);
    }

    private function publish(Request $request): void
    {
        $request->attributes->set('generations', $this->rows($request));
    }

    /**
     * @return list<array{id: int, prompt: string, status: string, status_label: string, amount: string, image_url: string|null, size: string, quality: string, created_at: string, error_message: string|null}>
     */
    private function rows(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $generations = [];

        foreach ($user->generations()->latest()->get() as $generation) {
            $generations[] = $this->presenter->present($generation);
        }

        return $generations;
    }
}
