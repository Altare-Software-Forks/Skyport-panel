<?php

namespace App\Http\Controllers\Daemon;

use App\Http\Controllers\Controller;
use App\Models\NodeCredential;
use App\Models\Server;
use App\Models\ServerUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class SftpAuthController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $daemonSecret = $request->bearerToken();

        if (! $daemonSecret) {
            return response()->json(
                ['message' => 'Missing daemon secret.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $credential = NodeCredential::query()
            ->where('daemon_secret_hash', hash('sha256', $daemonSecret))
            ->first();

        if (! $credential) {
            return response()->json(
                ['message' => 'Invalid daemon secret.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Username format: "email.server_id"
        $lastDot = strrpos($username, '.');

        if ($lastDot === false) {
            return response()->json(
                ['message' => 'Invalid username format. Use email.server_id.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $email = substr($username, 0, $lastDot);
        $serverIdStr = substr($username, $lastDot + 1);

        if (! is_numeric($serverIdStr)) {
            return response()->json(
                ['message' => 'Invalid username format. Use email.server_id.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $serverId = (int) $serverIdStr;
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(
                ['message' => 'Invalid credentials.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $server = Server::query()
            ->where('id', $serverId)
            ->where('node_id', $credential->node_id)
            ->first();

        if (! $server) {
            return response()->json(
                ['message' => 'Server not found on this node.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        $serverUser = ServerUser::query()
            ->where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->first();

        $hasAccess = $user->is_admin
            || $server->user_id === $user->id
            || ($serverUser && $serverUser->hasPermission(ServerUser::PERMISSION_FILES));

        if (! $hasAccess) {
            return response()->json(
                ['message' => 'You do not have file access permissions for this server.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return response()->json([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'permissions' => $this->resolvePermissions($user, $server, $serverUser),
        ]);
    }

    /**
     * @return list<string>
     */
    private function resolvePermissions(User $user, Server $server, ?ServerUser $serverUser): array
    {
        if ($user->is_admin || $server->user_id === $user->id) {
            return ['*'];
        }

        return $serverUser?->permissionList() ?? [];
    }
}
