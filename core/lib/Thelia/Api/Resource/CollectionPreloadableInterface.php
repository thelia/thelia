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

namespace Thelia\Api\Resource;

/**
 * A resource serving something its own row does not hold, and that a whole page
 * of them can read in one go.
 *
 * {@see \Thelia\Api\Bridge\Propel\Service\PropelRelationPreloader} covers the
 * relations a resource declares, because those are rows Propel knows how to read
 * for a collection. A field answered by a query of the resource's own — a select
 * with a group by, a computed list — is outside that, and the serializer asks for
 * it once per member: the cost of the page grows with its size.
 *
 * A resource implementing this is handed the page it belongs to, once, before the
 * serializer reads any of it, so that the reading is one statement rather than
 * one per member.
 */
interface CollectionPreloadableInterface
{
    /**
     * Fills whatever the serializer is about to ask each of these for.
     *
     * Called with one page of resources of this class, before any of them is
     * serialized. Never called for an item read: one member is already one query.
     *
     * @param list<PropelResourceInterface> $resources
     */
    public static function preloadCollection(array $resources): void;
}
