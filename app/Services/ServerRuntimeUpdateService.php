<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\NodeCredential;
use App\Models\Server;
use App\Models\ServerTransfer;
use InvalidArgumentException;

class ServerRuntimeUpdateService
{
    public function __construct(
        private PanelVersionService $panelVersionService,
    ) {}

    public function record(
        string $daemonSecret,
        Server $server,
        array $payload,
    ): Server {
        $credential = NodeCredential::query()
            ->with('node')
            ->where('daemon_secret_hash', hash('sha256', $daemonSecret))
            ->first();

        if (! $credential || ! $credential->node) {
            throw new InvalidArgumentException('The daemon secret is invalid.');
        }

        $this->panelVersionService->ensureCompatible($payload['version']);

        if (
            $credential->node->daemon_uuid &&
            $credential->node->daemon_uuid !== $payload['uuid']
        ) {
            throw new InvalidArgumentException(
                'The daemon identity does not match this node.',
            );
        }

        if ($server->node_id !== $credential->node->id) {
            throw new InvalidArgumentException(
                'The server does not belong to this node.',
            );
        }

        // Handle transfer progress updates.
        if (isset($payload['transfer_id'])) {
            $transfer = ServerTransfer::query()
                ->where('id', $payload['transfer_id'])
                ->where('server_id', $server->id)
                ->first();

            if ($transfer) {
                app(ServerTransferService::class)->handleProgress($transfer, $payload);
            }

            if (! isset($payload['status']) || $payload['status'] === null) {
                return $server;
            }
        }

        // Handle backup status updates.
        if (isset($payload['backup_id']) && isset($payload['backup_status'])) {
            $backup = Backup::query()
                ->where('id', $payload['backup_id'])
                ->where('server_id', $server->id)
                ->first();

            if ($backup) {
                $backup->update([
                    'status' => $payload['backup_status'],
                    'size_bytes' => $payload['backup_size_bytes'] ?? $backup->size_bytes,
                    'error' => $payload['backup_error'] ?? null,
                    'completed_at' => $payload['backup_status'] === 'completed' ? now() : $backup->completed_at,
                ]);
            }

            if (! isset($payload['status']) || $payload['status'] === null) {
                return $server;
            }
        }

        $server
            ->forceFill([
                'last_error' => $payload['last_error'] ?? null,
                'status' => $payload['status'],
            ])
            ->save();

        return $server->fresh() ?? $server;
    }
}
