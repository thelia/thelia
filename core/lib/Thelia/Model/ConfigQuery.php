<?php

namespace Thelia\Model;

use Thelia\Model\Base\ConfigQuery as BaseConfigQuery;


/**
 * Skeleton subclass for performing query and update operations on the 'config' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 */
class ConfigQuery extends BaseConfigQuery {

    protected static $cache = array();

    public static function read($search, $default = null)
    {
        if (array_key_exists($search, self::$cache)) {
            return self::$cache[$search];
        }

        $value = self::create()->findOneByName($search);

        self::$cache[$search] = $value ? $value->getValue() : $default;

        return self::$cache[$search];
    }

    public static function resetCache($key = null)
    {
        if($key) {
            if(array_key_exists($key, self::$cache)) {
                unset(self::$cache[$key]);
                return true;
            }
        }
        self::$cache = array();
        return true;
    }

    public static function getDefaultLangWhenNoTranslationAvailable()
    {
        return ConfigQuery::read("default_lang_without_translation", 1);
    }

    public static function isRewritingEnable()
    {
        return self::read("rewriting_enable") == 1;
    }

    public static function getPageNotFoundView()
    {
        return self::read("page_not_found_view", '404');
    }

    public static function getPassedUrlView()
    {
        return self::read('passed_url_view', 'passed-url');
    }


    public static function getActiveTemplate()
    {
        return self::read('active-template', 'default');
    }

    public static function useTaxFreeAmounts()
    {
        return self::read('use_tax_free_amounts', 'default') == 1;
    }
} // ConfigQuery
