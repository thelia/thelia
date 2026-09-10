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
-- Order status transitions and automatic actions
--
-- The transitions a merchant allows from a status. A status with no row
-- leaving it stays free, which is the behaviour of every shop before this
-- version: no row is seeded, so an updated shop keeps proposing every status.
-- RESTRICT on both sides: a status that still carries part of the graph is
-- removed through the core action, which drops the rows first, never by a
-- cascade that would silently reshape the graph.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_transition`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `from_status_id` INTEGER NOT NULL,
    `to_status_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `order_status_transition_from_to_UNIQUE` (`from_status_id`, `to_status_id`),
    INDEX `idx_order_status_transition_to_status_id` (`to_status_id`),
    CONSTRAINT `fk_order_status_transition_from_status_id`
        FOREIGN KEY (`from_status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_status_transition_to_status_id`
        FOREIGN KEY (`to_status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- What the shop does when an order enters a status (trigger_type `enter`) or
-- follows one transition (trigger_type `transition`, from_status_id set).
-- action_type names a service shipped by the core or a module; payload holds
-- the parameters of that service as JSON, validated by the service itself.
-- position orders the actions of one trigger.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_action`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `trigger_type` VARCHAR(20) NOT NULL,
    `from_status_id` INTEGER,
    `to_status_id` INTEGER NOT NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `payload` TEXT,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `active` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_status_action_to_status_id` (`to_status_id`),
    INDEX `idx_order_status_action_from_status_id` (`from_status_id`),
    CONSTRAINT `fk_order_status_action_from_status_id`
        FOREIGN KEY (`from_status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_status_action_to_status_id`
        FOREIGN KEY (`to_status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- A failed action leaves the status changed and lands here, so the back
-- office can show what did not happen on which order.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_action_failure`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `action_id` INTEGER NOT NULL,
    `order_id` INTEGER NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_status_action_failure_order_id` (`order_id`),
    INDEX `fk_order_status_action_failure_action_id` (`action_id`),
    CONSTRAINT `fk_order_status_action_failure_action_id`
        FOREIGN KEY (`action_id`)
        REFERENCES `order_status_action` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_status_action_failure_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The automatisms the core already runs through its listeners (invoice
-- numbering on payment, coupon release when an order stops being paid) are
-- offered as actions too, switched off so that nothing runs twice. Seeded
-- only when the table is still empty, so the script can be replayed.
INSERT INTO `order_status_action` (`trigger_type`, `from_status_id`, `to_status_id`, `action_type`, `payload`, `position`, `active`, `created_at`, `updated_at`)
SELECT `seed`.`trigger_type`, NULL, `order_status`.`id`, `seed`.`action_type`, NULL, `seed`.`position`, 0, NOW(), NOW()
FROM (
    SELECT 'enter' AS `trigger_type`, 'paid' AS `status_code`, 'allocate_invoice_ref' AS `action_type`, 1 AS `position`
    UNION ALL SELECT 'enter', 'not_paid', 'release_coupons', 1
    UNION ALL SELECT 'enter', 'canceled', 'release_coupons', 1
    UNION ALL SELECT 'enter', 'refunded', 'release_coupons', 1
) AS `seed`
INNER JOIN `order_status` ON `order_status`.`code` = `seed`.`status_code`
WHERE NOT EXISTS (SELECT 1 FROM `order_status_action`);

SET FOREIGN_KEY_CHECKS = 1;
