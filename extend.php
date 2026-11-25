<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Flarum\Extend;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // Shows up as "database" in Admin > Email > Mail Driver.
    (new Extend\Mail())
        ->driver('database', DatabaseDriver::class),

    // Wraps the real mail transport for the Catch / Log modes.
    (new Extend\ServiceProvider())
        ->register(MailCatcherServiceProvider::class),

    // Admin-only API used by the extension's admin page.
    (new Extend\Routes('api'))
        ->post('/mail-catcher/mode', 'mail-catcher.mode', ModeController::class)
        ->post('/mail-catcher/{id:[0-9]+}/release', 'mail-catcher.release', ReleaseController::class)
        ->get('/mail-catcher', 'mail-catcher.index', MessagesController::class)
        ->get('/mail-catcher/{id:[0-9]+}', 'mail-catcher.show', MessagesController::class)
        ->delete('/mail-catcher/{id:[0-9]+}', 'mail-catcher.delete', MessagesController::class)
        ->delete('/mail-catcher', 'mail-catcher.clear', MessagesController::class),
];
