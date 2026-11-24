<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTableIfNotExists('mail_catcher_messages', function (Blueprint $table) {
    $table->increments('id');
    $table->string('from_address', 255)->nullable();
    $table->text('to_address')->nullable();
    $table->text('subject')->nullable();
    $table->mediumText('html_body')->nullable();
    $table->mediumText('text_body')->nullable();
    $table->mediumText('raw')->nullable();
    $table->dateTime('created_at')->index();
});
