<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Transport for the "database" mail driver: stores every email, never sends.
 */
class DatabaseTransport implements TransportInterface
{
    /** @deprecated use MessageStore::TABLE */
    public const TABLE = MessageStore::TABLE;

    public function __construct(
        protected MessageStore $store
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $sent = new SentMessage($message, $envelope ?? Envelope::create($message));

        $this->store->record($message, $sent->getEnvelope(), $sent->toString(), [
            'status' => MessageStore::CAUGHT,
            'transport' => (string) $this,
        ]);

        return $sent;
    }

    public function __toString(): string
    {
        return 'database://mail-catcher';
    }
}
