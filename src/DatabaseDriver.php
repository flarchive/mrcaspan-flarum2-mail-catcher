<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Flarum\Mail\DriverInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\MessageBag;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Mail driver that stores every email in the database instead of sending it.
 */
class DatabaseDriver implements DriverInterface
{
    public function __construct(
        protected MessageStore $store
    ) {
    }

    public function availableSettings(): array
    {
        return [];
    }

    public function validate(SettingsRepositoryInterface $settings, Factory $validator): MessageBag
    {
        return new MessageBag;
    }

    // true so Flarum treats mail as working (e.g. the "Send test email" button).
    public function canSend(): bool
    {
        return true;
    }

    public function buildTransport(SettingsRepositoryInterface $settings): TransportInterface
    {
        return new DatabaseTransport($this->store);
    }
}
