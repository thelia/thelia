SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Catalog price rules
--
-- A rule prices a slice of the catalog for a period: "20% off the Winter
-- category in January", "9.90 on brand X for the named customers". Its scope
-- is a set of criteria (`catalog_price_rule_criterion`: categories, brands,
-- templates, feature values, attribute values, named products) combined with
-- AND across types and OR within a type; its audience is everyone or the
-- customers of `catalog_price_rule_customer`; its effect is a percentage, an
-- amount per currency or a fixed price per currency
-- (`catalog_price_rule_effect_currency`, tax included at the shop location).
--
-- Nothing is written into `product_price`: a rule keeps the prices a merchant
-- typed and the ones a flash sale wrote untouched, and stops showing the
-- moment it ends. What it computes lives in two tables of its own. The
-- sale elements a rule covers, as last derived from its criteria, are kept in
-- `catalog_price_rule_product_sale_elements`; the price the public rules give
-- each sale element, per currency, is kept in `catalog_price_rule_price` as
-- dated validity segments, so that an opening or a closing takes effect at
-- the second without waiting for the scheduled command. The command
-- `catalog-price-rule:recompute` catches up whatever the events missed.
--
-- The criterion table carries no foreign key on `target_id` on purpose: a
-- deleted category or brand must match nothing, not turn its criterion into
-- "all" and widen the rule to the whole shop.
--
-- Every statement can be replayed: the tables are created only when missing
-- and the resource is inserted only once.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `catalog_price_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `active` TINYINT(1) DEFAULT 0 NOT NULL,
    `priority` INTEGER DEFAULT 100 NOT NULL COMMENT 'rules covering the same sale element apply by ascending priority, then by ascending id',
    `stop_processing` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'once this rule has applied, the rules of a higher priority value are not examined',
    `start_date` DATETIME,
    `end_date` DATETIME COMMENT 'a rule without an end date runs until it is turned off',
    `effect_type` TINYINT DEFAULT 1 NOT NULL COMMENT '1 percentage off, 2 amount off per currency, 3 fixed price per currency',
    `percentage_value` DECIMAL(8,4) COMMENT 'the percentage taken off, read only when effect_type is 1',
    `audience_mode` TINYINT DEFAULT 0 NOT NULL COMMENT 'who the rule prices for: 0 everyone, 1 the customers named on it, 2 the customer groups named on it',
    `display_initial_price` TINYINT(1) DEFAULT 1 NOT NULL COMMENT 'show the catalog price struck through next to the rule price',
    `include_subcategories` TINYINT(1) DEFAULT 1 NOT NULL COMMENT 'a category criterion also covers the products of its descendant categories',
    `dirty` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'the stored prices of this rule are behind its definition or the catalog, and the recompute command owes it a pass',
    `computed_at` DATETIME COMMENT 'when the stored prices of this rule were last computed',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_catalog_price_rule_active_audience_mode` (`active`, `audience_mode`),
    INDEX `idx_catalog_price_rule_active_start_end_date` (`active`, `start_date`, `end_date`),
    INDEX `idx_catalog_price_rule_priority` (`priority`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_effect_currency`
(
    `catalog_price_rule_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `value` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the amount taken off or the fixed price, tax included at the shop location, in this currency',
    PRIMARY KEY (`catalog_price_rule_id`,`currency_id`),
    INDEX `fk_catalog_price_rule_effect_currency_currency_idx` (`currency_id`),
    CONSTRAINT `fk_catalog_price_rule_effect_currency_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_effect_currency_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_criterion`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `catalog_price_rule_id` INTEGER NOT NULL,
    `type` VARCHAR(32) NOT NULL COMMENT 'what the criterion names: category, brand, template, feature_av, attribute_av or product; a module may register its own',
    `target_id` INTEGER NOT NULL COMMENT 'the id of the named object; no foreign key on purpose, a deleted target matches nothing rather than widening the rule',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_catalog_price_rule_criterion_rule_id_type_target_id` (`catalog_price_rule_id`, `type`, `target_id`),
    INDEX `idx_catalog_price_rule_criterion_type_target_id` (`type`, `target_id`),
    CONSTRAINT `fk_catalog_price_rule_criterion_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `catalog_price_rule_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL COMMENT 'a customer the rule prices for, read when audience_mode is 1',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_catalog_price_rule_customer_rule_id_customer_id` (`catalog_price_rule_id`, `customer_id`),
    INDEX `fk_catalog_price_rule_customer_customer_idx` (`customer_id`),
    CONSTRAINT `fk_catalog_price_rule_customer_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_customer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_product_sale_elements`
(
    `catalog_price_rule_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL COMMENT 'a sale element the rule covers, as last materialized from its criteria',
    PRIMARY KEY (`catalog_price_rule_id`,`product_sale_elements_id`),
    INDEX `idx_catalog_price_rule_pse_pse_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_catalog_price_rule_pse_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_pse_pse_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_price`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `valid_from` DATETIME COMMENT 'the stored price applies from this moment; null when it applies since the rules were computed, whatever the clock says',
    `valid_until` DATETIME COMMENT 'the stored price applies until this moment, excluded; null when no covering rule has an end date',
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the price the public rules give the sale element, untaxed, before any customer discount',
    `catalog_price_rule_id` INTEGER NOT NULL COMMENT 'the last rule that applied in this segment',
    `display_initial_price` TINYINT(1) DEFAULT 1 NOT NULL,
    `computed_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_catalog_price_rule_price_pse_currency_valid_from` (`product_sale_elements_id`, `currency_id`, `valid_from`),
    INDEX `idx_catalog_price_rule_price_valid_until` (`valid_until`),
    INDEX `fk_catalog_price_rule_price_currency_idx` (`currency_id`),
    INDEX `fk_catalog_price_rule_price_rule_idx` (`catalog_price_rule_id`),
    CONSTRAINT `fk_catalog_price_rule_price_pse_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_price_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_price_rule_price_rule_id`
        FOREIGN KEY (`catalog_price_rule_id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `catalog_price_rule_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `catalog_price_rule_i18n_fk_12c8e0`
        FOREIGN KEY (`id`)
        REFERENCES `catalog_price_rule` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.catalog-price-rule', NOW(), NOW());

-- One row per language the fresh install seeds, so the profile screen falls
-- back on the default language instead of showing no row at all.
SET @catalog_price_rule_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.catalog-price-rule');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@catalog_price_rule_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'de_DE', 'Katalogpreisregeln', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'en_US', 'Catalog price rules', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'es_ES', 'Reglas de precios del catálogo', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'fr_FR', 'Règles de prix catalogue', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'it_IT', 'Regole di prezzo del catalogo', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'nl_NL', 'Catalogusprijsregels', NULL, NULL, NULL),
    (@catalog_price_rule_resource_id, 'ru_RU', 'Правила цен каталога', NULL, NULL, NULL);

-- What the cart said when an order was placed, so a new payment attempt can tell whether it still
-- says the same thing. Null on every order placed before this column existed, which reads as
-- "cannot be compared", so those orders are replaced rather than reused.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'cart_fingerprint');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `cart_fingerprint` VARCHAR(64) NULL AFTER `cart_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'cart_fingerprint');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `cart_fingerprint` VARCHAR(64) NULL AFTER `cart_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- A shop upgraded from Thelia 2 still carries these columns as the Thelia 2 install and scripts
-- created them: NOT NULL without a default, and a DATE for the invoice date. Propel leaves out of
-- an INSERT every column it has nothing to write, the model default included, so the database
-- refused a folder created at the root and a coupon, a currency, a state or an order status saved
-- without their optional value, and the invoice date lost its time on every write. MODIFY gives
-- each column its fresh install definition; replaying it changes nothing.
ALTER TABLE `folder` MODIFY `parent` INTEGER DEFAULT 0 NOT NULL;
ALTER TABLE `folder_version` MODIFY `parent` INTEGER DEFAULT 0 NOT NULL;
ALTER TABLE `coupon` MODIFY `expiration_date` DATETIME NULL;
ALTER TABLE `coupon_version` MODIFY `expiration_date` DATETIME NULL;
ALTER TABLE `currency` MODIFY `format` CHAR(10) NULL;
ALTER TABLE `state` MODIFY `isocode` VARCHAR(4) NULL;
ALTER TABLE `order_status` MODIFY `color` CHAR(7) NULL, MODIFY `position` INTEGER NULL;
ALTER TABLE `order` MODIFY `invoice_date` DATETIME NULL;
ALTER TABLE `order_version` MODIFY `invoice_date` DATETIME NULL;

-- The display type of a filter arrived empty in 3.0.0-alpha1: a shop upgraded from Thelia 2
-- carries its choice_filter rows without one. They get the checkbox list, the type the storefront
-- and the back office already give a filter that has none. Replaying it changes nothing.
UPDATE `choice_filter` SET `type` = 'checkbox' WHERE `type` IS NULL OR `type` = '';

-- 2.3.0-alpha2 added the password renewal token as NOT NULL without a default, while the fresh
-- install lets it be NULL. On a shop upgraded across that version, creating an administrator
-- (the token is left out of the INSERT) and changing a password (the token is emptied) both
-- failed. MODIFY gives the column its fresh install definition; replaying it changes nothing.
ALTER TABLE `admin` MODIFY `password_renew_token` VARCHAR(255) NULL;

-- Thelia 2.6 moved the file name of the product, category, content, folder, brand and module
-- images into their translations (`*_image_i18n.file`), on a fresh 2.6 install as on an update to
-- 2.6.1. Thelia 3 reads one file per image from `*_image.file`, and no script brought it back: on a
-- shop installed or upgraded on Thelia 2.6, no image could be found. Each image gets the file of the
-- shop's default language, or of the first language that has one when the default language has
-- none, then the column leaves the translations. Only a table whose translations still carry the
-- column is touched: a shop that never ran 2.6 and a fresh install are left alone, and a replay,
-- even after an interrupted run, finds nothing left to move.
SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `product_image` ADD `file` VARCHAR(255) NOT NULL AFTER `product_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `product_image` `image` LEFT JOIN `product_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `product_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `product_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `product_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `category_image` ADD `file` VARCHAR(255) NOT NULL AFTER `category_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `category_image` `image` LEFT JOIN `category_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `category_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `category_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `category_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `content_image` ADD `file` VARCHAR(255) NOT NULL AFTER `content_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `content_image` `image` LEFT JOIN `content_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `content_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `content_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `content_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `folder_image` ADD `file` VARCHAR(255) NOT NULL AFTER `folder_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `folder_image` `image` LEFT JOIN `folder_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `folder_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `folder_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `folder_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `brand_image` ADD `file` VARCHAR(255) NOT NULL AFTER `brand_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `brand_image` `image` LEFT JOIN `brand_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `brand_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `brand_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `brand_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_in_translations := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'module_image_i18n' AND `COLUMN_NAME` = 'file');
SET @image_file_on_image := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'module_image' AND `COLUMN_NAME` = 'file');
SET @image_file_statement := IF(@image_file_in_translations AND NOT @image_file_on_image, 'ALTER TABLE `module_image` ADD `file` VARCHAR(255) NOT NULL AFTER `module_id`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'UPDATE `module_image` `image` LEFT JOIN `module_image_i18n` `default_translation` ON `default_translation`.`id` = `image`.`id` AND `default_translation`.`locale` = (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1) AND CHAR_LENGTH(`default_translation`.`file`) > 0 LEFT JOIN `module_image_i18n` `first_translation` ON `first_translation`.`id` = `image`.`id` AND `first_translation`.`locale` = (SELECT MIN(`other`.`locale`) FROM `module_image_i18n` `other` WHERE `other`.`id` = `image`.`id` AND CHAR_LENGTH(`other`.`file`) > 0) SET `image`.`file` = COALESCE(`default_translation`.`file`, `first_translation`.`file`, `image`.`file`)', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;
SET @image_file_statement := IF(@image_file_in_translations, 'ALTER TABLE `module_image_i18n` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @image_file_statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

-- The image formats a shop offers on top of the source one, and the encoder quality of
-- each. A shop that upgrades keeps the images it has: the list arrives empty, so nothing
-- is re-encoded and no catalogue is regenerated on the first crawl. A fresh install gets
-- WebP, which every current browser reads. INSERT IGNORE leaves alone a shop that has
-- already chosen.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('image_formats', '', 0, 0, NOW(), NOW()),
    ('image_quality_webp', '75', 0, 0, NOW(), NOW()),
    ('image_quality_avif', '50', 0, 0, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
