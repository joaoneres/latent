<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Models\User;
use App\Services\Pixelforge\GenerationPresenter;
use Illuminate\Http\Request;

class ListGenerationsHandler implements HandlesHttpRequest
{
    private int $perPage = 20;

    private ?string $cursor = null;

    public function __construct(
        private Request $request,
        private GenerationPresenter $presenter,
    ) {}

    public function handle(): void
    {
        $this->readQuery($this->request);
        $this->publish($this->request);
    }

    private function readQuery(Request $request): void
    {
        $perPage = $request->query('per_page', 20);
        $perPage = is_numeric($perPage) ? (int) $perPage : 20;

        if ($perPage < 1) {
            $perPage = 20;
        }

        $this->perPage = min($perPage, 100);

        $cursor = $request->query('cursor');
        $this->cursor = is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    private function publish(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $query = $user->generations()->orderByDesc('created_at')->orderByDesc('id');
        $total = (clone $query)->count();
        $paginator = $query->cursorPaginate($this->perPage, cursor: $this->cursor);
        $rows = [];

        foreach ($paginator->items() as $generation) {
            $rows[] = $this->presenter->present($generation);
        }

        $request->attributes->set('generations', $rows);
        $request->attributes->set('pagination', [
            'type' => 'cursor',
            'total' => $total,
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
        ]);
    }
}
