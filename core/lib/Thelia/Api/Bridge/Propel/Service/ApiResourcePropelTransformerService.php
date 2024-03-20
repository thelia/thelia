<?php

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

use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
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
        Collection $langs = null,
        \ReflectionClass $parentReflector = null,
        ActiveRecordInterface $parentModel = null,
        bool $withRelation = true,
        ActiveRecordInterface $baseModel = null,
        bool $withAddon = true
    ): PropelResourceInterface {
        if ($langs === null) {
            $langs = LangQuery::create()->filterByActive(1)->find();
        }

        $baseModel = $baseModel ?? $propelModel;

        $modelToResourceEvent = new ModelToResourceEvent($baseModel, $parentModel);
        $this->eventDispatcher->dispatch($modelToResourceEvent, ModelToResourceEvent::BEFORE_TRANSFORM);
        $baseModel = $modelToResourceEvent->getModel();

        /** @var PropelResourceInterface $apiResource */
        $apiResource = new $resourceClass();
        $reflector = new \ReflectionClass($resourceClass);

        $this->processPropertiesRessource(
            apiResource: $apiResource,
            reflector: $reflector,
            parentReflector: $parentReflector,
            propelModel: $propelModel,
            parentModel: $parentModel,
            baseModel: $baseModel,
            context: $context,
            withRelation: $withRelation,
            withAddon: $withAddon
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
                langs: $langs
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

    public function resourceToModel(PropelResourceInterface $data, array $context = []): ActiveRecordInterface
    {
        $operation = $context['operation'] ?? null;
        if ($operation) {
            $this->validator->validate($data, $operation->getDenormalizationContext());
        }

        $propelModel = $this->initializePropelModel($data);

        $this->processPropertiesModel($data, $propelModel, $context);
        $this->processTranslations($data, $propelModel);

        return $propelModel;
    }

    public function getResourceCompositeIdentifierValues(\ReflectionClass $reflector, string $param): array
    {
        $compositeIdentifiersAttribute = $reflector->getAttributes(CompositeIdentifiers::class);

        if (empty($compositeIdentifiersAttribute)) {
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

    private function initializePropelModel(PropelResourceInterface $data): ActiveRecordInterface
    {
        $propelModel = $data->getPropelModel();
        if ($propelModel === null) {
            $propelTableMap = $data::getPropelRelatedTableMap();
            $modelClassName = $propelTableMap?->getClassName();
            $propelModel = new $modelClassName();
        }

        return $propelModel;
    }

    private function processPropertiesModel(
        PropelResourceInterface $data,
        ActiveRecordInterface $propelModel,
        array $context
    ): void {
        $resourceReflection = new \ReflectionClass($data);
        foreach ($resourceReflection->getProperties() as $property) {
            if (!$property->isInitialized($data)) {
                continue;
            }
            $setterForced = false;
            $propelSetter = $this->determinePropelSetterName($property, $setterForced);

            if (method_exists($propelModel, $propelSetter)) {
                $value = $this->getPropertyValue($data, $property);
                $value = $this->getRelationValue($value, $propelSetter, $setterForced);
                $value = $this->getArrayValue($value, $context, $property);

                $propelModel->$propelSetter($value);
            }
        }
    }

    private function determinePropelSetterName(
        \ReflectionProperty $property,
        bool &$setterForced = false
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
        string $defaultGetter
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
        while (!empty($availableMethods) && $value === null) {
            $method = array_pop($availableMethods);
            $value = $data->$method();
        }

        return $value;
    }

    private function getRelationValue(
        mixed $value,
        string &$propelSetter,
        bool $setterForced
    ): mixed {
        if (\is_object($value) && method_exists($value, 'getPropelModel')) {
            if (!$setterForced) {
                $propelSetter .= 'Id';
            }

            $valuePropelModel = $value->getPropelModel();
            if (null !== $valuePropelModel && method_exists($valuePropelModel, 'getId')) {
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
        \ReflectionProperty $property
    ): mixed {
        if (\is_array($value)) {
            $value = new Collection(
                array_map(
                    function ($value, $index) use ($context, $property) {
                        try {
                            return $this->resourceToModel($value, $context);
                        } catch (ValidationException $exception) {
                            $constrainViolationList = new ConstraintViolationList(
                                array_map(
                                    static function (ConstraintViolation $violation) use ($property, $index) {
                                        $newViolation = new \ReflectionClass($violation);
                                        $newViolation->getProperty('propertyPath')->setValue(
                                            $violation,
                                            $property->getName().'['.$index.'].'.$violation->getPropertyPath()
                                        );

                                        return $violation;
                                    },
                                    iterator_to_array($exception->getConstraintViolationList())
                                ),
                            );
                            throw new ValidationException(
                                $constrainViolationList
                            );
                        }
                    },
                    $value,
                    array_keys($value)
                )
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
    ): void {
        foreach ($reflector->getProperties() as $property) {
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

            $value = $propelModel->$propelGetter();

            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (!$withRelation || $value === null) {
                    continue 2;
                }

                $targetClass = $relationAttribute->getArguments()['targetResource'];
                if ($targetClass === $parentReflector?->getName() && $property->getType()?->getName() !== 'array') {
                    $apiResource->$resourceSetter(
                        $this->modelToResource(
                            resourceClass: $parentReflector?->getName(),
                            propelModel: $parentModel,
                            context: $context,
                            withRelation: false,
                            withAddon: $withAddon
                        )
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
                    withAddon: $withAddon
                );
            }
            $apiResource->$resourceSetter($value);
        }
    }

    private function processTranslations(PropelResourceInterface $data, ActiveRecordInterface $propelModel): void
    {
        if (is_subclass_of($data, TranslatableResourceInterface::class)) {
            foreach ($data->getI18ns() as $locale => $i18n) {
                $i18nGetters = array_filter(
                    array_map(
                        static function (\ReflectionProperty $reflectionProperty) use ($i18n) {
                            return $reflectionProperty->isInitialized($i18n) ? 'get'.ucfirst($reflectionProperty->getName()) : null;
                        },
                        (new \ReflectionClass($i18n))->getProperties()
                    )
                );

                $propelModel->setLocale($locale);
                foreach ($i18nGetters as $i18nGetter) {
                    if ($i18nGetter === 'getId') {
                        continue;
                    }
                    $propelSetter = substr_replace($i18nGetter, 's', 0, 1);
                    if (method_exists($propelModel, $propelSetter)) {
                        $propelModel->$propelSetter($i18n->$i18nGetter());
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
        bool $withAddon
    ): void {
        if ($value instanceof Collection) {
            $collection = new Collection();

            foreach ($value as $childPropelModel) {
                $collection->append(
                    $this->modelToResource(
                        resourceClass: $targetClass,
                        propelModel: $childPropelModel,
                        context: $context,
                        parentReflector: $reflector,
                        parentModel: $propelModel,
                        baseModel: $baseModel,
                        withAddon: $withAddon
                    )
                );
            }

            $value = iterator_to_array($collection);

            return;
        }
        $value = $this->modelToResource(
            resourceClass: $targetClass,
            propelModel: $value,
            context: $context,
            parentReflector: $reflector,
            parentModel: $propelModel,
            baseModel: $baseModel,
            withAddon: $withAddon
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
        Collection $langs
    ): void {
        foreach ($langs as $lang) {
            $i18nResource = new ($resourceClass::getI18nResourceClass());

            $i18nFields = array_map(
                static function (\ReflectionProperty $reflectionProperty) {
                    return $reflectionProperty;
                },
                (new \ReflectionClass($i18nResource))->getProperties()
            );

            $langHasI18nValue = false;

            /** @var \ReflectionProperty $i18nField */
            foreach ($i18nFields as $i18nField) {
                $i18nFieldName = $i18nField->getName();
                $groupAttributes = $i18nField->getAttributes(Groups::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;

                if (null === $groupAttributes) {
                    continue;
                }

                // @TODO : wait an official fix or rebuild full context array https://github.com/api-platform/api-platform/issues/2594
                if (isset($context['groups'])) {
                    $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

                    $matchingGroups = array_intersect($propertyGroups, $context['groups']);

                    if (empty($matchingGroups)) {
                        continue;
                    }
                }

                $fieldValue = null;

                $virtualColumn = ltrim(strtolower($parentReflector?->getShortName().'_'.$reflector->getShortName()).'_lang_'.$lang->getLocale().'_'.$i18nFieldName, '_');

                if ($baseModel->hasVirtualColumn($virtualColumn)) {
                    $fieldValue = $baseModel->getVirtualColumn($virtualColumn);
                }

                if (null === $fieldValue) {
                    $propelModel->setlocale($lang->getLocale());
                    $getter = 'get'.ucfirst($i18nFieldName);

                    $fieldValue = $propelModel->$getter();
                }

                if (null === $fieldValue) {
                    continue;
                }

                $setter = 'set'.ucfirst($i18nFieldName);
                $i18nResource->$setter($fieldValue);

                if ('id' !== $i18nFieldName && !empty($fieldValue)) {
                    $langHasI18nValue = true;
                }
            }

            if ($langHasI18nValue) {
                $apiResource->addI18n($i18nResource, $lang->getLocale());
            }
        }
    }
}
