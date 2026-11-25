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
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Saves one email (plus how its delivery went) to the mail_catcher_messages table.
 */
class MessageStore
{
    public const TABLE = 'mail_catcher_messages';

    public const CAUGHT = 'caught';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    public function __construct(
        protected ConnectionInterface $db
    ) {
    }

    /**
     * @param array{status?: string, transport?: ?string, message_id?: ?string, error?: ?string, debug?: ?string, duration_ms?: ?int} $delivery
     */
    public function record(RawMessage $message, ?Envelope $envelope, string $raw, array $delivery = []): void
    {
        $from = '';
        $to = '';

        try {
            $envelope ??= Envelope::create($message);
            $from = $envelope->getSender()->toString();
            $to = implode(', ', array_map(fn (Address $a) => $a->toString(), $envelope->getRecipients()));
        } catch (\Throwable) {
            // A message without a usable sender/recipient is still worth keeping.
        }

        $subject = null;
        $html = null;
        $text = null;

        if ($message instanceof Email) {
            if ($message->getFrom()) {
                $from = implode(', ', array_map(fn (Address $a) => $a->toString(), $message->getFrom()));
            }
            if ($to === '' && $message->getTo()) {
                $to = implode(', ', array_map(fn (Address $a) => $a->toString(), $message->getTo()));
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
            'raw' => $raw,
            'status' => $delivery['status'] ?? self::CAUGHT,
            'transport' => isset($delivery['transport']) ? mb_substr($delivery['transport'], 0, 255) : null,
            'message_id' => isset($delivery['message_id']) ? mb_substr($delivery['message_id'], 0, 255) : null,
            'error' => $delivery['error'] ?? null,
            'debug' => self::hideLogin($delivery['debug'] ?? null),
            'duration_ms' => $delivery['duration_ms'] ?? null,
            'created_at' => Carbon::now('UTC'),
        ]);
    }

    /**
     * The message id of a sent email, or null. SentMessage::getMessageId() throws
     * when no id was set (e.g. a raw, re-sent message the server gave no id for).
     */
    public static function messageId(?SentMessage $sent): ?string
    {
        try {
            return $sent?->getMessageId() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The SMTP transcript contains the login (base64 username/password on the
     * AUTH lines). Replace everything the client sends during AUTH with [hidden].
     */
    public static function hideLogin(?string $debug): ?string
    {
        if ($debug === null || trim($debug) === '') {
            return null;
        }

        $out = [];
        $inAuth = false;

        foreach (explode("\n", $debug) as $line) {
            if (preg_match('/^(\[[^\]]*\] > )AUTH\s+(\S+)(.*)$/i', $line, $m)) {
                $out[] = $m[1].'AUTH '.$m[2].(trim($m[3]) !== '' ? ' [hidden]' : '');
                $inAuth = true;
                continue;
            }

            if ($inAuth && preg_match('/^(\[[^\]]*\] > )/', $line, $m)) {
                $out[] = $m[1].'[hidden]';
                continue;
            }

            if ($inAuth && preg_match('/^\[[^\]]*\] < (\d{3})/', $line, $m) && $m[1] !== '334') {
                $inAuth = false;
            }

            $out[] = $line;
        }

        return implode("\n", $out);
    }

    /** @param string|resource|null $body */
    private function body($body): ?string
    {
        if (is_resource($body)) {
            return stream_get_contents($body) ?: null;
        }

        return $body;
    }
}
