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

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Metadata\FilterInterface as BaseFilterInterface;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface FilterInterface extends BaseFilterInterface
{
    public function apply(ModelCriteria $query, string $resourceClass, ?Operation $operation = null, array $context = []): void;
}
