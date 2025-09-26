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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Bridge\Propel\Event\ModelToResourceEvent;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

readonly class ApiResourcePropelTransformerService
{
    public function __construct(
        private array $apiResourceAddons,
        private ValidatorInterface $validator,
        private EventDispatcherInterface $eventDispatcher,
        private TypeCasterService $typeCasterService,
    ) {
    }

    public function getResourceAddonDefinitions($resourceClass): array
    {
        return $this->apiResourceAddons[$resourceClass] ?? [];
    }

    /**
     * @throws \ReflectionException
     */
    public function modelToResource(
        string $resourceClass,
        ActiveRecordInterface $propelModel,
        array $context,
        ?Collection $langs = null,
        ?\ReflectionClass $parentReflector = null,
        ?ActiveRecordInterface $parentModel = null,
        bool $withRelation = true,
        ?ActiveRecordInterface $baseModel = null,
        bool $withAddon = true,
    ): PropelResourceInterface {
        if (!$langs instanceof Collection) {
            $langs = LangQuery::create()->filterByActive(1)->find();
        }

        // Init internal recursion-control context
        $context['__visited'] ??= [];
        $context['__path'] ??= [];
        $context['__depth'] ??= 0;
        $context['enable_max_depth'] ??= true;
        $context['max_depth'] ??= 10;

        $baseModel ??= $propelModel;

        $modelToResourceEvent = new ModelToResourceEvent($baseModel, $parentModel);
        $this->eventDispatcher->dispatch($modelToResourceEvent, ModelToResourceEvent::BEFORE_TRANSFORM);
        $baseModel = $modelToResourceEvent->getModel();

        /** @var PropelResourceInterface $apiResource */
        $apiResource = new $resourceClass();
        $reflector = new \ReflectionClass($resourceClass);

        $visitKey = $this->makeVisitKey($resourceClass, $propelModel, $context);
        $alreadyVisited = isset($context['__visited'][$visitKey]);
        $shouldDescend = $withRelation && !$alreadyVisited;

        if ($context['enable_max_depth'] && $context['__depth'] >= $context['max_depth']) {
            $shouldDescend = false;
        }

        $context['__visited'][$visitKey] = true;
        $context['__path'][] = $resourceClass;
        ++$context['__depth'];

        $this->processPropertiesRessource(
            apiResource: $apiResource,
            reflector: $reflector,
            parentReflector: $parentReflector,
            propelModel: $propelModel,
            parentModel: $parentModel,
            baseModel: $baseModel,
            context: $context,
            withRelation: $shouldDescend,
            withAddon: $withAddon,
            langs: $langs,
        );

        if (is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            $this->manageTranslatableResource(
                resourceClass: $resourceClass,
                propelModel: $propelModel,
                baseModel: $baseModel,
                apiResource: $apiResource,
                parentReflector: $parentReflector,
                reflector: $reflector,
                context: $context,
                langs: $langs,
            );
        }

        $apiResource->setPropelModel($propelModel);

        if ($withAddon) {
            foreach ($this->getResourceAddonDefinitions($resourceClass) as $addonShortName => $addonClass) {
                if (is_subclass_of($addonClass, ResourceAddonInterface::class)) {
                    $addon = (new $addonClass())->buildFromModel($propelModel, $apiResource);
                    $apiResource->setResourceAddon($addonShortName, $addon);
                }
            }
        }

        $apiResource->afterModelToResource($context);

        $modelToResourceEvent->setResource($apiResource);
        $this->eventDispatcher->dispatch($modelToResourceEvent, ModelToResourceEvent::AFTER_TRANSFORM);

        return $modelToResourceEvent->getResource();
    }

    public function resourceToModel(
        PropelResourceInterface $data,
        Operation $operation,
        array $context = [],
        ?ActiveRecordInterface $previousPropelModel = null,
    ): ActiveRecordInterface {
        $this->validator->validate($data, $operation->getDenormalizationContext());
        $propelModel = $this->initializePropelModel(
            data: $data,
            previousPropelModel: $previousPropelModel,
            operation: $operation,
            context: $context,
        );

        if (method_exists($data, 'getId') && $data->getId()) {
            $propelModel->setNew(false);
        }

        $this->processPropertiesModel(
            data: $data,
            propelModel: $propelModel,
            context: $context,
            operation: $operation,
            previousPropelModel: $previousPropelModel,
        );

        $this->processTranslations(
            data: $data,
            propelModel: $propelModel,
        );

        if ($this->hasCompositeIdentifiersAlready($data, $previousPropelModel)) {
            $propelModel->setNew(false);
        }

        return $propelModel;
    }

    public function getResourceCompositeIdentifierValues(\ReflectionClass $reflector, string $param): array
    {
        $compositeIdentifiersAttribute = $reflector->getAttributes(CompositeIdentifiers::class);

        if ([] === $compositeIdentifiersAttribute) {
            return [];
        }

        if (isset($compositeIdentifiersAttribute[0], $compositeIdentifiersAttribute[0]->getArguments()[0])) {
            return $compositeIdentifiersAttribute[0]->getArguments()[0];
        }

        if (isset($compositeIdentifiersAttribute[0], $compositeIdentifiersAttribute[0]->getArguments()[$param])) {
            return $compositeIdentifiersAttribute[0]->getArguments()[$param];
        }

        return [];
    }

    /**
     * @throws \ReflectionException
     */
    public function getColumnValues(\ReflectionClass $reflector, array $columns): array
    {
        $columnValues = [];

        foreach ($columns as $column) {
            if (isset($reflector->getProperty($column)->getAttributes(Column::class)[0])) {
                $columnValues[$column] = $reflector->getProperty($column)->getAttributes(Column::class)[0]->getArguments();
            }
        }

        return $columnValues;
    }

    private function initializePropelModel(
        PropelResourceInterface $data,
        ?ActiveRecordInterface $previousPropelModel,
        Operation $operation,
        array $context,
    ): ActiveRecordInterface {
        $propelModel = $data->getPropelModel();

        if (!$propelModel instanceof ActiveRecordInterface) {
            $propelTableMap = $data::getPropelRelatedTableMap();
            $modelClassName = $propelTableMap?->getClassName();
            $propelModel = new $modelClassName();
        }

        if (\in_array($operation::class, [Patch::class, Put::class], true)) {
            $request = $context['request'];
            $reflector = new \ReflectionClass($data::class);
            /** @var ModelCriteria $queryClass */
            $queryClass = $data::getPropelRelatedTableMap()->getClassName().'Query';
            /** @var ModelCriteria $query */
            $query = $queryClass::create();
            $compositeIdentifiers = $this->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');
            $columnValues = $this->getColumnValues(reflector: $reflector, columns: $compositeIdentifiers);
            $uriVariables = [];
            $id = null;

            if (!$previousPropelModel && $request->get('id')) {
                $id = $request->get('id');
            }

            if (method_exists($data, 'getId') && $data->getId()) {
                $id = $data->getId();
            }

            if ($id) {
                $uriVariables['id'] = $id;
            }

            foreach ($compositeIdentifiers as $compositeIdentifier) {
                if ($previousPropelModel instanceof ActiveRecordInterface) {
                    $reflectorPreviousPropelModel = new \ReflectionClass($previousPropelModel::class);

                    if (ucfirst((string) $compositeIdentifier) === $reflectorPreviousPropelModel->getShortName()) {
                        $id = $previousPropelModel->getId();
                        $setter = 'set'.ucfirst((string) $compositeIdentifier).'Id';
                        $propelModel->{$setter}($id);

                        // This is a fix related to Propel. It enables database persistence of my entity in its child collections.
                        // It's not very clean, but it's the only workaround I found.
                        $previousPropelId = $previousPropelModel->getId();
                        $previousPropelModel->setId(null);
                        $previousPropelModel->setId($previousPropelId);

                        return $propelModel;
                    }
                }
            }

            if ([] !== $compositeIdentifiers) {
                return $propelModel;
            }

            $this->queryFilterById(uriVariables: $uriVariables, query: $query, columnValues: $columnValues);

            if (null !== $query->findOne() && \count($query->getMap()) > 0) {
                $propelModel = $query->findOne();
            }
        }

        return $propelModel;
    }

    private function processPropertiesModel(
        PropelResourceInterface $data,
        ActiveRecordInterface $propelModel,
        array $context,
        Operation $operation,
        ?ActiveRecordInterface $previousPropelModel,
    ): void {
        $resourceReflection = new \ReflectionClass($data);

        foreach ($resourceReflection->getProperties() as $property) {
            if ('id' === $property->name && !$previousPropelModel) {
                continue;
            }

            $setterForced = false;
            $propelSetter = $this->determinePropelSetterName($property, $setterForced);

            if ($operation instanceof Put && !$property->isInitialized($data)) {
                foreach ($property->getAttributes(Groups::class) as $groupAttribute) {
                    $propertyGroups = null;

                    if (isset($groupAttribute->getArguments()[0])) {
                        $propertyGroups = $groupAttribute->getArguments()[0];
                    }

                    if (isset($groupAttribute->getArguments()['groups'])) {
                        $propertyGroups = $groupAttribute->getArguments()['groups'];
                    }

                    if (!$property) {
                        continue 2;
                    }

                    $contextGroups = $operation->getDenormalizationContext()['groups'];
                    $isInContext = [] !== array_intersect($contextGroups, $propertyGroups);

                    foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                        if ($isInContext && 'array' !== $property->getType()?->getName()) {
                            $propelModel->{$propelSetter}(null);
                            continue 3;
                        }
                    }

                    if ($isInContext && $property->getType()?->isBuiltin()) {
                        $propelModel->{$propelSetter}(null);
                    }
                }

                continue;
            }

            if (!$property->isInitialized($data)) {
                continue;
            }

            if (method_exists($propelModel, $propelSetter)) {
                $value = $this->getPropertyValue($data, $property);
                $value = $this->getRelationValue($value, $propelSetter, $setterForced, $operation);
                $value = $this->getArrayValue($value, $context, $property, $propelModel, $operation);
                $method = new \ReflectionMethod($propelModel::class, $propelSetter);
                $paramNames = array_map(
                    static fn (\ReflectionParameter $param): string => $param->getName(),
                    $method->getParameters(),
                );

                if (\in_array('force', $paramNames, true)) {
                    $propelModel->{$propelSetter}($value, true);
                }

                $propelModel->{$propelSetter}($value);
            }
        }
    }

    private function determinePropelSetterName(
        \ReflectionProperty $property,
        bool &$setterForced = false,
    ): string {
        $propelSetter = 'set'.ucfirst($property->getName());

        foreach ($property->getAttributes(Column::class) as $columnAttribute) {
            if (isset($columnAttribute->getArguments()['propelFieldName'])) {
                $propelSetter = 'set'.ucfirst($columnAttribute->getArguments()['propelFieldName']);
            }

            if (isset($columnAttribute->getArguments()['propelSetter'])) {
                $setterForced = true;
                $propelSetter = $columnAttribute->getArguments()['propelSetter'];
            }
        }

        return $propelSetter;
    }

    private function determinePropelGetterName(
        \ReflectionProperty $property,
        string $attributeClass,
        string $argumentKey,
        string $defaultGetter,
    ): string {
        foreach ($property->getAttributes($attributeClass) as $attribute) {
            if (isset($attribute->getArguments()[$argumentKey])) {
                return 'get'.ucfirst($attribute->getArguments()[$argumentKey]);
            }
        }

        return $defaultGetter;
    }

    private function getPropertyValue(PropelResourceInterface $data, \ReflectionProperty $property): mixed
    {
        $possibleGetters = [
            'get'.ucfirst($property->getName()),
            'is'.ucfirst($property->getName()),
        ];

        $availableMethods = array_filter(array_intersect($possibleGetters, get_class_methods($data)));

        $value = null;

        while ([] !== $availableMethods && null === $value) {
            $method = array_pop($availableMethods);
            $value = $data->{$method}();
        }

        return $value;
    }

    private function getRelationValue(
        mixed $value,
        string &$propelSetter,
        bool $setterForced,
        Operation $operation,
    ): mixed {
        if (\is_object($value) && method_exists($value, 'getPropelModel')) {
            if (!$setterForced) {
                $propelSetter .= 'Id';
            }

            $valuePropelModel = $value->getPropelModel();

            if (null !== $valuePropelModel && !$operation instanceof Patch && method_exists($valuePropelModel, 'getId')) {
                $value = $valuePropelModel->getId();
            }

            // If value is still not transformed
            if (\is_object($value) && method_exists($value, 'getId')) {
                $value = $value->getId();
            }
        }

        return $value;
    }

    private function getArrayValue(
        mixed $value,
        array $context,
        \ReflectionProperty $property,
        ActiveRecordInterface $propelModel,
        Operation $operation,
    ): mixed {
        if (\is_array($value)) {
            $value = new Collection(
                array_map(
                    function ($value, $index) use ($context, $property, $propelModel, $operation): ActiveRecordInterface {
                        try {
                            return $this->resourceToModel(data: $value, operation: $operation, context: $context, previousPropelModel: $propelModel);
                        } catch (ValidationException $validationException) {
                            $constrainViolationList = new ConstraintViolationList(
                                array_map(
                                    static function (ConstraintViolation $violation) use ($property, $index): ConstraintViolation {
                                        $newViolation = new \ReflectionClass($violation);
                                        $newViolation->getProperty('propertyPath')->setValue(
                                            $violation,
                                            $property->getName().'['.$index.'].'.$violation->getPropertyPath(),
                                        );

                                        return $violation;
                                    },
                                    iterator_to_array($validationException->getConstraintViolationList()),
                                ),
                            );

                            throw new ValidationException($constrainViolationList);
                        }
                    },
                    $value,
                    array_keys($value),
                ),
            );
        }

        return $value;
    }

    private function processPropertiesRessource(
        PropelResourceInterface $apiResource,
        \ReflectionClass $reflector,
        ?\ReflectionClass $parentReflector,
        ActiveRecordInterface $propelModel,
        ?ActiveRecordInterface $parentModel,
        ActiveRecordInterface $baseModel,
        array $context,
        bool $withRelation,
        bool $withAddon,
        Collection $langs,
    ): void {
        foreach ($reflector->getProperties() as $property) {
            if (
                !$this->checkGroupsSerialization(property: $property, context: $context)
                && $this->isGroupsExcluded(property: $property, context: $context)
            ) {
                // This condition prevents circular references during resource serialization,
                // avoiding infinite recursion. To make this work, you need to use the
                // `excludedGroups` attribute on the related resource (e.g.:
                // #[Relation(targetResource: SelectionContainer::class, excludedGroups: [SelectionContainer::GROUP_READ])])
                // to exclude problematic serialization groups and break the circular dependency.
                continue;
            }
            $defaultGetter = 'get'.ucfirst($property->getName());
            $propelGetter = $this->determinePropelGetterName($property, Column::class, 'propelFieldName', $defaultGetter);
            $propelGetter = $this->determinePropelGetterName($property, Relation::class, 'relationAlias', $propelGetter);

            if (!method_exists($propelModel, $propelGetter)) {
                continue;
            }

            $resourceSetter = 'set'.ucfirst($property->getName());

            if (!method_exists($apiResource, $resourceSetter)) {
                continue;
            }

            $value = $propelModel->{$propelGetter}();

            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (!$withRelation || null === $value) {
                    continue 2;
                }

                $targetClass = $relationAttribute->getArguments()['targetResource'];

                if ($targetClass === $parentReflector?->getName() && 'array' !== $property->getType()?->getName()) {
                    $apiResource->{$resourceSetter}(
                        $this->modelToResource(
                            resourceClass: $parentReflector?->getName(),
                            propelModel: $parentModel,
                            context: $context,
                            langs: $langs,
                            withRelation: false,
                            withAddon: $withAddon,
                        ),
                    );
                    continue 2;
                }

                $this->manageCollectionAttribute(
                    value: $value,
                    targetClass: $targetClass,
                    reflector: $reflector,
                    propelModel: $propelModel,
                    baseModel: $baseModel,
                    context: $context,
                    withAddon: $withAddon,
                    langs: $langs,
                );
            }

            $castedValue = $this->typeCasterService->castValueForSetter($apiResource, $resourceSetter, $value);
            $apiResource->{$resourceSetter}($castedValue);
        }
    }

    private function processTranslations(PropelResourceInterface $data, ActiveRecordInterface $propelModel): void
    {
        if (is_subclass_of($data, TranslatableResourceInterface::class)) {
            foreach ($data->getI18ns() as $locale => $i18n) {
                $i18nGetters = array_filter(
                    array_map(
                        static fn (\ReflectionProperty $reflectionProperty) => $reflectionProperty->isInitialized($i18n) ? 'get'.ucfirst($reflectionProperty->getName()) : null,
                        (new \ReflectionClass($i18n))->getProperties(),
                    ),
                );

                $propelModel->setLocale($locale);

                foreach ($i18nGetters as $i18nGetter) {
                    if ('getId' === $i18nGetter) {
                        continue;
                    }

                    $propelSetter = substr_replace($i18nGetter, 's', 0, 1);

                    if (method_exists($propelModel, $propelSetter)) {
                        $propelModel->{$propelSetter}($i18n->{$i18nGetter}());
                    }
                }
            }
        }
    }

    private function manageCollectionAttribute(
        mixed &$value,
        string $targetClass,
        \ReflectionClass $reflector,
        ActiveRecordInterface $propelModel,
        ActiveRecordInterface $baseModel,
        array $context,
        bool $withAddon,
        Collection $langs,
    ): void {
        if ($value instanceof Collection) {
            $collection = new Collection();

            foreach ($value as $childPropelModel) {
                $collection->append(
                    $this->modelToResource(
                        resourceClass: $targetClass,
                        propelModel: $childPropelModel,
                        context: $context,
                        langs: $langs,
                        parentReflector: $reflector,
                        parentModel: $propelModel,
                        baseModel: $baseModel,
                        withAddon: $withAddon,
                    ),
                );
            }

            $value = iterator_to_array($collection);

            return;
        }

        $value = $this->modelToResource(
            resourceClass: $targetClass,
            propelModel: $value,
            context: $context,
            langs: $langs,
            parentReflector: $reflector,
            parentModel: $propelModel,
            baseModel: $baseModel,
            withAddon: $withAddon,
        );
    }

    private function manageTranslatableResource(
        string $resourceClass,
        ActiveRecordInterface $propelModel,
        ActiveRecordInterface $baseModel,
        PropelResourceInterface $apiResource,
        ?\ReflectionClass $parentReflector,
        \ReflectionClass $reflector,
        array $context,
        Collection $langs,
    ): void {
        foreach ($langs as $lang) {
            $i18nResource = new ($resourceClass::getI18nResourceClass());

            $i18nFields = array_map(
                static fn (\ReflectionProperty $reflectionProperty): \ReflectionProperty => $reflectionProperty,
                (new \ReflectionClass($i18nResource))->getProperties(),
            );

            $langHasI18nValue = false;

            /** @var \ReflectionProperty $i18nField */
            foreach ($i18nFields as $i18nField) {
                $i18nFieldName = $i18nField->getName();

                if (!$this->checkGroupsSerialization(property: $i18nField, context: $context)) {
                    continue;
                }

                $fieldValue = null;

                $virtualColumn = ltrim(strtolower($parentReflector?->getShortName().'_'.$reflector->getShortName()).'_lang_'.$lang->getLocale().'_'.$i18nFieldName, '_');

                if ($baseModel->hasVirtualColumn($virtualColumn)) {
                    $fieldValue = $baseModel->getVirtualColumn($virtualColumn);
                }

                if (null === $fieldValue) {
                    $propelModel->setlocale($lang->getLocale());
                    $getter = 'get'.ucfirst($i18nFieldName);

                    $fieldValue = $propelModel->{$getter}();
                }

                if (null === $fieldValue) {
                    continue;
                }

                $setter = 'set'.ucfirst($i18nFieldName);
                $i18nResource->{$setter}($fieldValue);

                if ('id' !== $i18nFieldName && !empty($fieldValue)) {
                    $langHasI18nValue = true;
                }
            }

            if ($langHasI18nValue) {
                $apiResource->addI18n($i18nResource, $lang->getLocale());
            }
        }
    }

    private function hasCompositeIdentifiersAlready(
        PropelResourceInterface $data,
        ?ActiveRecordInterface $previousPropelModel,
    ): bool {
        $reflector = new \ReflectionClass($data::class);
        $compositeIdentifiers = $this->getResourceCompositeIdentifierValues(reflector: $reflector, param: 'keys');

        if ([] !== $compositeIdentifiers) {
            /** @var ModelCriteria $queryClass */
            $queryClass = $data::getPropelRelatedTableMap()->getClassName().'Query';
            /** @var ModelCriteria $query */
            $query = $queryClass::create();
            $columnValues = $this->getColumnValues(reflector: $reflector, columns: $compositeIdentifiers);
            $uriVariables = [];

            foreach ($compositeIdentifiers as $compositeIdentifier) {
                if ($reflector->hasProperty($compositeIdentifier) && $reflector->getProperty($compositeIdentifier)->isInitialized($data)) {
                    $getter = 'get'.ucfirst((string) $compositeIdentifier);

                    if (method_exists($data, $getter)) {
                        $getterId = 'getId';
                        $uriVariables[$compositeIdentifier] = $data->{$getter}()->{$getterId}();
                    }
                }

                if ($previousPropelModel instanceof ActiveRecordInterface) {
                    $previousPropelModelRefector = new \ReflectionClass($previousPropelModel::class);

                    if (ucfirst((string) $compositeIdentifier) === $previousPropelModelRefector->getShortName() && method_exists($previousPropelModel, 'getId')) {
                        $uriVariables[$compositeIdentifier] = $previousPropelModel->getId();
                    }
                }
            }

            $this->queryFilterById(uriVariables: $uriVariables, query: $query, columnValues: $columnValues);

            if (\count($query->getMap()) < 2) {
                return false;
            }

            return null !== $query->findOne();
        }

        return false;
    }

    public function queryFilterById(array $uriVariables, $query, array $columnValues): void
    {
        foreach ($uriVariables as $field => $value) {
            $filterMethod = null;
            $filterName = $columnValues[$field]['propelQueryFilter'] ?? null;

            if ($filterName && method_exists($query, $filterName)) {
                $filterMethod = $columnValues[$field]['propelQueryFilter'];
                $value = $uriVariables[$field];
            }

            $filterName = 'filterBy'.ucfirst((string) $field).'Id';

            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            $filterName = 'filterBy'.ucfirst((string) $field);

            if (null === $filterMethod && method_exists($query, $filterName)) {
                $filterMethod = $filterName;
            }

            if (null !== $filterMethod) {
                $query->{$filterMethod}($value);
            }
        }
    }

    private function checkGroupsSerialization(\ReflectionProperty $property, array $context): bool
    {
        // @TODO : wait an official fix or rebuild full context array https://github.com/api-platform/api-platform/issues/2594
        return $this->matchGroupsFromAttribute($property, Groups::class, 'groups', $context);
    }

    private function isGroupsExcluded(\ReflectionProperty $property, array $context): bool
    {
        return $this->matchGroupsFromAttribute($property, Relation::class, 'excludedGroups', $context);
    }

    private function matchGroupsFromAttribute(
        \ReflectionProperty $property,
        string $attributeClass,
        string $groupKey,
        array $context,
    ): bool {
        $attribute = $property->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

        if (null === $attribute || !isset($context['groups'])) {
            return false;
        }

        $arguments = $attribute->getArguments();

        $propertyGroups = $arguments[$groupKey] ?? $arguments[0][$groupKey] ?? $arguments[0] ?? [];

        return !empty(array_intersect($propertyGroups, $context['groups']));
    }

    /**
     * @throws \JsonException
     */
    private function makeVisitKey(string $resourceClass, ActiveRecordInterface $model, array $context): string
    {
        if (method_exists($model, 'getId')) {
            $id = $model->getId();
        } elseif (method_exists($model, 'getPrimaryKey')) {
            $pk = $model->getPrimaryKey();
            $id = \is_array($pk) ? json_encode($pk) : (string) $pk;
        } else {
            $id = 'obj#'.spl_object_id($model);
        }

        $groups = $context['groups'] ?? [];
        $groupsHash = md5(\is_array($groups) ? json_encode($groups, \JSON_THROW_ON_ERROR) : (string) $groups);

        return $resourceClass.'#'.$id.'@'.$groupsHash;
    }
}
