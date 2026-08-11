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
