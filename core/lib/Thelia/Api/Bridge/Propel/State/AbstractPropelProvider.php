<?php

namespace Thelia\Api\Bridge\Propel\State;

use ApiPlatform\State\ProviderInterface;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

abstract class AbstractPropelProvider implements ProviderInterface
{
    protected function modelToResource($resourceClass, $propelModel, $langs = null): PropelResourceInterface
    {
        if ($langs === null) {
            $langs = LangQuery::create()->filterByActive(1)->find();
        }

        /** @var PropelResourceInterface $apiResource */
        $apiResource = new $resourceClass();
        foreach (get_class_methods($apiResource) as $methodName) {
            if (!str_starts_with($methodName, 'set')) {
                continue;
            }
            $propelGetter = 'get'.ucfirst(substr($methodName, 3));

            if (!method_exists($propelModel, $propelGetter)) {
                continue;
            }

            $apiResource->$methodName($propelModel->$propelGetter());
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
                    $virtualColumn = 'lang_'.$lang->getLocale().'_'.$i18nField;

                    if (
                        !$propelModel->hasVirtualColumn($virtualColumn)
                    ) {
                       continue;
                    }

                    $fieldValue = $propelModel->getVirtualColumn($virtualColumn);
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
    protected function setI18nFieldValue(I18n $i18nResource, $lang, $field, $propelModel): string
    {
        $virtualColumn = 'lang_'.$lang->getLocale().'_'.$field;
        $setter = 'set'.ucfirst($field);

        $value = '';

        if (
            $propelModel->hasVirtualColumn($virtualColumn)
            &&
            !empty($propelModel->getVirtualColumn($virtualColumn))
        ) {
            $value = $propelModel->getVirtualColumn($virtualColumn);
        }

        $i18nResource->$setter($value);

        return $value;
    }
}
