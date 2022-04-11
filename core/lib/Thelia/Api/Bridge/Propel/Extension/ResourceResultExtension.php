<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

/**
 * Transform propel models to api resources
 */
final class ResourceResultExtension implements QueryResultCollectionExtensionInterface
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
                $apiResource = new $resourceClass;
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
                            ->setLocale($lang->getLocale())
                            ->setTitle($propelModel->getVirtualColumn('lang_'.$lang->getLocale().'_'.'title'));

                        $apiResource->addI18n($i18nResource);
                    }
                }

                return $apiResource;
            },
            iterator_to_array($query->find())
        );
    }

    private function getI18ns(): array {

    }

    public function applyToCollection(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = [])
    {
        // TODO: Implement applyToCollection() method.
    }
}
