SET FOREIGN_KEY_CHECKS = 0;

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

SET FOREIGN_KEY_CHECKS = 1;
