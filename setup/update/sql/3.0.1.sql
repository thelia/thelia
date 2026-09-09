SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Client address of a form firewall attempt
--
-- The firewall counts the attempts of a client by its IP address, and looks
-- that client up by the same address on the next attempt. A 15-character
-- column only holds an IPv4 address: an IPv6 client was either refused the
-- write or had a prefix stored that no lookup matched again, so the firewall
-- counted nothing at all for visitors reaching the shop over IPv6.
--
-- 45 characters is the longest address there is (an IPv4-mapped IPv6 one).
-- Widened only where it is still too narrow, so the statement can be replayed.
-- ---------------------------------------------------------------------

SET @widen_column := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'form_firewall' AND `COLUMN_NAME` = 'ip_address' AND `CHARACTER_MAXIMUM_LENGTH` < 45);
SET @statement := IF(@widen_column, 'ALTER TABLE `form_firewall` MODIFY `ip_address` VARCHAR(45) NOT NULL', 'DO 0');
PREPARE widen_column_statement FROM @statement;
EXECUTE widen_column_statement;
DEALLOCATE PREPARE widen_column_statement;

-- ---------------------------------------------------------------------
-- Ordering without creating an account
--
-- A guest is a `customer` row like any other, carrying no password and marked
-- by `is_guest`, so that everything an order already hangs off a customer for
-- - the addresses, the invoice, the history - keeps working untouched. The
-- marker is what tells a guest apart from an account whose owner simply never
-- signed in again, and what the conversion to a real account clears.
--
-- `guest_checkout_forbidden` lets a shop keep a product out of the guest
-- checkout - a subscription, a downloadable licence, anything whose after-sale
-- needs an account to come back to.
--
-- `customer` and `product` are both versionable, so each column is mirrored in
-- its _version table.
--
-- Existing rows take the default: every account already on file is a real
-- account, and every product already on file may be bought as a guest. The
-- feature stays off until `guest_checkout_mode` is set to something else.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `COLUMN_NAME` = 'is_guest');
SET @statement := IF(@add_column, 'ALTER TABLE `customer` ADD `is_guest` TINYINT DEFAULT 0 NOT NULL COMMENT \'the customer ordered without creating an account and carries no password, until the account is completed\' AFTER `anonymized_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'is_guest');
SET @statement := IF(@add_column, 'ALTER TABLE `customer_version` ADD `is_guest` TINYINT DEFAULT 0 NOT NULL COMMENT \'the customer ordered without creating an account and carries no password, until the account is completed\' AFTER `anonymized_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'guest_checkout_forbidden');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `guest_checkout_forbidden` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product may only be bought from an account, so a cart holding it is refused the guest checkout\' AFTER `virtual`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'guest_checkout_forbidden');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `guest_checkout_forbidden` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product may only be bought from an account, so a cart holding it is refused the guest checkout\' AFTER `virtual`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- A shop that upgrades keeps the checkout it has: the setting arrives disabled,
-- and INSERT IGNORE leaves alone a shop that already chose a value.
INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('guest_checkout_mode', 'disabled', 0, 0, NOW(), NOW());

-- ---------------------------------------------------------------------
-- Consents of the checkout
--
-- `consent` is the list the merchant manages: what the buyer is asked to agree to at
-- the payment step, in which order, and which of it is required. A consent is turned
-- off rather than deleted when a shop stops asking for it, so that the acceptances
-- already collected keep their meaning.
--
-- `order_consent` is the proof, one row per consent the shop was asking for when the
-- order was placed — ticked or not. It carries the wording as displayed, the answer,
-- the address it came from and the date, and it holds the consent by code with no
-- foreign key: the consent may be deleted afterwards, the proof stays.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `consent`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'the name the checkout, the order rows and the code refer this consent by',
    `content_id` INTEGER COMMENT 'the content holding the text the buyer consents to, when the shop published one',
    `mandatory` TINYINT DEFAULT 0 NOT NULL COMMENT 'the order is refused as long as this consent is not accepted',
    `active` TINYINT DEFAULT 1 NOT NULL COMMENT 'a consent turned off is no longer asked for, and is kept so the acceptances already collected keep their meaning',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `consent_code_UNIQUE` (`code`),
    INDEX `idx_consent_content_id` (`content_id`),
    CONSTRAINT `fk_consent_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `consent_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `consent_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `consent` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `order_consent`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `consent_code` VARCHAR(64) NOT NULL COMMENT 'the consent this answer is about, kept as a plain code: the consent may be deleted, the proof stays',
    `title` VARCHAR(255) NOT NULL COMMENT 'the wording the buyer was shown, frozen the way order_product.title is',
    `description` LONGTEXT COMMENT 'the long text shown under the box, frozen alongside the wording',
    `accepted` TINYINT DEFAULT 0 NOT NULL COMMENT 'whether the box was ticked',
    `answered_at` DATETIME COMMENT 'when the buyer answered the box, which precedes the order it ends up on',
    `ip_address` VARCHAR(45) COMMENT 'the address the answer came from, null when the order was not placed over HTTP',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_consent_order_id` (`order_id`),
    CONSTRAINT `fk_order_consent_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The terms and conditions the shop already asks for become the first consent of the
-- list, pointing at the very content `terms_conditions_content_id` names.
--
-- The setting ships as an empty string and stays that way until the merchant picks a
-- content, so an empty value has to read as "no content", not as content number 0 — and
-- a value naming a content that has since been deleted has to read the same way, or the
-- insert would fail on the foreign key. Hence the three conditions: digits only, above
-- zero, and a `content` row that still exists.
SET @terms_content_id := (
    SELECT CAST(`config`.`value` AS UNSIGNED)
    FROM `config`
    WHERE `config`.`name` = 'terms_conditions_content_id'
      AND `config`.`value` REGEXP '^[0-9]+$'
      AND CAST(`config`.`value` AS UNSIGNED) > 0
      AND EXISTS (SELECT 1 FROM `content` WHERE `content`.`id` = CAST(`config`.`value` AS UNSIGNED))
);

-- Seeded optional, where a fresh install seeds it mandatory. A shop that updates its
-- core keeps whatever front-office theme it had, and a theme that predates this release
-- displays no box at all: made mandatory here, the consent would refuse every order and
-- the checkout would simply stop. Optional, the shop keeps selling, and the merchant
-- ticks "mandatory" in the back office the day their theme shows the box.
INSERT IGNORE INTO `consent` (`code`, `content_id`, `mandatory`, `active`, `position`, `created_at`, `updated_at`) VALUES
    ('terms_and_conditions', @terms_content_id, 0, 1, 1, NOW(), NOW());

-- Replaying the update must not undo a choice the merchant made in the back office, so
-- the link is only filled in while it is still empty.
UPDATE `consent`
    SET `content_id` = @terms_content_id, `updated_at` = NOW()
    WHERE `code` = 'terms_and_conditions'
      AND `content_id` IS NULL
      AND @terms_content_id IS NOT NULL;

-- Every language the shop has, not just the two written here: a locale left without a
-- row makes I18n fall back to the shop default and, failing that, forge the literal
-- string "DEFAULT TITLE" — which is what the buyer would be asked to tick, and what
-- would be frozen on their order as the proof of what they accepted. The fresh install
-- seeds all its locales the same way (see setup/insert.sql).
INSERT IGNORE INTO `consent_i18n` (`id`, `locale`, `title`, `description`)
    SELECT `consent`.`id`, `lang`.`locale`,
           CASE WHEN `lang`.`locale` = 'fr_FR'
                THEN 'J\'ai lu et j\'accepte les conditions générales de vente'
                ELSE 'I have read and accept the terms and conditions of sale'
           END,
           NULL
    FROM `consent` CROSS JOIN `lang`
    WHERE `consent`.`code` = 'terms_and_conditions';

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.consent', NOW(), NOW());

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
    SELECT `resource`.`id`, 'en_US', 'Configuration checkout consents', NULL, NULL, NULL
    FROM `resource` WHERE `resource`.`code` = 'admin.configuration.consent';

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
    SELECT `resource`.`id`, 'fr_FR', 'Configuration des consentements du tunnel de commande', NULL, NULL, NULL
    FROM `resource` WHERE `resource`.`code` = 'admin.configuration.consent';

-- ---------------------------------------------------------------------
-- Reserved sales and their countdown
--
-- A sale used to be for everyone: whoever saw the product got the discount.
-- `audience_mode` is what opens an operation to a part of the customers only —
-- 0 everyone, as every operation already on file, 1 the customers named on it
-- in `sale_customer`, 2 the customer groups it is opened to (the groups
-- themselves come later, the value is reserved here so the meaning of the
-- column never shifts). `hide_products` decides what a visitor the operation
-- is not open to sees: nothing at all, or the products at their usual price.
--
-- `countdown_mode` drives the urgency shown to the buyer: 0 no countdown,
-- 1 from `countdown_lead_hours` before the end date, 2 from the opening. The
-- lead is nullable because it is only read in mode 1.
--
-- `sale` is not versionable, so nothing is mirrored in a _version table.
--
-- Existing rows take the defaults: every operation already on file stays
-- public, keeps showing its products and shows no countdown.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale' AND `COLUMN_NAME` = 'audience_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `sale` ADD `audience_mode` TINYINT DEFAULT 0 NOT NULL COMMENT \'who the operation is open to: 0 everyone, 1 the customers named on it, 2 the customer groups named on it\' AFTER `price_offset_type`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale' AND `COLUMN_NAME` = 'hide_products');
SET @statement := IF(@add_column, 'ALTER TABLE `sale` ADD `hide_products` TINYINT(1) DEFAULT 0 NOT NULL COMMENT \'the products of a reserved operation are hidden from the visitors it is not open to, instead of being shown at their usual price\' AFTER `audience_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale' AND `COLUMN_NAME` = 'countdown_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `sale` ADD `countdown_mode` TINYINT DEFAULT 0 NOT NULL COMMENT \'when the countdown is shown: 0 never, 1 from countdown_lead_hours before the end, 2 from the opening\' AFTER `hide_products`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale' AND `COLUMN_NAME` = 'countdown_lead_hours');
SET @statement := IF(@add_column, 'ALTER TABLE `sale` ADD `countdown_lead_hours` INTEGER NULL COMMENT \'how many hours before the end date the countdown starts showing, read only when countdown_mode is 1\' AFTER `countdown_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The front office asks on every product page whether an active operation is
-- reserved, so the answer has to come from an index rather than a table scan.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale' AND `INDEX_NAME` = 'idx_sales_active_audience_mode');
SET @statement := IF(@add_index, 'ALTER TABLE `sale` ADD INDEX `idx_sales_active_audience_mode` (`active`, `audience_mode`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- The customers an operation is reserved for, when `audience_mode` is 1. Both
-- sides cascade: the list has no meaning without its operation, and a customer
-- who is deleted or purged leaves no trace on the operations they were named on.
CREATE TABLE IF NOT EXISTS `sale_customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL COMMENT 'a customer the operation is reserved for, read when audience_mode is 1',
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_sale_customer_sales_id_customer_id` (`sale_id`, `customer_id`),
    INDEX `fk_sale_customer_customer_idx` (`customer_id`),
    CONSTRAINT `fk_sale_customer_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_customer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- Types of relation between products
--
-- The accessory a shop already had is now one relation among several, told apart
-- by `accessory.type_id`. The table `accessory` itself is kept, columns and all:
-- modules query `AccessoryQuery` directly, and every relation already saved has
-- to stay exactly where the merchant put it.
--
-- Three types ship with the release. `accessory` is the one the existing rows
-- take, and the one the core names by code when the back office adds an
-- accessory, so it may not be deleted. `reciprocal` is set on the complementary
-- products alone: relating a mug to a coffee maker is worth stating both ways,
-- where an accessory or a higher-end model reads in one direction only.
--
-- The column arrives nullable, is filled in, and only then is required. Adding a
-- NOT NULL column with no default to a table that already has rows is refused
-- outright in strict mode, which is what a shop with accessories would hit.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `product_association_type`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'the name the relations, the front-office blocks and the code refer this type by',
    `visible` TINYINT DEFAULT 1 NOT NULL COMMENT 'a type turned off is no longer offered nor displayed, and is kept so the relations already saved keep their meaning',
    `reciprocal` TINYINT DEFAULT 0 NOT NULL COMMENT 'relating a product to another also writes the relation the other way round',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `product_association_type_code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `product_association_type_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_association_type_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product_association_type` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

INSERT IGNORE INTO `product_association_type` (`code`, `visible`, `reciprocal`, `position`, `created_at`, `updated_at`) VALUES
    ('accessory', 1, 0, 1, NOW(), NOW()),
    ('cross_selling', 1, 1, 2, NOW(), NOW()),
    ('up_selling', 1, 0, 3, NOW(), NOW());

-- Every language the shop has, not just the two written here: a locale left
-- without a row makes I18n fall back to the shop default and, failing that,
-- forge the literal string "DEFAULT TITLE" — which is what would then title a
-- block of the product sheet. The fresh install seeds all its locales the same
-- way (see setup/insert.sql).
INSERT IGNORE INTO `product_association_type_i18n` (`id`, `locale`, `title`, `description`)
    SELECT `product_association_type`.`id`, `lang`.`locale`,
           CASE `product_association_type`.`code`
               WHEN 'accessory' THEN IF(`lang`.`locale` = 'fr_FR', 'Accessoires', 'Accessories')
               WHEN 'cross_selling' THEN IF(`lang`.`locale` = 'fr_FR', 'Produits complémentaires', 'Complementary products')
               ELSE IF(`lang`.`locale` = 'fr_FR', 'Modèles supérieurs', 'Higher-end models')
           END,
           CASE `product_association_type`.`code`
               WHEN 'accessory' THEN IF(`lang`.`locale` = 'fr_FR', 'Produits qui complètent celui-ci, comme une housse ou une pièce détachée', 'Products that complete this one, such as a case or a spare part')
               WHEN 'cross_selling' THEN IF(`lang`.`locale` = 'fr_FR', 'Produits qui vont bien avec celui-ci', 'Products that go well with this one')
               ELSE IF(`lang`.`locale` = 'fr_FR', 'Produits de même nature, une gamme au-dessus de celui-ci', 'Products of the same kind, a range above this one')
           END
    FROM `product_association_type` CROSS JOIN `lang`
    WHERE `product_association_type`.`code` IN ('accessory', 'cross_selling', 'up_selling');

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'accessory' AND `COLUMN_NAME` = 'type_id');
SET @statement := IF(@add_column, 'ALTER TABLE `accessory` ADD `type_id` INTEGER NULL COMMENT \'what the relation means: an accessory, a cross-sell, an up-sell, or a type the merchant declared\' AFTER `accessory`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- Every relation saved before this release is an accessory, which is the whole
-- point of keeping the table: the merchant finds their accessories where they
-- left them, in the accessory block, at the position they gave them.
SET @accessory_type_id := (SELECT `id` FROM `product_association_type` WHERE `code` = 'accessory');

UPDATE `accessory`
    SET `type_id` = @accessory_type_id
    WHERE `type_id` IS NULL
      AND @accessory_type_id IS NOT NULL;

SET @require_column := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'accessory' AND `COLUMN_NAME` = 'type_id' AND `IS_NULLABLE` = 'YES'
) AND (SELECT COUNT(*) = 0 FROM `accessory` WHERE `type_id` IS NULL);
SET @statement := IF(@require_column, 'ALTER TABLE `accessory` MODIFY `type_id` INTEGER NOT NULL COMMENT \'what the relation means: an accessory, a cross-sell, an up-sell, or a type the merchant declared\'', 'DO 0');
PREPARE require_column_statement FROM @statement;
EXECUTE require_column_statement;
DEALLOCATE PREPARE require_column_statement;

-- The index serves a single block of a product sheet in one read, which is how
-- the front office asks for it. The two indexes the table already had are left
-- alone: modules still query the table by product alone.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'accessory' AND `INDEX_NAME` = 'idx_accessory_product_id_type_id_position');
SET @statement := IF(@add_index, 'ALTER TABLE `accessory` ADD INDEX `idx_accessory_product_id_type_id_position` (`product_id`, `type_id`, `position`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- RESTRICT, not CASCADE: deleting a type a merchant still uses would take their
-- relations with it, silently. The back office refuses the deletion instead.
SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'accessory' AND `CONSTRAINT_NAME` = 'fk_accessory_type_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `accessory` ADD CONSTRAINT `fk_accessory_type_id` FOREIGN KEY (`type_id`) REFERENCES `product_association_type` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

SET FOREIGN_KEY_CHECKS = 1;
