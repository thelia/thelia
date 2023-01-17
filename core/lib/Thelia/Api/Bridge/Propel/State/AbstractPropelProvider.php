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
                /** @var I18n $i18nResource */
                $i18nResource = new ($resourceClass::getI18nResourceClass());

                $i18nResource
                    ->setLocale($lang->getLocale());

                // Todo : dynamic translatable fields
                $this->setI18nFieldValue($i18nResource, $lang, 'title', $propelModel);
                $this->setI18nFieldValue($i18nResource, $lang, 'chapo', $propelModel);

                $apiResource->addI18n($i18nResource);
            }
        }

        return $apiResource;
    }
    protected function setI18nFieldValue(I18n $i18nResource, $lang, $field, $propelModel): void
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
    }
}
