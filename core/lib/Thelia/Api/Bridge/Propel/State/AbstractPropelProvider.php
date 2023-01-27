<?php

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\State\ProviderInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Propel\Runtime\Collection\ObjectCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Thelia\Api\Bridge\Propel\Attribute\CompositeIdentifiers;
use Thelia\Api\Bridge\Propel\Attribute\Relation;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

abstract class AbstractPropelProvider implements ProviderInterface
{
    protected function modelToResource(
        $resourceClass,
        $propelModel,
        $context,
        $langs = null,
        $parentClass = null,
        $parentModel = null,
        $withRelation = true,
        $baseModel = null
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
            $groups = array_reduce(
                $property->getAttributes(Groups::class),
                function ($carry, $item) {
                    $value = $item->getArguments()[0];
                    return array_merge($carry, is_string($value) ? [$value] : $value);
                },
                []
            );

            $matchingGroups = array_intersect($groups, $context['groups']);

            if (empty($matchingGroups) && !in_array($property->getName(), $compositeIdentifiers)) {
                continue;
            }

            $propelGetter = 'get'.ucfirst($property->getName());

            if (!method_exists($propelModel, $propelGetter)) {
                continue;
            }

            $resourceSetter = 'set'.ucfirst($property->getName());

            if (!method_exists($apiResource, $resourceSetter)) {
                continue;
            }

            $value = $propelModel->$propelGetter();


            foreach ($property->getAttributes(Relation::class) as $relationAttribute) {
                if (!$withRelation) {
                    continue 2;
                }

                $targetClass = $relationAttribute->getArguments()['targetResource'];
                if ($targetClass === $parentClass) {
                    $apiResource->$resourceSetter(
                        $this->modelToResource(
                            resourceClass: $parentClass,
                            propelModel: $parentModel,
                            context: $context,
                            withRelation: false
                        )
                    );
                    continue 2;
                }
                if ($value instanceof ObjectCollection) {

                    $collection = new ArrayCollection();

                    foreach ($value as $childPropelModel) {
                        $collection->add(
                            $this->modelToResource(
                                resourceClass: $targetClass,
                                propelModel: $childPropelModel,
                                context: $context,
                                parentClass: $resourceClass,
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
                        parentClass: $resourceClass,
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
                        return $reflectionProperty->getName();
                    },
                    (new \ReflectionClass($i18nResource))->getProperties()
                );

                $langHasI18nValue = false;

                foreach ($i18nFields as $i18nField) {
                    $virtualColumn = strtolower($reflector->getShortName()).'_'.'lang_'.$lang->getLocale().'_'.$i18nField;

                    if (
                        !$baseModel->hasVirtualColumn($virtualColumn)
                    ) {
                       continue;
                    }

                    $fieldValue = $baseModel->getVirtualColumn($virtualColumn);
                    $setter = 'set'.ucfirst($i18nField);
                    $i18nResource->$setter($fieldValue);

                    if (!empty($fieldValue)) {
                        $langHasI18nValue = true;
                    }
                }

                if ($langHasI18nValue) {
                    $apiResource->addI18n($i18nResource, $lang->getLocale());
                }
            }
        }

        $apiResource->setPropelModel($propelModel);

        return $apiResource;
    }
}
