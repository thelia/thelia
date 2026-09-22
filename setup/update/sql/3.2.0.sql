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

SET FOREIGN_KEY_CHECKS = 1;
