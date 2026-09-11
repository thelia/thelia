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
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;

/**
 * Reads the relations of a whole collection at once.
 *
 * {@see \Thelia\Api\Bridge\Propel\Extension\EagerLoadingExtension} joins what a
 * query can join: the many-to-one relations of the resource being read, and its
 * translations. It stops at the first collection, because joining one product
 * with three sale elements would come back as three product rows and make the
 * page count wrong. The query therefore returns the parents alone and the
 * transformer asks each parent for its children, one query per row.
 *
 * Underneath that collection nothing is joined at all, so each child in turn
 * paid a query for its own relations and one per language for their
 * translations: a product carrying twelve characteristics spent forty-eight
 * queries on the features and the values behind them.
 *
 * Propel reads the same rows for a whole collection in one statement per
 * relation, which is what this does, before the first parent is transformed.
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
     * @param bool                                  $joinedByTheQuery whether the query that returned these rows
     *                                                                joined their many-to-one relations and their
     *                                                                translations, which holds for the collection a
     *                                                                provider hands over and never for one reached
     *                                                                underneath it
     */
    public function preload(
        ObjectCollection $models,
        string $resourceClass,
        array $context,
        bool $joinedByTheQuery = true,
    ): void {
        // populateRelation() hands each row back to its relatives through the
        // instance pool. Without it Propel refuses the call, and reading the
        // relation row by row stays the only way.
        if (!Propel::isInstancePoolingEnabled()) {
            return;
        }

        $this->walk($models, $resourceClass, $context, [], 0, $joinedByTheQuery);
    }

    /**
     * @param class-string<PropelResourceInterface> $resourceClass
     * @param array<class-string, true>             $visited
     */
    private function walk(
        ObjectCollection $models,
        string $resourceClass,
        array $context,
        array $visited,
        int $depth,
        bool $joinedByTheQuery,
    ): void {
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

        if (!$joinedByTheQuery) {
            $this->preloadTranslations($models, $tableMap, $resourceClass, $reflector, $context);
        }

        foreach ($reflector->getProperties() as $property) {
            $relationAttribute = $property->getAttributes(Relation::class)[0] ?? null;

            if (null === $relationAttribute) {
                continue;
            }

            $isCollectionRelation = 'array' === $property->getType()?->getName();

            // The eager loading extension joins the many-to-one ends of the rows
            // the query returned, but it only adds their columns: nothing reads
            // those back, so the object is never hydrated and the transformer
            // pays a query per row. The instance pool covers that while the rows
            // point at the same target, which is why it goes unnoticed; a
            // relation that says otherwise is read here for the whole page.
            if (!$isCollectionRelation && $joinedByTheQuery && !$this->asksToBePreloaded($relationAttribute)) {
                continue;
            }

            if (!$this->transformerService->shouldHydrateRelation($property, $reflector, $context)) {
                continue;
            }

            $targetClass = $relationAttribute->getArguments()['targetResource'] ?? null;

            // A relation pointing back at a resource already walked is the way
            // back up the branch, and those rows are in hand: reading them again
            // would cost a query to answer with what the caller passed in.
            if (\is_string($targetClass) && isset($visited[$targetClass])) {
                continue;
            }

            $relationName = $this->relationNameBehind(
                $tableMap,
                $this->transformerService->resolvePropelGetterName($property),
                $isCollectionRelation,
            );

            if (null === $relationName) {
                continue;
            }

            $related = $models->populateRelation($relationName);

            if (!$related instanceof ObjectCollection) {
                continue;
            }

            if (\is_string($targetClass) && is_subclass_of($targetClass, PropelResourceInterface::class)) {
                $this->walk($related, $targetClass, $context, $visited, $depth + 1, joinedByTheQuery: false);
            }
        }
    }

    private function asksToBePreloaded(\ReflectionAttribute $relationAttribute): bool
    {
        return true === ($relationAttribute->getArguments()['preload'] ?? false);
    }

    /**
     * A translated model answers getTranslation() out of the translations it
     * already holds before it queries for one, so reading them for the whole
     * collection covers every language of every row in one statement.
     *
     * @param class-string<PropelResourceInterface> $resourceClass
     */
    private function preloadTranslations(
        ObjectCollection $models,
        TableMap $tableMap,
        string $resourceClass,
        \ReflectionClass $reflector,
        array $context,
    ): void {
        if (!is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            return;
        }

        if (!$this->returnsTranslations($reflector, $context)) {
            return;
        }

        $relationName = $tableMap->getPhpName().'I18n';

        if (!$tableMap->hasRelation($relationName)) {
            return;
        }

        $models->populateRelation($relationName);
    }

    /**
     * The gate {@see \Thelia\Api\Bridge\Propel\Extension\EagerLoadingExtension}
     * applies before joining the translations of the resource it reads.
     */
    private function returnsTranslations(\ReflectionClass $reflector, array $context): bool
    {
        if (!isset($context['groups']) || !$reflector->hasProperty('i18ns')) {
            return true;
        }

        $groupsAttribute = $reflector->getProperty('i18ns')
            ->getAttributes(Groups::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if (null === $groupsAttribute) {
            return false;
        }

        $arguments = $groupsAttribute->getArguments();
        $groups = $arguments['groups'] ?? $arguments[0] ?? [];

        return [] !== array_intersect((array) $groups, (array) $context['groups']);
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
    private function relationNameBehind(TableMap $tableMap, string $getterName, bool $isCollectionRelation): ?string
    {
        $wantedType = $isCollectionRelation ? RelationMap::ONE_TO_MANY : RelationMap::MANY_TO_ONE;

        foreach ($tableMap->getRelations() as $relation) {
            if ($wantedType !== $relation->getType()) {
                continue;
            }

            $name = $isCollectionRelation ? $relation->getPluralName() : $relation->getName();

            if ('get'.$name === $getterName) {
                return $relation->getName();
            }
        }

        return null;
    }
}
