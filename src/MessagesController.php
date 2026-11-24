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
 * Admin-only API for caught emails.
 *   GET    /api/mail-catcher        list (no bodies, newest 500)
 *   GET    /api/mail-catcher/{id}   one email with bodies
 *   DELETE /api/mail-catcher/{id}   delete one
 *   DELETE /api/mail-catcher        delete all
 */
class MessagesController implements RequestHandlerInterface
{
    public function __construct(
        protected ConnectionInterface $db
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = Arr::get((array) $request->getAttribute('routeParameters'), 'id')
            ?? Arr::get($request->getQueryParams(), 'id');
        $table = fn () => $this->db->table(DatabaseTransport::TABLE);

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
                'created_at' => $this->date($row->created_at),
            ]);
        }

        $rows = $table()
            ->select('id', 'to_address', 'subject', 'created_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return new JsonResponse([
            'total' => $table()->count(),
            'data' => $rows->map(fn ($r) => [
                'id' => (int) $r->id,
                'to' => $r->to_address,
                'subject' => $r->subject,
                'created_at' => $this->date($r->created_at),
            ])->all(),
        ]);
    }

    private function date($value): ?string
    {
        return $value ? Carbon::parse($value, 'UTC')->toIso8601String() : null;
    }
}
