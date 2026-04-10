<?php

namespace App\Services;

use App\Models\Allocation;
use App\Models\Server;
use App\Models\ServerTransfer;
use Illuminate\Support\Facades\Http;
use Throwable;

class ServerTransferService
{
    public function __construct(
        private ServerConfigurationService $serverConfigurationService,
    ) {}

    public function initiate(Server $server, int $targetNodeId, int $targetAllocationId): ServerTransfer
    {
        $server->loadMissing('node.credential');

        $transfer = ServerTransfer::query()->create([
            'server_id' => $server->id,
            'source_node_id' => $server->node_id,
            'target_node_id' => $targetNodeId,
            'target_allocation_id' => $targetAllocationId,
            'status' => 'archiving',
        ]);

        $server->update(['status' => 'transferring']);

        // Tell the source daemon to archive the server volume.
        $this->notifySourceDaemon($server, $transfer);

        return $transfer;
    }

    public function cancel(ServerTransfer $transfer): void
    {
        $transfer->update([
            'status' => 'cancelled',
            'error' => 'Transfer was cancelled by an administrator.',
        ]);

        $server = $transfer->server;
        $server->update(['status' => 'offline']);

        // Tell the source daemon to clean up.
        $this->notifySourceDaemonCancel($server, $transfer);
    }

    public function handleProgress(ServerTransfer $transfer, array $payload): void
    {
        $update = [];

        if (isset($payload['transfer_status'])) {
            $update['status'] = $payload['transfer_status'];
        }

        if (isset($payload['transfer_progress'])) {
            $update['progress'] = min(100, max(0, (int) $payload['transfer_progress']));
        }

        if (isset($payload['transfer_archive_size'])) {
            $update['archive_size_bytes'] = (int) $payload['transfer_archive_size'];
        }

        if (isset($payload['transfer_error'])) {
            $update['error'] = $payload['transfer_error'];
        }

        if (! empty($update)) {
            $transfer->update($update);
        }

        // If archiving is complete, start the transfer to the target node.
        if (($payload['transfer_status'] ?? '') === 'transferring') {
            $this->initiateTransferToTarget($transfer);
        }

        // If the transfer completed on the target, finalize it.
        if (($payload['transfer_status'] ?? '') === 'completed') {
            $this->finalizeTransfer($transfer);
        }

        // If the transfer failed, restore the server.
        if (($payload['transfer_status'] ?? '') === 'failed') {
            $transfer->server->update(['status' => 'offline']);
        }
    }

    private function finalizeTransfer(ServerTransfer $transfer): void
    {
        $server = $transfer->server;
        $targetAllocation = Allocation::query()->find($transfer->target_allocation_id);

        if (! $targetAllocation) {
            $transfer->update(['status' => 'failed', 'error' => 'Target allocation no longer exists.']);
            $server->update(['status' => 'offline']);

            return;
        }

        // Update server to point to the new node and allocation.
        $server->update([
            'node_id' => $transfer->target_node_id,
            'allocation_id' => $transfer->target_allocation_id,
            'status' => 'offline',
        ]);

        // Update the allocation to point to this server.
        $targetAllocation->update(['server_id' => $server->id]);

        // Release the old allocation.
        Allocation::query()
            ->where('server_id', $server->id)
            ->where('id', '!=', $transfer->target_allocation_id)
            ->update(['server_id' => null]);

        // Push the new configuration to the target daemon.
        $server->refresh()->loadMissing(['allocation', 'cargo', 'node.credential', 'user']);

        $this->pushToTargetDaemon($server);

        // Delete the server from the source daemon.
        $this->deleteFromSourceDaemon($transfer);
    }

    private function notifySourceDaemon(Server $server, ServerTransfer $transfer): void
    {
        $callbackToken = $server->node->credential?->daemon_callback_token;

        if (! $callbackToken) {
            return;
        }

        $scheme = $server->node->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/%d/transfer',
            $scheme,
            $server->node->fqdn,
            $server->node->daemon_port,
            $server->id,
        );

        try {
            Http::timeout(10)
                ->withToken($callbackToken)
                ->post($url, [
                    'action' => 'archive',
                    'transfer_id' => $transfer->id,
                    'panel_version' => config('app.version'),
                    'uuid' => $server->node->daemon_uuid,
                ]);
        } catch (Throwable) {
            $transfer->update(['status' => 'failed', 'error' => 'Could not reach the source daemon.']);
            $server->update(['status' => 'offline']);
        }
    }

    private function notifySourceDaemonCancel(Server $server, ServerTransfer $transfer): void
    {
        $server->loadMissing('node.credential');
        $callbackToken = $server->node->credential?->daemon_callback_token;

        if (! $callbackToken) {
            return;
        }

        $scheme = $server->node->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/%d/transfer',
            $scheme,
            $server->node->fqdn,
            $server->node->daemon_port,
            $server->id,
        );

        try {
            Http::timeout(10)
                ->withToken($callbackToken)
                ->post($url, [
                    'action' => 'cancel',
                    'transfer_id' => $transfer->id,
                    'panel_version' => config('app.version'),
                    'uuid' => $server->node->daemon_uuid,
                ]);
        } catch (Throwable) {
            // Best effort.
        }
    }

    private function initiateTransferToTarget(ServerTransfer $transfer): void
    {
        // The transfer of the archive from source → target is handled by the daemons directly.
        // The source daemon pushes the archive to the target daemon.
    }

    private function pushToTargetDaemon(Server $server): void
    {
        $callbackToken = $server->node->credential?->daemon_callback_token;
        $daemonUuid = $server->node->daemon_uuid;

        if (! $callbackToken || ! $daemonUuid) {
            return;
        }

        $scheme = $server->node->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/sync',
            $scheme,
            $server->node->fqdn,
            $server->node->daemon_port,
        );

        try {
            Http::timeout(10)
                ->withToken($callbackToken)
                ->post($url, [
                    'panel_version' => config('app.version'),
                    'server' => $this->serverConfigurationService->payload($server),
                    'uuid' => $daemonUuid,
                ]);
        } catch (Throwable) {
            // Best effort.
        }
    }

    private function deleteFromSourceDaemon(ServerTransfer $transfer): void
    {
        $sourceNode = $transfer->sourceNode;
        $sourceNode->loadMissing('credential');

        $callbackToken = $sourceNode->credential?->daemon_callback_token;
        $daemonUuid = $sourceNode->daemon_uuid;

        if (! $callbackToken || ! $daemonUuid) {
            return;
        }

        $scheme = $sourceNode->use_ssl ? 'https' : 'http';
        $url = sprintf(
            '%s://%s:%d/api/daemon/servers/%d',
            $scheme,
            $sourceNode->fqdn,
            $sourceNode->daemon_port,
            $transfer->server_id,
        );

        try {
            Http::timeout(10)
                ->withToken($callbackToken)
                ->send('DELETE', $url, [
                    'json' => [
                        'panel_version' => config('app.version'),
                        'uuid' => $daemonUuid,
                    ],
                ]);
        } catch (Throwable) {
            // Best effort.
        }
    }
}
