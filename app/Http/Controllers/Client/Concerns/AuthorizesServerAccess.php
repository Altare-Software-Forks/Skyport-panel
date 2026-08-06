<?php

namespace App\Http\Controllers\Client\Concerns;

use App\Models\Server;
use App\Models\ServerUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesServerAccess
{
    protected function authorizeServerAccess(
        Request $request,
        Server $server,
        ?string $requiredPermission = null,
    ): void {
        $user = $request->user();

        if ($user?->is_admin || $server->user_id === $user?->id) {
            return;
        }

        $serverUser = ServerUser::query()
            ->where('server_id', $server->id)
            ->where('user_id', $user?->id)
            ->first();

        abort_unless($serverUser !== null, Response::HTTP_FORBIDDEN);

        if ($requiredPermission !== null) {
            abort_unless(
                $serverUser->hasPermission($requiredPermission),
                Response::HTTP_FORBIDDEN,
                'You do not have permission to perform this action.',
            );
        }
    }
}
