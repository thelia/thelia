<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

/**
 * Transform propel models to api resources
 */
final class ResourceResultExtension
{
    public function supportsResult(string $resourceClass, string $operationName = null): bool
    {
        return true;
    }

    public function getResult(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = [])
    {
        $langs = LangQuery::create()->filterByActive(1)->find();

        return array_map(
            function ($propelModel) use ($resourceClass, $langs) {
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

                        $this->setI18nFieldValue($i18nResource, $lang, 'title', $propelModel);
                        $this->setI18nFieldValue($i18nResource, $lang, 'chapo', $propelModel);

                        $apiResource->addI18n($i18nResource);
                    }
                }

                return $apiResource;
            },
            iterator_to_array($query->find())
        );
    }

    private function setI18nFieldValue(I18n $i18nResource, $lang, $field, $propelModel): void
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

    public function applyToCollection(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = [])
    {
        // TODO: Implement applyToCollection() method.
    }
}
