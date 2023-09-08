<?php

namespace Thelia\Api\Resource;

use ApiPlatform\Metadata\Operation;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\ActiveRecord\ActiveRecordInterface;
use Propel\Runtime\Map\TableMap;

abstract class AbstractResourceAddon implements ResourceAddonInterface
{
    public static function getAddonName()
    {
        return (new \ReflectionClass(static::class))->getShortName();
    }

    public static function getPropelRelatedTableMap(): ?TableMap
    {
        return null;
    }

    public static function extendQuery(ModelCriteria $query, Operation $operation = null, array $context = []): void
    {
        $addonName =  static::getAddonName();
        $tableMap = static::getPropelRelatedTableMap();

        if (null === $tableMap) {
            throw new \Exception("You must either specify a propel related table or implement the extendQuery method in \"$addonName\" addon");
        }

        $use = "use".$tableMap->getPhpName().'Query';
        $query->$use(joinType: Criteria::LEFT_JOIN)->endUse();

        $addonName =  static::getAddonName();
        foreach ($tableMap->getColumns() as $column) {
            $query->withColumn($column->getFullyQualifiedName(), $addonName.'_'.$column->getName());
        }
    }

    public function buildFromModel(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        foreach (get_class_vars(static::class) as $property => $value) {
            if (null !== $value) {
                continue;
            }

            $virtualColumnName = static::getAddonName().'_'.$property;
            if (!$activeRecord->hasVirtualColumn($virtualColumnName)) {
                continue;
            }

            $this->$property = $activeRecord->getVirtualColumn($virtualColumnName);
        }

        return $this;
    }

    public function buildFromArray(array $data, PropelResourceInterface $abstractPropelResource): ResourceAddonInterface
    {
        foreach (get_class_vars(static::class) as $property => $value) {
            if (null !== $value) {
                continue;
            }

            if (!isset($data[$property])) {
                continue;
            }

            $this->$property = $data[$property];
        }

        return $this;
    }

    /**
     * If the id of base resource is not related to your id override this method
     */
    public function doSave(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        $model = $this->findOrCreateModel($activeRecord);

        $tableMap = static::getPropelRelatedTableMap();
        $columnPhpNames = TableMap::getFieldnamesForClass($tableMap->getClassName(), TableMap::TYPE_PHPNAME);
        foreach (TableMap::getFieldnamesForClass($tableMap->getClassName(), TableMap::TYPE_FIELDNAME) as $columnIndex => $columnName) {
            $setter = 'set'.$columnPhpNames[$columnIndex];
            $getter = 'get'.$columnPhpNames[$columnIndex];
            if (method_exists($this, $getter) && method_exists($model, $setter)) {
               $model->$setter($this->$getter());
            }
        }

        $model->save();
    }

    public function doDelete(ActiveRecordInterface $activeRecord, PropelResourceInterface $abstractPropelResource): void
    {
        $model = $this->findOrCreateModel($activeRecord);

        $model->delete();
    }

    protected function findOrCreateModel(ActiveRecordInterface $activeRecord): ActiveRecordInterface
    {
        $addonName =  static::getAddonName();
        $tableMap = static::getPropelRelatedTableMap();

        if (null === $tableMap) {
            throw new \Exception("You must either specify a propel related table or implement the doSave / doRemove method in \"$addonName\" addon");
        }

        /** @var ModelCriteria $queryClass */
        $queryClass = $tableMap->getClassName().'Query';
        /** @var ModelCriteria $query */
        $query = $queryClass::create();

        return $query->filterById($activeRecord->getId())
            ->findOneOrCreate();
    }
}
