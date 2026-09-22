<?php

namespace App\Domain\Audit\Services;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class LogRedactionProcessor implements ProcessorInterface
{
    /**
     * Keys whose values should always be completely redacted.
     *
     * @var array<string>
     */
    protected array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'client_secret',
        'account_id',
        'client_id',
        'webhook_secret_token',
        'secret',
        'token',
        'access_token',
        'refresh_token',
        'host_key',
        'passcode',
        'mfa_secret',
        'secret_token',
        'plainToken',
        'encryptedToken',
        'start_url',
    ];

    /**
     * Regular expressions for sensitive patterns within text/messages.
     *
     * @var array<string, string>
     */
    protected array $regexPatterns = [
        // Bearer tokens
        '/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i' => 'Bearer [REDACTED]',
        // Zoom start URLs
        '/https:\/\/[a-z0-9\-\.]*zoom\.us\/s\/[^\s"\'<>]+/i' => 'https://zoom.us/s/[REDACTED_START_URL]',
        // Host keys (6-digit standalone or labelled)
        '/(host_key[\'":\s=]+)(\d{6})/i' => '$1[REDACTED]',
        // Passcodes
        '/(passcode[\'":\s=]+)([^\s,"\'&]+)/i' => '$1[REDACTED]',
    ];

    /**
     * Handle monolog invoke.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $record->message;
        foreach ($this->regexPatterns as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message);
        }

        $context = $this->redactArray($record->context);
        $extra = $this->redactArray($record->extra);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra
        );
    }

    /**
     * Recursively redact an array based on sensitive keys and patterns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function redactArray(array $data): array
    {
        $redacted = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                $redacted[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redactArray($value);
            } elseif (is_string($value)) {
                $redacted[$key] = $this->redactString($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Check if a key name matches any sensitive key.
     */
    protected function isSensitiveKey(string $key): bool
    {
        foreach ($this->sensitiveKeys as $sensitive) {
            if ($key === $sensitive || str_contains($key, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact sensitive string patterns.
     */
    protected function redactString(string $value): string
    {
        foreach ($this->regexPatterns as $pattern => $replacement) {
            $value = (string) preg_replace($pattern, $replacement, $value);
        }

        return $value;
    }
}
