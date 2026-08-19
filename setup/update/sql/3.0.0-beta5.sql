SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Company identifiers of the customer
--
-- Electronic invoicing requires the legal identifier and the VAT number of a
-- business buyer to appear on the invoice. They belong to the address, next
-- to the company name they identify, and not to the customer: one customer
-- may bill under several legal entities.
--
-- The three tables mirror the path `company` already takes, so that the
-- values are copied from the address to the cart and then frozen on the
-- order. Existing rows stay NULL; nothing is required retroactively.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'address' AND `COLUMN_NAME` = 'siret');
SET @statement := IF(@add_column, 'ALTER TABLE `address` ADD `siret` VARCHAR(20) COMMENT \'company registration number, normalized, NULL when the address carries no company name\' AFTER `company`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'address' AND `COLUMN_NAME` = 'vat_number');
SET @statement := IF(@add_column, 'ALTER TABLE `address` ADD `vat_number` VARCHAR(20) COMMENT \'VAT number, normalized and upper-cased, NULL when the address carries no company name\' AFTER `siret`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_address' AND `COLUMN_NAME` = 'siret');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_address` ADD `siret` VARCHAR(20) COMMENT \'company registration number, copied from the address the cart address was built from\' AFTER `company`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart_address' AND `COLUMN_NAME` = 'vat_number');
SET @statement := IF(@add_column, 'ALTER TABLE `cart_address` ADD `vat_number` VARCHAR(20) COMMENT \'VAT number, copied from the address the cart address was built from\' AFTER `siret`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_address' AND `COLUMN_NAME` = 'siret');
SET @statement := IF(@add_column, 'ALTER TABLE `order_address` ADD `siret` VARCHAR(20) COMMENT \'company registration number frozen on the order, as the other address fields are\' AFTER `company`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_address' AND `COLUMN_NAME` = 'vat_number');
SET @statement := IF(@add_column, 'ALTER TABLE `order_address` ADD `vat_number` VARCHAR(20) COMMENT \'VAT number frozen on the order, as the other address fields are\' AFTER `siret`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET FOREIGN_KEY_CHECKS = 1;
