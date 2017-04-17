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
    (@max_id + 1, 'de_DE', 'Registerkarte SEO - Update-Formular', '', ''),
    (@max_id + 2, 'de_DE', 'Bestellung bearbeiten - Produkttabelle Header', '', ''),
    (@max_id + 3, 'de_DE', 'Bestellung bearbeiten - Produkttabelle Zeile', '', ''),
    (@max_id + 4, 'de_DE', 'Administratoren - Header', '', ''),
    (@max_id + 5, 'de_DE', 'Administratoren - Zeile', '', ''),
    (@max_id + 6, 'de_DE', 'Erweiterte Konfiguration', '', ''),
    (@max_id + 7, 'de_DE', 'Erweiterte Konfiguration - JavaScript', '', ''),
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
    (@max_id + 1, 'fr_FR', 'Onglet SEO - formulaire de mise à jour', '', ''),
    (@max_id + 2, 'fr_FR', 'Modification commande - en-tête des produits', '', ''),
    (@max_id + 3, 'fr_FR', 'Modification commande - ligne du tableau des produits', '', ''),
    (@max_id + 4, 'fr_FR', 'Administrateurs - en-tête', '', ''),
    (@max_id + 5, 'fr_FR', 'Administrateurs - ligne', '', ''),
    (@max_id + 6, 'fr_FR', 'Configuration avancée', '', ''),
    (@max_id + 7, 'fr_FR', 'Configuration avancée - JavaScript', '', '')
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
