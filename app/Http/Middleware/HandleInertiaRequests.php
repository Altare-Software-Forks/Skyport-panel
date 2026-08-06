<?php

namespace App\Http\Middleware;

use App\Models\Server;
use App\Models\User;
use App\Services\AppSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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
                'user' => fn (): ?array => $this->sharedUser($request->user()),
            ],
            'flash' => [
                'info' => fn (): ?string => $request->session()->get('info'),
                'success' => fn (): ?string => $request
                    ->session()
                    ->get('success'),
                'warning' => fn (): ?string => $request
                    ->session()
                    ->get('warning'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') ||
                $request->cookie('sidebar_state') === 'true',
            'impersonating' => $request->session()->has('impersonator_id'),
            'announcement' => fn (): ?string => app(
                AppSettingsService::class,
            )->announcement(),
            'announcementType' => fn (): string => app(
                AppSettingsService::class,
            )->announcementType(),
            'announcementDismissable' => fn (): bool => app(
                AppSettingsService::class,
            )->announcementDismissable(),
            'announcementIcon' => fn (): string => app(
                AppSettingsService::class,
            )->announcementIcon(),
            'themeCSS' => fn (): ?string => $this->buildThemeCSS(),
            'serverSwitcher' => fn (): array => $this->sharedServerSwitcher(
                $request,
            ),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, status: string}>
     */
    protected function sharedServerSwitcher(Request $request): array
    {
        $user = $request->user();

        if (! $user || ! Schema::hasTable('servers')) {
            return [];
        }

        // Track recently visited servers in user session
        if ($request->hasSession() && preg_match('#^/server/(\d+)#', $request->getPathInfo(), $matches)) {
            $visitedId = (int) $matches[1];
            $recent = $request->session()->get('recent_server_ids', []);
            if (! is_array($recent)) {
                $recent = [];
            }
            $recent = array_values(array_unique(array_merge([$visitedId], $recent)));
            $request->session()->put('recent_server_ids', array_slice($recent, 0, 10));
        }

        $sessionRecentIds = $request->hasSession() ? $request->session()->get('recent_server_ids', []) : [];
        if (! is_array($sessionRecentIds)) {
            $sessionRecentIds = [];
        }

        // When an admin views a server they don't own, scope the switcher
        // to the server owner's servers instead of showing every server.
        if ($user->is_admin && preg_match('#^/server/(\d+)#', $request->getPathInfo(), $matches)) {
            $server = Server::query()->find((int) $matches[1]);

            if ($server && $server->user_id !== $user->id) {
                return Server::query()
                    ->where('user_id', $server->user_id)
                    ->select(['id', 'name', 'status'])
                    ->orderByDesc('updated_at')
                    ->limit(10)
                    ->get()
                    ->map(
                        fn (Server $s): array => [
                            'id' => $s->id,
                            'name' => $s->name,
                            'status' => $s->status,
                        ],
                    )
                    ->all();
            }
        }

        $baseQuery = $user->is_admin ? Server::query() : $user->servers();

        // 1. Fetch recently visited servers present in user's scope
        $recentServers = collect();
        if (! empty($sessionRecentIds)) {
            $fetched = (clone $baseQuery)
                ->whereIn('id', $sessionRecentIds)
                ->select(['id', 'name', 'status'])
                ->get();

            $idOrderMap = array_flip($sessionRecentIds);
            $recentServers = $fetched->sort(
                fn (Server $a, Server $b) => ($idOrderMap[$a->id] ?? 999) <=> ($idOrderMap[$b->id] ?? 999),
            )->values();
        }

        // 2. If less than 10, fill up with most recently updated servers
        $needed = 10 - $recentServers->count();
        if ($needed > 0) {
            $excludeIds = $recentServers->pluck('id')->all();
            $additional = (clone $baseQuery)
                ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
                ->select(['id', 'name', 'status'])
                ->orderByDesc('updated_at')
                ->limit($needed)
                ->get();

            $recentServers = $recentServers->concat($additional);
        }

        return $recentServers
            ->map(
                fn (Server $server): array => [
                    'id' => $server->id,
                    'name' => $server->name,
                    'status' => $server->status,
                ],
            )
            ->all();
    }

    /**
     * @return array<string, bool|int|string|null>|null
     */
    protected function buildThemeCSS(): ?string
    {
        return app(AppSettingsService::class)->buildThemeCSS();
    }

    protected function sharedUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'is_admin' => $user->is_admin,
            'suspended_at' => $user->suspended_at?->toIso8601String(),
            'two_factor_enabled' => $user->two_factor_secret !== null,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }
}
