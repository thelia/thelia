SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.5' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

ALTER TABLE `category` CHANGE `parent` `parent` INT( 11 ) NULL DEFAULT '0';
ALTER TABLE `category_version` CHANGE `parent` `parent` INT( 11 ) NULL DEFAULT '0';

SELECT @max_id := MAX(`id`) FROM hook;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    (@max_id+1, 'invoice.order-product', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+2, 'delivery.order-product', 3, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`) VALUES
    (@max_id+1, 'ar_SA', NULL, '', ''),
    (@max_id+2, 'ar_SA', NULL, '', ''),
    (@max_id+1, 'cs_CZ', NULL, '', ''),
    (@max_id+2, 'cs_CZ', NULL, '', ''),
    (@max_id+1, 'de_DE', 'Rechnung - Weitere Produktinformationen', '', ''),
    (@max_id+2, 'de_DE', 'Lieferung - Weitere Produktinformationen', '', ''),
    (@max_id+1, 'el_GR', NULL, '', ''),
    (@max_id+2, 'el_GR', NULL, '', ''),
    (@max_id+1, 'en_US', 'Invoice - additional product information', '', ''),
    (@max_id+2, 'en_US', 'Delivery - additional product information', '', ''),
    (@max_id+1, 'es_ES', 'Factura - información adicional del producto', '', ''),
    (@max_id+2, 'es_ES', 'Entrega - información adicional del producto', '', ''),
    (@max_id+1, 'fa_IR', NULL, '', ''),
    (@max_id+2, 'fa_IR', NULL, '', ''),
    (@max_id+1, 'fr_FR', 'Facture - informations additionnelles pour un produit', '', ''),
    (@max_id+2, 'fr_FR', 'Bon de livraison - informations additionnelles pour un produit', '', ''),
    (@max_id+1, 'hu_HU', NULL, '', ''),
    (@max_id+2, 'hu_HU', NULL, '', ''),
    (@max_id+1, 'id_ID', NULL, '', ''),
    (@max_id+2, 'id_ID', NULL, '', ''),
    (@max_id+1, 'it_IT', NULL, '', ''),
    (@max_id+2, 'it_IT', NULL, '', ''),
    (@max_id+1, 'pl_PL', NULL, '', ''),
    (@max_id+2, 'pl_PL', 'Dostawa - dodatkowe informacje o produkcie', '', ''),
    (@max_id+1, 'pt_BR', NULL, '', ''),
    (@max_id+2, 'pt_BR', NULL, '', ''),
    (@max_id+1, 'pt_PT', NULL, '', ''),
    (@max_id+2, 'pt_PT', NULL, '', ''),
    (@max_id+1, 'ru_RU', 'Счет-фактура - дополнительная информация о товаре', '', ''),
    (@max_id+2, 'ru_RU', 'Доставка - дополнительная информация о товаре', '', ''),
    (@max_id+1, 'sk_SK', 'Faktúra - ďalšie informácie o produkte', '', ''),
    (@max_id+2, 'sk_SK', NULL, '', ''),
    (@max_id+1, 'tr_TR', 'Fatura - daha fazla ürün bilgisi', '', ''),
    (@max_id+2, 'tr_TR', 'Dağıtım - daha fazla ürün bilgisi', '', ''),
    (@max_id+1, 'uk_UA', NULL, '', ''),
    (@max_id+2, 'uk_UA', NULL, '', '')
;

UPDATE `hook` SET `by_module` = 1 WHERE `code` = 'module.config-js';

SET FOREIGN_KEY_CHECKS = 1;