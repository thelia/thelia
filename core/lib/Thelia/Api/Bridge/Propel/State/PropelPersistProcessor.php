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

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Propel\Runtime\Collection\Collection;
use Thelia\Api\Bridge\Propel\Attribute\Column;
use Thelia\Api\Resource\AbstractPropelResource;
use Thelia\Api\Resource\TranslatableResourceInterface;

class PropelPersistProcessor implements ProcessorInterface
{
    public function __construct(private readonly PropelItemProvider $itemProvider)
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $propelModel = $this->resourceToModel($data);

        $propelModel->save();

        $propelModel->reload();

        $data->setId($propelModel->getId());

        /** @var Post $postOperation */
        $postOperation = $context['operation'] ?? null;
        if (null !== $postOperation) {
            $data = $this->itemProvider->modelToResource(get_class($data), $propelModel, $postOperation->getNormalizationContext());
        }

        return $data;
    }

    public function resourceToModel(mixed $data)
    {
        $propelModel = $data->getPropelModel();

        if (null === $propelModel) {
            $propelModelClass = $data::getPropelModelClass();
            $propelModel = new $propelModelClass();
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

                if ($value instanceof AbstractPropelResource) {
                    if (!$setterForced) {
                        $propelSetter = $propelSetter.'Id';
                    }
                    $value = $value->getPropelModel()->getId();
                }

                if (\is_array($value)) {
                    $value = new Collection(array_map(function ($value) {return $this->resourceToModel($value); }, $value));
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
