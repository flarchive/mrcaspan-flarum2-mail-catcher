<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Symfony Mailer transport that writes each message to the database.
 */
class DatabaseTransport implements TransportInterface
{
    public const TABLE = 'mail_catcher_messages';

    public function __construct(
        protected ConnectionInterface $db
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $envelope ??= Envelope::create($message);
        $sent = new SentMessage($message, $envelope);

        $from = $envelope->getSender()->toString();
        $to = implode(', ', array_map(fn (Address $a) => $a->toString(), $envelope->getRecipients()));
        $subject = null;
        $html = null;
        $text = null;

        if ($message instanceof Email) {
            if ($message->getFrom()) {
                $from = implode(', ', array_map(fn (Address $a) => $a->toString(), $message->getFrom()));
            }
            $subject = $message->getSubject();
            $html = $this->body($message->getHtmlBody());
            $text = $this->body($message->getTextBody());
        }

        $this->db->table(self::TABLE)->insert([
            'from_address' => mb_substr($from, 0, 255),
            'to_address' => $to,
            'subject' => $subject,
            'html_body' => $html,
            'text_body' => $text,
            'raw' => $message->toString(),
            'created_at' => Carbon::now('UTC'),
        ]);

        return $sent;
    }

    /** @param string|resource|null $body */
    private function body($body): ?string
    {
        if (is_resource($body)) {
            return stream_get_contents($body) ?: null;
        }

        return $body;
    }

    public function __toString(): string
    {
        return 'database://mail-catcher';
    }
}
