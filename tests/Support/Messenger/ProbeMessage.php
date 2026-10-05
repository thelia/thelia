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

namespace Thelia\Tests\Support\Messenger;

/**
 * A job carrying nothing but a label, to put through a real queue in a test.
 */
final readonly class ProbeMessage
{
    public function __construct(
        public string $label,
    ) {
    }
}
