<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasColumn('mail_catcher_messages', 'status')) {
            return;
        }

        $schema->table('mail_catcher_messages', function (Blueprint $table) {
            $table->string('status', 10)->default('caught')->index();
            $table->string('transport', 255)->nullable();
            $table->string('message_id', 255)->nullable();
            $table->text('error')->nullable();
            $table->mediumText('debug')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
        });
    },

    'down' => function (Builder $schema) {
        if (! $schema->hasColumn('mail_catcher_messages', 'status')) {
            return;
        }

        $schema->table('mail_catcher_messages', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'transport', 'message_id', 'error', 'debug', 'duration_ms']);
        });
    },
];
