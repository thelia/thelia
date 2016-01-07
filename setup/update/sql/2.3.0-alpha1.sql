SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Add new column in module_hook table
ALTER TABLE `module_hook` ADD `templates` TEXT AFTER`position`;

-- Add new columns in currency table
ALTER TABLE `currency` ADD  `format` CHAR( 10 ) NOT NULL AFTER  `symbol`;
ALTER TABLE `currency` ADD  `visible` TINYINT( 1 ) NOT NULL DEFAULT '0' AFTER  `rate`;

-- Update currencies
UPDATE `currency` SET `visible` = 1 WHERE 1;
UPDATE `currency` SET `format` = '%n %s' WHERE `code` NOT IN ('USD', 'GBP');
UPDATE `currency` SET `format` = '%s%n' WHERE `code` IN ('USD', 'GBP');

-- new config
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1,'default_language_on_change', '0', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
    (@max_id + 1, 'en_US', 'Stay on the default language at use arrows \"Next\" and \"Previous\" on the product, category, content, folder and brand page. 1 for yes, 0 for no.', NULL, NULL, NULL),    (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;