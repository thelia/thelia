<?php

namespace Thelia\Model;

use Exception;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\ClassNotFoundException;
use Propel\Runtime\Propel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\Base\Import as BaseImport;
use Thelia\Model\Tools\ModelEventDispatcherTrait;
use Thelia\Model\Tools\PositionManagementTrait;

class Import extends BaseImport
{
    use PositionManagementTrait;
    use ModelEventDispatcherTrait;

    /**
     * @param  ContainerInterface $container
     * @return ImportHandler
     * @throws \ErrorException
     */
    public function getHandleClassInstance(ContainerInterface $container)
    {
        $class = $this->getHandleClass();

        if (!class_exists($class)) {
            $this->delete();

            throw new ClassNotFoundException(
                Translator::getInstance()->trans(
                    "The class \"%class\" doesn't exist",
                    [
                        "%class" => $class
                    ]
                )
            );
        }

        $instance = new $class($container);

        if (!$instance instanceof ImportHandler) {
            $this->delete();

            throw new \ErrorException(
                Translator::getInstance()->trans(
                    "The class \"%class\" must extend %baseClass",
                    [
                        "%class" => $class,
                        "%baseClass" => "Thelia\\ImportExport\\Import\\ImportHandler",
                    ]
                )
            );
        }

        return $instance;
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        return true;
    }

    public function addCriteriaToPositionQuery($query)
    {
        $query->filterByImportCategoryId($this->getImportCategoryId());
    }
}
