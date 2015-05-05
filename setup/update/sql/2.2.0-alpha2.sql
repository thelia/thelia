SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';


-- new config

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'smarty_cache_disabled', '0', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max_id + 1, 'en_US', 'Disable the smarty cache plugin (1 : disabled, 0 : enabled)', NULL, NULL, NULL),(@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),(@max_id + 1, 'fr_FR', 'Désactiver le plugin de cache smarty (1 : désactivé, 0 : activé)', NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;