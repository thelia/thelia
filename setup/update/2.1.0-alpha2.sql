SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (@max_id+1, 'order-edit.cart-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+2, 'order-edit.cart-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+3, 'order-edit.bill-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+4, 'order-edit.bill-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+5, 'order.before-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+6, 'order.before-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+7, 'order.after-order-product-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (@max_id+8, 'order.after-order-product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW())
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
  (@max_id+8, 'fr_FR', 'Commande - Après la liste des produits', '', '')
;


SET FOREIGN_KEY_CHECKS = 1;