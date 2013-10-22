<?php

namespace Thelia\Model;

use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Core\Event\Lang\LangEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\Base\Lang as BaseLang;

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
