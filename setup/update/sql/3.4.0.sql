SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Stock: sale at zero stock, reservation, low stock threshold
--
-- `product_sale_elements.out_of_stock_policy` says what happens at zero stock
-- (0 refuse, 1 sell without limit, 2 sell up to `preorder_limit` units below
-- zero). The defaults (refuse, no limit, no date, no threshold) leave every
-- existing shop behaving as before; no row is rewritten.
-- `cart_item.reserved_until` holds the stock of a line for its cart until that
-- moment; a past or empty value holds nothing.
-- `cart_item.reserved_quantity` is the quantity the listeners of the reservations
-- were told the line holds, written with `reserved_until` and emptied when the
-- line is released; an empty value on a line that has a date reads as its quantity.
-- `cart_item.reserved_since` is when the line was first held since its last
-- release, written with the first hold and emptied with the release: a hold is
-- never pushed back past a bound from that moment.
-- `order_product.is_preorder` and `restock_date` freeze, when the order is
-- placed, whether the line was sold above the physical stock.
--
-- `idx_cart_item_reserved_until` serves the purge of the dates that have run out.
--
-- Every column and the index are added through PREPARE/EXECUTE only when
-- missing, so the script can be replayed. This block stays before any DML of
-- the script.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'out_of_stock_policy');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `out_of_stock_policy` TINYINT DEFAULT 0 NOT NULL COMMENT \'what happens at zero stock: 0 refuse the sale, 1 sell without limit, 2 sell up to preorder_limit units below zero\' AFTER `manufacturer_brand_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'preorder_limit');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `preorder_limit` FLOAT COMMENT \'units that can be sold below zero stock when out_of_stock_policy is 2\' AFTER `out_of_stock_policy`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'restock_date');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `restock_date` DATE COMMENT \'date the stock is expected again, shown on preorders\' AFTER `preorder_limit`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'low_stock_threshold');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `low_stock_threshold` FLOAT COMMENT \'stock level at or under which the shop owner is alerted; null: the shop-wide threshold\' AFTER `restock_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- `bundle_composition` is only added further down by the bundle block: on a
-- 3.3 shop the column goes after `offered_by_coupon_id`, and the bundle block
-- then slides its own column in between, as on a fresh install.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'reserved_until');
SET @after_column := IF((SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'bundle_composition') > 0, 'bundle_composition', 'offered_by_coupon_id');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `cart_item` ADD `reserved_until` DATETIME COMMENT \'the stock of the line is held for the cart until this moment; null or past: not held\' AFTER `', @after_column, '`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- `reserved_until` is added just above, so it is always there to be followed.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'reserved_quantity');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD `reserved_quantity` FLOAT COMMENT \'the quantity the listeners of the reservations were told the line holds, read to tell its release; null: nothing told\' AFTER `reserved_until`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- `reserved_quantity` is added just above, so it is always there to be followed.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'reserved_since');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD `reserved_since` DATETIME COMMENT \'the moment the line was first held since its last release; a hold is never pushed back past a bound from it\' AFTER `reserved_quantity`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'is_preorder');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `is_preorder` TINYINT DEFAULT 0 NOT NULL COMMENT \'the line was sold above the physical stock, on preorder\' AFTER `virtual_document`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'restock_date');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `restock_date` DATE COMMENT \'restock date of the sale element when the line was ordered, frozen on preorder lines\' AFTER `is_preorder`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` = 'idx_cart_item_pse_reserved_until');
SET @statement := IF(@add_index, 'ALTER TABLE `cart_item` ADD INDEX `idx_cart_item_pse_reserved_until` (`product_sale_elements_id`, `reserved_until`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` = 'idx_cart_item_reserved_until');
SET @statement := IF(@add_index, 'ALTER TABLE `cart_item` ADD INDEX `idx_cart_item_reserved_until` (`reserved_until`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- ---------------------------------------------------------------------
-- Promotion priority, stop and exclusions
--
-- `priority` orders the promotions that compete for a cart (lowest first),
-- `stop_processing` sets aside the promotions after the one that applies, and
-- `coupon_exclusion` lists the pairs of promotions that never apply together
-- (one row per direction: the pair is written both ways).
--
-- The defaults (priority 0, no stop, no exclusion) leave every existing
-- promotion behaving as before; no row is rewritten. `coupon` is versionable,
-- so both columns are mirrored in `coupon_version`.
--
-- The table is created through PREPARE/EXECUTE only when it is missing: a bare
-- CREATE TABLE IF NOT EXISTS commits the transaction ModuleManagement opened,
-- even when the table exists. This block stays before any DML of the script.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `COLUMN_NAME` = 'priority');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon` ADD `priority` INTEGER DEFAULT 0 NOT NULL COMMENT \'the order in which promotions are considered, lowest first, a tie going to the oldest promotion\' AFTER `per_customer_usage_count`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `COLUMN_NAME` = 'stop_processing');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon` ADD `stop_processing` TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'once this promotion applies, the promotions after it are set aside\' AFTER `priority`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon_version' AND `COLUMN_NAME` = 'priority');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon_version` ADD `priority` INTEGER DEFAULT 0 NOT NULL COMMENT \'the order in which promotions are considered, lowest first, a tie going to the oldest promotion\' AFTER `per_customer_usage_count`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon_version' AND `COLUMN_NAME` = 'stop_processing');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon_version` ADD `stop_processing` TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'once this promotion applies, the promotions after it are set aside\' AFTER `priority`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `INDEX_NAME` = 'idx_coupon_priority');
SET @statement := IF(@add_index, 'ALTER TABLE `coupon` ADD INDEX `idx_coupon_priority` (`priority`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @create_table := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon_exclusion');
SET @statement := IF(@create_table, 'CREATE TABLE `coupon_exclusion` (`coupon_id` INTEGER NOT NULL, `excluded_coupon_id` INTEGER NOT NULL, PRIMARY KEY (`coupon_id`, `excluded_coupon_id`), INDEX `fk_coupon_exclusion_excluded_coupon_id_idx` (`excluded_coupon_id`), CONSTRAINT `fk_coupon_exclusion_coupon_id` FOREIGN KEY (`coupon_id`) REFERENCES `coupon` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE, CONSTRAINT `fk_coupon_exclusion_excluded_coupon_id` FOREIGN KEY (`excluded_coupon_id`) REFERENCES `coupon` (`id`) ON UPDATE RESTRICT ON DELETE CASCADE) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ROW_FORMAT=DYNAMIC', 'DO 0');
PREPARE create_table_statement FROM @statement;
EXECUTE create_table_statement;
DEALLOCATE PREPARE create_table_statement;

