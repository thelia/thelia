SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- customer.anonymized_at (#3589)
--
-- Marks an account whose identifying data has been erased. A shop needs it
-- to tell an anonymous account from an account that simply has no name yet,
-- to stop offering the operation twice, and to skip already erased accounts
-- when a retention job runs - hence the index.
--
-- `customer` is versionable, so the column is mirrored in customer_version.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `COLUMN_NAME` = 'anonymized_at');
SET @statement := IF(@add_column, 'ALTER TABLE `customer` ADD `anonymized_at` DATETIME COMMENT \'when the identifying data of the account was erased, NULL as long as the account carries an identity\' AFTER `confirmation_token_expires_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer' AND `INDEX_NAME` = 'idx_customer_anonymized_at');
SET @statement := IF(@add_index, 'CREATE INDEX `idx_customer_anonymized_at` ON `customer` (`anonymized_at`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_version' AND `COLUMN_NAME` = 'anonymized_at');
SET @statement := IF(@add_column, 'ALTER TABLE `customer_version` ADD `anonymized_at` DATETIME COMMENT \'when the identifying data of the account was erased, NULL as long as the account carries an identity\' AFTER `confirmation_token_expires_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- order.payment_module_id and order.delivery_module_id become nullable (#1145)
--
-- A module used by at least one order can never be deleted today, because
-- both columns are NOT NULL behind a RESTRICT foreign key. Dropping NOT NULL
-- is the structural half of letting an order outlive its module; the code
-- that fills in the module identity on the order, and the deletion path
-- itself, follow separately.
--
-- No existing row changes: every order keeps the module ids it has.
-- `order` is versionable, so order_version is altered the same way.
-- ---------------------------------------------------------------------

SET @drop_not_null := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'payment_module_id' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@drop_not_null, 'ALTER TABLE `order` MODIFY `payment_module_id` INTEGER NULL COMMENT \'the module that took the payment, NULL once that module is gone\'', 'DO 0');
PREPARE drop_not_null_statement FROM @statement;
EXECUTE drop_not_null_statement;
DEALLOCATE PREPARE drop_not_null_statement;

SET @drop_not_null := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_module_id' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@drop_not_null, 'ALTER TABLE `order` MODIFY `delivery_module_id` INTEGER NULL COMMENT \'the module that shipped the order, NULL once that module is gone\'', 'DO 0');
PREPARE drop_not_null_statement FROM @statement;
EXECUTE drop_not_null_statement;
DEALLOCATE PREPARE drop_not_null_statement;

SET @drop_not_null := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'payment_module_id' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@drop_not_null, 'ALTER TABLE `order_version` MODIFY `payment_module_id` INTEGER NULL COMMENT \'the module that took the payment, NULL once that module is gone\'', 'DO 0');
PREPARE drop_not_null_statement FROM @statement;
EXECUTE drop_not_null_statement;
DEALLOCATE PREPARE drop_not_null_statement;

SET @drop_not_null := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_module_id' AND `IS_NULLABLE` = 'NO');
SET @statement := IF(@drop_not_null, 'ALTER TABLE `order_version` MODIFY `delivery_module_id` INTEGER NULL COMMENT \'the module that shipped the order, NULL once that module is gone\'', 'DO 0');
PREPARE drop_not_null_statement FROM @statement;
EXECUTE drop_not_null_statement;
DEALLOCATE PREPARE drop_not_null_statement;

SET FOREIGN_KEY_CHECKS = 1;
