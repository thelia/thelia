# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';


ALTER TABLE `module` ADD INDEX `idx_module_activate` (`activate`);

SELECT @max := MAX(`id`) FROM `resource`;
SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.configuration.store', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
(@max, 'en_US', 'Store information configuration'),
(@max, 'fr_FR', 'Informations sur la boutique');

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.configuration.variable', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
(@max, 'en_US', 'Configuration variables'),
(@max, 'fr_FR', 'Variables de configuration');

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
  (@max, 'admin.configuration.admin-logs', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
  (@max, 'en_US', 'View administration logs'),
  (@max, 'fr_FR', 'Consulter les logs d\'administration');

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
  (@max, 'admin.configuration.system-logs', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
  (@max, 'en_US', 'Logging system configuration'),
  (@max, 'fr_FR', 'Configuration du système de log');

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
  (@max, 'admin.configuration.advanced', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
  (@max, 'en_US', 'Advanced configuration'),
  (@max, 'fr_FR', 'Configuration avancée');

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
  (@max, 'admin.configuration.translations', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
  (@max, 'en_US', 'Translations'),
  (@max, 'fr_FR', 'Traductions');

SET FOREIGN_KEY_CHECKS = 1;
