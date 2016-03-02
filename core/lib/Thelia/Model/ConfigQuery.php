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
class ConfigQuery extends BaseConfigQuery
{
    protected static $cache = array();

    /**
     *
     * Find a config variable and return the value or default value if not founded.
     *
     * Use this method for better performance, a cache is created for each variable already searched
     *
     * @param $search
     * @param  null  $default
     * @return mixed
     */
    public static function read($search, $default = null)
    {
        if (array_key_exists($search, self::$cache)) {
            return self::$cache[$search];
        }

        $value = self::create()->findOneByName($search);

        self::$cache[$search] = $value ? $value->getValue() : $default;

        return self::$cache[$search];
    }

    public static function write($configName, $value, $secured = null, $hidden = null)
    {
        $config = self::create()->findOneByName($configName);

        if (null == $config) {
            $config = new Config();
            $config->setName($configName);
        }

        if ($secured !== null) {
            $config->setSecured($secured ? 1 : 0);
        }
        if ($hidden !== null) {
            $config->setHidden($hidden ? 1 : 0);
        }

        $config->setValue($value);
        $config->save();

        self::$cache[$configName] = $value;
    }

    public static function resetCache($key = null)
    {
        if ($key) {
            if (array_key_exists($key, self::$cache)) {
                unset(self::$cache[$key]);

                return true;
            }
        }
        self::$cache = array();

        return true;
    }

    public static function getConfiguredShopUrl()
    {
        return ConfigQuery::read("url_site", '');
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
        return self::read("page_not_found_view", '404.html');
    }

    public static function getObsoleteRewrittenUrlView()
    {
        return self::read('obsolete_rewriten_url_view', 'obsolete-rewritten-url');
    }

    public static function useTaxFreeAmounts()
    {
        return self::read('use_tax_free_amounts', 'default') == 1;
    }

    public static function checkAvailableStock()
    {
        return self::read('check-available-stock', 1) != 0;
    }

    public static function getUnknownFlagPath()
    {
        return self::read('unknown-flag-path', '/assets/img/flags/unknown.png');
    }

    public static function getStoreEmail()
    {
        return self::read('store_email', null);
    }

    public static function getStoreName()
    {
        return self::read('store_name', '');
    }

    public static function getStoreDescription()
    {
        return self::read('store_description', '');
    }

    /**
     * @since 2.3.0-alpha2
     * @return int|null the store country id
     */
    public static function getStoreCountry()
    {
        return self::read('store_country', null);
    }

    /**
     * @return array a list of email addresses to send the shop's notifications
     */
    public static function getNotificationEmailsList()
    {
        $contactEmail = self::getStoreEmail();

        $list = preg_split("/[,;]/", self::read('store_notification_emails', $contactEmail));

        $arr = [];

        foreach ($list as $item) {
            $arr[] = trim($item);
        }

        return $arr;
    }

    /* smtp config */
    public static function isSmtpEnable()
    {
        return self::read('smtp.enabled') == 1;
    }

    public static function getSmtpHost()
    {
        return self::read('smtp.host', 'localhost');
    }

    public static function getSmtpPort()
    {
        return self::read('smtp.port');
    }

    public static function getSmtpEncryption()
    {
        return self::read('smtp.encryption');
    }

    public static function getSmtpUsername()
    {
        return self::read('smtp.username');
    }

    public static function getSmtpPassword()
    {
        return self::read('smtp.password');
    }

    public static function getSmtpAuthMode()
    {
        return self::read('smtp.authmode');
    }

    public static function getSmtpTimeout()
    {
        return self::read('smtp.timeout', 30);
    }

    public static function getSmtpSourceIp()
    {
        return self::read('smtp.sourceip');
    }

    public static function enableSmtp()
    {
        self::write('smtp.enabled', 1, 1, 1);
    }

    public static function disableSmtp()
    {
        self::write('smtp.enabled', 0, 1, 1);
    }

    public static function setSmtpHost($value)
    {
        self::write('smtp.host', $value, 1, 1);
    }

    public static function setSmtpPort($value)
    {
        self::write('smtp.port', $value, 1, 1);
    }

    public static function setSmtpEncryption($value)
    {
        self::write('smtp.encryption', $value, 1, 1);
    }

    public static function setSmtpUsername($value)
    {
        self::write('smtp.username', $value, 1, 1);
    }

    public static function setSmtpPassword($value)
    {
        self::write('smtp.password', $value, 1, 1);
    }

    public static function setSmtpAuthMode($value)
    {
        self::write('smtp.authmode', $value, 1, 1);
    }

    public static function setSmtpTimeout($value)
    {
        self::write('smtp.timeout', $value, 1, 1);
    }

    public static function setSmtpSourceIp($value)
    {
        self::write('smtp.sourceip', $value, 1, 1);
    }
    /* end smtp config */

    /* Thelia version */
    public static function getTheliaSimpleVersion()
    {
        $majorVersion = self::read('thelia_major_version');
        $minorVersion = self::read('thelia_minus_version');
        $releaseVersion = self::read('thelia_release_version');

        return $majorVersion.'.'.$minorVersion.'.'.$releaseVersion;
    }

    public static function isShowingErrorMessage()
    {
        return (bool) static::read("error_message.show", false);
    }

    /**
     * @param bool $v
     */
    public static function setShowingErrorMessage($v)
    {
        static::write("error_message.show", (int) (@(bool) $v));
    }

    public static function getErrorMessagePageName()
    {
        return static::read("error_message.page_name");
    }

    public static function setErrorMessagePageName($v)
    {
        static::write("error_message.page_name", $v);
    }

    public static function getAdminCacheHomeStatsTTL()
    {
        return intval(static::read("admin_cache_home_stats_ttl", 600));
    }

    /**
     * check if Thelia multi domain is activated. (Means one domain for each language)
     *
     * @return bool
     */
    public static function isMultiDomainActivated()
    {
        return (bool) self::read("one_domain_foreach_lang", false);
    }

    public static function getMinimuAdminPasswordLength()
    {
        return self::read("minimum_admin_password_length", 4);
    }
}
// ConfigQuery
