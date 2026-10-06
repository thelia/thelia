<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Messenger\Message;

/**
 * A message that carries some state of its own runs (how often it was looked at
 * again): replayed by an administrator, it starts afresh.
 *
 * The back office replays a failed job with {@see forReplay()}, and so does
 * `messenger:failed:retry` through {@see \Thelia\Messenger\Middleware\ReplayedJobMiddleware}.
 */
interface ReplayableJob
{
    public function forReplay(): static;
}
