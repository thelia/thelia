SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.5.5' WHERE `name`='thelia_version';

UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'order_rounding_mode', '1', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@max_id + 1, 'cs_CZ', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'de_DE', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'en_US', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'fr_FR', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'it_IT', NULL, NULL, NULL, NULL),
        (@max_id + 1, 'ru_RU', NULL, NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
