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
-- Promotions that apply on their own, and the lines they offer
--
-- A coupon used to be reachable one way only: the customer typed its code.
-- `trigger_mode` is what tells the two apart - `code`, the behaviour every
-- coupon already on file keeps, or `automatic`, where a cart matching the
-- conditions is enough and nothing is ever typed. An automatic promotion has
-- no code to carry, so `code` becomes nullable; the `code_UNIQUE` index stays,
-- MariaDB counting each NULL as distinct.
--
-- `coupon` is versionable, so both changes are mirrored in `coupon_version`.
--
-- A promotion may also put a line in the cart itself - the offered product of
-- a buy X get Y rule. `cart_item.is_offered` marks that line so the customer
-- can neither change its quantity nor remove it, and `offered_by_coupon_id`
-- says which promotion put it there, so the line can be taken back when the
-- cart stops matching. No foreign key on purpose: the line is reconciled on
-- every evaluation, and a deleted coupon must leave the cart standing rather
-- than take rows down with it. `cart_item` is not versionable.
--
-- On the order side, `order_coupon` recorded the code and looked the coupon up
-- by it again - which an automatic promotion, having no code, cannot do.
-- `coupon_id` is what resolves the coupon now, the code staying as the
-- fallback for the orders already on file. `serialized_effects` freezes what
-- the coupon did at the time of the order, so editing the promotion later
-- never rewrites an order already placed. Both are nullable: nothing is
-- backfilled, and the orders already on file keep resolving by code.
--
-- Existing rows take the defaults: every coupon already on file stays a code
-- coupon, and no cart line is offered.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `COLUMN_NAME` = 'trigger_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon` ADD `trigger_mode` VARCHAR(20) DEFAULT \'code\' NOT NULL COMMENT \'what makes the promotion apply: code, the customer types it, or automatic, the cart matching the conditions is enough\' AFTER `code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon_version' AND `COLUMN_NAME` = 'trigger_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `coupon_version` ADD `trigger_mode` VARCHAR(20) DEFAULT \'code\' NOT NULL COMMENT \'what makes the promotion apply: code, the customer types it, or automatic, the cart matching the conditions is enough\' AFTER `code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- Only relaxed where it is still NOT NULL, so the statement can be replayed.
SET @relax_column := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `COLUMN_NAME` = 'code' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@relax_column, 'ALTER TABLE `coupon` MODIFY `code` VARCHAR(45) NULL COMMENT \'the code the customer types, empty on a promotion that applies on its own\'', 'DO 0');
PREPARE relax_column_statement FROM @statement;
EXECUTE relax_column_statement;
DEALLOCATE PREPARE relax_column_statement;

SET @relax_column := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon_version' AND `COLUMN_NAME` = 'code' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@relax_column, 'ALTER TABLE `coupon_version` MODIFY `code` VARCHAR(45) NULL COMMENT \'the code the customer types, empty on a promotion that applies on its own\'', 'DO 0');
PREPARE relax_column_statement FROM @statement;
EXECUTE relax_column_statement;
DEALLOCATE PREPARE relax_column_statement;

