<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Thelia\Model;

use Thelia\Model\Base\ConfigQuery as BaseConfigQuery;

/**
 * Skeleton subclass for performing query and update operations on the 'config' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class ConfigQuery extends BaseConfigQuery
{
    protected static $booted = false;

    protected static $cache = [];

    /**
     * @internal
     *
     * @param mixed[] $configs
     */
    public static function initCache(array $configs): void
    {
        self::$booted = true;
        self::$cache = $configs;
    }

    /**
     * Find a config variable and return the value or default value if not founded.
     *
     * Use this method for better performance, a cache is created for each variable already searched
     */
    public static function read(string $search, $default = null, bool $ignoreCache = false)
    {
        if ($ignoreCache || !self::$booted || !\array_key_exists($search, self::$cache)) {
            $model = self::create()->filterByName($search)->findOneOrCreate();

            $value = $model->getValue() ?: $default;

            self::$cache[$search] = $value;
        }

        return self::$cache[$search];
    }

    public static function write($configName, $value, $secured = null, $hidden = null): void
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
    }

    public static function getConfiguredShopUrl()
    {
        return self::read('url_site', '');
    }

    public static function getDefaultLangWhenNoTranslationAvailable()
    {
        return self::read('default_lang_without_translation', 1);
    }

    public static function isRewritingEnable()
    {
        return self::read('rewriting_enable') == 1;
    }

    public static function getPageNotFoundView()
    {
        return self::read('page_not_found_view', '404.html');
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
     *
     * @return int|null the store country id
     */
    public static function getStoreCountry()
    {
        return self::read('store_country', null);
    }

    /**
     * @since 2.3.0-alpha2
     *
     * @return bool
     */
    public static function getNotifyNewsletterSubscription()
    {
        return self::read('notify_newsletter_subscription', 0) != 0;
    }

    /**
     * @since 2.4
     *
     * @return bool
     */
    public static function isCustomerEmailConfirmationEnable()
    {
        return (bool) self::read('customer_email_confirmation', false);
    }

    /**
     * @return array a list of email addresses to send the shop's notifications
     */
    public static function getNotificationEmailsList()
    {
        $contactEmail = self::getStoreEmail();

        $list = preg_split('/[,;]/', (string) self::read('store_notification_emails', $contactEmail));

        $arr = [];

        foreach ($list as $item) {
            $arr[] = trim($item);
        }

        return $arr;
    }

    /* smtp config */
    public static function isSmtpInEnv()
    {
        return isset($_ENV['SMTP_ENABLED']) || isset($_ENV['SMTP_HOST']) || isset($_ENV['MAILER_DSN']);
    }

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
        return self::read('smtp.port', 25);
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

    public static function enableSmtp(): void
    {
        self::write('smtp.enabled', 1, 1, 1);
    }

    public static function disableSmtp(): void
    {
        self::write('smtp.enabled', 0, 1, 1);
    }

    public static function setSmtpHost($value): void
    {
        self::write('smtp.host', $value, 1, 1);
    }

    public static function setSmtpPort($value): void
    {
        self::write('smtp.port', $value, 1, 1);
    }

    public static function setSmtpEncryption($value): void
    {
        self::write('smtp.encryption', $value, 1, 1);
    }

    public static function setSmtpUsername($value): void
    {
        self::write('smtp.username', $value, 1, 1);
    }

    public static function setSmtpPassword($value): void
    {
        self::write('smtp.password', $value, 1, 1);
    }

    public static function setSmtpAuthMode($value): void
    {
        self::write('smtp.authmode', $value, 1, 1);
    }

    public static function setSmtpTimeout($value): void
    {
        self::write('smtp.timeout', $value, 1, 1);
    }

    public static function setSmtpSourceIp($value): void
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
        return (bool) static::read('error_message.show', false);
    }

    /**
     * @param bool $v
     */
    public static function setShowingErrorMessage($v): void
    {
        static::write('error_message.show', (int) (@(bool) $v));
    }

    public static function getErrorMessagePageName()
    {
        return static::read('error_message.page_name');
    }

    public static function setErrorMessagePageName($v): void
    {
        static::write('error_message.page_name', $v);
    }

    public static function getAdminCacheHomeStatsTTL()
    {
        return (int) static::read('admin_cache_home_stats_ttl', 600);
    }

    /**
     * check if Thelia multi domain is activated. (Means one domain for each language).
     *
     * @return bool
     */
    public static function isMultiDomainActivated()
    {
        return (bool) self::read('one_domain_foreach_lang', false);
    }

    public static function getMinimuAdminPasswordLength()
    {
        return self::read('minimum_admin_password_length', 4);
    }
}

// ConfigQuery
