<?php

/*
 * This file is part of mrcaspan/flarum2-mail-catcher.
 *
 * For license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace MrCaspan\MailCatcher;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Database\ConnectionInterface;

/**
 * The capture mode chosen at the top of the admin page:
 *   off     - mail is sent normally, nothing is stored
 *   catch   - mail is stored and NOT sent
 *   log     - mail is sent AND stored, until log_until (max 7 days)
 *   forward - mail is sent ONLY to forward_to (not to its recipients), nothing is stored
 */
class CaptureMode
{
    public const OFF = 'off';
    public const CATCH = 'catch';
    public const LOG = 'log';
    public const FORWARD = 'forward';

    public const MAX_DAYS = 7;

    public const MODE_KEY = 'mrcaspan-flarum2-mail-catcher.mode';
    public const UNTIL_KEY = 'mrcaspan-flarum2-mail-catcher.log_until';
    public const FORWARD_KEY = 'mrcaspan-flarum2-mail-catcher.forward_to';

    public function __construct(
        protected ConnectionInterface $db,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Read straight from the settings table (not the cached repository) so a
     * change or an expiry is picked up even by a long-running queue worker.
     *
     * @return array{mode: string, until: ?Carbon, forwardTo: ?string}
     */
    public function current(): array
    {
        $values = $this->db->table('settings')
            ->whereIn('key', [self::MODE_KEY, self::UNTIL_KEY, self::FORWARD_KEY])
            ->pluck('value', 'key');

        $mode = $values[self::MODE_KEY] ?? self::OFF;
        $until = ! empty($values[self::UNTIL_KEY]) ? Carbon::parse($values[self::UNTIL_KEY], 'UTC') : null;
        $forwardTo = $values[self::FORWARD_KEY] ?? null;
        $forwardTo = $forwardTo && filter_var($forwardTo, FILTER_VALIDATE_EMAIL) ? $forwardTo : null;

        $state = ['mode' => self::OFF, 'until' => null, 'forwardTo' => $forwardTo];

        if ($mode === self::CATCH) {
            $state['mode'] = self::CATCH;
        } elseif ($mode === self::LOG && $until && $until->isFuture()) {
            $state['mode'] = self::LOG;
            $state['until'] = $until;
        } elseif ($mode === self::FORWARD && $forwardTo) {
            $state['mode'] = self::FORWARD;
        }

        return $state;
    }

    public function set(string $mode, int $days = 1, ?string $forwardTo = null): void
    {
        $this->settings->set(self::MODE_KEY, $mode);
        $this->settings->set(
            self::UNTIL_KEY,
            $mode === self::LOG ? Carbon::now('UTC')->addDays($days)->toIso8601String() : ''
        );

        // Keep the last forward address so the Forward modal can suggest it again.
        if ($mode === self::FORWARD && $forwardTo) {
            $this->settings->set(self::FORWARD_KEY, $forwardTo);
        }
    }

    public function toArray(): array
    {
        $state = $this->current();

        return [
            'mode' => $state['mode'],
            'until' => $state['until']?->toIso8601String(),
            'forwardTo' => $state['forwardTo'],
            'maxDays' => self::MAX_DAYS,
        ];
    }
}
