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

-- The image formats a shop offers on top of the source one, and the encoder quality of
-- each. A shop that upgrades keeps the images it has: the list arrives empty, so nothing
-- is re-encoded and no catalogue is regenerated on the first crawl. A fresh install gets
-- WebP, which every current browser reads. INSERT IGNORE leaves alone a shop that has
-- already chosen.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('image_formats', '', 0, 0, NOW(), NOW()),
    ('image_quality_webp', '75', 0, 0, NOW(), NOW()),
    ('image_quality_avif', '50', 0, 0, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Images by language
--
-- The file of an image is translated, as in Thelia 2.6: each language of a
-- product, category, content, folder, brand or module image may show its own
-- file, and a language without one shows the file of the default language when
-- the shop replaces missing translations (`default_lang_without_translation`).
-- The file moves from `<type>_image` to `<type>_image_i18n`, nullable there: a
-- translation that only carries a title has no file of its own.
--
-- Three starting points, told apart by the schema itself:
-- - a shop on Thelia 3.0 or 3.1, or one that came from 2.5 or earlier, holds the
--   file on `<type>_image`: it is copied into every translation the image has,
--   the default language gets a translation when it had none (the rule of the
--   2.6.1 script), then the column is dropped;
-- - a shop that came from 2.6 already holds the files in the translations: they
--   are kept as they are, the column only becomes nullable, and a file still
--   found on `<type>_image` only fills the translations that have none;
-- - an empty file name is stored as NULL, which is what "no file" reads as.
--
-- Every statement checks the schema first and runs through PREPARE, which MySQL
-- reads as well as MariaDB: replaying the block, or running it on a database
-- already up to date, changes nothing.
-- ---------------------------------------------------------------------

SET @image_file_default_locale := (SELECT `locale` FROM `lang` WHERE `by_default` = 1 ORDER BY `id` LIMIT 1);

-- product_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `product_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `product_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `product_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `product_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `product_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `product_image_i18n` `translation` INNER JOIN `product_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `product_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `product_image_i18n` SET `file` = NULL WHERE `file` = '';

-- category_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `category_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `category_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `category_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `category_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `category_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `category_image_i18n` `translation` INNER JOIN `category_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `category_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `category_image_i18n` SET `file` = NULL WHERE `file` = '';

-- content_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `content_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `content_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `content_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `content_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `content_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `content_image_i18n` `translation` INNER JOIN `content_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `content_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `content_image_i18n` SET `file` = NULL WHERE `file` = '';

-- folder_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `folder_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `folder_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `folder_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `folder_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `folder_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `folder_image_i18n` `translation` INNER JOIN `folder_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `folder_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `folder_image_i18n` SET `file` = NULL WHERE `file` = '';

-- brand_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `brand_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `brand_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `brand_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `brand_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `brand_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `brand_image_i18n` `translation` INNER JOIN `brand_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `brand_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `brand_image_i18n` SET `file` = NULL WHERE `file` = '';

-- module_image
SET @image_file_translated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'module_image_i18n' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_translated, 'ALTER TABLE `module_image_i18n` MODIFY `file` VARCHAR(255) NULL', 'ALTER TABLE `module_image_i18n` ADD `file` VARCHAR(255) NULL AFTER `locale`');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @image_file_untranslated := (SELECT COUNT(*) FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'module_image' AND `COLUMN_NAME` = 'file');
SET @statement := IF(@image_file_untranslated AND @image_file_default_locale IS NOT NULL, 'INSERT INTO `module_image_i18n` (`id`, `locale`, `file`) SELECT `image`.`id`, ?, `image`.`file` FROM `module_image` `image` WHERE CHAR_LENGTH(`image`.`file`) > 0 AND NOT EXISTS (SELECT 1 FROM `module_image_i18n` `translation` WHERE `translation`.`id` = `image`.`id` AND `translation`.`locale` = ?)', 'DO ?, ?');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement USING @image_file_default_locale, @image_file_default_locale;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'UPDATE `module_image_i18n` `translation` INNER JOIN `module_image` `image` ON `image`.`id` = `translation`.`id` SET `translation`.`file` = `image`.`file` WHERE COALESCE(CHAR_LENGTH(`translation`.`file`), 0) = 0 AND CHAR_LENGTH(`image`.`file`) > 0', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

SET @statement := IF(@image_file_untranslated, 'ALTER TABLE `module_image` DROP COLUMN `file`', 'DO 0');
PREPARE image_file_statement FROM @statement;
EXECUTE image_file_statement;
DEALLOCATE PREPARE image_file_statement;

UPDATE `module_image_i18n` SET `file` = NULL WHERE `file` = '';

-- ---------------------------------------------------------------------
-- The Smarty back office left the default install
--
-- `default-twig` is the only back office the core installs. A shop still
-- running the Smarty one (`default`, left over from Thelia 2) is switched to
-- it, and the two modules that only came with the Smarty back office are
-- turned off: after a composer update their code is gone from vendor, and a
-- module row that stays active without code on disk aborts the boot in debug
-- environments. A shop that requires the Smarty back office itself sets the
-- template back and activates TheliaSmarty again.
-- ---------------------------------------------------------------------

UPDATE `config` SET `value` = 'default-twig' WHERE `name` = 'active-admin-template' AND `value` = 'default';

UPDATE `module` SET `activate` = 0 WHERE `code` IN ('TheliaSmarty', 'VirtualProductControl');

-- ---------------------------------------------------------------------
-- Content slots
--
-- A theme asks the core for the links of a slot (the header, the footer,
-- the page of a consent) instead of naming contents by their id.
--
-- The header reads `header_menu_items`, an ordered list of references written
-- `folder:<id>,content:<id>`. The theme used to show folder 2 and content 1
-- whatever the shop held, so an updated shop gets those two, for the ones that
-- exist: it keeps the header it had, and can change it from now on. A fresh
-- install starts empty. INSERT IGNORE leaves alone a shop that already set it.
--
-- The terms and conditions get a single source, the content of the
-- `terms_and_conditions` consent; `terms_conditions_content_id` is deprecated.
-- A consent that names no content takes the one the setting names, under the
-- conditions 3.1.0 applied: digits only, above zero, and a `content` row that
-- still exists. A consent that already names one keeps it.
-- ---------------------------------------------------------------------

SET @header_menu_items := CONCAT_WS(
    ',',
    (SELECT 'folder:2' FROM `folder` WHERE `folder`.`id` = 2),
    (SELECT 'content:1' FROM `content` WHERE `content`.`id` = 1)
);

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('header_menu_items', @header_menu_items, 0, 0, NOW(), NOW());

SET @terms_content_id := (
    SELECT CAST(`config`.`value` AS UNSIGNED)
    FROM `config`
    WHERE `config`.`name` = 'terms_conditions_content_id'
      AND `config`.`value` REGEXP '^[0-9]+$'
      AND CAST(`config`.`value` AS UNSIGNED) > 0
      AND EXISTS (SELECT 1 FROM `content` WHERE `content`.`id` = CAST(`config`.`value` AS UNSIGNED))
);

UPDATE `consent`
SET `content_id` = @terms_content_id, `updated_at` = NOW()
WHERE `code` = 'terms_and_conditions'
  AND `content_id` IS NULL
  AND @terms_content_id IS NOT NULL;

-- ---------------------------------------------------------------------
-- Second factor of the administrator accounts
--
-- `admin_two_factor` holds the TOTP secret of an administrator and the last
-- step it accepted; `admin_two_factor_backup_code` the hashes of the one-time
-- codes that stand in for the phone. Both go with the account they belong to.
-- The `admin_two_factor_required` setting, off by default, makes the second
-- factor mandatory for every administrator: an updated shop keeps signing in
-- as before until it is turned on.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `admin_two_factor`
(
    `admin_id` INTEGER NOT NULL,
    `secret` VARCHAR(64) NOT NULL COMMENT 'the shared TOTP secret, base32 encoded',
    `enabled_at` DATETIME COMMENT 'when the administrator proved the secret with a first code',
    `last_used_step` INTEGER COMMENT 'the last 30-second TOTP step accepted, so that a code cannot be used twice',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`admin_id`),
    CONSTRAINT `fk_admin_two_factor_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `admin_two_factor_backup_code`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `admin_id` INTEGER NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL COMMENT 'the password hash of a backup code, never the code itself',
    `used_at` DATETIME COMMENT 'set when the code was used, a used code is refused',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_admin_two_factor_backup_code_admin_id` (`admin_id`),
    CONSTRAINT `fk_admin_two_factor_backup_code_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('admin_two_factor_required', '0', 0, 1, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Alternative text and decorative flag on the images
--
-- An image had a translatable title and nothing else to describe it: the
-- front office published the title as the alt attribute, then the store name,
-- and nothing told a decorative image, whose alt must stay empty, from an image
-- nobody had described yet. `alt` is translatable and lives in the i18n table;
-- `decorative` is a fact about the file and lives on the image itself.
--
-- Every image the core manages takes both, so that a theme reads one convention
-- whatever the parent. Existing rows keep an empty alt: the front office keeps
-- falling back on the title for them.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image' AND `COLUMN_NAME` = 'decorative');
SET @statement := IF(@add_column, 'ALTER TABLE `product_image` ADD `decorative` TINYINT DEFAULT 0 NOT NULL COMMENT \'a decorative image carries no information and is published with an empty alt attribute; the flag tells it apart from an image not described yet\' AFTER `visible`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_image_i18n' AND `COLUMN_NAME` = 'alt');
SET @statement := IF(@add_column, 'ALTER TABLE `product_image_i18n` ADD `alt` VARCHAR(255) AFTER `title`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image' AND `COLUMN_NAME` = 'decorative');
SET @statement := IF(@add_column, 'ALTER TABLE `category_image` ADD `decorative` TINYINT DEFAULT 0 NOT NULL COMMENT \'a decorative image carries no information and is published with an empty alt attribute; the flag tells it apart from an image not described yet\' AFTER `visible`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'category_image_i18n' AND `COLUMN_NAME` = 'alt');
SET @statement := IF(@add_column, 'ALTER TABLE `category_image_i18n` ADD `alt` VARCHAR(255) AFTER `title`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image' AND `COLUMN_NAME` = 'decorative');
SET @statement := IF(@add_column, 'ALTER TABLE `folder_image` ADD `decorative` TINYINT DEFAULT 0 NOT NULL COMMENT \'a decorative image carries no information and is published with an empty alt attribute; the flag tells it apart from an image not described yet\' AFTER `visible`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_image_i18n' AND `COLUMN_NAME` = 'alt');
SET @statement := IF(@add_column, 'ALTER TABLE `folder_image_i18n` ADD `alt` VARCHAR(255) AFTER `title`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image' AND `COLUMN_NAME` = 'decorative');
SET @statement := IF(@add_column, 'ALTER TABLE `content_image` ADD `decorative` TINYINT DEFAULT 0 NOT NULL COMMENT \'a decorative image carries no information and is published with an empty alt attribute; the flag tells it apart from an image not described yet\' AFTER `visible`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_image_i18n' AND `COLUMN_NAME` = 'alt');
SET @statement := IF(@add_column, 'ALTER TABLE `content_image_i18n` ADD `alt` VARCHAR(255) AFTER `title`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image' AND `COLUMN_NAME` = 'decorative');
SET @statement := IF(@add_column, 'ALTER TABLE `brand_image` ADD `decorative` TINYINT DEFAULT 0 NOT NULL COMMENT \'a decorative image carries no information and is published with an empty alt attribute; the flag tells it apart from an image not described yet\' AFTER `visible`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'brand_image_i18n' AND `COLUMN_NAME` = 'alt');
SET @statement := IF(@add_column, 'ALTER TABLE `brand_image_i18n` ADD `alt` VARCHAR(255) AFTER `title`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Product videos
--
-- A video becomes a product medium in its own right, next to the images and the
-- documents: ordered, shown or hidden, described in every language, and bound
-- to the sale elements it illustrates. It plays from a platform (youtube,
-- vimeo, dailymotion), of which only the identifier is kept - the player's
-- address is built by the code, never from what the merchant typed - or from a
-- file the shop hosts itself.
--
-- The thumbnail is one of the product's own images, and goes back to the first
-- image of the product when that image is deleted.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `product_video`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `provider` VARCHAR(32) NOT NULL COMMENT 'where the video is played from: youtube, vimeo, dailymotion, or file for a video the shop hosts itself',
    `external_id` VARCHAR(255) COMMENT 'the identifier of the video on its platform, the only part of the address the shop keeps; empty for a hosted file',
    `file` VARCHAR(255) COMMENT 'the name of the hosted file in the videos library; empty for a platform video',
    `thumbnail_image_id` INTEGER COMMENT 'the product image shown until the player is loaded; the first image of the product when empty',
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_product_video_product_id` (`product_id`),
    INDEX `idx_product_video_product_id_position` (`product_id`, `position`),
    INDEX `idx_product_video_thumbnail_image_id` (`thumbnail_image_id`),
    CONSTRAINT `fk_product_video_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_video_thumbnail_image_id`
        FOREIGN KEY (`thumbnail_image_id`)
        REFERENCES `product_image` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `product_video_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `alt` VARCHAR(255) COMMENT 'the accessible name of the player, read out in place of the video',
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_video_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product_video` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `product_sale_elements_product_video`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_video_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `product_sale_elements_product_video_UNIQUE` (`product_sale_elements_id`, `product_video_id`),
    INDEX `fk_pse_product_video_product_video_id_idx` (`product_video_id`),
    CONSTRAINT `fk_pse_product_video_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_pse_product_video_product_video_id`
        FOREIGN KEY (`product_video_id`)
        REFERENCES `product_video` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- Where the hosted files go, and which platforms the merchant may paste an
-- address from. INSERT IGNORE leaves alone a shop that already chose a value.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('videos_library_path', 'local/media/videos', 0, 0, NOW(), NOW()),
    ('video_providers', 'youtube,vimeo,dailymotion', 0, 0, NOW(), NOW());

-- The two flag facets of a product listing: on sale, and new.
--
-- `choice_filter_other` is what makes a filter that hangs off no feature and
-- no attribute reachable from the back office: the category and the template
-- screens list its rows, and a merchant decides there whether the facet
-- shows, where it sits and how it is drawn. A fresh install seeds both rows
-- (setup/insert.sql); an upgraded shop gets them here.
--
-- The filters are matched by `type`, never by id, so a row is inserted only
-- when no row of that type exists and takes the next free id: a shop that
-- added rows of its own keeps them. The i18n rows cover the eight locales a
-- fresh install seeds, and INSERT IGNORE keeps a title the merchant already typed.
INSERT INTO `choice_filter_other` (`type`, `visible`)
    SELECT 'promo', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `choice_filter_other` WHERE `type` = 'promo');

INSERT INTO `choice_filter_other` (`type`, `visible`)
    SELECT 'new', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `choice_filter_other` WHERE `type` = 'new');

SET @promo_filter_id := (SELECT MIN(`id`) FROM `choice_filter_other` WHERE `type` = 'promo');
SET @new_filter_id := (SELECT MIN(`id`) FROM `choice_filter_other` WHERE `type` = 'new');

INSERT IGNORE INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (@promo_filter_id, 'cs_CZ', 'Akce', NULL),
    (@new_filter_id, 'cs_CZ', 'Novinka', NULL),
    (@promo_filter_id, 'de_DE', 'Aktion', NULL),
    (@new_filter_id, 'de_DE', 'Neuheit', NULL),
    (@promo_filter_id, 'en_US', 'Promotion', NULL),
    (@new_filter_id, 'en_US', 'Newness', NULL),
    (@promo_filter_id, 'es_ES', 'Promoción', NULL),
    (@new_filter_id, 'es_ES', 'Novedad', NULL),
    (@promo_filter_id, 'fr_FR', 'Promotion', NULL),
    (@new_filter_id, 'fr_FR', 'Nouveauté', NULL),
    (@promo_filter_id, 'it_IT', 'Promozione', NULL),
    (@new_filter_id, 'it_IT', 'Novità', NULL),
    (@promo_filter_id, 'nl_NL', 'Promotie', NULL),
    (@new_filter_id, 'nl_NL', 'Nieuw', NULL),
    (@promo_filter_id, 'ru_RU', 'Акция', NULL),
    (@new_filter_id, 'ru_RU', 'Новинка', NULL);
-- ---------------------------------------------------------------------
-- Customer lists
--
-- A list a customer keeps and recalls: the purchase list is the only sort
-- shipped, the `type` column leaves room for the others (favorites) without
-- reworking the lists already saved. A line keeps the reference as typed and
-- the sale element it resolved to; the sale element is nulled, never
-- cascaded, when it leaves the catalog, so an old list still shows the line
-- it lost. `shared` stays at 0 until customers can belong to a company.
--
-- Both tables are created only when missing: the block can be replayed.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customer_list`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NOT NULL COMMENT 'the customer who created the list and owns it',
    `type` VARCHAR(32) DEFAULT 'purchase' NOT NULL COMMENT 'the sort of list, one of the CustomerListType values',
    `title` VARCHAR(255) NOT NULL,
    `shared` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'the list is shared with the company of its owner',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_customer_list_customer_id_type` (`customer_id`, `type`),
    INDEX `idx_customer_list_shared` (`shared`),
    CONSTRAINT `fk_customer_list_customer_id`
        FOREIGN KEY (`customer_id`)
            REFERENCES `customer` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `customer_list_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_list_id` INTEGER NOT NULL,
    `ref` VARCHAR(255) NOT NULL COMMENT 'the reference as the customer entered it, kept when the sale element is gone',
    `product_sale_elements_id` INTEGER COMMENT 'the sale element the reference was resolved to, null once it left the catalog',
    `quantity` INTEGER NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_customer_list_item_customer_list_id_position` (`customer_list_id`, `position`),
    INDEX `fk_customer_list_item_product_sale_elements_idx` (`product_sale_elements_id`),
    CONSTRAINT `fk_customer_list_item_customer_list_id`
        FOREIGN KEY (`customer_list_id`)
            REFERENCES `customer_list` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `fk_customer_list_item_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
            REFERENCES `product_sale_elements` (`id`)
            ON UPDATE RESTRICT
            ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- Gift wrapping services, the note for the recipient, and the order line
-- that invoices the service beside the goods.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `gift_wrapping`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'the name the cart, the order lines and the code refer this wrapping by',
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the price of the service, tax excluded, the way a product price is stored',
    `tax_rule_id` INTEGER NOT NULL,
    `active` TINYINT DEFAULT 1 NOT NULL COMMENT 'a wrapping turned off is no longer offered at checkout, and is kept so the orders already placed keep reading',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `gift_wrapping_code_UNIQUE` (`code`),
    INDEX `idx_gift_wrapping_tax_rule_id` (`tax_rule_id`),
    CONSTRAINT `fk_gift_wrapping_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `gift_wrapping_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `gift_wrapping_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `gift_wrapping` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The wrapping the buyer picked, released rather than blocked when the merchant deletes
-- the service: the cart loses the choice, which is what deleting it means.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'gift_wrapping_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `gift_wrapping_id` INTEGER NULL AFTER `discount`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_gift_wrapping_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_gift_wrapping_id` (`gift_wrapping_id`)', 'DO 0');
-- The quick order looks sale elements up by their EAN code as well as by their
-- reference, for up to five hundred codes at once: without an index, each of
-- those lookups reads the whole table.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_ean_code');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_ean_code` (`ean_code`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_gift_wrapping_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_gift_wrapping_id` FOREIGN KEY (`gift_wrapping_id`) REFERENCES `gift_wrapping` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

-- The note for whoever receives the parcel, on the cart while it is being written and on
-- the order once it is placed. Null on every cart and every order that predates it, which
-- reads as "no note", exactly right.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'gift_message');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `gift_message` TEXT NULL AFTER `gift_wrapping_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'gift_message');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `gift_message` TEXT NULL AFTER `cart_fingerprint`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'gift_message');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `gift_message` TEXT NULL AFTER `cart_fingerprint`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- What an order line stands for. Every line written before this column existed is a good
-- taken off the catalogue, which is what the default says, so nothing has to be backfilled.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'line_type');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `line_type` VARCHAR(32) NOT NULL DEFAULT ''product'' AFTER `is_offered`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.gift-wrapping', NOW(), NOW());

SET @gift_wrapping_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.gift-wrapping');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@gift_wrapping_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'en_US', 'Configuration gift wrappings', NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'fr_FR', 'Configuration des emballages cadeaux', NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@gift_wrapping_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
