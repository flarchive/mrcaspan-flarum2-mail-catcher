<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

/*
 * Versions before 1.3 caught email with their own "database" mail driver, which
 * has been replaced by Catch mode. A forum still set to it would otherwise fall
 * back to Flarum's "null" driver and silently drop every email, so switch it to
 * Flarum's "log" driver (never emails anyone) with Catch mode on: email keeps
 * being caught exactly as before.
 */
return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();

        if ($db->table('settings')->where('key', 'mail_driver')->value('value') !== 'database') {
            return;
        }

        $db->table('settings')->where('key', 'mail_driver')->update(['value' => 'log']);
        $db->table('settings')->updateOrInsert(
            ['key' => 'mrcaspan-flarum2-mail-catcher.mode'],
            ['value' => 'catch']
        );
    },

    'down' => function (Builder $schema) {
        // Nothing to undo: the "database" driver no longer exists.
    },
];
