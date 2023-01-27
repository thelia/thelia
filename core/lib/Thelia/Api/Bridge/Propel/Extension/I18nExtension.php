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

namespace Thelia\Api\Bridge\Propel\Extension;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;

final class I18nExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        $this->apply($query, $resourceClass, $operation, $context);
    }

    public function apply(ModelCriteria $query, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (!is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            return;
        }

        $langs = LangQuery::create()->filterByActive(1)->find();
        $resourceName = (new \ReflectionClass($resourceClass))->getShortName();
        $joinMethodName = 'join'.$resourceName.'I18n';
        foreach ($langs as $lang) {
            $joinAlias = 'lang_'.$lang->getLocale().'_';
            $query->$joinMethodName($joinAlias);
            $query->addJoinCondition($joinAlias, $joinAlias.'.locale = ?', $lang->getLocale(), null, \PDO::PARAM_STR);

            foreach ($resourceClass::getTranslatableFields() as $translatableField) {
                $query->withColumn($joinAlias.'.'.$translatableField);
            }
        }
    }
}
