<?php

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\Base\Import as BaseImport;
use Thelia\Model\Map\ImportTableMap;

class Import extends BaseImport
{
    public function upPosition()
    {

        if (($position = $this->getPosition()) > 1) {

            $previous = ImportQuery::create()
                ->filterByPosition($position - 1)
                ->findOneByImportCategoryId($this->getImportCategoryId());

            if (null !== $previous) {
                $previous->setPosition($position)->save();
            }

            $this->setPosition($position - 1)->save();
        }

        return $this;
    }

    public function downPosition()
    {
        $max = ImportQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ImportTableMap::POSITION)
            ->findOne()
        ;

        $count = $this->getImportCategory()->countImports();

        if ($count > $max) {
            $max = $count;
        }

        $position = $this->getPosition();

        if ($position < $max) {

            $next = ImportQuery::create()
                ->filterByPosition($position + 1)
                ->findOneByImportCategoryId($this->getImportCategoryId());

            if (null !== $next) {
                $next->setPosition($position)->save();
            }

            $this->setPosition($position + 1)->save();
        }

        return $this;
    }

    public function updatePosition($position)
    {
        $reverse = ImportQuery::create()
            ->findOneByPosition($position)
        ;

        if (null !== $reverse) {
            $reverse->setPosition($this->getPosition())->save();
        }

        $this->setPosition($position)->save();
    }

    public function setPositionToLast()
    {
        $max = ImportQuery::create()
            ->orderByPosition(Criteria::DESC)
            ->select(ImportTableMap::POSITION)
            ->findOne()
        ;

        if (null === $max) {
            $this->setPosition(1);
        } else {
            $this->setPosition($max+1);
        }

        return $this;
    }

    /**
     * @param ContainerInterface $container
     * @return ImportHandler
     * @throws \ErrorException
     */
    public function getHandleClassInstance(ContainerInterface $container)
    {
        $class = $this->getHandleClass();

        if (!class_exists($class)) {
            throw new \ErrorException(
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
}
