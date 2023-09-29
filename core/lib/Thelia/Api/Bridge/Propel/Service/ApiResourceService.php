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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Validator\ValidatorInterface;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Map\TableMap;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\ResourceAddonInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

class ApiResourceService
{
    public function __construct(
        private readonly array $apiResourceAddons,
        private readonly ValidatorInterface $validator
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

        if (null === $baseModel) {
            $baseModel = $propelModel;
        }

        /** @var PropelResourceInterface $apiResource */
        $apiResource = new $resourceClass();

        $reflector = new \ReflectionClass($resourceClass);

        $compositeIdentifiersAttribute = $reflector->getAttributes(CompositeIdentifiers::class);
        $compositeIdentifiers = !empty($compositeIdentifiersAttribute) ? $compositeIdentifiersAttribute[0]->getArguments()[0] : [];

        foreach ($reflector->getProperties() as $property) {
            $groupAttributes = $property->getAttributes(Groups::class)[0] ?? null;

            if (null === $groupAttributes) {
                continue;
            }

            $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

            $matchingGroups = array_intersect($propertyGroups, $context['groups']);

            if (empty($matchingGroups) && !\in_array($property->getName(), $compositeIdentifiers)) {
                continue;
            }

            $propelGetter = 'get'.ucfirst($property->getName());

            foreach ($property->getAttributes(Column::class) as $columnAttribute) {
                if (isset($columnAttribute->getArguments()['propelFieldName'])) {
                    $propelGetter = 'get'.ucfirst($columnAttribute->getArguments()['propelFieldName']);
                }
            }
            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (isset($relationAttribute->getArguments()['relationAlias'])) {
                    $propelGetter = 'get'.$relationAttribute->getArguments()['relationAlias'];
                }
            }

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
                if ($targetClass === $parentReflector?->getName()) {
                    $apiResource->$resourceSetter(
                        $this->modelToResource(
                            resourceClass: $parentReflector->getName(),
                            propelModel: $parentModel,
                            context: $context,
                            withRelation: false,
                            withAddon: $withAddon
                        )
                    );
                    continue 2;
                }
                if ($value instanceof ObjectCollection) {
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
                } else {
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
            }

            $apiResource->$resourceSetter($value);
        }

        if (is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            foreach ($langs as $lang) {
                $i18nResource = new ($resourceClass::getI18nResourceClass());

                $i18nFields = array_map(
                    function (\ReflectionProperty $reflectionProperty) {
                        return $reflectionProperty;
                    },
                    (new \ReflectionClass($i18nResource))->getProperties()
                );

                $langHasI18nValue = false;

                /** @var \ReflectionProperty $i18nField */
                foreach ($i18nFields as $i18nField) {
                    $i18nFieldName = $i18nField->getName();
                    $groupAttributes = $i18nField->getAttributes(Groups::class)[0] ?? null;

                    if (null === $groupAttributes) {
                        continue;
                    }

                    $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

                    $matchingGroups = array_intersect($propertyGroups, $context['groups']);

                    if (empty($matchingGroups)) {
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

        return $apiResource;
    }

    /**
     * @param PropelResourceInterface $data
     */
    public function resourceToModel(mixed $data, array $context = [])
    {
        /** @var Operation $operation */
        $operation = $context['operation'] ?? null;

        if ($operation) {
            $this->validator->validate($data, $operation->getDenormalizationContext());
        }

        $propelModel = $data->getPropelModel();

        if (null === $propelModel) {
            /** @var TableMap $propelTableMap */
            $propelTableMap = $data::getPropelRelatedTableMap();
            $modelClassName = $propelTableMap->getClassName();
            $propelModel = new $modelClassName();
        }

        $resourceReflection = new \ReflectionClass($data);
        foreach ($resourceReflection->getProperties() as $property) {
            if (!$property->isInitialized($data)) {
                continue;
            }

            $setterForced = false;
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

            if (method_exists($propelModel, $propelSetter)) {
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

                if (is_object($value) && method_exists($value, 'getPropelModel')) {
                    if (!$setterForced) {
                        $propelSetter = $propelSetter.'Id';
                    }

                    $currentPropelModel = $value->getPropelModel();
                    if (null !== $currentPropelModel && method_exists($currentPropelModel, 'getId')) {
                        $value = $currentPropelModel->getId();
                    }

                    // If value is still not transformed
                    if (is_object($value) && method_exists($value, 'getId')) {
                        $value = $value->getId();
                    }
                }

                if (\is_array($value)) {
                        $value = new Collection(
                            array_map(
                                function ($value, $index) use ($context, $property) {
                                    try {
                                        return $this->resourceToModel($value, $context);
                                    } catch (ValidationException $exception) {
                                        $constrainViolationList = new ConstraintViolationList(
                                            array_map(
                                                function (ConstraintViolation $violation) use ($property, $index) {
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

                $propelModel->$propelSetter($value);
            }
        }

        if (is_subclass_of($data, TranslatableResourceInterface::class)) {
            foreach ($data->getI18ns() as $locale => $i18n) {
                $i18nGetters = array_filter(
                    array_map(
                        function (\ReflectionProperty $reflectionProperty) use ($i18n) {
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

        return $propelModel;
    }
}
