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

namespace Thelia\Api\Bridge\Propel\Service;

use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\PropelResourceInterface;

/**
 * Reads the to-many relations of a whole collection at once.
 *
 * A collection cannot join them: one product with three sale elements would
 * come back as three product rows and the page count would be wrong. So the
 * query returns the parents alone, and the transformer then asks each parent
 * for its children, one query per row — the shape that makes a page cost more
 * as the catalogue grows. Propel reads the same rows for the whole collection
 * in one statement per relation, which is what this does, before the first
 * parent is transformed.
 */
final readonly class PropelRelationPreloader
{
    public function __construct(
        private ApiResourcePropelTransformerService $transformerService,
        private int $maxDepth = 5,
    ) {
    }

    /**
     * @param class-string<PropelResourceInterface> $resourceClass
     */
    public function preload(ObjectCollection $models, string $resourceClass, array $context): void
    {
        // populateRelation() hands each child back to its parent through the
        // instance pool. Without it Propel refuses the call, and reading the
        // relation row by row stays the only way.
        if (!Propel::isInstancePoolingEnabled()) {
            return;
        }

        $this->walk($models, $resourceClass, $context, [], 0);
    }

    /**
     * @param class-string<PropelResourceInterface> $resourceClass
     * @param array<class-string, true>             $visited
     */
    private function walk(ObjectCollection $models, string $resourceClass, array $context, array $visited, int $depth): void
    {
        if ($models->isEmpty() || $depth >= $this->maxDepth || isset($visited[$resourceClass])) {
            return;
        }

        $tableMap = $this->runtimeTableMap($resourceClass);

        // A collection names its model the way its formatter was built, short
        // name included, so the rows themselves say whether the table map of
        // the resource is the one that describes them.
        if (!$tableMap instanceof TableMap || !is_a($models->getFirst(), $tableMap->getClassName())) {
            return;
        }

        $visited[$resourceClass] = true;
        $reflector = new \ReflectionClass($resourceClass);

        foreach ($reflector->getProperties() as $property) {
            $relationAttribute = $property->getAttributes(Relation::class)[0] ?? null;

            // Many-to-one relations are already joined by the eager loading
            // extension, which only leaves out the ones that would duplicate rows.
            if (null === $relationAttribute || 'array' !== $property->getType()?->getName()) {
                continue;
            }

            if (!$this->transformerService->shouldHydrateRelation($property, $reflector, $context)) {
                continue;
            }

            $relationName = $this->relationNameBehind($tableMap, $this->transformerService->resolvePropelGetterName($property));

            if (null === $relationName) {
                continue;
            }

            $related = $models->populateRelation($relationName);

            if (!$related instanceof ObjectCollection) {
                continue;
            }

            $targetClass = $relationAttribute->getArguments()['targetResource'] ?? null;

            if (\is_string($targetClass) && is_subclass_of($targetClass, PropelResourceInterface::class)) {
                $this->walk($related, $targetClass, $context, $visited, $depth + 1);
            }
        }
    }

    /**
     * A resource hands over a table map it builds itself, outside the database
     * map: it knows its own columns, but naming a relation means reaching the
     * table at the other end, which only the registered one can do.
     *
     * @param class-string<PropelResourceInterface> $resourceClass
     */
    private function runtimeTableMap(string $resourceClass): ?TableMap
    {
        $tableMap = $resourceClass::getPropelRelatedTableMap();

        if (!$tableMap instanceof TableMap || !\defined($tableMap::class.'::DATABASE_NAME')) {
            return null;
        }

        return Propel::getServiceContainer()
            ->getDatabaseMap((string) \constant($tableMap::class.'::DATABASE_NAME'))
            ->getTableByPhpName($tableMap->getClassName());
    }

    /**
     * A table reachable through several foreign keys carries one relation per
     * key, and only the one the resource reads may be populated: the getter
     * name is what tells them apart.
     */
    private function relationNameBehind(TableMap $tableMap, string $getterName): ?string
    {
        foreach ($tableMap->getRelations() as $relation) {
            if (RelationMap::ONE_TO_MANY !== $relation->getType()) {
                continue;
            }

            if ('get'.$relation->getPluralName() === $getterName) {
                return $relation->getName();
            }
        }

        return null;
    }
}
