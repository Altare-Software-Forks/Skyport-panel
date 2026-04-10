<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Client\Concerns\AuthorizesServerAccess;
use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Models\Server;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ServerBackupsController extends Controller
{
    use AuthorizesServerAccess;

    public function index(Request $request, Server $server): Response
    {
        $this->authorizeServerAccess($request, $server);

        $backups = $server->backups()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Backup $backup): array => [
                'id' => $backup->id,
                'name' => $backup->name,
                'uuid' => $backup->uuid,
                'size_bytes' => $backup->size_bytes,
                'checksum' => $backup->checksum,
                'status' => $backup->status,
                'error' => $backup->error,
                'completed_at' => $backup->completed_at?->toIso8601String(),
                'created_at' => $backup->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('server/backups', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'status' => $server->status,
                'backup_limit' => $server->backup_limit,
            ],
            'backups' => $backups,
        ]);
    }

    public function store(Request $request, Server $server): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Please enter a backup name.',
        ]);

        $completedCount = $server->backups()
            ->whereIn('status', ['completed', 'creating'])
            ->count();

        if ($server->backup_limit <= 0) {
            return Redirect::back()->withErrors([
                'name' => 'Backups are disabled for this server.',
            ]);
        }

        if ($completedCount >= $server->backup_limit) {
            return Redirect::back()->withErrors([
                'name' => "You have reached the backup limit of {$server->backup_limit}.",
            ]);
        }

        $uuid = Str::uuid()->toString();

        $backup = Backup::query()->create([
            'server_id' => $server->id,
            'name' => $validated['name'],
            'uuid' => $uuid,
            'status' => 'creating',
        ]);

        $this->dispatchBackupToDaemon($server, $backup, 'create');

        return Redirect::back()->with('success', 'Backup is being created.');
    }

    public function restore(Request $request, Server $server, Backup $backup): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);

        abort_unless($backup->server_id === $server->id, 422, 'This backup does not belong to this server.');
        abort_unless($backup->status === 'completed', 422, 'This backup is not in a completed state.');

        $backup->update(['status' => 'restoring']);

        $this->dispatchBackupToDaemon($server, $backup, 'restore');

        return Redirect::back()->with('success', 'Backup is being restored. Server files will be replaced.');
    }

    public function download(Request $request, Server $server, Backup $backup): StreamedResponse|RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);

        abort_unless($backup->server_id === $server->id, 422, 'This backup does not belong to this server.');
        abort_unless($backup->status === 'completed', 422, 'This backup is not ready for download.');

        $server->loadMissing('node.credential');

        $callbackToken = $server->node->credential?->daemon_callback_token;
        $daemonUuid = $server->node->daemon_uuid;

        if (! $callbackToken || ! $daemonUuid) {
            return Redirect::back()->with('warning', 'Could not connect to the daemon.');
        }

        $scheme = $server->node->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/%d/backups/%s/download',
            $scheme,
            $server->node->fqdn,
            $server->node->daemon_port,
            $server->id,
            $backup->uuid,
        );

        return response()->streamDownload(
            function () use ($url, $callbackToken) {
                $response = Http::timeout(300)
                    ->withToken($callbackToken)
                    ->withOptions(['stream' => true])
                    ->get($url);

                $body = $response->toPsrResponse()->getBody();

                while (! $body->eof()) {
                    echo $body->read(8192);
                    flush();
                }
            },
            "{$backup->name}.tar.gz",
            ['Content-Type' => 'application/gzip'],
        );
    }

    public function destroy(Request $request, Server $server, Backup $backup): RedirectResponse
    {
        $this->authorizeServerAccess($request, $server);

        abort_unless($backup->server_id === $server->id, 422, 'This backup does not belong to this server.');

        $this->dispatchBackupToDaemon($server, $backup, 'delete');

        $backup->delete();

        return Redirect::back()->with('success', 'Backup deleted.');
    }

    private function dispatchBackupToDaemon(Server $server, Backup $backup, string $action): void
    {
        $server->loadMissing('node.credential');

        $callbackToken = $server->node->credential?->daemon_callback_token;
        $daemonUuid = $server->node->daemon_uuid;

        if (! $callbackToken || ! $daemonUuid) {
            return;
        }

        $scheme = $server->node->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/%d/backups',
            $scheme,
            $server->node->fqdn,
            $server->node->daemon_port,
            $server->id,
        );

        try {
            Http::timeout(10)
                ->acceptJson()
                ->withToken($callbackToken)
                ->post($url, [
                    'action' => $action,
                    'backup_id' => $backup->id,
                    'backup_uuid' => $backup->uuid,
                    'backup_name' => $backup->name,
                    'panel_version' => config('app.version'),
                    'uuid' => $daemonUuid,
                ]);
        } catch (Throwable) {
            // Daemon may be offline; the backup will be picked up later
        }
    }
}
