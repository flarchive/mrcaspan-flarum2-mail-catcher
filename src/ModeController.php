<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/mail-catcher/mode  {"mode": "off"|"catch"|"log"|"forward", "days": 1-7, "to": "for forward"}
 */
class ModeController implements RequestHandlerInterface
{
    public function __construct(
        protected CaptureMode $mode
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = (array) $request->getParsedBody();
        $mode = Arr::get($body, 'mode');
        $days = (int) Arr::get($body, 'days', 1);
        $to = trim((string) Arr::get($body, 'to', ''));

        if (! in_array($mode, [CaptureMode::OFF, CaptureMode::CATCH, CaptureMode::LOG, CaptureMode::FORWARD], true)) {
            throw new ValidationException(['mode' => 'Unknown mode.']);
        }

        if ($mode === CaptureMode::LOG && ($days < 1 || $days > CaptureMode::MAX_DAYS)) {
            throw new ValidationException(['days' => 'Days must be between 1 and '.CaptureMode::MAX_DAYS.'.']);
        }

        if ($mode === CaptureMode::FORWARD && ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['to' => 'Enter a valid email address to forward to.']);
        }

        $this->mode->set($mode, $days, $to ?: null);

        return new JsonResponse($this->mode->toArray());
    }
}
