<?php

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\State\ProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Collection\Collection;
use Propel\Runtime\Collection\ObjectCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

abstract class AbstractPropelProvider implements ProviderInterface
{
    protected function modelToResource(
        string $resourceClass,
        ActiveRecordInterface $propelModel,
        array $context,
        Collection $langs = null,
        \ReflectionClass $parentReflector = null,
        ActiveRecordInterface $parentModel = null,
        bool $withRelation = true,
        ActiveRecordInterface $baseModel = null
    ): PropelResourceInterface
    {
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
        $compositeIdentifiers = !empty($compositeIdentifiersAttribute)? $compositeIdentifiersAttribute[0]->getArguments()[0] : [];

        foreach ($reflector->getProperties() as $property) {
            $groupAttributes = $property->getAttributes(Groups::class)[0]?? null;

            if (null === $groupAttributes) {
                continue;
            }

            $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

            $matchingGroups = array_intersect($propertyGroups, $context['groups']);

            if (empty($matchingGroups) && !in_array($property->getName(), $compositeIdentifiers)) {
                continue;
            }

            $propelGetter = 'get'.ucfirst($property->getName());

            foreach ($property->getAttributes(Column::class) as $columnAttribute){
                if (isset($columnAttribute->getArguments()['propelGetter'])){
                    $propelGetter = $columnAttribute->getArguments()['propelGetter'];
                }
            }
            foreach ($property->getAttributes(Relation::class) as $relationAttribute){
                if (isset($relationAttribute->getArguments()['relationAlias'])){
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
                            withRelation: false
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
                                baseModel: $baseModel
                            )
                        );
                    }

                    $value = $collection;
                } else {
                    $value = $this->modelToResource(
                        resourceClass: $targetClass,
                        propelModel: $value,
                        context: $context,
                        parentReflector: $reflector,
                        parentModel: $propelModel,
                        baseModel: $baseModel
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
                    $groupAttributes = $i18nField->getAttributes(Groups::class)[0]?? null;

                    if (null === $groupAttributes) {
                        continue;
                    }

                    $propertyGroups = $groupAttributes->getArguments()['groups'] ?? $groupAttributes->getArguments()[0] ?? null;

                    $matchingGroups = array_intersect($propertyGroups, $context['groups']);

                    if (empty($matchingGroups)) {
                        continue;
                    }

                    $fieldValue = null;

                    $virtualColumn = ltrim(strtolower($parentReflector?->getShortName().'_'.$reflector->getShortName()).'_'.'lang_'.$lang->getLocale().'_'.$i18nFieldName, '_');

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
        $apiResource->afterModelToResource($context);

        return $apiResource;
    }
}
