SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('cart.use_persistent_cookie', '0', 0, 0, NOW(), NOW()),
('cart.cookie_name', 'thelia_cart', 0, 0, NOW(), NOW()),
('cart.cookie_lifetime', '31536060', 0, 0, NOW(), NOW())
;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max_id + 1, 'en_US', 'Use a persistent cookie to keep track of customer cart', NULL, NULL, NULL),
(@max_id + 2, 'fr_FR', 'Nom du cookie de stockage du panier', NULL, NULL, NULL),
(@max_id + 3, 'en_US', 'Name the cart cookie', NULL, NULL, NULL),
(@max_id + 4, 'fr_FR', 'Nom du cookie de stockage du panier', NULL, NULL, NULL),
(@max_id + 5, 'en_US', 'Life time of the cart cookie in the customer browser, in seconds', NULL, NULL, NULL),
(@max_id + 6, 'fr_FR', 'Durée de vie du cookie du panier dans le navigateur du client, en secondes', NULL, NULL, NULL)
;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (@max_id+1, 'order-edit.cart-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+2, 'order-edit.cart-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+3, 'order-edit.bill-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+4, 'order-edit.bill-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+5, 'order-edit.before-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+6, 'order-edit.before-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+7, 'order-edit.after-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+8, 'order-edit.after-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW())
  (@max_id+9, 'sales.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+10, 'sales.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+11, 'sales.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+12, 'sales.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+13, 'sale.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+14, 'sale.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+15, 'sales.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
  (@max_id+1, 'en_US', 'Order - cart top', '', ''),
  (@max_id+1, 'fr_FR', 'Commande - panier haut', '', ''),
  (@max_id+2, 'en_US', 'Order - cart bottom', '', ''),
  (@max_id+2, 'fr_FR', 'Commande - panier bas', '', ''),
  (@max_id+3, 'en_US', 'Order - bill top', '', ''),
  (@max_id+3, 'fr_FR', 'Commande - facture haut', '', ''),
  (@max_id+4, 'en_US', 'Order - bill bottom', '', ''),
  (@max_id+4, 'fr_FR', 'Commande - facture bas', '', ''),
  (@max_id+5, 'en_US', 'Order - Before product list', '', ''),
  (@max_id+5, 'fr_FR', 'Commande - Avant la liste des produits', '', ''),
  (@max_id+6, 'en_US', 'Order - Before starting product row', '', ''),
  (@max_id+6, 'fr_FR', 'Commande - Avant d''ouvrir la ligne produit', '', ''),
  (@max_id+7, 'en_US', 'Order - After closing product row', '', ''),
  (@max_id+7, 'fr_FR', 'Commande - Après avoir fermé la ligne produit', '', ''),
  (@max_id+8, 'en_US', 'Order - After product list', '', ''),
  (@max_id+8, 'fr_FR', 'Commande - Après la liste des produits', '', ''),
  (@max_id+9, 'en_US', 'Sales - at the top', '', ''),
  (@max_id+9, 'fr_FR', 'Promotions - en haut', '', ''),
  (@max_id+10, 'en_US', 'Sales - table header', '', ''),
  (@max_id+10, 'fr_FR', 'Promotions - colonne tableau', '', ''),
  (@max_id+11, 'en_US', 'Sales - table row', '', ''),
  (@max_id+11, 'fr_FR', 'Promotions - ligne du tableau', '', ''),
  (@max_id+12, 'en_US', 'Sales - at the bottom', '', ''),
  (@max_id+12, 'fr_FR', 'Promotions - en bas', '', ''),
  (@max_id+13, 'en_US', 'Sale - create form', '', ''),
  (@max_id+13, 'fr_FR', 'Promotion - formulaire de création', '', ''),
  (@max_id+14, 'en_US', 'Sale - delete form', '', ''),
  (@max_id+14, 'fr_FR', 'Promotion - formulaire de suppression', '', ''),
  (@max_id+15, 'en_US', 'Sales - JavaScript', '', ''),
  (@max_id+15, 'fr_FR', 'Promotions - JavaScript', '', '')
;

# ======================================================================================================================
# Module version, min & max Thelia version supported
# ======================================================================================================================

ALTER TABLE `module`
  ADD COLUMN `category` VARCHAR(50) DEFAULT 'classic' NOT NULL
  AFTER `type`
;

UPDATE `module` SET `category` = 'classic' WHERE `type` = 1;
UPDATE `module` SET `category` = 'delivery' WHERE `type` = 2;
UPDATE `module` SET `category` = 'payment' WHERE `type` = 3;

ALTER TABLE `module`
  ADD COLUMN `version` VARCHAR(10) DEFAULT '' NOT NULL
  AFTER `code`
;

SET FOREIGN_KEY_CHECKS = 1;