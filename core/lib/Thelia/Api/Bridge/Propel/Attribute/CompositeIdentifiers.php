<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Api\Bridge\Propel\Attribute;

use Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class CompositeIdentifiers
{
    public function __construct(
        private array $keys
    ) {
    }
}
