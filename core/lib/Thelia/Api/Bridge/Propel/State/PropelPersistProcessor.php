<?php

namespace Thelia\Api\Bridge\Propel\State;

use _PHPStan_9a6ded56a\Nette\Neon\Exception;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Propel\Runtime\Collection\Collection;
use Thelia\Api\Resource\AbstractPropelResource;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\ProductSaleElements;

class PropelPersistProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $propelModel = $this->resourceToModel($data);

        $propelModel->save();

        $data->setId($propelModel->getId());

        return $data;
    }

    public function resourceToModel(mixed $data)
    {
        $propelModel = $data->getPropelModel();

        if (null === $propelModel) {
            $propelModelClass = $data::getPropelModelClass();
            $propelModel = new $propelModelClass;
        }

        $resourceReflection = new \ReflectionClass($data);

        foreach ($resourceReflection->getProperties() as $property) {
            if (!$property->isInitialized($data)) {
                continue;
            }

            $propelSetter = 'set'.ucfirst($property->getName());

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

                if ($value instanceof AbstractPropelResource)
                {
                    $propelSetter = $propelSetter.'Id';
                    $value = $value->getId();
                }

                if ($value instanceof Collection)
                {
                    $value = new Collection(array_map(function ($value) {return  $this->resourceToModel($value);}, iterator_to_array($value)));
                }

                $propelModel->$propelSetter($value);
            }
        }

        if (is_subclass_of($data, TranslatableResourceInterface::class)) {
            foreach ($data->getI18ns() as $locale => $i18n) {
                $i18nGetters = array_filter(
                    array_map(
                        function (\ReflectionProperty $reflectionProperty) use ($i18n){
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
