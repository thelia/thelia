SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

-- fix currency already created
update currency set by_default = 0 where by_default is NULL;

ALTER TABLE `category_version` ADD COLUMN `default_template_id` INTEGER AFTER  `position`;

-- new hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    (@max_id + 1, 'order-edit.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id + 2, 'order-edit.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id + 3, 'mini-cart', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id + 1, 'de_DE', 'Bestellung - Tabellenkopf', '', ''),
    (@max_id + 2, 'de_DE', 'Bestellung - Tabellenzeile', '', ''),
    (@max_id + 3, 'de_DE', 'Mini-Warenkorb', '', ''),
    (@max_id + 1, 'en_US', 'Order - table header', '', ''),
    (@max_id + 2, 'en_US', 'Order - table row', '', ''),
    (@max_id + 3, 'en_US', 'Mini cart', '', ''),
    (@max_id + 1, 'es_ES', 'Orden - encabezado de tabla', '', ''),
    (@max_id + 2, 'es_ES', 'Orden - fila de la tabla', '', ''),
    (@max_id + 3, 'es_ES', 'Mini tarjeta', '', ''),
    (@max_id + 1, 'fr_FR', 'Commande - colonne tableau', '', ''),
    (@max_id + 2, 'fr_FR', 'Commande - ligne tableau', '', ''),
    (@max_id + 3, 'fr_FR', 'Mini panier', '', '')
;

ALTER TABLE `rewriting_url` CHANGE `url` `url` VARBINARY( 255 ) NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
