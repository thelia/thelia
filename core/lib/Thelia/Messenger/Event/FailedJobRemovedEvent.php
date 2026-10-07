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

namespace Thelia\Messenger\Event;

/**
 * A failed job an administrator deleted: it will never be replayed, so what was kept
 * for its replay (the uploaded file of an import) can go.
 */
final readonly class FailedJobRemovedEvent
{
    public function __construct(
        public object $message,
    ) {
    }
}
