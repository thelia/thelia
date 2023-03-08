<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Thelia\Api\Bridge\Propel\Filter;

use ApiPlatform\Api\IdentifiersExtractorInterface;
use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\ProductQuery;

final class LocalizedSearchFilter extends AbstractSearchFilter implements FilterInterface
{
    public function __construct(
        private RequestStack $requestStack,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor = null,
        LoggerInterface $logger = null,
        array $properties = null,
        IdentifiersExtractorInterface $identifiersExtractor = null,
        NameConverterInterface $nameConverter = null
    ) {
        parent::__construct(
            iriConverter: $iriConverter,
            propertyAccessor: $propertyAccessor,
            logger: $logger,
            properties: $properties,
            identifiersExtractor: $identifiersExtractor,
            nameConverter: $nameConverter
        );
    }
    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $request = $this->requestStack->getMainRequest();
        $locale = $request->get('locale') ?? Lang::getDefaultLanguage()->getLocale();

        if (
            null === $value ||
            !$this->isPropertyEnabled($property, $resourceClass)
        ) {
            return;
        }

        $field = $property;

        $values = $this->normalizeValues((array) $value, $property);

        if (null === $values) {
            return;
        }




        $associations = [];
//        if ($this->isPropertyNested($property, $resourceClass)) {
//            [$alias, $field, $associations] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
//        }

        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;

//        $metadata = $this->getNestedMetadata($resourceClass, $associations);
//
//        if ($metadata->hasField($field)) {
//            $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $alias, $field, $values, $caseSensitive);
//
//            return;
//        }
//
//        // metadata doesn't have the field, nor an association on the field
//        if (!$metadata->hasAssociation($field)) {
//            return;
//        }

        $this->addWhereByStrategy(
            strategy: $strategy,
            query:  $query,
            field: $field,
            values:  $values,
            locale: $locale
        );

        return;

//        $values = array_map($this->getIdFromValue(...), $values);
//
//        $associationResourceClass = $metadata->getAssociationTargetClass($field);
//        $associationFieldIdentifier = $metadata->getIdentifierFieldNames()[0];
//        $doctrineTypeField = $this->getDoctrineFieldType($associationFieldIdentifier, $associationResourceClass);
//
//        if (!$this->hasValidValues($values, $doctrineTypeField)) {
//            $this->logger->notice('Invalid filter ignored', [
//                'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the doctrine type.', $field)),
//            ]);
//
//            return;
//        }
//
//        $associationAlias = $alias;
//        $associationField = $field;
//        if ($metadata->isCollectionValuedAssociation($associationField) || $metadata->isAssociationInverseSide($field)) {
//            $associationAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $alias, $associationField);
//            $associationField = $associationFieldIdentifier;
//        }
//
        $this->addWhereByStrategy($strategy, $query, $values);
    }

    /**
     * Adds where clause according to the strategy.
     *
     * @throws InvalidArgumentException If strategy does not exist
     */
    protected function addWhereByStrategy(string $strategy, ModelCriteria $query, string $field, mixed $values, string $locale): void
    {
        if (!\is_array($values)) {
            $values = [$values];
        }

        $fieldPath = $query->getTableMap()->getName().'_lang_'.$locale.'.'.$field;

        if (!$strategy || self::STRATEGY_EXACT === $strategy) {
            $query->addUsingOperator(
                $fieldPath,
                1 === \count($values) ? $values[0]: $values,
                1 === \count($values) ? Criteria::EQUAL: Criteria::IN
            );

            return;
        }

        $conditions = [];

        foreach ($values as $key => $value) {
            $conditionName = "cond_" . $key;
            switch ($strategy) {
                case self::STRATEGY_PARTIAL:
                    $query->addCond($conditionName, $fieldPath, '%' . $value . '%', Criteria::LIKE);
                    break;
                case self::STRATEGY_START:
                    $query->addCond($conditionName, $fieldPath, $value . '%', Criteria::LIKE);
                    break;
                case self::STRATEGY_END:
                    $query->addCond($conditionName, $fieldPath, '%' . $value, Criteria::LIKE);
                    break;
                case self::STRATEGY_WORD_START:
                    $query->addCond('first_world', $fieldPath,$value . '%', Criteria::LIKE);
                    $query->addCond('other_worlds', $fieldPath,'% ' . $value . '%', Criteria::LIKE);
                    $query->combine(['first_world', 'other_worlds'], Criteria::LOGICAL_OR, $conditionName);
                    break;
                default:
                    continue 2;
            }

            $conditions[] = $conditionName;
        }


        $query->combine($conditions, Criteria::LOGICAL_OR);




//            $keyValueParameter = sprintf('%s_%s', $valueParameter, $key);
//            $parameters[] = [$caseSensitive ? $value : strtolower($value), $keyValueParameter];
//
//            $ors[] = match ($strategy) {
//                self::STRATEGY_PARTIAL => $queryBuilder->expr()->like(
//                    $wrapCase($aliasedField),
//                    $wrapCase((string) $queryBuilder->expr()->concat("'%'", $keyValueParameter, "'%'"))
//                ),
//                self::STRATEGY_START => $queryBuilder->expr()->like(
//                    $wrapCase($aliasedField),
//                    $wrapCase((string) $queryBuilder->expr()->concat($keyValueParameter, "'%'"))
//                ),
//                self::STRATEGY_END => $queryBuilder->expr()->like(
//                    $wrapCase($aliasedField),
//                    $wrapCase((string) $queryBuilder->expr()->concat("'%'", $keyValueParameter))
//                ),
//                self::STRATEGY_WORD_START => $queryBuilder->expr()->orX(
//                    $queryBuilder->expr()->like(
//                        $wrapCase($aliasedField),
//                        $wrapCase((string) $queryBuilder->expr()->concat($keyValueParameter, "'%'"))
//                    ),
//                    $queryBuilder->expr()->like(
//                        $wrapCase($aliasedField),
//                        $wrapCase((string) $queryBuilder->expr()->concat("'% '", $keyValueParameter, "'%'"))
//                    )
//                ),
//                default => throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy)),
//            };
//        }
//
//        $queryBuilder->andWhere($queryBuilder->expr()->orX(...$ors));
//        foreach ($parameters as $parameter) {
//            $queryBuilder->setParameter($parameter[1], $parameter[0]);
//        }
    }


    public function getDescription(string $resourceClass): array
    {
        $filterProperties = $this->getProperties();
        if (null === $filterProperties) {
            return [];
        }

        if (!is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            return [];
        }

        return $this->getSearchDescription($resourceClass::getI18nResourceClass(), true);
    }
}
