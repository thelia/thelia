SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-beta3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta3' WHERE `name`='thelia_extra_version';

-- fix hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'tab-seo.update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'order-edit.order-product-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'order-edit.order-product-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'administrators.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'administrators.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'advanced-configuration', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 7, 'advanced-configuration.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id + 1, 'ar_SA', NULL, '', ''),
    (@max_id + 2, 'ar_SA', NULL, '', ''),
    (@max_id + 3, 'ar_SA', NULL, '', ''),
    (@max_id + 4, 'ar_SA', NULL, '', ''),
    (@max_id + 5, 'ar_SA', NULL, '', ''),
    (@max_id + 6, 'ar_SA', NULL, '', ''),
    (@max_id + 7, 'ar_SA', NULL, '', ''),
    (@max_id + 1, 'cs_CZ', NULL, '', ''),
    (@max_id + 2, 'cs_CZ', NULL, '', ''),
    (@max_id + 3, 'cs_CZ', NULL, '', ''),
    (@max_id + 4, 'cs_CZ', NULL, '', ''),
    (@max_id + 5, 'cs_CZ', NULL, '', ''),
    (@max_id + 6, 'cs_CZ', NULL, '', ''),
    (@max_id + 7, 'cs_CZ', NULL, '', ''),
    (@max_id + 1, 'de_DE', 'Registerkarte SEO - Update-Formular', '', ''),
    (@max_id + 2, 'de_DE', 'Bestellung bearbeiten - Produkttabelle Header', '', ''),
    (@max_id + 3, 'de_DE', 'Bestellung bearbeiten - Produkttabelle Zeile', '', ''),
    (@max_id + 4, 'de_DE', 'Administratoren - Header', '', ''),
    (@max_id + 5, 'de_DE', 'Administratoren - Zeile', '', ''),
    (@max_id + 6, 'de_DE', 'Erweiterte Konfiguration', '', ''),
    (@max_id + 7, 'de_DE', 'Erweiterte Konfiguration - JavaScript', '', ''),
    (@max_id + 1, 'el_GR', NULL, '', ''),
    (@max_id + 2, 'el_GR', NULL, '', ''),
    (@max_id + 3, 'el_GR', NULL, '', ''),
    (@max_id + 4, 'el_GR', NULL, '', ''),
    (@max_id + 5, 'el_GR', NULL, '', ''),
    (@max_id + 6, 'el_GR', NULL, '', ''),
    (@max_id + 7, 'el_GR', NULL, '', ''),
    (@max_id + 1, 'en_US', 'Tab SEO - update form', '', ''),
    (@max_id + 2, 'en_US', 'Order edit - order product table header', '', ''),
    (@max_id + 3, 'en_US', 'Order edit - order product table row', '', ''),
    (@max_id + 4, 'en_US', 'Administrators - header', '', ''),
    (@max_id + 5, 'en_US', 'Administrators - row', '', ''),
    (@max_id + 6, 'en_US', 'Advanced Configuration', '', ''),
    (@max_id + 7, 'en_US', 'Advanced Configuration - Javascript', '', ''),
    (@max_id + 1, 'es_ES', 'Ficha SEO - formato de actualización', '', ''),
    (@max_id + 2, 'es_ES', 'Edición de Pedido - encabezado de la tabla de pedido de producto', '', ''),
    (@max_id + 3, 'es_ES', 'Edición de Pedido - fila de la tabla del pedido de producto', '', ''),
    (@max_id + 4, 'es_ES', 'Administradores - cabecera', '', ''),
    (@max_id + 5, 'es_ES', 'Administradores - fila', '', ''),
    (@max_id + 6, 'es_ES', 'Configuración avanzada', '', ''),
    (@max_id + 7, 'es_ES', 'Configuración avanzada - JavaScript', '', ''),
    (@max_id + 1, 'fa_IR', NULL, '', ''),
    (@max_id + 2, 'fa_IR', NULL, '', ''),
    (@max_id + 3, 'fa_IR', NULL, '', ''),
    (@max_id + 4, 'fa_IR', 'مدیران - هدر', '', ''),
    (@max_id + 5, 'fa_IR', 'مدیران - ردیف', '', ''),
    (@max_id + 6, 'fa_IR', 'پیکربندی پیشرفته', '', ''),
    (@max_id + 7, 'fa_IR', 'پیکربندی پیشرفته - جاوا اسکریپت', '', ''),
    (@max_id + 1, 'fr_FR', 'Onglet SEO - formulaire de mise à jour', '', ''),
    (@max_id + 2, 'fr_FR', 'Modification commande - en-tête des produits', '', ''),
    (@max_id + 3, 'fr_FR', 'Modification commande - ligne du tableau des produits', '', ''),
    (@max_id + 4, 'fr_FR', 'Administrateurs - en-tête', '', ''),
    (@max_id + 5, 'fr_FR', 'Administrateurs - ligne', '', ''),
    (@max_id + 6, 'fr_FR', 'Configuration avancée', '', ''),
    (@max_id + 7, 'fr_FR', 'Configuration avancée - JavaScript', '', ''),
    (@max_id + 1, 'hu_HU', NULL, '', ''),
    (@max_id + 2, 'hu_HU', NULL, '', ''),
    (@max_id + 3, 'hu_HU', NULL, '', ''),
    (@max_id + 4, 'hu_HU', NULL, '', ''),
    (@max_id + 5, 'hu_HU', NULL, '', ''),
    (@max_id + 6, 'hu_HU', NULL, '', ''),
    (@max_id + 7, 'hu_HU', NULL, '', ''),
    (@max_id + 1, 'id_ID', NULL, '', ''),
    (@max_id + 2, 'id_ID', NULL, '', ''),
    (@max_id + 3, 'id_ID', NULL, '', ''),
    (@max_id + 4, 'id_ID', 'Administrator - header', '', ''),
    (@max_id + 5, 'id_ID', 'Administrator - baris', '', ''),
    (@max_id + 6, 'id_ID', NULL, '', ''),
    (@max_id + 7, 'id_ID', NULL, '', ''),
    (@max_id + 1, 'it_IT', NULL, '', ''),
    (@max_id + 2, 'it_IT', NULL, '', ''),
    (@max_id + 3, 'it_IT', NULL, '', ''),
    (@max_id + 4, 'it_IT', NULL, '', ''),
    (@max_id + 5, 'it_IT', NULL, '', ''),
    (@max_id + 6, 'it_IT', NULL, '', ''),
    (@max_id + 7, 'it_IT', NULL, '', ''),
    (@max_id + 1, 'pl_PL', NULL, '', ''),
    (@max_id + 2, 'pl_PL', NULL, '', ''),
    (@max_id + 3, 'pl_PL', NULL, '', ''),
    (@max_id + 4, 'pl_PL', 'Administratorzy - nagłówek', '', ''),
    (@max_id + 5, 'pl_PL', 'Administratorzy - wiersz', '', ''),
    (@max_id + 6, 'pl_PL', 'Zaawansowana konfiguracja', '', ''),
    (@max_id + 7, 'pl_PL', 'Zaawansowana konfiguracja - po załadowaniu javascript', '', ''),
    (@max_id + 1, 'pt_BR', NULL, '', ''),
    (@max_id + 2, 'pt_BR', NULL, '', ''),
    (@max_id + 3, 'pt_BR', NULL, '', ''),
    (@max_id + 4, 'pt_BR', NULL, '', ''),
    (@max_id + 5, 'pt_BR', NULL, '', ''),
    (@max_id + 6, 'pt_BR', NULL, '', ''),
    (@max_id + 7, 'pt_BR', NULL, '', ''),
    (@max_id + 1, 'pt_PT', NULL, '', ''),
    (@max_id + 2, 'pt_PT', NULL, '', ''),
    (@max_id + 3, 'pt_PT', NULL, '', ''),
    (@max_id + 4, 'pt_PT', NULL, '', ''),
    (@max_id + 5, 'pt_PT', NULL, '', ''),
    (@max_id + 6, 'pt_PT', NULL, '', ''),
    (@max_id + 7, 'pt_PT', NULL, '', ''),
    (@max_id + 1, 'ru_RU', 'Вкладка SEO - форма обновления', '', ''),
    (@max_id + 2, 'ru_RU', 'Редактирование заказа - заголовок таблицы заказ товара', '', ''),
    (@max_id + 3, 'ru_RU', 'Редактирование заказа - строка таблицы заказ товара', '', ''),
    (@max_id + 4, 'ru_RU', 'Администраторы - заголовок', '', ''),
    (@max_id + 5, 'ru_RU', 'Администраторы - строка', '', ''),
    (@max_id + 6, 'ru_RU', 'Расширенная конфигурация', '', ''),
    (@max_id + 7, 'ru_RU', 'Расширенная конфигурация - Javascript', '', ''),
    (@max_id + 1, 'sk_SK', NULL, '', ''),
    (@max_id + 2, 'sk_SK', NULL, '', ''),
    (@max_id + 3, 'sk_SK', NULL, '', ''),
    (@max_id + 4, 'sk_SK', 'Správcovia - hlavička', '', ''),
    (@max_id + 5, 'sk_SK', NULL, '', ''),
    (@max_id + 6, 'sk_SK', 'Pokročilá konfigurácia', '', ''),
    (@max_id + 7, 'sk_SK', 'Pokročilá konfigurácia - Javascript', '', ''),
    (@max_id + 1, 'tr_TR', 'Sekme SEO - güncelleştirme formu', '', ''),
    (@max_id + 2, 'tr_TR', 'Siparişi gir - sipariş ürün tablo başlığı', '', ''),
    (@max_id + 3, 'tr_TR', 'Siparişi gir - sipariş ürün tablo satırı', '', ''),
    (@max_id + 4, 'tr_TR', 'Yöneticiler - başlık', '', ''),
    (@max_id + 5, 'tr_TR', 'Yöneticiler - kürek', '', ''),
    (@max_id + 6, 'tr_TR', 'Gelişmiş yapılandırma', '', ''),
    (@max_id + 7, 'tr_TR', 'Gelişmiş yapılandırma - JavaScript', '', ''),
    (@max_id + 1, 'uk_UA', NULL, '', ''),
    (@max_id + 2, 'uk_UA', NULL, '', ''),
    (@max_id + 3, 'uk_UA', NULL, '', ''),
    (@max_id + 4, 'uk_UA', NULL, '', ''),
    (@max_id + 5, 'uk_UA', NULL, '', ''),
    (@max_id + 6, 'uk_UA', NULL, '', ''),
    (@max_id + 7, 'uk_UA', NULL, '', '')
;

UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'main.topbar-top' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'main.topbar-bottom' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'product.combinations-row' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.top' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.table-header' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.table-row' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.bottom' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.create-form' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.delete-form' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.js' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.tab-content' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.edit-js' AND `type` = 2;

-- add index --
ALTER TABLE `rewriting_url` ADD INDEX `idx_rewriting_url` (`view_locale`, `view`, `view_id`, `redirected`);

SET FOREIGN_KEY_CHECKS = 1;
