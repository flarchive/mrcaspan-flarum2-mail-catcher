<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Flarum\Foundation\AbstractServiceProvider;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Wraps whatever transport the configured mail driver builds in a LoggingTransport,
 * so the Catch / Log modes work with any driver.
 */
class MailCatcherServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->extend('symfony.mailer.transport', function (TransportInterface $transport, Container $container) {
            return new LoggingTransport(
                $transport,
                $container->make(CaptureMode::class),
                $container->make(MessageStore::class),
                $container->make(LoggerInterface::class)
            );
        });
    }
}
