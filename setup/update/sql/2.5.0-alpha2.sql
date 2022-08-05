SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.5.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

-- new hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'item.edition.images', 2, 0, 0, 1, 1, 1, NOW(), NOW());

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'cs_CZ', NULL, NULL, NULL),
(@max_id + 1, 'de_DE', NULL, NULL, NULL),
(@max_id + 1, 'en_US', "Edit images for an item", NULL, NULL),
(@max_id + 1, 'es_ES', NULL, NULL, NULL),
(@max_id + 1, 'fr_FR', NULL, NULL, NULL),
(@max_id + 1, 'it_IT', NULL, NULL, NULL),
(@max_id + 1, 'ru_RU', NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
