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

SET FOREIGN_KEY_CHECKS = 1;
