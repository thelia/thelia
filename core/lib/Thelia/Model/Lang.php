<?php

namespace Thelia\Model;

use Thelia\Model\Base\Lang as BaseLang;

class Lang extends BaseLang {

    /**
     * Return the default language object, using a local variable to cache it.
     *
     * @throws RuntimeException
     */
    private static $default_lang = null;

    public static function getDefaultLanguage() {

        if (self::$default_lang == null) {
            $default_lang = LangQuery::create()->findOneByByDefault(true);

            if ($default_lang == null) throw new RuntimeException("No default language is defined. Please define one.");
        }

        return $default_lang;
    }

    public function getDateFormat() {
        return "d/m/Y";
    }

    public function getTimeFormat() {
        return "H:i:s";
    }

    public function getDateTimeFormat() {
        return "d/m/Y H:i:s";
    }
}
