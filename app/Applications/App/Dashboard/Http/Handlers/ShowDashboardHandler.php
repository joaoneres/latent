<?php

namespace App\Applications\App\Dashboard\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Pixelforge\GenerationPresenter;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ShowDashboardHandler implements HandlesHttpRequest
{
    private User $user;

    /** @var Collection<int, Generation> */
    private Collection $generations;

    public function __construct(
        private Request $request,
        private CreditLedger $ledger,
        private MoneyFormatter $formatter,
        private GenerationPresenter $presenter,
    ) {}

    public function handle(): void
    {
        $this->loadUser($this->request);
        $this->loadGenerations();
        $this->publish($this->request);
    }

    private function loadUser(Request $request): void
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->user = $user;
    }

    private function loadGenerations(): void
    {
        $this->generations = $this->user->generations()->latest()->get();
    }

    private function publish(Request $request): void
    {
        $request->attributes->set('dashboard', [
            'balance' => $this->formatter->format($this->ledger->balanceNano($this->user)),
            'in_progress' => $this->countOpen(),
            'succeeded' => $this->count(GenerationStatus::Succeeded),
            'failed' => $this->count(GenerationStatus::Failed),
            'charged' => $this->formatter->format($this->chargedNano()),
            'recent' => $this->recent(),
        ]);
    }

    private function countOpen(): int
    {
        $count = 0;

        foreach ($this->generations as $generation) {
            if (! $generation->status->isFinal()) {
                $count++;
            }
        }

        return $count;
    }

    private function count(GenerationStatus $status): int
    {
        $count = 0;

        foreach ($this->generations as $generation) {
            if ($generation->status === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function chargedNano(): int
    {
        $total = 0;

        foreach ($this->generations as $generation) {
            if ($generation->status === GenerationStatus::Succeeded) {
                $total += $generation->charge_nano ?? 0;
            }
        }

        return $total;
    }

    /**
     * @return list<array{id: int, prompt: string, status: string, status_label: string, amount: string, image_url: string|null, size: string, quality: string, created_at: string, error_message: string|null}>
     */
    private function recent(): array
    {
        $rows = [];

        foreach ($this->generations->take(5) as $generation) {
            $rows[] = $this->presenter->present($generation);
        }

        return $rows;
    }
}
