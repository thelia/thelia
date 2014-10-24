SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

INSERT INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  ('order-edit.cart-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  ('order-edit.cart-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  ('order-edit.bill-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  ('order-edit.bill-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`locale`, `title`, `description`, `chapo`) VALUES
  ('en_US', 'Order - cart top', '', ''),
  ('fr_FR', 'Commande - panier haut', '', ''),
  ('en_US', 'Order - cart bottom', '', ''),
  ('fr_FR', 'Commande - panier bas', '', ''),
  ('en_US', 'Order - bill top', '', ''),
  ('fr_FR', 'Commande - facture haut', '', ''),
  ('en_US', 'Order - bill bottom', '', ''),
  ('fr_FR', 'Commande - facture bas', '', '')
;


SET FOREIGN_KEY_CHECKS = 1;