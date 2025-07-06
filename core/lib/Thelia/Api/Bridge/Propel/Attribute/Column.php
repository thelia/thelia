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

namespace Thelia\Api\Bridge\Propel\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        private readonly ?string $propelFieldName = null,
        private readonly ?string $propelSetter = null,
        private readonly ?string $propelGetter = null,
        private readonly ?string $propelQueryFilter = null,
    ) {
    }
}
