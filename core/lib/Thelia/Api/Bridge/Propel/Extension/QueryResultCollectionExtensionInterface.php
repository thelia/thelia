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
namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;

interface QueryResultCollectionExtensionInterface extends QueryCollectionExtensionInterface
{
    public function supportsResult(string $resourceClass, Operation $operation = null, array $context = []): bool;

    public function getResult(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []);
}
