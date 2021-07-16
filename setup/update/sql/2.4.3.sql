SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @configIdMax := IFNULL(MAX(`id`),0) FROM `config`;

-- add new config variable allow_module_zip_install if it doesn't exists
INSERT IGNORE INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@configIdMax+1, 'allow_module_zip_install', '1', 0, 0, NOW(), NOW())

;

-- add new locales for allow_module_zip_install config variable if it doesn't exists
INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@configIdMax+1, 'de_DE', 'Modulinstallation aus ZIP-Dateien zulassen.', NULL, NULL, NULL),
    (@configIdMax+1, 'en_US', 'Allow module installation from ZIP files.', NULL, NULL, NULL),
    (@configIdMax+1, 'es_ES', 'Permitir la instalación del módulo desde archivos ZIP.', NULL, NULL, NULL),
    (@configIdMax+1, 'fr_FR', 'Autoriser l\'installation des modules par fichier ZIP.', NULL, NULL, NULL),
    (@configIdMax+1, 'ru_RU', NULL, NULL, NULL, NULL)
;

SET FOREIGN_KEY_CHECKS = 1;