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
(@max, 'ar_SA', NULL),
(@max+1, 'ar_SA', NULL),
(@max+2, 'ar_SA', NULL),
(@max+3, 'ar_SA', NULL),
(@max+4, 'ar_SA', 'التكوين المتقدم'),
(@max+5, 'ar_SA', NULL),
(@max+6, 'ar_SA', NULL),
(@max+7, 'ar_SA', NULL),
(@max+8, 'ar_SA', NULL),
(@max, 'cs_CZ', NULL),
(@max+1, 'cs_CZ', NULL),
(@max+2, 'cs_CZ', NULL),
(@max+3, 'cs_CZ', NULL),
(@max+4, 'cs_CZ', NULL),
(@max+5, 'cs_CZ', NULL),
(@max+6, 'cs_CZ', NULL),
(@max+7, 'cs_CZ', NULL),
(@max+8, 'cs_CZ', NULL),
(@max, 'de_DE', 'Shop Informationen'),
(@max+1, 'de_DE', 'Konfigurations Variablen'),
(@max+2, 'de_DE', 'Administration Logs ansehen'),
(@max+3, 'de_DE', 'Logs System Konfiguration'),
(@max+4, 'de_DE', 'Erweiterte Konfiguration'),
(@max+5, 'de_DE', 'Übersetzungen'),
(@max+6, 'de_DE', 'Tools'),
(@max+7, 'de_DE', 'Exporten-Verwaltung'),
(@max+8, 'de_DE', 'Export für die Newsletter Angemeldeten'),
(@max, 'el_GR', NULL),
(@max+1, 'el_GR', NULL),
(@max+2, 'el_GR', NULL),
(@max+3, 'el_GR', NULL),
(@max+4, 'el_GR', NULL),
(@max+5, 'el_GR', NULL),
(@max+6, 'el_GR', NULL),
(@max+7, 'el_GR', NULL),
(@max+8, 'el_GR', NULL),
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
(@max, 'fa_IR', NULL),
(@max+1, 'fa_IR', 'پیکربندی متغییرها'),
(@max+2, 'fa_IR', 'مشاهده لاگ مدیریت'),
(@max+3, 'fa_IR', NULL),
(@max+4, 'fa_IR', 'پیکربندی پیشرفته'),
(@max+5, 'fa_IR', 'ترجمه‌ها'),
(@max+6, 'fa_IR', NULL),
(@max+7, 'fa_IR', NULL),
(@max+8, 'fa_IR', NULL),
(@max, 'fr_FR', 'Configuration des informations sur la boutique'),
(@max+1, 'fr_FR', 'Variables de configuration'),
(@max+2, 'fr_FR', 'Consulter les logs d\'administration'),
(@max+3, 'fr_FR', 'Configuration du système de log'),
(@max+4, 'fr_FR', 'Configuration avancée'),
(@max+5, 'fr_FR', 'Traductions'),
(@max+6, 'fr_FR', 'Outils'),
(@max+7, 'fr_FR', 'gestion des exports'),
(@max+8, 'fr_FR', 'Export des inscrits à la newsletter'),
(@max, 'hu_HU', NULL),
(@max+1, 'hu_HU', NULL),
(@max+2, 'hu_HU', NULL),
(@max+3, 'hu_HU', NULL),
(@max+4, 'hu_HU', 'Speciális beállítások'),
(@max+5, 'hu_HU', 'Fordítások'),
(@max+6, 'hu_HU', NULL),
(@max+7, 'hu_HU', NULL),
(@max+8, 'hu_HU', NULL),
(@max, 'id_ID', NULL),
(@max+1, 'id_ID', NULL),
(@max+2, 'id_ID', NULL),
(@max+3, 'id_ID', NULL),
(@max+4, 'id_ID', NULL),
(@max+5, 'id_ID', 'Alih Bahasa'),
(@max+6, 'id_ID', NULL),
(@max+7, 'id_ID', NULL),
(@max+8, 'id_ID', NULL),
(@max, 'it_IT', NULL),
(@max+1, 'it_IT', NULL),
(@max+2, 'it_IT', NULL),
(@max+3, 'it_IT', NULL),
(@max+4, 'it_IT', 'Configurazione avanzata'),
(@max+5, 'it_IT', NULL),
(@max+6, 'it_IT', NULL),
(@max+7, 'it_IT', NULL),
(@max+8, 'it_IT', NULL),
(@max, 'pl_PL', NULL),
(@max+1, 'pl_PL', 'Zmienne konfiguracyjne'),
(@max+2, 'pl_PL', NULL),
(@max+3, 'pl_PL', NULL),
(@max+4, 'pl_PL', 'Zaawansowana konfiguracja'),
(@max+5, 'pl_PL', 'Tłumaczenia'),
(@max+6, 'pl_PL', NULL),
(@max+7, 'pl_PL', NULL),
(@max+8, 'pl_PL', NULL),
(@max, 'pt_BR', NULL),
(@max+1, 'pt_BR', NULL),
(@max+2, 'pt_BR', NULL),
(@max+3, 'pt_BR', NULL),
(@max+4, 'pt_BR', 'Configuração avançada'),
(@max+5, 'pt_BR', NULL),
(@max+6, 'pt_BR', NULL),
(@max+7, 'pt_BR', NULL),
(@max+8, 'pt_BR', NULL),
(@max, 'pt_PT', NULL),
(@max+1, 'pt_PT', NULL),
(@max+2, 'pt_PT', NULL),
(@max+3, 'pt_PT', NULL),
(@max+4, 'pt_PT', NULL),
(@max+5, 'pt_PT', NULL),
(@max+6, 'pt_PT', NULL),
(@max+7, 'pt_PT', NULL),
(@max+8, 'pt_PT', NULL),
(@max, 'ru_RU', 'Конфигурация информации магазина'),
(@max+1, 'ru_RU', 'Конфигурация переменных'),
(@max+2, 'ru_RU', 'Просмотр административных логов'),
(@max+3, 'ru_RU', 'Конфигурация системы логов'),
(@max+4, 'ru_RU', 'Расширенная конфигурация'),
(@max+5, 'ru_RU', 'Переводы'),
(@max+6, 'ru_RU', 'Панель инструментов'),
(@max+7, 'ru_RU', 'Управление экспортом в админке'),
(@max+8, 'ru_RU', 'экспорт подписчиков рассылки'),
(@max, 'sk_SK', NULL),
(@max+1, 'sk_SK', NULL),
(@max+2, 'sk_SK', NULL),
(@max+3, 'sk_SK', NULL),
(@max+4, 'sk_SK', 'Pokročilá konfigurácia'),
(@max+5, 'sk_SK', 'Preklady'),
(@max+6, 'sk_SK', NULL),
(@max+7, 'sk_SK', 'Riadenie vývozov back-office'),
(@max+8, 'sk_SK', NULL),
(@max, 'tr_TR', 'Mağaza bilgileri yapılandırma'),
(@max+1, 'tr_TR', 'Yapılandırma değişkenleri'),
(@max+2, 'tr_TR', 'Yönetim günlüklerini görüntüleme'),
(@max+3, 'tr_TR', 'Günlük sistem yapılandırmasını'),
(@max+4, 'tr_TR', 'Gelişmiş yapılandırma'),
(@max+5, 'tr_TR', 'Çeviri'),
(@max+6, 'tr_TR', 'Araçlar paneli'),
(@max+7, 'tr_TR', 'Arka ofis ihracat yönetimi'),
(@max+8, 'tr_TR', 'bülten abonesi ihracat'),
(@max, 'uk_UA', NULL),
(@max+1, 'uk_UA', NULL),
(@max+2, 'uk_UA', NULL),
(@max+3, 'uk_UA', NULL),
(@max+4, 'uk_UA', NULL),
(@max+5, 'uk_UA', NULL),
(@max+6, 'uk_UA', NULL),
(@max+7, 'uk_UA', NULL),
(@max+8, 'uk_UA', NULL)
;

SELECT @max := MAX(`id`) FROM `lang`;
SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Russian', 'ru', 'ru_RU', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());

SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Czech', 'cs', 'cs_CZ', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());


SET FOREIGN_KEY_CHECKS = 1;
