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
class Relation
{
    /**
     * @param bool $hydrateOutOfGroups reads the relation even when no group of the
     *                                 current context serializes it. Only a resource
     *                                 computing another of its fields from that
     *                                 relation needs it, and it costs a query per row.
     * @param bool $preload            reads this many-to-one end for the whole page at
     *                                 once. The eager loading extension joins it but
     *                                 never hydrates it, so the transformer asks the
     *                                 getter and pays a query per row; the instance
     *                                 pool hides that only while the rows point at the
     *                                 same target. A relation whose target changes from
     *                                 one row to the next asks for it here.
     */
    public function __construct(
        private readonly string $targetResource,
        private readonly ?string $relationAlias = null,
        private readonly ?array $propertyGroups = [],
        private readonly ?bool $forceJoin = null,
        private readonly ?array $excludedGroups = [],
        private readonly bool $hydrateOutOfGroups = false,
        private readonly bool $preload = false,
    ) {
    }
}
