<?php

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;

class PropelPersistProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $propelModel = $data->getPropelModel();

        if (null === $propelModel) {
            $propelModelClass = $data::getPropelModelClass();
            $propelModel = new $propelModelClass;
        }

        foreach (get_class_methods($propelModel) as $methodName) {
            if (!str_starts_with($methodName, 'set') || $methodName === 'setId') {
                continue;
            }

            $possibleGetters = [
                'get'.ucfirst(substr($methodName, 3)),
                'is'.ucfirst(substr($methodName, 3)),
            ];

            $availableMethods = array_filter(array_intersect($possibleGetters, get_class_methods($data)));

            if (empty($availableMethods)) {
                continue;
            }

            $reflectionMethod = new \ReflectionMethod($propelModel, $methodName);
            $parameters = $reflectionMethod->getParameters();

            if (!isset($parameters[0])) {
                continue;
            }

            $value = null;
            while (!empty($availableMethods) && $value === null) {
                $method = array_pop($availableMethods);
                $value = $data->$method();
            }

            if (null !== $parameters[0]->getType() && $parameters[0]->getType()->__toString() == \gettype($value)) {
                continue;
            }

            $propelModel->$methodName($value);
        }

        if (is_subclass_of($data, TranslatableResourceInterface::class)) {
            foreach ($data->getI18ns() as $locale => $i18n) {
                $i18nGetters = array_map(
                    function (\ReflectionProperty $reflectionProperty) {
                        return 'get'.ucfirst($reflectionProperty->getName());
                    },
                    (new \ReflectionClass($i18n))->getProperties()
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

        $propelModel->save();

        $data->setId($propelModel->getId());

        return $data;
    }
}
