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

namespace Thelia\Tests\Unit\Core\SharedCache;

use Psr\Log\AbstractLogger;

final class TestLogger extends AbstractLogger
{
    /** @var list<array{string, string}> */
    public array $records = [];

    /** @var list<array<string, mixed>> */
    public array $contexts = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [(string) $level, (string) $message];
        $this->contexts[] = $context;
    }
}
