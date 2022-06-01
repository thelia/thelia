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

use ApiPlatform\Exception\RuntimeException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Bridge\Propel\Extension\QueryResultCollectionExtensionInterface;
use Thelia\Api\Resource\I18n;
use Thelia\Api\Resource\PropelResourceInterface;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

class PropelCollectionProvider implements ProviderInterface
{
    public function __construct(private iterable $propelCollectionExtensions = [])
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $resourceClass = $operation->getClass();

        if (!is_subclass_of($resourceClass, PropelResourceInterface::class)) {
            throw new RuntimeException('Bad provider');
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $resourceClass::getPropelModelClass().'Query';

        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        foreach ($this->propelCollectionExtensions as $extension) {
            $extension->applyToCollection($query, $resourceClass, $operation->getName(), $context);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operation->getName(), $context)) {
                return $extension->getResult($query, $resourceClass, $operation->getName(), $context);
            }
        }

        $langs = LangQuery::create()->filterByActive(1)->find();


        $items = array_map(
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

        return $items;
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

}
