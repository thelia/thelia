<?php

namespace Thelia\Api\Bridge\Propel\Extension;

use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Api\Resource\TranslatableResourceInterface;
use Thelia\Model\LangQuery;
use Thelia\Model\Map\ProductI18nTableMap;

final class I18nExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function applyToCollection(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = [])
    {
        $this->apply($query, $resourceClass, $operationName, $context);
    }

    public function applyToItem(ModelCriteria $query, string $resourceClass, array $identifiers, string $operationName = null, array $context = [])
    {
        $this->apply($query, $resourceClass, $operationName, $context);
    }

    public function apply(ModelCriteria $query, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!is_subclass_of($resourceClass, TranslatableResourceInterface::class)) {
            return;
        }

        $langs = LangQuery::create()->filterByActive(1)->find();
        $resourceName = (new \ReflectionClass($resourceClass))->getShortName();
        $joinMethodName = "join".$resourceName."I18n";
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
