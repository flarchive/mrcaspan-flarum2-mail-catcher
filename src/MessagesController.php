<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Carbon\Carbon;
use Flarum\Http\RequestUtil;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin-only API for captured emails.
 *   GET    /api/mail-catcher        list (no bodies, newest 500) + statistics + current mode
 *   GET    /api/mail-catcher/{id}   one email with bodies and delivery details
 *   DELETE /api/mail-catcher/{id}   delete one
 *   DELETE /api/mail-catcher        delete all
 */
class MessagesController implements RequestHandlerInterface
{
    public function __construct(
        protected ConnectionInterface $db,
        protected CaptureMode $mode
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = Arr::get((array) $request->getAttribute('routeParameters'), 'id')
            ?? Arr::get($request->getQueryParams(), 'id');
        $table = fn () => $this->db->table(MessageStore::TABLE);

        if ($request->getMethod() === 'DELETE') {
            $deleted = $id ? $table()->where('id', (int) $id)->delete() : $table()->delete();

            return new JsonResponse(['deleted' => $deleted]);
        }

        if ($id) {
            $row = $table()->where('id', (int) $id)->first();

            if (! $row) {
                throw new ModelNotFoundException;
            }

            return new JsonResponse([
                'id' => (int) $row->id,
                'from' => $row->from_address,
                'to' => $row->to_address,
                'subject' => $row->subject,
                'html' => $row->html_body,
                'text' => $row->text_body,
                'raw' => $row->raw,
                'status' => $row->status,
                'transport' => $row->transport,
                'message_id' => $row->message_id,
                'error' => $row->error,
                'debug' => $row->debug,
                'duration_ms' => $row->duration_ms === null ? null : (int) $row->duration_ms,
                'created_at' => $this->date($row->created_at),
            ]);
        }

        $rows = $table()
            ->select('id', 'to_address', 'subject', 'status', 'created_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        $counts = $table()
            ->select('status', $this->db->raw('COUNT(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status');

        $stats = [
            MessageStore::SENT => (int) ($counts[MessageStore::SENT] ?? 0),
            MessageStore::FAILED => (int) ($counts[MessageStore::FAILED] ?? 0),
            MessageStore::CAUGHT => (int) ($counts[MessageStore::CAUGHT] ?? 0),
        ];

        return new JsonResponse([
            'total' => (int) $counts->sum(),
            'stats' => $stats,
            'mode' => $this->mode->toArray(),
            'data' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'to' => $r->to_address,
                'subject' => $r->subject,
                'status' => $r->status,
                'created_at' => $this->date($r->created_at),
            ])->all(),
        ]);
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value, 'UTC')->toIso8601String() : null;
    }
}