-- Every cart evaluation asks for the automatic promotions and nothing else, so
-- the answer has to come from an index rather than a scan of every coupon.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'coupon' AND `INDEX_NAME` = 'idx_trigger_mode');
SET @statement := IF(@add_index, 'ALTER TABLE `coupon` ADD INDEX `idx_trigger_mode` (`trigger_mode`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'is_offered');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD `is_offered` TINYINT DEFAULT 0 NOT NULL COMMENT \'the line was put in the cart by a promotion, not by the customer, and the customer may neither change nor remove it\' AFTER `promo`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `COLUMN_NAME` = 'offered_by_coupon_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_item` ADD `offered_by_coupon_id` INTEGER NULL COMMENT \'the coupon that offers the line, read to take the line back when the promotion no longer applies\' AFTER `is_offered`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- The offered line, once the cart is gone
--
-- A line a promotion offered is priced at zero by the cart discount, not by its
-- own price: on the order it therefore reads exactly like a line the customer
-- paid for. `order_product.is_offered` copies the cart marker at the moment the
-- order is written, the way the title, the price and the tax rule are already
-- copied, so the back office, the invoice and a later return can still tell the
-- gift from a purchase long after the cart it came from has been purged.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'is_offered');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `is_offered` TINYINT DEFAULT 0 NOT NULL COMMENT \'the line was offered by a promotion, copied from the cart so the order and its documents still say so once the cart is gone\' AFTER `virtual_document`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_item' AND `INDEX_NAME` = 'idx_cart_item_offered_by_coupon_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart_item` ADD INDEX `idx_cart_item_offered_by_coupon_id` (`offered_by_coupon_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_coupon' AND `COLUMN_NAME` = 'coupon_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_coupon` ADD `coupon_id` INTEGER NULL COMMENT \'the coupon the order was placed with, kept to find it again when the code is empty or was changed since\' AFTER `order_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_coupon' AND `COLUMN_NAME` = 'serialized_effects');
SET @statement := IF(@add_column, 'ALTER TABLE `order_coupon` ADD `serialized_effects` LONGTEXT NULL COMMENT \'the effects the coupon carried when the order was placed, copied from the coupon so a later change never rewrites the order\' AFTER `type`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @relax_column := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_coupon' AND `COLUMN_NAME` = 'code' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@relax_column, 'ALTER TABLE `order_coupon` MODIFY `code` VARCHAR(45) NULL COMMENT \'the code the customer typed, empty on a promotion that applied on its own\'', 'DO 0');
PREPARE relax_column_statement FROM @statement;
EXECUTE relax_column_statement;
DEALLOCATE PREPARE relax_column_statement;

-- The order history resolves its coupons by id, and the usage counters walk
-- back from a coupon to the orders that used it.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_coupon' AND `INDEX_NAME` = 'idx_order_coupon_coupon_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order_coupon` ADD INDEX `idx_order_coupon_coupon_id` (`coupon_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- ---------------------------------------------------------------------
-- Product returns (RMA)
--
-- A return is a request a customer opens on an order they were delivered:
-- which lines, how many of each, why, and what they expect in exchange. It
-- carries its own reference and its own lifecycle, `order_return_status`
-- holding the states the merchant moves it through and `order_return_reason`
-- the motives the merchant offers to pick from.
--
-- `order_return.reason_id` releases to NULL when the merchant deletes a
-- reason, `reason_title` keeping the label as it was displayed, so a request
-- already filed never loses what it was filed for. `refund_amount` is the
-- amount computed from the prices actually paid on the returned lines.
--
-- `order_return` is versionable, which is also why the version tables of its
-- versionable relations gain a referrer snapshot below: without those columns
-- Propel selects a column that does not exist.
--
-- The feature arrives disabled, exactly as on a fresh install: a shop that
-- updates its core displays nothing about returns until the merchant turns
-- `order_return_enabled` on.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_return_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(45) NOT NULL,
  `equivalent_code` varchar(45) DEFAULT NULL,
  `color` char(7) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `protected_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_status_code_UNIQUE` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_reason` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(45) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_reason_code_UNIQUE` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref` varchar(45) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `reason_id` int(11) DEFAULT NULL COMMENT 'the merchant-managed reason, NULL once that reason is deleted',
  `reason_title` varchar(255) DEFAULT NULL COMMENT 'the reason label snapshot, kept when the reason is deleted',
  `expected_resolution` varchar(45) DEFAULT NULL COMMENT 'what the customer expects: refund, credit or exchange',
  `customer_comment` text DEFAULT NULL,
  `refusal_reason` text DEFAULT NULL COMMENT 'the motive given by the merchant when the request is refused',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the amount to refund, computed on the paid prices of the returned lines',
  `include_postage` tinyint(1) DEFAULT 0 COMMENT 'whether the postage is included in the refundable amount',
  `created_by_admin` tinyint(1) DEFAULT 0 COMMENT 'true when the merchant opened the return without a customer request',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `version` int(11) DEFAULT 0,
  `version_created_at` timestamp NULL DEFAULT NULL,
  `version_created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_ref_UNIQUE` (`ref`),
  KEY `idx_order_return_order_id` (`order_id`),
  KEY `idx_order_return_customer_id` (`customer_id`),
  KEY `idx_order_return_status_id` (`status_id`),
  KEY `idx_order_return_reason_id` (`reason_id`),
  CONSTRAINT `fk_order_return_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`),
  CONSTRAINT `fk_order_return_order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`),
  CONSTRAINT `fk_order_return_reason_id` FOREIGN KEY (`reason_id`) REFERENCES `order_return_reason` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_return_status_id` FOREIGN KEY (`status_id`) REFERENCES `order_return_status` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_return_id` int(11) NOT NULL,
  `order_product_id` int(11) NOT NULL,
  `product_sale_elements_id` int(11) DEFAULT NULL COMMENT 'the sale element to restock, snapshot from the order product',
  `quantity` float NOT NULL COMMENT 'the quantity the customer asks to return',
  `quantity_received` float DEFAULT 0 COMMENT 'the quantity actually received by the merchant',
  `received_condition` varchar(45) DEFAULT NULL COMMENT 'the condition the returned goods were received in',
  `resellable` tinyint(1) DEFAULT 0 COMMENT 'whether the received goods can be sold again',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the paid taxed price for the returned quantity of this line',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_return_line_order_return_id` (`order_return_id`),
  KEY `idx_order_return_line_order_product_id` (`order_product_id`),
  CONSTRAINT `fk_order_return_line_order_product_id` FOREIGN KEY (`order_product_id`) REFERENCES `order_product` (`id`),
  CONSTRAINT `fk_order_return_line_order_return_id` FOREIGN KEY (`order_return_id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_status_i18n` (
  `id` int(11) NOT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en_US',
  `title` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `chapo` text DEFAULT NULL,
  `postscriptum` text DEFAULT NULL,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `order_return_status_i18n_fk_2bef6d` FOREIGN KEY (`id`) REFERENCES `order_return_status` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_reason_i18n` (
  `id` int(11) NOT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en_US',
  `title` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `order_return_reason_i18n_fk_c07ca8` FOREIGN KEY (`id`) REFERENCES `order_return_reason` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_version` (
  `id` int(11) NOT NULL,
  `ref` varchar(45) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `reason_id` int(11) DEFAULT NULL COMMENT 'the merchant-managed reason, NULL once that reason is deleted',
  `reason_title` varchar(255) DEFAULT NULL COMMENT 'the reason label snapshot, kept when the reason is deleted',
  `expected_resolution` varchar(45) DEFAULT NULL COMMENT 'what the customer expects: refund, credit or exchange',
  `customer_comment` text DEFAULT NULL,
  `refusal_reason` text DEFAULT NULL COMMENT 'the motive given by the merchant when the request is refused',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the amount to refund, computed on the paid prices of the returned lines',
  `include_postage` tinyint(1) DEFAULT 0 COMMENT 'whether the postage is included in the refundable amount',
  `created_by_admin` tinyint(1) DEFAULT 0 COMMENT 'true when the merchant opened the return without a customer request',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 0,
  `version_created_at` timestamp NULL DEFAULT NULL,
  `version_created_by` varchar(100) DEFAULT NULL,
  `order_id_version` int(11) DEFAULT 0,
  `customer_id_version` int(11) DEFAULT 0,
  PRIMARY KEY (`id`,`version`),
  CONSTRAINT `order_return_version_fk_6cd0c8` FOREIGN KEY (`id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'order_return_ids');
SET @statement := IF(@add_column, 'ALTER TABLE `customer_version` ADD `order_return_ids` TEXT DEFAULT NULL', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'order_return_versions');
SET @statement := IF(@add_column, 'ALTER TABLE `customer_version` ADD `order_return_versions` TEXT DEFAULT NULL', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'order_return_ids');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `order_return_ids` TEXT DEFAULT NULL', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'order_return_versions');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `order_return_versions` TEXT DEFAULT NULL', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Reference data for the product returns feature.
--
-- The status and reason ids are fixed, the tables being new; the config,
-- message and resource rows are auto-incremented so they never collide with
-- rows an existing shop already owns, and are found again by the unique key
-- they carry.
--
-- Every statement is INSERT IGNORE: a run that stops halfway - a disconnect,
-- a statement the shop refuses - can be replayed from the top without the
-- unique keys turning the second attempt into a failure.
--
-- The i18n rows cover the eight locales a fresh install seeds (see
-- setup/insert.sql), not just two: a locale left without a row makes an
-- updated shop display a status or a reason with no label at all, exactly
-- where the customer is being asked to pick one.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `order_return_status` (`id`, `code`, `color`, `position`, `protected_status`, `created_at`, `updated_at`) VALUES
    (1, 'requested', '#f39922', 1, 1, NOW(), NOW()),
    (2, 'info_awaited', '#5bc0de', 2, 1, NOW(), NOW()),
    (3, 'accepted', '#5cb85c', 3, 1, NOW(), NOW()),
    (4, 'refused', '#dc3545', 4, 1, NOW(), NOW()),
    (5, 'received', '#986dff', 5, 1, NOW(), NOW()),
    (6, 'settled', '#20c997', 6, 1, NOW(), NOW()),
    (7, 'expired', '#6c757d', 7, 1, NOW(), NOW());

INSERT IGNORE INTO `order_return_status_i18n` (`id`, `locale`, `title`) VALUES
    (1, 'cs_CZ', NULL),
    (2, 'cs_CZ', NULL),
    (3, 'cs_CZ', NULL),
    (4, 'cs_CZ', NULL),
    (5, 'cs_CZ', NULL),
    (6, 'cs_CZ', NULL),
    (7, 'cs_CZ', NULL),
    (1, 'de_DE', 'Angefragt'),
    (2, 'de_DE', 'Warten auf Informationen'),
    (3, 'de_DE', 'Akzeptiert'),
    (4, 'de_DE', 'Abgelehnt'),
    (5, 'de_DE', 'Erhalten'),
    (6, 'de_DE', 'Abgeschlossen'),
    (7, 'de_DE', 'Abgelaufen'),
    (1, 'en_US', 'Requested'),
    (2, 'en_US', 'Information awaited'),
    (3, 'en_US', 'Accepted'),
    (4, 'en_US', 'Refused'),
    (5, 'en_US', 'Received'),
    (6, 'en_US', 'Settled'),
    (7, 'en_US', 'Expired'),
    (1, 'es_ES', 'Solicitada'),
    (2, 'es_ES', 'A la espera de información'),
    (3, 'es_ES', 'Aceptada'),
    (4, 'es_ES', 'Rechazada'),
    (5, 'es_ES', 'Recibida'),
    (6, 'es_ES', 'Resuelta'),
    (7, 'es_ES', 'Caducada'),
    (1, 'fr_FR', 'Demandé'),
    (2, 'fr_FR', 'En attente d\'informations'),
    (3, 'fr_FR', 'Accepté'),
    (4, 'fr_FR', 'Refusé'),
    (5, 'fr_FR', 'Reçu'),
    (6, 'fr_FR', 'Réglé'),
    (7, 'fr_FR', 'Expiré'),
    (1, 'it_IT', NULL),
    (2, 'it_IT', NULL),
    (3, 'it_IT', NULL),
    (4, 'it_IT', NULL),
    (5, 'it_IT', NULL),
    (6, 'it_IT', NULL),
    (7, 'it_IT', NULL),
    (1, 'nl_NL', 'Aangevraagd'),
    (2, 'nl_NL', 'Wachten op informatie'),
    (3, 'nl_NL', 'Geaccepteerd'),
    (4, 'nl_NL', 'Geweigerd'),
    (5, 'nl_NL', 'Ontvangen'),
    (6, 'nl_NL', 'Afgehandeld'),
    (7, 'nl_NL', 'Verlopen'),
    (1, 'ru_RU', 'Запрошен'),
    (2, 'ru_RU', 'Ожидается информация'),
    (3, 'ru_RU', 'Принят'),
    (4, 'ru_RU', 'Отклонён'),
    (5, 'ru_RU', 'Получен'),
    (6, 'ru_RU', 'Урегулирован'),
    (7, 'ru_RU', 'Истёк');

INSERT IGNORE INTO `order_return_reason` (`id`, `code`, `position`, `visible`, `created_at`, `updated_at`) VALUES
    (1, 'not_conform', 1, 1, NOW(), NOW()),
    (2, 'defective', 2, 1, NOW(), NOW()),
    (3, 'wrong_item', 3, 1, NOW(), NOW()),
    (4, 'no_longer_needed', 4, 1, NOW(), NOW()),
    (5, 'other', 5, 1, NOW(), NOW());

INSERT IGNORE INTO `order_return_reason_i18n` (`id`, `locale`, `title`) VALUES
    (1, 'cs_CZ', NULL),
    (2, 'cs_CZ', NULL),
    (3, 'cs_CZ', NULL),
    (4, 'cs_CZ', NULL),
    (5, 'cs_CZ', NULL),
    (1, 'de_DE', 'Produkt entspricht nicht der Beschreibung'),
    (2, 'de_DE', 'Defektes Produkt'),
    (3, 'de_DE', 'Falscher Artikel erhalten'),
    (4, 'de_DE', 'Nicht mehr benötigt'),
    (5, 'de_DE', 'Sonstiges'),
    (1, 'en_US', 'Product not as described'),
    (2, 'en_US', 'Defective product'),
    (3, 'en_US', 'Wrong item received'),
    (4, 'en_US', 'No longer needed'),
    (5, 'en_US', 'Other'),
    (1, 'es_ES', 'Producto no coincide con la descripción'),
    (2, 'es_ES', 'Producto defectuoso'),
    (3, 'es_ES', 'Artículo equivocado recibido'),
    (4, 'es_ES', 'Ya no se necesita'),
    (5, 'es_ES', 'Otro'),
    (1, 'fr_FR', 'Produit non conforme'),
    (2, 'fr_FR', 'Produit défectueux'),
    (3, 'fr_FR', 'Mauvais article reçu'),
    (4, 'fr_FR', 'Plus nécessaire'),
    (5, 'fr_FR', 'Autre'),
    (1, 'it_IT', NULL),
    (2, 'it_IT', NULL),
    (3, 'it_IT', NULL),
    (4, 'it_IT', NULL),
    (5, 'it_IT', NULL),
    (1, 'nl_NL', 'Product niet zoals beschreven'),
    (2, 'nl_NL', 'Defect product'),
    (3, 'nl_NL', 'Verkeerd artikel ontvangen'),
    (4, 'nl_NL', 'Niet langer nodig'),
    (5, 'nl_NL', 'Overig'),
    (1, 'ru_RU', 'Товар не соответствует описанию'),
    (2, 'ru_RU', 'Бракованный товар'),
    (3, 'ru_RU', 'Получен неверный товар'),
    (4, 'ru_RU', 'Больше не нужен'),
    (5, 'ru_RU', 'Другое');

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_return_enabled', '0', 0, 0, NOW(), NOW()),
    ('order_return_window_days', '14', 0, 0, NOW(), NOW()),
    ('order_return_restock_mode', 'resellable', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('order_return_status_changed', NULL, 'order_return_status_changed.txt', 'order_return_status_changed.html', NOW(), NOW());

-- Read back by name rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @order_return_message_id := (SELECT `id` FROM `message` WHERE `name` = 'order_return_status_changed');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@order_return_message_id, 'cs_CZ', NULL, NULL),
    (@order_return_message_id, 'de_DE', 'Aktualisierung des Rückgabestatus an den Kunden gesendet', 'Aktualisierung zu Ihrer Rückgabe {{ return_ref }}'),
    (@order_return_message_id, 'en_US', 'Return status update sent to the customer', 'Update on your return {{ return_ref }}'),
    (@order_return_message_id, 'es_ES', 'Actualización del estado de la devolución enviada al cliente', 'Actualización de tu devolución {{ return_ref }}'),
    (@order_return_message_id, 'fr_FR', 'Mise à jour du statut de retour envoyée au client', 'Mise à jour de votre retour {{ return_ref }}'),
    (@order_return_message_id, 'it_IT', NULL, NULL),
    (@order_return_message_id, 'nl_NL', 'Update van de retourstatus naar de klant verzonden', 'Update over je retour {{ return_ref }}'),
    (@order_return_message_id, 'ru_RU', 'Обновление статуса возврата отправлено клиенту', 'Обновление по вашему возврату {{ return_ref }}');

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.order-return', NOW(), NOW()),
    ('admin.configuration.order-return-reason', NOW(), NOW());

SET @order_return_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.order-return');
SET @order_return_reason_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.order-return-reason');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@order_return_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@order_return_resource_id, 'de_DE', 'Produktrückgaben', NULL, NULL, NULL),
    (@order_return_resource_id, 'en_US', 'Product returns', NULL, NULL, NULL),
    (@order_return_resource_id, 'es_ES', 'Devoluciones de productos', NULL, NULL, NULL),
    (@order_return_resource_id, 'fr_FR', 'Retours produits', NULL, NULL, NULL),
    (@order_return_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@order_return_resource_id, 'nl_NL', 'Productretouren', NULL, NULL, NULL),
    (@order_return_resource_id, 'ru_RU', 'Возвраты товаров', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'de_DE', 'Rückgabegründe', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'en_US', 'Return reasons', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'es_ES', 'Motivos de devolución', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'fr_FR', 'Motifs de retour', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'nl_NL', 'Retourredenen', NULL, NULL, NULL),
    (@order_return_reason_resource_id, 'ru_RU', 'Причины возврата', NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