-- ---------------------------------------------------------------------
-- AI reasoning effort
--
-- `ai_tier_assignment.effort` is the effort of a tier, `ai_usage_setting.effort`
-- the one a usage sets for itself (empty: the effort of its tier). Empty means
-- no effort: nothing is sent and the provider keeps its own default, which is
-- what every existing shop already runs. No row is rewritten. Same guard as the
-- columns above, so the script can be replayed.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'ai_tier_assignment' AND `COLUMN_NAME` = 'effort');
SET @statement := IF(@add_column, 'ALTER TABLE `ai_tier_assignment` ADD `effort` VARCHAR(16) COMMENT \'how hard the model reasons: low, medium, high, xhigh or max; empty: nothing is sent, the provider keeps its default\' AFTER `model_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'ai_usage_setting' AND `COLUMN_NAME` = 'effort');
SET @statement := IF(@add_column, 'ALTER TABLE `ai_usage_setting` ADD `effort` VARCHAR(16) COMMENT \'how hard the model reasons for this usage: low, medium, high, xhigh or max; empty: the effort of the tier\' AFTER `pinned_model_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Provenance mark of generated content, off by default
--
-- A generative usage marks what it writes only once the merchant turns the
-- mark on in its AI settings. A shop that saved the settings of a usage keeps
-- the value it saved: only the default of the column changes, no row is
-- rewritten. Replaying the script changes nothing.
-- ---------------------------------------------------------------------

ALTER TABLE `ai_usage_setting` MODIFY `provenance_mark` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '1 puts the provenance mark on what a generative usage writes (AI Act 50(2)); off by default, the usage then shows as not compliant';

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

-- ---------------------------------------------------------------------
-- Customer groups
--
-- A group carries a discount rate, how the prices are shown (with or without
-- taxes), its own prices per sale element and currency, the payment modules
-- and the categories it is restricted to. Every customer belongs to exactly
-- one group, the default one until an administrator moves the account, and
-- the group of the buyer is frozen on the order.
--
-- Replayable: each table is created only when missing, each column, index and
-- foreign key is added only when missing, and the default group is seeded
-- only while no group is the default.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customer_group`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'stable machine name a deployment references a customer group by; read by the front through the customer group API, not meant as a label',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `is_default` TINYINT DEFAULT 0 NOT NULL COMMENT 'the group a new account and a visitor belong to; exactly one',
    `discount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the discount rate of the group, as a percentage, applied before the discount of the customer',
    `display_taxed_price` TINYINT DEFAULT 1 NOT NULL COMMENT 'whether the prices are shown with taxes (1) or without taxes (0) to the members of the group',
    `restricts_categories` TINYINT DEFAULT 0 NOT NULL COMMENT '1 when the group may browse only the categories it lists (customer_group_category), an empty list included; 0 opens the whole catalog; set by the writer of the list alone, never by the deletion of a listed category',
    `restricts_payment_modules` TINYINT DEFAULT 0 NOT NULL COMMENT '1 when the group may pay only with the modules it lists (customer_group_payment_module), an empty list included; 0 opens every module; set by the writer of the list alone, never by the deletion of a listed module',
    `registration_token` VARCHAR(64) COMMENT 'secret token of the registration link of the group: a visitor who registers through it joins the group; NULL when the link is closed; never read by the front, never written by a client',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `customer_group_code_unique` (`code`),
    UNIQUE INDEX `customer_group_registration_token_unique` (`registration_token`),
    INDEX `idx_customer_group_is_default` (`is_default`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `customer_group_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `customer_group_i18n_fk_6a2a90`
        FOREIGN KEY (`id`)
        REFERENCES `customer_group` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- What a group pays for a sale element in a currency, when it does not pay the
-- base price less its discount. A NULL price or promotional price means the
-- group has no price of its own for it.
CREATE TABLE IF NOT EXISTS `customer_group_product_price`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_group_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) COMMENT 'the price without taxes the group pays, NULL when the group has no price of its own',
    `promo_price` DECIMAL(16,6) COMMENT 'the promotional price without taxes the group pays, NULL when the group has no promotional price of its own',
    `from_default_currency` TINYINT DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `customer_group_product_price_unique` (`customer_group_id`, `product_sale_elements_id`, `currency_id`),
    INDEX `idx_customer_group_product_price_product_sale_elements_id` (`product_sale_elements_id`),
    INDEX `idx_customer_group_product_price_currency_id` (`currency_id`),
    CONSTRAINT `fk_customer_group_product_price_customer_group_id`
        FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_customer_group_product_price_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_customer_group_product_price_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The payment modules and the categories a group is restricted to. The list is
-- read only when the group says it is restricted (customer_group.restricts_*):
-- then the rows are the only ones open, and none when the list is empty, which
-- is what deleting the one category or module listed leaves.
CREATE TABLE IF NOT EXISTS `customer_group_payment_module`
(
    `customer_group_id` INTEGER NOT NULL COMMENT 'a payment module the customer group may pay with, read only when customer_group.restricts_payment_modules is 1: then the modules listed are the only ones, and none when the list is empty',
    `module_id` INTEGER NOT NULL,
    PRIMARY KEY (`customer_group_id`,`module_id`),
    INDEX `idx_customer_group_payment_module_module_id` (`module_id`),
    CONSTRAINT `fk_customer_group_payment_module_customer_group_id`
        FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_customer_group_payment_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `customer_group_category`
(
    `customer_group_id` INTEGER NOT NULL COMMENT 'a category the customer group may browse, read only when customer_group.restricts_categories is 1: then the categories listed are the only ones open, and none when the list is empty',
    `category_id` INTEGER NOT NULL,
    PRIMARY KEY (`customer_group_id`,`category_id`),
    INDEX `idx_customer_group_category_category_id` (`category_id`),
    CONSTRAINT `fk_customer_group_category_customer_group_id`
        FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_customer_group_category_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The groups a sale, and a catalog price rule, are reserved for when their
-- audience mode is the customer groups one (2).
CREATE TABLE IF NOT EXISTS `sale_customer_group`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_id` INTEGER NOT NULL,
    `customer_group_id` INTEGER NOT NULL COMMENT 'a customer group the operation is reserved for, read when audience_mode is 2',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_sale_customer_group_sales_id_group_id` (`sale_id`, `customer_group_id`),
    INDEX `fk_sale_customer_group_group_idx` (`customer_group_id`),
    CONSTRAINT `fk_sale_customer_group_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_customer_group_group_id`
        FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_customer_group`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `catalog_price_rule_id` INTEGER NOT NULL,
    `customer_group_id` INTEGER NOT NULL COMMENT 'a customer group the rule prices for, read when audience_mode is 2',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_catalog_price_rule_customer_group_rule_id_group_id` (`catalog_price_rule_id`, `customer_group_id`),
    INDEX `fk_catalog_price_rule_customer_group_group_idx` (`customer_group_id`),
    CONSTRAINT `fk_catalog_price_rule_customer_group_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_customer_group_group_id`
        FOREIGN KEY (`customer_group_id`)
        REFERENCES `customer_group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- Whether a group is restricted. A group that lists categories or payment modules
-- on a database that already has the lists is restricted to them: the flag is
-- derived from the lists once, when the column is added, and never again, so that
-- a replay does not reopen a group whose list was emptied by the deletion of the
-- category or the module it listed.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `COLUMN_NAME` = 'restricts_categories');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `customer_group` ADD COLUMN `restricts_categories` TINYINT DEFAULT 0 NOT NULL COMMENT ', QUOTE('1 when the group may browse only the categories it lists (customer_group_category), an empty list included; 0 opens the whole catalog; set by the writer of the list alone, never by the deletion of a listed category'), ' AFTER `display_taxed_price`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

UPDATE `customer_group` SET `restricts_categories` = EXISTS (SELECT 1 FROM `customer_group_category` WHERE `customer_group_category`.`customer_group_id` = `customer_group`.`id`) WHERE @add_column;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `COLUMN_NAME` = 'restricts_payment_modules');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `customer_group` ADD COLUMN `restricts_payment_modules` TINYINT DEFAULT 0 NOT NULL COMMENT ', QUOTE('1 when the group may pay only with the modules it lists (customer_group_payment_module), an empty list included; 0 opens every module; set by the writer of the list alone, never by the deletion of a listed module'), ' AFTER `restricts_categories`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

UPDATE `customer_group` SET `restricts_payment_modules` = EXISTS (SELECT 1 FROM `customer_group_payment_module` WHERE `customer_group_payment_module`.`customer_group_id` = `customer_group`.`id`) WHERE @add_column;

-- The registration link of a group. A database that has the table without the column
-- gets both the column and its unique index; a replay finds them and does nothing.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `COLUMN_NAME` = 'registration_token');
SET @statement := IF(@add_column, CONCAT('ALTER TABLE `customer_group` ADD COLUMN `registration_token` VARCHAR(64) NULL COMMENT ', QUOTE('secret token of the registration link of the group: a visitor who registers through it joins the group; NULL when the link is closed; never read by the front, never written by a client'), ' AFTER `restricts_payment_modules`'), 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_group' AND `INDEX_NAME` = 'customer_group_registration_token_unique');
SET @statement := IF(@add_index, 'ALTER TABLE `customer_group` ADD UNIQUE INDEX `customer_group_registration_token_unique` (`registration_token`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- The default group, which every existing customer joins below. Seeded before
-- the customer column exists, and only while no group is the default.
INSERT IGNORE INTO `customer_group` (`code`, `position`, `is_default`, `discount`, `display_taxed_price`, `created_at`, `updated_at`)
    SELECT 'default', 1, 1, 0.000000, 1, NOW(), NOW() FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `customer_group` WHERE `is_default` = 1);

-- One row per language the fresh install seeds, so the back office falls back
-- on the default language instead of showing a group with no name.
SET @default_customer_group_id := (SELECT `id` FROM `customer_group` WHERE `is_default` = 1 ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `customer_group_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (@default_customer_group_id, 'cs_CZ', NULL, NULL),
    (@default_customer_group_id, 'de_DE', 'Standard-Kundengruppe', NULL),
    (@default_customer_group_id, 'en_US', 'Default customer group', NULL),
    (@default_customer_group_id, 'es_ES', 'Grupo de clientes por defecto', NULL),
    (@default_customer_group_id, 'fr_FR', 'Groupe de clients par défaut', NULL),
    (@default_customer_group_id, 'it_IT', 'Gruppo di clienti predefinito', NULL),
    (@default_customer_group_id, 'nl_NL', 'Standaard klantgroep', NULL),
    (@default_customer_group_id, 'ru_RU', 'Группа клиентов по умолчанию', NULL);

-- The group of a customer. NOT NULL, so it goes in three steps: the column
-- nullable, every existing customer put in the default group, then the column
-- made required. `customer` is versionable: the column is mirrored on
-- `customer_version`, whose rows go through the same three steps.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `COLUMN_NAME` = 'customer_group_id');
SET @statement := IF(@add_column, 'ALTER TABLE `customer` ADD COLUMN `customer_group_id` INTEGER NULL COMMENT ''the customer group the account belongs to, the default group until an administrator moves it'' AFTER `is_guest`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

UPDATE `customer` SET `customer_group_id` = @default_customer_group_id WHERE `customer_group_id` IS NULL;

SET @make_required := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `COLUMN_NAME` = 'customer_group_id' AND `IS_NULLABLE` = 'YES');
SET @statement := IF(@make_required, 'ALTER TABLE `customer` MODIFY `customer_group_id` INTEGER NOT NULL COMMENT ''the customer group the account belongs to, the default group until an administrator moves it''', 'DO 0');
PREPARE make_required_statement FROM @statement;
EXECUTE make_required_statement;
DEALLOCATE PREPARE make_required_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `INDEX_NAME` = 'idx_customer_customer_group_id');
SET @statement := IF(@add_index, 'ALTER TABLE `customer` ADD INDEX `idx_customer_customer_group_id` (`customer_group_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `CONSTRAINT_NAME` = 'fk_customer_customer_group_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `customer` ADD CONSTRAINT `fk_customer_customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'customer_group_id');
SET @statement := IF(@add_column, 'ALTER TABLE `customer_version` ADD COLUMN `customer_group_id` INTEGER NULL COMMENT ''the customer group the account belongs to, the default group until an administrator moves it'' AFTER `is_guest`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

UPDATE `customer_version` SET `customer_group_id` = @default_customer_group_id WHERE `customer_group_id` IS NULL;

SET @make_required := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'customer_group_id' AND `IS_NULLABLE` = 'YES');
SET @statement := IF(@make_required, 'ALTER TABLE `customer_version` MODIFY `customer_group_id` INTEGER NOT NULL COMMENT ''the customer group the account belongs to, the default group until an administrator moves it''', 'DO 0');
PREPARE make_required_statement FROM @statement;
EXECUTE make_required_statement;
DEALLOCATE PREPARE make_required_statement;

-- The group the buyer belonged to, and the discount rate it gave, frozen on the
-- order like the customer discount rate beside it. NULL group for an order that
-- predates the groups, so an existing shop is untouched. `order` is versionable:
-- the columns are mirrored on `order_version`.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'customer_group_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `customer_group_id` INTEGER NULL DEFAULT NULL COMMENT ''the customer group the buyer belonged to when the order was placed, NULL for an order that predates the groups or once the group is deleted'' AFTER `sale_unit_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'customer_group_discount_rate');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `customer_group_discount_rate` DECIMAL(16,6) DEFAULT 0.000000 COMMENT ''the customer group discount rate, as a percentage, already included in the order products prices'' AFTER `customer_group_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'customer_group_display_taxed_price');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `customer_group_display_taxed_price` TINYINT DEFAULT 1 NOT NULL COMMENT ''the customer group display mode when the order was placed: 1 taxed prices, 0 untaxed prices'' AFTER `customer_group_discount_rate`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'fi_order_customer_group_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `fi_order_customer_group_id` (`customer_group_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_customer_group_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_customer_group_id` FOREIGN KEY (`customer_group_id`) REFERENCES `customer_group` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'customer_group_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `customer_group_id` INTEGER NULL DEFAULT NULL COMMENT ''the customer group the buyer belonged to when the order was placed, NULL for an order that predates the groups or once the group is deleted'' AFTER `sale_unit_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'customer_group_discount_rate');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `customer_group_discount_rate` DECIMAL(16,6) DEFAULT 0.000000 COMMENT ''the customer group discount rate, as a percentage, already included in the order products prices'' AFTER `customer_group_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'customer_group_display_taxed_price');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `customer_group_display_taxed_price` TINYINT DEFAULT 1 NOT NULL COMMENT ''the customer group display mode when the order was placed: 1 taxed prices, 0 untaxed prices'' AFTER `customer_group_discount_rate`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.customer.group', NOW(), NOW());

-- One row per language the fresh install seeds, so the profile screen falls
-- back on the default language instead of showing no row at all.
SET @customer_group_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.customer.group');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@customer_group_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@customer_group_resource_id, 'de_DE', 'Kundengruppen', NULL, NULL, NULL),
    (@customer_group_resource_id, 'en_US', 'Customer groups', NULL, NULL, NULL),
    (@customer_group_resource_id, 'es_ES', 'Grupos de clientes', NULL, NULL, NULL),
    (@customer_group_resource_id, 'fr_FR', 'Groupes de clients', NULL, NULL, NULL),
    (@customer_group_resource_id, 'it_IT', 'Gruppi di clienti', NULL, NULL, NULL),
    (@customer_group_resource_id, 'nl_NL', 'Klantgroepen', NULL, NULL, NULL),
    (@customer_group_resource_id, 'ru_RU', 'Группы клиентов', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Stock settings and the low stock alert message
--
-- Reservation is off, starts at the checkout and lasts 15 minutes; the shop
-- wide low stock threshold is 5. INSERT IGNORE leaves alone a shop that
-- already chose a value. The titles are filled the same way as the express
-- payment zones above: only the rows that are missing or empty.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('stock_reservation_enabled', '0', 0, 0, NOW(), NOW()),
    ('stock_reservation_scope', 'checkout', 0, 0, NOW(), NOW()),
    ('stock_reservation_duration', '15', 0, 0, NOW(), NOW()),
    ('low_stock_threshold', '5', 0, 0, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS `config_title_seed`;

CREATE TEMPORARY TABLE `config_title_seed` (
    `name` VARCHAR(255) NOT NULL,
    `locale` CHAR(5) NOT NULL,
    `title` VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci';

INSERT IGNORE INTO `config_title_seed` (`name`, `locale`, `title`) VALUES
    ('stock_reservation_enabled', 'en_US', 'Hold the stock of the items of a cart while the customer checks out (1 = yes, 0 = no)'),
    ('stock_reservation_enabled', 'fr_FR', 'Réserver le stock des articles d''un panier pendant que le client commande (1 = oui, 0 = non)'),
    ('stock_reservation_enabled', 'de_DE', 'Den Bestand der Artikel eines Warenkorbs während der Bestellung reservieren (1 = ja, 0 = nein)'),
    ('stock_reservation_enabled', 'es_ES', 'Reservar el stock de los artículos de un carrito mientras el cliente finaliza la compra (1 = sí, 0 = no)'),
    ('stock_reservation_enabled', 'nl_NL', 'De voorraad van de artikelen in een winkelwagen reserveren terwijl de klant afrekent (1 = ja, 0 = nee)'),
    ('stock_reservation_enabled', 'ru_RU', 'Резервировать запас товаров корзины, пока клиент оформляет заказ (1 = да, 0 = нет)'),
    ('stock_reservation_scope', 'en_US', 'When the stock of a cart is held: checkout (from the start of the checkout) or cart (as soon as an item is in the cart)'),
    ('stock_reservation_scope', 'fr_FR', 'Moment où le stock d''un panier est réservé : checkout (dès l''entrée dans le tunnel de commande) ou cart (dès la mise au panier)'),
    ('stock_reservation_scope', 'de_DE', 'Wann der Bestand eines Warenkorbs reserviert wird: checkout (ab Beginn der Bestellung) oder cart (sobald ein Artikel im Warenkorb liegt)'),
    ('stock_reservation_scope', 'es_ES', 'Momento en que se reserva el stock de un carrito: checkout (desde el inicio del pedido) o cart (en cuanto un artículo está en el carrito)'),
    ('stock_reservation_scope', 'nl_NL', 'Wanneer de voorraad van een winkelwagen wordt gereserveerd: checkout (vanaf het begin van het afrekenen) of cart (zodra een artikel in de winkelwagen ligt)'),
    ('stock_reservation_scope', 'ru_RU', 'Когда резервируется запас корзины: checkout (с начала оформления заказа) или cart (как только товар попал в корзину)'),
    ('stock_reservation_duration', 'en_US', 'Minutes a stock reservation is held, extended at each step of the checkout (15 when invalid)'),
    ('stock_reservation_duration', 'fr_FR', 'Minutes pendant lesquelles une réservation de stock est conservée, prolongée à chaque étape du tunnel de commande (15 si invalide)'),
    ('stock_reservation_duration', 'de_DE', 'Minuten, die eine Bestandsreservierung gehalten wird, bei jedem Bestellschritt verlängert (15 bei ungültigem Wert)'),
    ('stock_reservation_duration', 'es_ES', 'Minutos que se mantiene una reserva de stock, ampliada en cada paso del pedido (15 si no es válido)'),
    ('stock_reservation_duration', 'nl_NL', 'Minuten dat een voorraadreservering wordt vastgehouden, bij elke stap van het afrekenen verlengd (15 bij een ongeldige waarde)'),
    ('stock_reservation_duration', 'ru_RU', 'Минуты хранения резерва запаса, продлевается на каждом шаге оформления заказа (15 при неверном значении)'),
    ('low_stock_threshold', 'en_US', 'Stock level at or under which the shop owner is alerted that a sale element runs low (5 when invalid), a sale element can set its own'),
    ('low_stock_threshold', 'fr_FR', 'Niveau de stock à partir duquel le gérant est averti qu''une déclinaison s''épuise (5 si invalide), une déclinaison peut fixer le sien'),
    ('low_stock_threshold', 'de_DE', 'Bestandsmenge, ab der der Shopbetreiber vor einer knappen Variante gewarnt wird (5 bei ungültigem Wert), eine Variante kann einen eigenen Wert festlegen'),
    ('low_stock_threshold', 'es_ES', 'Nivel de stock a partir del cual se avisa al gestor de que una variante se agota (5 si no es válido), una variante puede fijar el suyo'),
    ('low_stock_threshold', 'nl_NL', 'Voorraadniveau waarop de winkeleigenaar wordt gewaarschuwd dat een variant bijna op is (5 bij een ongeldige waarde), een variant kan een eigen waarde instellen'),
    ('low_stock_threshold', 'ru_RU', 'Уровень запаса, при котором владелец магазина получает уведомление о нехватке комбинации (5 при неверном значении), комбинация может задать свой');

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

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('low_stock_alert', NULL, 'low_stock_alert.txt', 'low_stock_alert.html', NOW(), NOW());

-- Read back by name rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @low_stock_alert_message_id := (SELECT `id` FROM `message` WHERE `name` = 'low_stock_alert');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@low_stock_alert_message_id, 'cs_CZ', NULL, 'Nízký stav zásob na {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'de_DE', 'An den Shopbetreiber gesendete Warnung bei knappem Bestand', 'Geringer Bestand bei {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'en_US', 'Message sent to the shop owner when the stock of a sale element runs low', 'Low stock on {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'es_ES', 'Aviso enviado al gestor de la tienda cuando se agota el stock de una variante', 'Stock bajo en {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'fr_FR', 'Message envoyé au gérant de la boutique quand le stock d''une déclinaison s''épuise', 'Stock bas sur {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'it_IT', NULL, 'Scorte basse su {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'nl_NL', 'Bericht aan de winkeleigenaar wanneer de voorraad van een variant bijna op is', 'Lage voorraad bij {{ config("store_name") }}'),
    (@low_stock_alert_message_id, 'ru_RU', 'Уведомление владельцу магазина о нехватке запаса комбинации', 'Низкий запас в {{ config("store_name") }}');

-- ---------------------------------------------------------------------
-- Newsletter switch
--
-- `newsletter_enabled` turns the visitor sign-up off when it is 0. It is
-- seeded at 1, so an updated shop keeps offering the newsletter, and
-- INSERT IGNORE leaves a value the merchant already set. Replaying the script
-- changes nothing.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('newsletter_enabled', '1', 0, 0, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS `config_title_seed`;

CREATE TEMPORARY TABLE `config_title_seed` (
    `name` VARCHAR(255) NOT NULL,
    `locale` CHAR(5) NOT NULL,
    `title` VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci';

INSERT IGNORE INTO `config_title_seed` (`name`, `locale`, `title`) VALUES
    ('newsletter_enabled', 'en_US', 'Offer newsletter sign-up to visitors (1 = yes, 0 = no)'),
    ('newsletter_enabled', 'fr_FR', 'Proposer l''inscription à la newsletter aux visiteurs (1 = oui, 0 = non)'),
    ('newsletter_enabled', 'de_DE', 'Besuchern die Newsletter-Anmeldung anbieten (1 = ja, 0 = nein)'),
    ('newsletter_enabled', 'es_ES', 'Ofrecer a los visitantes la suscripción al boletín (1 = sí, 0 = no)'),
    ('newsletter_enabled', 'nl_NL', 'Bezoekers de nieuwsbriefaanmelding aanbieden (1 = ja, 0 = nee)'),
    ('newsletter_enabled', 'ru_RU', 'Предлагать посетителям подписку на рассылку (1 = да, 0 = нет)');

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
-- Reports sent by e-mail
--
-- A report mailing sends an export, over a relative period, to a list of
-- recipients on a schedule. Replayable: the table is created only when
-- missing, and INSERT IGNORE leaves the message a shop already has alone. The message
-- carries a stored body, which stands in for the template of the mail theme when the
-- theme in use has none (a theme older than the report e-mail).
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `report_mailing`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'what the merchant calls the report; the subject and the title of the e-mail',
    `export_ref` VARCHAR(255) NOT NULL COMMENT 'ref of the export the report is made of',
    `period` VARCHAR(32) NOT NULL COMMENT 'one of ReportPeriod: the period the export covers, relative to the day it is sent',
    `frequency` VARCHAR(16) NOT NULL COMMENT 'one of ReportFrequency: daily, weekly or monthly',
    `day_of_week` TINYINT COMMENT 'weekly: the day of the week, 1 (Monday) to 7 (Sunday)',
    `day_of_month` TINYINT COMMENT 'monthly: the day of the month, 1 to 28',
    `send_hour` TINYINT DEFAULT 7 NOT NULL COMMENT 'the hour of the day, in the timezone of PHP, from which the report is sent',
    `locale` VARCHAR(10) NOT NULL COMMENT 'the language of the e-mail and of the export',
    `recipients` TEXT NOT NULL COMMENT 'e-mail addresses, comma separated',
    `enabled` TINYINT DEFAULT 1 NOT NULL,
    `last_period_end` DATE COMMENT 'the end of the last period sent to every recipient',
    `pending_recipients` TEXT COMMENT 'the recipients the last send failed for, comma separated, to be tried again on their own; NULL when none',
    `pending_attempts` TINYINT DEFAULT 0 NOT NULL COMMENT 'how many times the period has been tried again for the recipients still waiting; back to 0 with each new period',
    `last_sent_at` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('report_mail', NULL, 'report_mail.txt', 'report_mail.html', NOW(), NOW());

-- Read back by name rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @report_mail_message_id := (SELECT `id` FROM `message` WHERE `name` = 'report_mail');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
    (@report_mail_message_id, 'cs_CZ', NULL, '{{ report_title }}: od {{ period_start }} do {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'de_DE', 'Per E-Mail versendeter Bericht', '{{ report_title }}: vom {{ period_start }} bis {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'en_US', 'Report sent by e-mail', '{{ report_title }}: {{ period_start }} to {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'es_ES', 'Informe enviado por correo electrónico', '{{ report_title }}: del {{ period_start }} al {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'fr_FR', 'Rapport envoyé par e-mail', '{{ report_title }} : du {{ period_start }} au {{ period_end }}', 'Bonjour,<br><br>{{ report_title }}<br><br>Période : du {{ period_start }} au {{ period_end }}<br>Export : {{ export_title }}<br>{% if is_empty %}<br>Rien à signaler sur cette période : aucune donnée, donc aucun fichier joint.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>Le fichier complet est joint : {{ attachment_name }}{% elseif download_url %}<br>Télécharger le rapport: {{ download_url|raw }}<br>Ce lien expire le {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Bonjour,</p><p><strong>{{ report_title }}</strong></p><p>Période : du {{ period_start }} au {{ period_end }}<br>Export : {{ export_title }}</p>{% if is_empty %}<p>Rien à signaler sur cette période : aucune donnée, donc aucun fichier joint.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>Le fichier complet est joint : {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Télécharger le rapport</a><br>Ce lien expire le {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'it_IT', NULL, '{{ report_title }}: dal {{ period_start }} al {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'nl_NL', 'Per e-mail verzonden rapport', '{{ report_title }}: van {{ period_start }} tot {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}'),
    (@report_mail_message_id, 'ru_RU', 'Отчёт, отправленный по электронной почте', '{{ report_title }}: с {{ period_start }} по {{ period_end }}', 'Hello,<br><br>{{ report_title }}<br><br>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}<br>{% if is_empty %}<br>Nothing to report for this period: no data, so no file is attached.{% else %}{% if summary %}<br>{% for line in summary %}{{ line.label }}: {{ line.value }}<br>{% endfor %}{% endif %}{% if attachment_name %}<br>The complete file is attached: {{ attachment_name }}{% elseif download_url %}<br>Download the report: {{ download_url|raw }}<br>This link expires on {{ download_expires_at }}.{% endif %}{% endif %}', '<p>Hello,</p><p><strong>{{ report_title }}</strong></p><p>Period: from {{ period_start }} to {{ period_end }}<br>Export: {{ export_title }}</p>{% if is_empty %}<p>Nothing to report for this period: no data, so no file is attached.</p>{% else %}{% if summary %}<ul>{% for line in summary %}<li>{{ line.label }}: <strong>{{ line.value }}</strong></li>{% endfor %}</ul>{% endif %}{% if attachment_name %}<p>The complete file is attached: {{ attachment_name }}</p>{% elseif download_url %}<p><a href="{{ download_url }}">Download the report</a><br>This link expires on {{ download_expires_at }}.</p>{% endif %}{% endif %}');

INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.order.edit', NOW(), NOW());

SET @order_edit_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.order.edit');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@order_edit_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@order_edit_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@order_edit_resource_id, 'en_US', 'Editing the lines of an order', NULL, NULL, NULL),
    (@order_edit_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@order_edit_resource_id, 'fr_FR', 'Modification des lignes d\'une commande', NULL, NULL, NULL),
    (@order_edit_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@order_edit_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@order_edit_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

-- Report of an export job
--
-- What an export reported (the counts of the sales journal, the orders it
-- left out) is kept on its job, for the screen of the job to show once the
-- worker is done. Added only when missing, so a replay changes nothing.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'export_job' AND `COLUMN_NAME` = 'report');
SET @statement := IF(@add_column, 'ALTER TABLE `export_job` ADD `report` TEXT COMMENT \'what the export reported: its lines and warnings, as JSON, NULL for an export that reports nothing\' AFTER `error`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Accounting exports
--
-- The sales journal and the tax summary select the invoices of a period by
-- their date. Their chart of accounts is five settings, seeded empty: the
-- exports refuse to run until the merchant fills the accounts, and the
-- journal code and label fall back to VE and Ventes. INSERT IGNORE leaves a
-- value the merchant already set, and replaying the script changes nothing.
-- ---------------------------------------------------------------------

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'idx_order_invoice_date');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `idx_order_invoice_date` (`invoice_date`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('accounting_journal_code', '', 0, 0, NOW(), NOW()),
    ('accounting_journal_label', '', 0, 0, NOW(), NOW()),
    ('accounting_customer_account', '', 0, 0, NOW(), NOW()),
    ('accounting_shipping_account', '', 0, 0, NOW(), NOW()),
    ('accounting_rate_accounts', '', 0, 0, NOW(), NOW()),
    ('accounting_commercial_credit_account', '', 0, 0, NOW(), NOW());

DROP TEMPORARY TABLE IF EXISTS `config_title_seed`;

CREATE TEMPORARY TABLE `config_title_seed` (
    `name` VARCHAR(255) NOT NULL,
    `locale` CHAR(5) NOT NULL,
    `title` VARCHAR(255) NOT NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci';

INSERT IGNORE INTO `config_title_seed` (`name`, `locale`, `title`) VALUES
    ('accounting_journal_code', 'en_US', 'Code of the sales journal in the accounting exports (VE when empty)'),
    ('accounting_journal_code', 'fr_FR', 'Code du journal des ventes dans les exports comptables (VE si vide)'),
    ('accounting_journal_code', 'de_DE', 'Code des Verkaufsjournals in den Buchhaltungsexporten (VE wenn leer)'),
    ('accounting_journal_code', 'es_ES', 'Código del diario de ventas en las exportaciones contables (VE si está vacío)'),
    ('accounting_journal_code', 'nl_NL', 'Code van het verkoopdagboek in de boekhoudexports (VE indien leeg)'),
    ('accounting_journal_code', 'ru_RU', 'Код журнала продаж в бухгалтерских выгрузках (VE, если пусто)'),
    ('accounting_journal_code', 'it_IT', 'Codice del giornale delle vendite nelle esportazioni contabili (VE se vuoto)'),
    ('accounting_journal_label', 'en_US', 'Label of the sales journal in the accounting exports (Ventes when empty)'),
    ('accounting_journal_label', 'fr_FR', 'Libellé du journal des ventes dans les exports comptables (Ventes si vide)'),
    ('accounting_journal_label', 'de_DE', 'Bezeichnung des Verkaufsjournals in den Buchhaltungsexporten (Ventes wenn leer)'),
    ('accounting_journal_label', 'es_ES', 'Nombre del diario de ventas en las exportaciones contables (Ventes si está vacío)'),
    ('accounting_journal_label', 'nl_NL', 'Naam van het verkoopdagboek in de boekhoudexports (Ventes indien leeg)'),
    ('accounting_journal_label', 'ru_RU', 'Название журнала продаж в бухгалтерских выгрузках (Ventes, если пусто)'),
    ('accounting_journal_label', 'it_IT', 'Descrizione del giornale delle vendite nelle esportazioni contabili (Ventes se vuoto)'),
    ('accounting_customer_account', 'en_US', 'Customer account the sales journal debits, e.g. 411000 (required by the accounting exports)'),
    ('accounting_customer_account', 'fr_FR', 'Compte client débité par le journal des ventes, par exemple 411000 (obligatoire pour les exports comptables)'),
    ('accounting_customer_account', 'de_DE', 'Kundenkonto, das das Verkaufsjournal belastet, z. B. 411000 (für die Buchhaltungsexporte erforderlich)'),
    ('accounting_customer_account', 'es_ES', 'Cuenta de cliente que carga el diario de ventas, p. ej. 411000 (obligatoria para las exportaciones contables)'),
    ('accounting_customer_account', 'nl_NL', 'Klantrekening die het verkoopdagboek debiteert, bijv. 411000 (verplicht voor de boekhoudexports)'),
    ('accounting_customer_account', 'ru_RU', 'Счёт клиента, который дебетует журнал продаж, например 411000 (обязателен для бухгалтерских выгрузок)'),
    ('accounting_customer_account', 'it_IT', 'Conto cliente addebitato dal giornale delle vendite, ad es. 411000 (obbligatorio per le esportazioni contabili)'),
    ('accounting_shipping_account', 'en_US', 'Account the sales journal credits with the shipping, e.g. 708500 (required by the accounting exports)'),
    ('accounting_shipping_account', 'fr_FR', 'Compte crédité du port par le journal des ventes, par exemple 708500 (obligatoire pour les exports comptables)'),
    ('accounting_shipping_account', 'de_DE', 'Konto, dem das Verkaufsjournal die Versandkosten gutschreibt, z. B. 708500 (für die Buchhaltungsexporte erforderlich)'),
    ('accounting_shipping_account', 'es_ES', 'Cuenta que abona el diario de ventas con el envío, p. ej. 708500 (obligatoria para las exportaciones contables)'),
    ('accounting_shipping_account', 'nl_NL', 'Rekening die het verkoopdagboek crediteert met de verzendkosten, bijv. 708500 (verplicht voor de boekhoudexports)'),
    ('accounting_shipping_account', 'ru_RU', 'Счёт, который журнал продаж кредитует на сумму доставки, например 708500 (обязателен для бухгалтерских выгрузок)'),
    ('accounting_shipping_account', 'it_IT', 'Conto accreditato delle spese di spedizione dal giornale delle vendite, ad es. 708500 (obbligatorio per le esportazioni contabili)'),
    ('accounting_rate_accounts', 'en_US', 'Sales account and tax collected account per tax rate, e.g. 20:706200:445720,5.5:706055:445705,0:706000 (required by the accounting exports)'),
    ('accounting_rate_accounts', 'fr_FR', 'Compte de ventes et compte de TVA collectée par taux, par exemple 20:706200:445720,5.5:706055:445705,0:706000 (obligatoire pour les exports comptables)'),
    ('accounting_rate_accounts', 'de_DE', 'Erlöskonto und Konto der vereinnahmten Steuer je Steuersatz, z. B. 20:706200:445720,5.5:706055:445705,0:706000 (für die Buchhaltungsexporte erforderlich)'),
    ('accounting_rate_accounts', 'es_ES', 'Cuenta de ventas y cuenta del impuesto repercutido por tipo, p. ej. 20:706200:445720,5.5:706055:445705,0:706000 (obligatoria para las exportaciones contables)'),
    ('accounting_rate_accounts', 'nl_NL', 'Omzetrekening en rekening van de geïnde belasting per tarief, bijv. 20:706200:445720,5.5:706055:445705,0:706000 (verplicht voor de boekhoudexports)'),
    ('accounting_rate_accounts', 'ru_RU', 'Счёт продаж и счёт начисленного налога по каждой ставке, например 20:706200:445720,5.5:706055:445705,0:706000 (обязателен для бухгалтерских выгрузок)'),
    ('accounting_rate_accounts', 'it_IT', 'Conto vendite e conto IVA a debito per aliquota, ad es. 20:706200:445720,5.5:706055:445705,0:706000 (obbligatorio per le esportazioni contabili)'),
    ('accounting_commercial_credit_account', 'en_US', 'Account debited of commercial credit notes (rebate, discount, refund of a difference), e.g. 709000 (sales account of their rate when empty)'),
    ('accounting_commercial_credit_account', 'fr_FR', 'Compte débité des avoirs commerciaux (rabais, remise, remboursement de différence), par exemple 709000 (compte de ventes de leur taux si vide)'),
    ('accounting_commercial_credit_account', 'de_DE', 'Konto, das mit kaufmännischen Gutschriften (Rabatt, Nachlass, Erstattung einer Differenz) belastet wird, z. B. 709000 (Erlöskonto ihres Steuersatzes wenn leer)'),
    ('accounting_commercial_credit_account', 'es_ES', 'Cuenta que carga los abonos comerciales (rebaja, descuento, reembolso de una diferencia), p. ej. 709000 (cuenta de ventas de su tipo si está vacía)'),
    ('accounting_commercial_credit_account', 'nl_NL', 'Rekening die met commerciële creditnota\'s (korting, rabat, terugbetaling van een verschil) wordt gedebiteerd, bijv. 709000 (omzetrekening van hun tarief indien leeg)'),
    ('accounting_commercial_credit_account', 'ru_RU', 'Счёт, который дебетуется коммерческими кредит-нотами (скидка, уступка, возврат разницы), например 709000 (счёт продаж их ставки, если пусто)'),
    ('accounting_commercial_credit_account', 'it_IT', 'Conto addebitato delle note di credito commerciali (ribasso, sconto, rimborso di una differenza), ad es. 709000 (conto vendite della loro aliquota se vuoto)');

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

SET FOREIGN_KEY_CHECKS = 1;
