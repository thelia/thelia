# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('front_cart_country_cookie_name','fcccn', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('front_cart_country_cookie_expires','2592000', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('sitemap_ttl','7200', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('feed_ttl','7200', 1, 1, NOW(), NOW());

ALTER TABLE `module` ADD INDEX `idx_module_activate` (`activate`);

SELECT @max := MAX(`id`) FROM `resource`;
SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.configuration.store', NOW(), NOW()),
(@max+1, 'admin.configuration.variable', NOW(), NOW()),
(@max+2, 'admin.configuration.admin-logs', NOW(), NOW()),
(@max+3, 'admin.configuration.system-logs', NOW(), NOW()),
(@max+4, 'admin.configuration.advanced', NOW(), NOW()),
(@max+5, 'admin.configuration.translations', NOW(), NOW()),
(@max+6, 'admin.tools', NOW(), NOW()),
(@max+7, 'admin.export', NOW(), NOW()),
(@max+8, 'admin.export.customer.newsletter', NOW(), NOW())
;

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
(@max, 'de_DE', 'Shop Informationen'),
(@max+1, 'de_DE', 'Konfigurations Variablen'),
(@max+2, 'de_DE', 'Administration Logs ansehen'),
(@max+3, 'de_DE', 'Logs System Konfiguration'),
(@max+4, 'de_DE', 'Erweiterte Konfiguration'),
(@max+5, 'de_DE', 'Übersetzungen'),
(@max+6, 'de_DE', 'Tools'),
(@max+7, 'de_DE', 'Exporten-Verwaltung'),
(@max+8, 'de_DE', 'Export für die Newsletter Angemeldeten'),
(@max, 'en_US', 'Store information configuration'),
(@max+1, 'en_US', 'Configuration variables'),
(@max+2, 'en_US', 'View administration logs'),
(@max+3, 'en_US', 'Logging system configuration'),
(@max+4, 'en_US', 'Advanced configuration'),
(@max+5, 'en_US', 'Translations'),
(@max+6, 'en_US', 'Tools panel'),
(@max+7, 'en_US', 'Back-office export management'),
(@max+8, 'en_US', 'export of newsletter subscribers'),
(@max, 'es_ES', 'Configuración de la información de tienda'),
(@max+1, 'es_ES', 'Variables de configuración'),
(@max+2, 'es_ES', 'Ver logs de administración'),
(@max+3, 'es_ES', 'Configuración de sistema de registro'),
(@max+4, 'es_ES', 'Configuración avanzada'),
(@max+5, 'es_ES', 'Traducciones'),
(@max+6, 'es_ES', 'Panel de herramientas'),
(@max+7, 'es_ES', 'Gestor de exportación de Back Office'),
(@max+8, 'es_ES', 'exportación de los suscriptores del boletín de noticias'),
(@max, 'fr_FR', 'Configuration des informations sur la boutique'),
(@max+1, 'fr_FR', 'Variables de configuration'),
(@max+2, 'fr_FR', 'Consulter les logs d\'administration'),
(@max+3, 'fr_FR', 'Configuration du système de log'),
(@max+4, 'fr_FR', 'Configuration avancée'),
(@max+5, 'fr_FR', 'Traductions'),
(@max+6, 'fr_FR', 'Outils'),
(@max+7, 'fr_FR', 'gestion des exports'),
(@max+8, 'fr_FR', 'Export des inscrits à la newsletter')
;

SELECT @max := MAX(`id`) FROM `lang`;
SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Russian', 'ru', 'ru_RU', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());

SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Czech', 'cs', 'cs_CZ', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());


SET FOREIGN_KEY_CHECKS = 1;
