SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Express payment zones
--
-- Whether a shop shows the express payment buttons of its payment modules,
-- and where. Empty means nowhere, which is the shop every existing boutique
-- already is: no wallet appears until the merchant turns the checkout zone on
-- and installs a module that fills it. INSERT IGNORE leaves alone a shop that
-- already chose a value.
--
-- The title goes through a temporary table so that one list serves both the
-- rows to create (INSERT IGNORE leaves alone a shop that already wrote its own
-- title) and the rows that exist with an empty title (UPDATE fills only those).
-- The temporary table lives on the connection and is dropped at the end:
-- replaying the script changes nothing.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('express_payment_zones', '', 0, 0, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS `config_title_seed`;

CREATE TEMPORARY TABLE `config_title_seed` (
    `name` VARCHAR(255) NOT NULL,
    `locale` CHAR(5) NOT NULL,
    `title` VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci';

INSERT IGNORE INTO `config_title_seed` (`name`, `locale`, `title`) VALUES
    ('express_payment_zones', 'en_US', 'Where the express payment buttons of the payment modules show, comma separated among checkout (empty: nowhere)'),
    ('express_payment_zones', 'fr_FR', 'Emplacements où s''affichent les boutons de paiement express des modules de paiement, séparés par des virgules parmi checkout (vide : nulle part)'),
    ('express_payment_zones', 'de_DE', 'Wo die Express-Zahlungsschaltflächen der Zahlungsmodule erscheinen, kommagetrennt aus checkout (leer: nirgends)'),
    ('express_payment_zones', 'es_ES', 'Dónde aparecen los botones de pago exprés de los módulos de pago, separados por comas entre checkout (vacío: en ningún sitio)'),
    ('express_payment_zones', 'nl_NL', 'Waar de expresbetaalknoppen van de betaalmodules verschijnen, door komma''s gescheiden uit checkout (leeg: nergens)'),
    ('express_payment_zones', 'ru_RU', 'Где показываются кнопки экспресс-оплаты платёжных модулей, через запятую из checkout (пусто: нигде)');

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `config_title_seed`.`locale`, `config_title_seed`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN `config_title_seed` ON `config_title_seed`.`name` = `config`.`name`;

UPDATE `config_i18n`
INNER JOIN `config` ON `config`.`id` = `config_i18n`.`id`
INNER JOIN `config_title_seed` ON `config_title_seed`.`name` = `config`.`name` AND `config_title_seed`.`locale` = `config_i18n`.`locale`
SET `config_i18n`.`title` = `config_title_seed`.`title`
WHERE `config_i18n`.`title` IS NULL OR `config_i18n`.`title` = '';

DROP TEMPORARY TABLE `config_title_seed`;

-- ---------------------------------------------------------------------
-- Product bundles
--
-- A bundle is a product whose sale elements stand for a composition of other
-- products. Every column defaults to "not a bundle", so an upgraded shop does
-- not change. The versioned copy of the product follows the product table.
-- A component whose sale element is deleted keeps its line (SET NULL): the
-- bundle is then no longer sellable if the component was required.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'is_bundle');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `is_bundle` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product is a bundle: a sellable product made of the sale elements of other products\' AFTER `free_shipping`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'bundle_price_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `bundle_price_mode` VARCHAR(16) DEFAULT \'fixed\' NOT NULL COMMENT \'fixed: the price is the one of the bundle sale element, computed: the sum of the components less the discount\' AFTER `is_bundle`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'bundle_discount');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `bundle_discount` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT \'discount of a computed bundle, in percent or in the currency amount (see bundle_discount_type)\' AFTER `bundle_price_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'bundle_discount_type');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `bundle_discount_type` VARCHAR(16) DEFAULT \'percent\' NOT NULL COMMENT \'percent or amount\' AFTER `bundle_discount`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'is_bundle');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `is_bundle` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product is a bundle: a sellable product made of the sale elements of other products\' AFTER `free_shipping`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'bundle_price_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `bundle_price_mode` VARCHAR(16) DEFAULT \'fixed\' NOT NULL COMMENT \'fixed: the price is the one of the bundle sale element, computed: the sum of the components less the discount\' AFTER `is_bundle`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'bundle_discount');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `bundle_discount` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT \'discount of a computed bundle, in percent or in the currency amount (see bundle_discount_type)\' AFTER `bundle_price_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'bundle_discount_type');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `bundle_discount_type` VARCHAR(16) DEFAULT \'percent\' NOT NULL COMMENT \'percent or amount\' AFTER `bundle_discount`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'bundle_composition');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD `bundle_composition` TEXT COMMENT \'the components a customer chose for a bundle line, as canonical JSON; null outside a bundle\' AFTER `offered_by_coupon_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

CREATE TABLE IF NOT EXISTS `product_bundle_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `bundle_product_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER COMMENT 'null once the sale element is deleted: the line stays, the bundle is no longer sellable if the line was required',
    `quantity` DECIMAL(16,6) DEFAULT 1.000000 NOT NULL COMMENT 'quantity of the component in one bundle; the fixed quantity when min and max are null',
    `min_quantity` DECIMAL(16,6) COMMENT 'lowest quantity a customer may choose for an optional component',
    `max_quantity` DECIMAL(16,6) COMMENT 'highest quantity a customer may choose for an optional component',
    `required` TINYINT DEFAULT 1 NOT NULL COMMENT 'a required component is always part of the bundle',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `uniq_product_bundle_item_bundle_product_sale_elements` (`bundle_product_id`, `product_sale_elements_id`),
    INDEX `idx_product_bundle_item_bundle_product_position` (`bundle_product_id`, `position`),
    INDEX `idx_product_bundle_item_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_product_bundle_item_bundle_product_id`
        FOREIGN KEY (`bundle_product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_bundle_item_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- A sale element is a component of a bundle once: the check of the catalogue is a read then a
-- write, and two writes that both pass it meet here. A table created by an earlier run of this
-- script gets the index too, after the duplicates it could hold are folded into the oldest line.
-- A NULL sale element (a deleted one) is never a duplicate.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_bundle_item' AND `INDEX_NAME` = 'uniq_product_bundle_item_bundle_product_sale_elements');
SET @statement := IF(@add_index, 'DELETE `newer` FROM `product_bundle_item` AS `newer` INNER JOIN `product_bundle_item` AS `older` ON `older`.`bundle_product_id` = `newer`.`bundle_product_id` AND `older`.`product_sale_elements_id` = `newer`.`product_sale_elements_id` AND `older`.`id` < `newer`.`id`', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @statement := IF(@add_index, 'ALTER TABLE `product_bundle_item` ADD UNIQUE INDEX `uniq_product_bundle_item_bundle_product_sale_elements` (`bundle_product_id`, `product_sale_elements_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- The comment of `order_product`.`parent` says what the column holds now (it said "not managed
-- yet"): a shop that upgrades reads the same schema as a new install.
SET @comment_is_old := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'parent' AND `COLUMN_COMMENT` <> 'on the line of a bundle component, the id of the bundle line (line_type bundle) that brought it; null on every other line. A component line carries the stock and the weight of one component, never a price');
SET @statement := IF(@comment_is_old, 'ALTER TABLE `order_product` MODIFY `parent` INTEGER COMMENT \'on the line of a bundle component, the id of the bundle line (line_type bundle) that brought it; null on every other line. A component line carries the stock and the weight of one component, never a price\'', 'DO 0');
PREPARE modify_column_statement FROM @statement;
EXECUTE modify_column_statement;
DEALLOCATE PREPARE modify_column_statement;

-- The comment of `order_product`.`line_type` names the two types a bundle adds.
SET @comment_is_old := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'line_type' AND `COLUMN_COMMENT` <> 'what the line stands for: a good taken off the catalogue (product), a service the shop invoices beside them such as a gift wrapping (service), the priced line of a bundle (bundle), or one component of a bundle, with no price (bundle_component). Everything that walks the lines of an order reads this to know whether it is looking at something to pick, to ship or to count as stock.');
SET @statement := IF(@comment_is_old, 'ALTER TABLE `order_product` MODIFY `line_type` VARCHAR(32) NOT NULL DEFAULT \'product\' COMMENT \'what the line stands for: a good taken off the catalogue (product), a service the shop invoices beside them such as a gift wrapping (service), the priced line of a bundle (bundle), or one component of a bundle, with no price (bundle_component). Everything that walks the lines of an order reads this to know whether it is looking at something to pick, to ship or to count as stock.\'', 'DO 0');
PREPARE modify_column_statement FROM @statement;
EXECUTE modify_column_statement;
DEALLOCATE PREPARE modify_column_statement;

-- The shop has a bundle: read by the catalogue to leave its readers alone when it has none. It
-- is kept up to date as products change shape; a value of 0 is the answer of a shop with none,
-- and INSERT IGNORE leaves a shop that already counted alone.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('catalog_has_bundles', '0', 0, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
