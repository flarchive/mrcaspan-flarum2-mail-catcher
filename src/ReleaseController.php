<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * POST /api/mail-catcher/{id}/release  {"to": "optional@other.address"}
 *
 * Sends a caught email, exactly as it was caught, with the real mail transport
 * (skipping Catch mode for this one email).
 *   - no "to":   goes to the original recipients; the entry becomes sent/failed.
 *   - with "to": goes to that address only; the original stays caught and a new
 *                sent/failed entry is added for the other address.
 */
class ReleaseController implements RequestHandlerInterface
{
    public function __construct(
        protected ConnectionInterface $db,
        protected Container $container
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = (int) Arr::get((array) $request->getAttribute('routeParameters'), 'id');
        $other = trim((string) Arr::get((array) $request->getParsedBody(), 'to', ''));

        $row = $this->db->table(MessageStore::TABLE)->where('id', $id)->first();

        if (! $row) {
            throw new ModelNotFoundException;
        }

        if ($other !== '' && ! filter_var($other, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['to' => 'That is not a valid email address.']);
        }

        $transport = $this->realTransport();
        $recipients = $other !== '' ? [new Address($other)] : $this->addresses((string) $row->to_address);

        if (! $recipients) {
            throw new ValidationException(['to' => 'This email has no recipient to release it to.']);
        }

        $sender = $this->addresses((string) $row->from_address)[0] ?? null;

        if (! $sender) {
            throw new ValidationException(['to' => 'This email has no sender address.']);
        }

        $delivery = ['transport' => (string) $transport];
        $start = hrtime(true);

        $sent = null;
        $failure = null;

        try {
            $sent = $transport->send(new RawMessage((string) $row->raw), new Envelope($sender, $recipients));
        } catch (\Throwable $e) {
            $failure = $e;
        }

        if ($failure) {
            $delivery += [
                'status' => MessageStore::FAILED,
                'message_id' => null,
                'debug' => MessageStore::hideLogin($failure instanceof TransportExceptionInterface ? $failure->getDebug() : null),
                'error' => get_class($failure).': '.$failure->getMessage(),
            ];
        } else {
            $delivery += [
                'status' => MessageStore::SENT,
                'message_id' => MessageStore::messageId($sent) ?? $this->headerMessageId((string) $row->raw),
                'debug' => MessageStore::hideLogin($sent?->getDebug()),
                'error' => null,
            ];
        }

        $delivery['duration_ms'] = (int) round((hrtime(true) - $start) / 1e6);

        if ($other === '') {
            $this->db->table(MessageStore::TABLE)->where('id', $id)->update($delivery);
            $resultId = $id;
        } else {
            $copy = (array) $row;
            unset($copy['id']);
            $resultId = (int) $this->db->table(MessageStore::TABLE)->insertGetId(array_merge($copy, $delivery, [
                'to_address' => $other,
                'created_at' => Carbon::now('UTC'),
            ]));
        }

        return new JsonResponse([
            'id' => $resultId,
            'status' => $delivery['status'],
            'error' => $delivery['error'],
        ]);
    }

    /** The Message-ID header of the stored email, if any. */
    private function headerMessageId(string $raw): ?string
    {
        $headers = strstr($raw, "\r\n\r\n", true) ?: $raw;

        return preg_match('/^Message-ID:\s*<?([^>\r\n]+)>?/im', $headers, $m) ? trim($m[1]) : null;
    }

    /** The real transport behind Mail Catcher. Not available with the "database" driver. */
    private function realTransport(): TransportInterface
    {
        $transport = $this->container->make('symfony.mailer.transport');

        if ($transport instanceof LoggingTransport) {
            return $transport->inner();
        }

        throw new ValidationException(['to' => 'There is no real mail driver to send with. Choose one in Email settings.']);
    }

    /**
     * Parse a stored list like `"Name" <a@b.c>, d@e.f` into addresses.
     *
     * @return Address[]
     */
    private function addresses(string $list): array
    {
        preg_match_all('/(?:"(?:[^"\\\\]|\\\\.)*"\s*)?<[^>]+>|[^\s,<>"]+@[^\s,<>"]+/', $list, $m);

        $out = [];
        foreach ($m[0] as $part) {
            try {
                $out[] = Address::create(trim($part));
            } catch (\Throwable) {
                // skip anything that isn't a usable address
            }
        }

        return $out;
    }
}
