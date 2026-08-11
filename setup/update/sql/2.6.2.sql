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

SET FOREIGN_KEY_CHECKS = 1;
