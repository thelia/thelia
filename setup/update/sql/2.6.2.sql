SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.6.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @configIdMax := IFNULL(MAX(`id`),0) FROM `config`;

-- add the CDN config variables if they don't exist : they are created on a fresh install since 2.4,
-- but no update script ever created them.
INSERT IGNORE INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@configIdMax+1, 'cdn.documents-base-url', '', 0, 0, NOW(), NOW()),
(@configIdMax+2, 'cdn.assets-base-url', '', 0, 0, NOW(), NOW())

;

-- add the missing translation rows of these variables, for every locale of the shop
INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `lang`.`locale`, NULL, NULL, NULL, NULL
FROM `config`, `lang`
WHERE `config`.`name` IN ('cdn.documents-base-url', 'cdn.assets-base-url')

;

-- remove the use_tax_free_amounts variable : it was never implemented, and had no effect at all.
DELETE FROM `config_i18n` WHERE `id` IN (SELECT `id` FROM `config` WHERE `name` = 'use_tax_free_amounts');
DELETE FROM `config` WHERE `name` = 'use_tax_free_amounts';

-- add the ISO 4217 numeric currency code (#2866)
ALTER TABLE `currency` ADD COLUMN `isocode_numeric` VARCHAR(3) DEFAULT NULL AFTER `code`;

UPDATE `currency` SET `isocode_numeric` = CASE `code`
    WHEN 'EUR' THEN '978'
    WHEN 'USD' THEN '840'
    WHEN 'GBP' THEN '826'
    WHEN 'CHF' THEN '756'
    WHEN 'MXN' THEN '484'
    WHEN 'PLN' THEN '985'
    WHEN 'CNY' THEN '156'
    WHEN 'NOK' THEN '578'
    WHEN 'MDL' THEN '498'
    WHEN 'PYG' THEN '600'
    WHEN 'ARS' THEN '032'
    WHEN 'BYR' THEN '974'
    WHEN 'FJD' THEN '242'
    WHEN 'RSD' THEN '941'
    WHEN 'SEK' THEN '752'
    WHEN 'HRK' THEN '191'
    WHEN 'DKK' THEN '208'
    WHEN 'NGN' THEN '566'
    WHEN 'HKD' THEN '344'
    WHEN 'CAD' THEN '124'
    WHEN 'SAR' THEN '682'
    WHEN 'CZK' THEN '203'
    WHEN 'CRC' THEN '188'
    WHEN 'AZN' THEN '944'
    WHEN 'IDR' THEN '360'
    WHEN 'PKR' THEN '586'
    WHEN 'BRL' THEN '986'
    WHEN 'VND' THEN '704'
    WHEN 'PHP' THEN '608'
    WHEN 'GTQ' THEN '320'
    WHEN 'TRY' THEN '949'
    WHEN 'JPY' THEN '392'
    WHEN 'RUB' THEN '643'
    WHEN 'PEN' THEN '604'
    WHEN 'EGP' THEN '818'
    WHEN 'GEL' THEN '981'
    WHEN 'BOB' THEN '068'
    WHEN 'AED' THEN '784'
    WHEN 'THB' THEN '764'
    WHEN 'ILS' THEN '376'
    WHEN 'MYR' THEN '458'
    WHEN 'VEF' THEN '937'
    WHEN 'HUF' THEN '348'
    WHEN 'KES' THEN '404'
    WHEN 'UAH' THEN '980'
    WHEN 'TND' THEN '788'
    WHEN 'BGN' THEN '975'
    WHEN 'INR' THEN '356'
    ELSE `isocode_numeric`
END;

-- fix country names swapped between Palau (id 19, ISO 585/PW/PLW) and Belarus (id 24, ISO 112/BY/BLR):
-- id 19 held the Belarus name despite having Palau's ISO codes, and id 24 held an untranslated
-- "Bielorussia" / mistranslated name instead of Belarus.
UPDATE `country_i18n` SET `title`='Palau' WHERE `id`=19 AND `locale`='de_DE';
UPDATE `country_i18n` SET `title`='Palau' WHERE `id`=19 AND `locale`='en_US';
UPDATE `country_i18n` SET `title`='Palaos' WHERE `id`=19 AND `locale`='es_ES';
UPDATE `country_i18n` SET `title`='Palaos' WHERE `id`=19 AND `locale`='fr_FR';
UPDATE `country_i18n` SET `title`='Палау' WHERE `id`=19 AND `locale`='ru_RU';
UPDATE `country_i18n` SET `title`='Belarus' WHERE `id`=24 AND `locale`='en_US';
UPDATE `country_i18n` SET `title`='Belarús' WHERE `id`=24 AND `locale`='es_ES';
UPDATE `country_i18n` SET `title`='Беларусь' WHERE `id`=24 AND `locale`='ru_RU';

-- rename Zaire (country id 191, ISO 180/CD/COD) to its current official name, in use since 1997.
UPDATE `country_i18n` SET `title`='Demokratische Republik Kongo' WHERE `id`=191 AND `locale`='de_DE';
UPDATE `country_i18n` SET `title`='Democratic Republic of the Congo' WHERE `id`=191 AND `locale`='en_US';
UPDATE `country_i18n` SET `title`='República Democrática del Congo' WHERE `id`=191 AND `locale`='es_ES';
UPDATE `country_i18n` SET `title`='République démocratique du Congo' WHERE `id`=191 AND `locale`='fr_FR';
UPDATE `country_i18n` SET `title`='Демократическая Республика Конго' WHERE `id`=191 AND `locale`='ru_RU';

SET FOREIGN_KEY_CHECKS = 1;
