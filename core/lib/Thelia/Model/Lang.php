<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;
use Thelia\Core\Event\Lang\LangEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Lang as BaseLang;
use Thelia\Model\Map\LangTableMap;

class Lang extends BaseLang
{
    // Constants to define behavior when a request string does not exists in the current language
    const STRICTLY_USE_REQUESTED_LANGUAGE = 0;
    const REPLACE_BY_DEFAULT_LANGUAGE = 1;

    use \Thelia\Model\Tools\ModelEventDispatcherTrait;

    protected static $defaultLanguage;

    /**
     * Return the default language object, using a local variable to cache it.
     *
     * @throws \RuntimeException
     */
    public static function getDefaultLanguage()
    {
        if (null === self::$defaultLanguage) {
            self::$defaultLanguage = LangQuery::create()->findOneByByDefault(1);

            if (null === self::$defaultLanguage) {
                throw new \RuntimeException("No default language is defined. Please define one.");
            }
        }

        return self::$defaultLanguage;
    }

    public function toggleDefault()
    {
        if ($this->getId() === null) {
            throw new \RuntimeException("impossible to just uncheck default language, choose a new one");
        }
        if (!$this->getByDefault()) {
            $con = Propel::getWriteConnection(LangTableMap::DATABASE_NAME);
            $con->beginTransaction();
            try {
                LangQuery::create()
                    ->filterByByDefault(1)
                    ->update(array('ByDefault' => 0), $con);

                $this
                    ->setVisible(1)
                    ->setActive(1)
                    ->setByDefault(1)
                    ->save($con);

                $con->commit();
            } catch (PropelException $e) {
                $con->rollBack();
                throw $e;
            }
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

        $this->dispatchEvent(TheliaEvents::LANG_FIX_MISSING_FLAG, new LangEvent($this));
    }

    public function preUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::BEFORE_UPDATELANG, new LangEvent($this));

        return true;
    }

    public function postUpdate(ConnectionInterface $con = null)
    {
        $this->dispatchEvent(TheliaEvents::AFTER_UPDATELANG, new LangEvent($this));

        $this->dispatchEvent(TheliaEvents::LANG_FIX_MISSING_FLAG, new LangEvent($this));
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

    public function preSave(ConnectionInterface $con = null)
    {
        // If the date/time format is not specified, generate it.
        $dateTimeFormat = $this->getDateTimeFormat();
        if (empty($dateTimeFormat)) {
            $this->setDatetimeFormat(sprintf("%s %s", $this->getDateFormat(), $this->getTimeFormat()));
        }

        return true;
    }
}
