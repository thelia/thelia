<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

final class EagerLoadingExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly int $maxJoins = 30
    )
    {

    }
    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $options = [];

        if (!isset($context['groups']) && !isset($context['attributes'])) {
            $contextType = isset($context['api_denormalize']) ? 'denormalization_context' : 'normalization_context';
            if ($operation) {
                $context += 'denormalization_context' === $contextType ? ($operation->getDenormalizationContext() ?? []) : ($operation->getNormalizationContext() ?? []);
            }
        }

        if (empty($context[AbstractNormalizer::GROUPS]) && !isset($context[AbstractNormalizer::ATTRIBUTES])) {
            return;
        }

        if (!empty($context[AbstractNormalizer::GROUPS])) {
            $options['serializer_groups'] = (array) $context[AbstractNormalizer::GROUPS];
        }

        if ($operation && $normalizationGroups = $operation->getNormalizationContext()['groups'] ?? null) {
            $options['normalization_groups'] = $normalizationGroups;
        }

        if ($operation && $denormalizationGroups = $operation->getDenormalizationContext()['groups'] ?? null) {
            $options['denormalization_groups'] = $denormalizationGroups;
        }

        $this->joinRelations($query, $resourceClass, $operation, $context, $options);
    }

    private function joinRelations(
        ModelCriteria $query,
        string $resourceClass,
        Operation $operation = null,
        array $context = [],
        array $options = [],
        bool $wasLeftJoin = false,
        int &$joinCount = 0,
        int $currentDepth = null,
        string $parentClass = null,
        \ReflectionClass $parentReflector = null
    ) {
        if ($joinCount > $this->maxJoins) {
            throw new RuntimeException('The total number of joined relations has exceeded the specified maximum. Raise the limit if necessary with the "api_platform.eager_loading.max_joins" configuration key (https://api-platform.com/docs/core/performance/#eager-loading), or limit the maximum serialization depth using the "enable_max_depth" option of the Symfony serializer (https://symfony.com/doc/current/components/serializer.html#handling-serialization-depth).');
        }

        $reflector = new \ReflectionClass($resourceClass);

        if (is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            $langs = LangQuery::create()->filterByActive(1)->find();
            $i18nResource = new ($resourceClass::getI18nResourceClass());

            if (!$i18nResource instanceof I18n) {
                throw new RuntimeException($i18nResource::class.' should extend '.I18n::class.' to be used as i18n resource');
            }

            $i18nFields = array_map(
                function (\ReflectionProperty $reflectionProperty) {
                    return $reflectionProperty->getName();
                },
                (new \ReflectionClass($i18nResource))->getProperties()
            );

            $joinMethodName = 'join'.$reflector->getShortName().'I18n';
            foreach ($langs as $lang) {
                $joinAlias = ltrim(strtolower($parentReflector?->getShortName().'_'.$reflector->getShortName()).'_'.'lang_'.$lang->getLocale(), '_');
                $query->$joinMethodName($joinAlias);
                $query->addJoinCondition($joinAlias, $joinAlias.'.locale = ?', $lang->getLocale(), null, \PDO::PARAM_STR);

                foreach ($i18nFields as $i18nField) {
                    $query->withColumn($joinAlias.'.'.$i18nField, $joinAlias.'_'.$i18nField);
                }
            }
        }

        foreach ($reflector->getProperties() as $property) {
            $groupAttributes = $property->getAttributes(Groups::class)[0]?? null;

            if (null === $groupAttributes) {
                continue;
            }

            $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

            if (empty(array_intersect($propertyGroups, $context['groups']))) {
                continue;
            }

            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                $targetClass = $relationAttribute->getArguments()['targetResource'];

                if ($parentClass === $targetClass) {
                    continue;
                }

                $targetReflector = new \ReflectionClass($targetClass);
                $isNullable = $property->getType()->allowsNull();
                $isLeftJoin = false !== $wasLeftJoin || true === $isNullable;
                $joinFunctionName = 'use'.ucfirst($targetReflector->getShortName()).'Query';
                if (!method_exists($query, $joinFunctionName)) {
                    continue;
                }

                ++$joinCount;
                /** @var ModelCriteria $relationQuery */
                $relationQuery = $query->$joinFunctionName(null, $isLeftJoin ? Criteria::LEFT_JOIN : Criteria::INNER_JOIN);

                // Avoid recursive joins for self-referencing relations
                if ($targetClass === $resourceClass) {
                    $relationQuery->endUse();
                    continue;
                }

                $this->joinRelations(
                    query: $relationQuery,
                    resourceClass: $targetClass,
                    operation: $operation,
                    context: $context,
                    options: $options,
                    wasLeftJoin:  $isLeftJoin,
                    joinCount: $joinCount,
                    currentDepth: $currentDepth,
                    parentClass: $resourceClass,
                    parentReflector: $reflector
                );
                $relationQuery->endUse();
            }
        }
    }
}
