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
    `accepted` TINYINT DEFAULT 0 NOT NULL COMMENT 'whether the box was ticked',
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

SET FOREIGN_KEY_CHECKS = 1;
