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
    /**
     * Every unit price is rounded to the cent before being multiplied by the
     * quantity. This is the historical Thelia behaviour and stays the default.
     */
    public const ROUNDING_MODE_SUM_OF_ROUNDINGS = 1;

    /**
     * The unit price is multiplied by the quantity at full precision, and only
     * the resulting line total is rounded to the cent. Shops selling by weight
     * or by volume need this: a price stored per gram or per millilitre is
     * meaningless once rounded to the cent.
     */
    public const ROUNDING_MODE_ROUNDING_OF_SUMS = 2;

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
     * @internal
     */
    public static function resetCache(): void
    {
        self::$booted = false;
        self::$cache = [];
    }

    /**
     * @internal
     *
     * The whole table, name => value. It is small enough to load in one go
     * and to hand to {@see initCache()} as an authoritative snapshot: every
     * name absent from the result has no row in the table at all.
     *
     * @return array<string, string>
     */
    public static function findAllAsMap(): array
    {
        $configs = [];

        foreach (self::create()->find() as $config) {
            $configs[$config->getName()] = $config->getValue();
        }

        return $configs;
    }

    /**
     * Find a config variable and return the value or default value if not founded.
     *
     * Use this method for better performance, a cache is created for each variable already searched
     */
    public static function read(string $search, $default = null, bool $ignoreCache = false)
    {
        if ($ignoreCache) {
            $model = self::create()->filterByName($search)->findOneOrCreate();

            // The default only applies to a variable that does not exist yet: a stored
            // '0' or '' is a value, and the cached path returns it as such.
            return self::$cache[$search] = $model->getValue() ?? $default;
        }

        if (!self::$booted) {
            // Nothing warmed the cache yet (this can run before
            // ConfigCacheService gets a chance to, e.g. a debug logger reading
            // its own config while Propel itself is still being wired up).
            // Load the whole table once instead of paying for every distinct
            // name read from here on, one row at a time.
            self::initCache(self::findAllAsMap());
        }

        if (!\array_key_exists($search, self::$cache)) {
            // The snapshot above is exhaustive: a name missing from it has no
            // row in the table, it is not a lookup that has not run yet.
            return $default;
        }

        return self::$cache[$search] ?? $default;
    }

    public static function write($configName, $value, $secured = null, $hidden = null): void
    {
        $config = self::create()->findOneByName($configName);

        if (null === $config) {
            $config = new Config();
            $config->setName($configName);
        }

        if (null !== $secured) {
            $config->setSecured($secured ? 1 : 0);
        }

        if (null !== $hidden) {
            $config->setHidden($hidden ? 1 : 0);
        }

        $config->setValue($value !== null ? (string) $value : '');
        $config->save();

        self::$cache[$configName] = $config->getValue();
    }

    public static function getConfiguredShopUrl()
    {
        return self::read('url_site', '');
    }

    public static function getDefaultLangWhenNoTranslationAvailable()
    {
        return self::read('default_lang_without_translation', 1);
    }

    public static function isRewritingEnable(): bool
    {
        return '1' === self::read('rewriting_enable');
    }

    public static function getPageNotFoundView()
    {
        return self::read('page_not_found_view', '404.html');
    }

    public static function getObsoleteRewrittenUrlView()
    {
        return self::read('obsolete_rewriten_url_view', 'obsolete-rewritten-url');
    }

    public static function useTaxFreeAmounts(): bool
    {
        return '1' === self::read('use_tax_free_amounts', 'default');
    }

    public static function checkAvailableStock(): bool
    {
        return '0' !== self::read('check-available-stock', '1');
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
     * @return int|null the store country id
     */
    public static function getStoreCountry(): ?int
    {
        $value = self::read('store_country');

        return null === $value ? null : (int) $value;
    }

    public static function getStoreSiret(): string
    {
        return (string) self::read('store_siret', '');
    }

    public static function getStoreVatIntracom(): string
    {
        return (string) self::read('store_vat_intracom', '');
    }

    public static function getStoreApeCode(): string
    {
        return (string) self::read('store_ape_code', '');
    }

    public static function getStoreEori(): string
    {
        return (string) self::read('store_eori', '');
    }

    public static function isStoreVatExempt(): bool
    {
        return '1' === self::read('store_vat_exempt', '0');
    }

    public static function isStoreRegistrationExempt(): bool
    {
        return '1' === self::read('store_registration_exempt', '0');
    }

    public static function getStoreLegalMentions(): string
    {
        return (string) self::read('store_legal_mentions', '');
    }

    public static function getNotifyNewsletterSubscription(): bool
    {
        return '0' !== self::read('notify_newsletter_subscription', false);
    }

    public static function isCustomerEmailConfirmationEnable(): bool
    {
        return (bool) self::read('customer_email_confirmation', false);
    }

    /**
     * @return array a list of email addresses to send the shop's notifications
     */
    public static function getNotificationEmailsList(): array
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
    public static function isSmtpInEnv(): bool
    {
        return isset($_ENV['SMTP_ENABLED']) || isset($_ENV['SMTP_HOST']) || isset($_ENV['MAILER_DSN']);
    }

    public static function isSmtpEnable(): bool
    {
        return '1' === self::read('smtp.enabled');
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

    public static function setShowingErrorMessage(bool $v): void
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
     */
    public static function isMultiDomainActivated(): bool
    {
        return (bool) self::read('one_domain_foreach_lang', false);
    }

    public static function getMinimuAdminPasswordLength()
    {
        return self::read('minimum_admin_password_length', 4);
    }

    /**
     * Orders placed before Thelia 2.4 were totalled without any rounding at all.
     * Their amount is frozen: reading them back with a newer rule would restate
     * an invoice the customer has already paid.
     */
    public static function isOrderWithLegacyRounding(int $orderId): bool
    {
        return $orderId <= (int) self::read('last_legacy_rounding_order_id', 0);
    }

    /**
     * The last order that keeps a sum-of-roundings total whatever
     * order_rounding_mode says. A shop switching to rounding of sums writes its
     * current maximum order id here, exactly the way the 2.4 upgrade wrote
     * last_legacy_rounding_order_id, so that the orders already invoiced are
     * left alone. Zero, the default, means the shop has nothing to protect.
     */
    public static function isOrderWithSumOfRoundings(int $orderId): bool
    {
        return $orderId <= (int) self::read('last_sum_of_roundings_order_id', 0);
    }

    /**
     * Tells how a line total has to be built from a unit price and a quantity.
     *
     * Pass the id of a persisted order to get the mode that order was invoiced
     * with; pass nothing for a cart, which is always priced with the mode the
     * shop runs today.
     *
     * Any value other than the two known modes reads as the historical one: a
     * typo in a configuration variable must not restate an invoice.
     */
    public static function getOrderRoundingMode(?int $orderId = null): int
    {
        if (null !== $orderId && self::isOrderWithSumOfRoundings($orderId)) {
            return self::ROUNDING_MODE_SUM_OF_ROUNDINGS;
        }

        $configuredMode = (int) self::read('order_rounding_mode', self::ROUNDING_MODE_SUM_OF_ROUNDINGS);

        return self::ROUNDING_MODE_ROUNDING_OF_SUMS === $configuredMode
            ? self::ROUNDING_MODE_ROUNDING_OF_SUMS
            : self::ROUNDING_MODE_SUM_OF_ROUNDINGS;
    }

    public static function isRoundingModeRoundingOfSums(?int $orderId = null): bool
    {
        return self::ROUNDING_MODE_ROUNDING_OF_SUMS === self::getOrderRoundingMode($orderId);
    }
}

// ConfigQuery
