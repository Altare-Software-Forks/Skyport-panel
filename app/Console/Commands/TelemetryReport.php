<?php

namespace App\Console\Commands;

use App\Services\TelemetryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelemetryReport extends Command
{
    protected $signature = 'telemetry:report
                            {--event=heartbeat : The event type to report (install, heartbeat)}';

    protected $description = 'Send anonymous telemetry report to help improve Skyport';

    public function handle(TelemetryService $telemetry): int
    {
        if (! $telemetry->isEnabled()) {
            $this->components->info('Telemetry is disabled — skipping.');

            return self::SUCCESS;
        }

        $event = $this->option('event');

        return match ($event) {
            'install' => $this->reportInstall($telemetry),
            'heartbeat' => $this->reportHeartbeat($telemetry),
            default => $this->invalidEvent($event),
        };
    }

    private function reportInstall(TelemetryService $telemetry): int
    {
        $this->components->info('Sending install telemetry...');

        if ($telemetry->reportInstall()) {
            $this->components->info('Install event reported.');

            return self::SUCCESS;
        }

        $this->components->warn('Could not send install telemetry (this is non-critical).');

        return self::SUCCESS;
    }

    private function reportHeartbeat(TelemetryService $telemetry): int
    {
        $errors = $this->collectRecentErrors();

        if ($telemetry->reportHeartbeat($errors)) {
            $this->components->info('Heartbeat reported with '.count($errors).' error type(s).');

            return self::SUCCESS;
        }

        $this->components->warn('Could not send heartbeat telemetry (this is non-critical).');

        return self::SUCCESS;
    }

    /**
     * Collect and aggregate recent errors from the log.
     *
     * @return list<array{type: string, message: string, count: int}>
     */
    private function collectRecentErrors(): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! file_exists($logPath)) {
            return [];
        }

        // Read the last 100KB of the log file
        $handle = fopen($logPath, 'r');
        if (! $handle) {
            return [];
        }

        $fileSize = filesize($logPath);
        $readSize = min($fileSize, 100 * 1024);
        fseek($handle, -$readSize, SEEK_END);
        $content = fread($handle, $readSize);
        fclose($handle);

        if (! $content) {
            return [];
        }

        // Extract error-level entries
        $errors = [];
        preg_match_all(
            '/\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*\]\s*\w+\.(ERROR|CRITICAL|EMERGENCY):\s*(.+?)(?=\n\[|\z)/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $grouped = [];
        foreach ($matches as $match) {
            $type = $match[1];
            // Take only the first line of the error message
            $message = trim(explode("\n", $match[2])[0]);
            $key = $type.'|'.$message;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'type' => strtolower($type),
                    'message' => $message,
                    'count' => 0,
                ];
            }
            $grouped[$key]['count']++;
        }

        return array_values($grouped);
    }

    private function invalidEvent(string $event): int
    {
        $this->components->error("Unknown telemetry event: {$event}");
        $this->components->info('Supported events: install, heartbeat');

        return self::FAILURE;
    }
}
