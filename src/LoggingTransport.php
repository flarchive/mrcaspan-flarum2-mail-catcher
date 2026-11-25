<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * Wraps the real transport (SMTP, Mailgun, ...) built by the configured mail driver.
 *
 *   off   - hands the email straight to the real transport
 *   catch - stores it, does not send
 *   log   - sends it with the real transport, then stores it with the result
 *           (sent/failed, error, message id, SMTP transcript, time taken)
 *   forward - sends it only to the forward address instead of its recipients; not stored
 *
 * In log mode a problem with storing never stops the email from being sent.
 */
class LoggingTransport implements TransportInterface
{
    public function __construct(
        protected TransportInterface $inner,
        protected CaptureMode $mode,
        protected MessageStore $store,
        protected LoggerInterface $logger
    ) {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $state = $this->state();
        $mode = $state['mode'];

        if ($mode === CaptureMode::FORWARD) {
            // Same email, delivered only to the forward address. Nothing is stored.
            $sender = ($envelope ?? Envelope::create($message))->getSender();

            return $this->inner->send($message, new Envelope($sender, [new Address($state['forwardTo'])]));
        }

        if ($mode === CaptureMode::CATCH) {
            $sent = new SentMessage($message, $envelope ?? Envelope::create($message));

            $this->store->record($message, $sent->getEnvelope(), $sent->toString(), [
                'status' => MessageStore::CAUGHT,
                'transport' => (string) $this->inner,
            ]);

            return $sent;
        }

        if ($mode !== CaptureMode::LOG) {
            return $this->inner->send($message, $envelope);
        }

        $start = hrtime(true);

        try {
            $sent = $this->inner->send($message, $envelope);
        } catch (\Throwable $e) {
            $this->safeRecord($message, $envelope, $message->toString(), [
                'status' => MessageStore::FAILED,
                'error' => get_class($e).': '.$e->getMessage(),
                'debug' => $e instanceof TransportExceptionInterface ? $e->getDebug() : null,
                'duration_ms' => $this->since($start),
            ]);

            throw $e;
        }

        $this->safeRecord($message, $sent?->getEnvelope() ?? $envelope, $sent ? $sent->toString() : $message->toString(), [
            'status' => MessageStore::SENT,
            'message_id' => MessageStore::messageId($sent),
            'debug' => $sent?->getDebug(),
            'duration_ms' => $this->since($start),
        ]);

        return $sent;
    }

    private function state(): array
    {
        try {
            return $this->mode->current();
        } catch (\Throwable $e) {
            $this->logger->error('[mail-catcher] Could not read the capture mode: '.$e->getMessage());

            return ['mode' => CaptureMode::OFF, 'until' => null, 'forwardTo' => null];
        }
    }

    private function safeRecord(RawMessage $message, ?Envelope $envelope, string $raw, array $delivery): void
    {
        try {
            $this->store->record($message, $envelope, $raw, $delivery + ['transport' => (string) $this->inner]);
        } catch (\Throwable $e) {
            $this->logger->error('[mail-catcher] Could not log an email: '.$e->getMessage());
        }
    }

    private function since(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1e6);
    }

    /** The real transport (SMTP, Mailgun, ...), used to release caught emails. */
    public function inner(): TransportInterface
    {
        return $this->inner;
    }

    public function __toString(): string
    {
        return (string) $this->inner;
    }
}
