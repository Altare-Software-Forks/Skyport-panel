<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelemetryService
{
    /**
     * The endpoint where anonymous telemetry is sent.
     */
    private const TELEMETRY_ENDPOINT = 'https://telemetry.skyport.sh/v1/report';

    /**
     * The API key used to authenticate with the telemetry service.
     */
    private const TELEMETRY_API_KEY = '33573d1aa590e3980770cbc0643bf80b10192c62c6f1c7ece92fbe493b68f128';

    /**
     * Maximum number of error reports to include per telemetry payload.
     */
    private const MAX_ERRORS_PER_REPORT = 50;

    public function __construct(
        private AppSettingsService $settings,
    ) {}

    /**
     * Whether telemetry collection is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->settings->telemetryEnabled();
    }

    /**
     * Report an install event (called once during installation).
     */
    public function reportInstall(): bool
    {
        return $this->send([
            'event' => 'install',
            'panel_version' => $this->panelVersion(),
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ]);
    }

    /**
     * Report a periodic heartbeat with aggregated error/issue data.
     *
     * @param  array<int, array{type: string, message: string, count: int}>  $errors
     */
    public function reportHeartbeat(array $errors = []): bool
    {
        return $this->send([
            'event' => 'heartbeat',
            'panel_version' => $this->panelVersion(),
            'php_version' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'errors' => array_slice($errors, 0, self::MAX_ERRORS_PER_REPORT),
        ]);
    }

    /**
     * Report an error/issue type for later aggregation.
     */
    public function reportError(string $type, string $message): bool
    {
        return $this->send([
            'event' => 'error',
            'panel_version' => $this->panelVersion(),
            'error_type' => $type,
            'error_message' => $this->sanitizeErrorMessage($message),
        ]);
    }

    /**
     * Send a telemetry payload if telemetry is enabled.
     *
     * @param  array<string, mixed>  $payload
     */
    private function send(array $payload): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload['timestamp'] = now()->toIso8601String();

        try {
            $response = Http::timeout(5)
                ->retry(2, 100)
                ->withToken(self::TELEMETRY_API_KEY)
                ->post(self::TELEMETRY_ENDPOINT, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            // Telemetry must never interrupt normal operation.
            Log::debug('Telemetry send failed: '.$e->getMessage());

            return false;
        }
    }

    private function panelVersion(): string
    {
        return (string) config('app.version', 'unknown');
    }

    /**
     * Strip any potentially sensitive data from error messages.
     * Removes file paths, IPs, emails, and query strings.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove file paths
        $message = preg_replace('#/[a-zA-Z0-9_./-]+\.(php|js|ts|tsx)#', '[path]', $message) ?? $message;

        // Remove IPs
        $message = preg_replace('/\b\d{1,3}(\.\d{1,3}){3}\b/', '[ip]', $message) ?? $message;

        // Remove emails
        $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email]', $message) ?? $message;

        // Truncate to a reasonable length
        return mb_substr($message, 0, 500);
    }
}
