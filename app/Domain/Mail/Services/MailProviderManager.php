<?php

namespace App\Domain\Mail\Services;

use App\Domain\Mail\Contracts\MailProviderInterface;
use App\Domain\Mail\Drivers\GmailApiMailProvider;
use App\Domain\Mail\Drivers\LogMailProvider;
use App\Domain\Mail\Drivers\MicrosoftGraphMailProvider;
use App\Domain\Mail\Drivers\SmtpMailProvider;
use App\Domain\Settings\Models\Setting;
use InvalidArgumentException;

class MailProviderManager
{
    /**
     * Resolve the active MailProvider instance.
     */
    public function getProvider(?string $driver = null): MailProviderInterface
    {
        $driver = $driver ?? Setting::get('mail.provider', config('mail.default', 'smtp'));

        return match ($driver) {
            'smtp' => app(SmtpMailProvider::class),
            'gmail', 'gmail_api' => app(GmailApiMailProvider::class),
            'graph', 'microsoft_graph' => app(MicrosoftGraphMailProvider::class),
            'log', 'array' => app(LogMailProvider::class),
            default => throw new InvalidArgumentException("Unsupported mail driver: [{$driver}]"),
        };
    }
}
