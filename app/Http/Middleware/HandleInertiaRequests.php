<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Credits\CreditLedger;
use App\Services\Pixelforge\MoneyFormatter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $this->sharedUser($request),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'creditBalance' => $this->creditBalance($request),
        ];
    }

    private function creditBalance(Request $request): ?string
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return app(MoneyFormatter::class)->format(app(CreditLedger::class)->balanceNano($user));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $shared = $user->toArray();
        unset($shared['public_id']);
        $shared['id'] = $user->public_id;
        $shared['is_admin'] = $user->is_admin;

        return $shared;
    }
}
