<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Lang\LangEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Lang as BaseLang;
use Thelia\Model\Base\LangQuery;
use Thelia\Model\Map\LangTableMap;

class Lang extends BaseLang {

    use \Thelia\Model\Tools\ModelEventDispatcherTrait;
    /**
     * Return the default language object, using a local variable to cache it.
     *
     * @throws \RuntimeException
     */
    public static function getDefaultLanguage() {


        $default_lang = LangQuery::create()->findOneByByDefault(1);

        if ($default_lang == null) throw new \RuntimeException("No default language is defined. Please define one.");

        return $default_lang;
    }

    public function toggleDefault()
    {
        if($this->getId() === null) {
            throw new \RuntimeException("impossible to just uncheck default language, choose a new one");
        }
        $con = Propel::getWriteConnection(LangTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            LangQuery::create()
                ->filterByByDefault(1)
                ->update(array('ByDefault' => 0), $con);

            $this
                ->setByDefault(1)
                ->save($con);

            $con->commit();
        } catch(PropelException $e) {
            $con->rollBack();
            throw $e;
        }

    }

    public function preInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_CREATELANG, new LangEvent($this));

        return true;
    }

    public function postInsert(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_CREATELANG, new LangEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATELANG, new LangEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATELANG, new LangEvent($this));
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_DELETELANG, new LangEvent($this));

        return true;
    }

    public function postDelete(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_DELETELANG, new LangEvent($this));
    }

}
