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

-- ---------------------------------------------------------------------
-- The Mexican federal district kept its pre-2016 name (#3168)
--
-- The entity was renamed Ciudad de Mexico in 2016, when it was also recoded
-- 'CMX'. The Spanish title already said so, the English, French and Russian
-- ones still read Distrito Federal, so the same state answered to two names
-- depending on the language of the shop.
--
-- Matched on the outdated title, so a shop that renamed it by hand is left
-- untouched and a second run is a no-op.
-- ---------------------------------------------------------------------

UPDATE `state_i18n`
INNER JOIN `state` ON `state`.`id` = `state_i18n`.`id`
INNER JOIN `country` ON `country`.`id` = `state`.`country_id`
SET `state_i18n`.`title` = 'Ciudad de México'
WHERE `country`.`isoalpha3` = 'MEX'
  AND `state`.`isocode` = 'CMX'
  AND `state_i18n`.`locale` IN ('en_US', 'fr_FR')
  AND `state_i18n`.`title` = 'Distrito Federal';

UPDATE `state_i18n`
INNER JOIN `state` ON `state`.`id` = `state_i18n`.`id`
INNER JOIN `country` ON `country`.`id` = `state`.`country_id`
SET `state_i18n`.`title` = 'Мехико'
WHERE `country`.`isoalpha3` = 'MEX'
  AND `state`.`isocode` = 'CMX'
  AND `state_i18n`.`locale` = 'ru_RU'
  AND `state_i18n`.`title` = 'Федеральный округ';

-- ---------------------------------------------------------------------
-- Four Italian provinces dissolved in 2016 (#3168)
--
-- Carbonia-Iglesias, Medio Campidano, Ogliastra and Olbia-Tempio were merged
-- into Sud Sardegna and Sassari. ISO 3166-2:IT withdrew IT-CI, IT-VS, IT-OG
-- and IT-OT and assigns IT-SU to the new province, so the four rows produced
-- a code the standard no longer defines.
--
-- They are hidden rather than deleted: an address already pointing at one of
-- them keeps its state, it simply cannot be picked again. Sud Sardegna is
-- added if the shop does not have it yet, so Sardinia stays selectable.
-- ---------------------------------------------------------------------

SET @italy_id := (SELECT `id` FROM `country` WHERE `isoalpha3` = 'ITA' LIMIT 1);

UPDATE `state` SET `visible` = 0
WHERE `country_id` = @italy_id AND `isocode` IN ('CI', 'OG', 'OT', 'VS');

INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, 'SU', @italy_id, NOW(), NOW() FROM DUAL
WHERE @italy_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @italy_id AND s.`isocode` = 'SU');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, l.`locale`, NULL FROM `state` s, `lang` l
WHERE s.`country_id` = @italy_id AND s.`isocode` = 'SU'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = l.`locale`);

UPDATE `state_i18n` INNER JOIN `state` ON `state`.`id` = `state_i18n`.`id`
SET `state_i18n`.`title` = 'Sud Sardegna'
WHERE `state`.`country_id` = @italy_id AND `state`.`isocode` = 'SU'
  AND `state_i18n`.`locale` IN ('en_US', 'fr_FR', 'it_IT')
  AND `state_i18n`.`title` IS NULL;

UPDATE `state_i18n` INNER JOIN `state` ON `state`.`id` = `state_i18n`.`id`
SET `state_i18n`.`title` = 'Южная Сардиния'
WHERE `state`.`country_id` = @italy_id AND `state`.`isocode` = 'SU'
  AND `state_i18n`.`locale` = 'ru_RU'
  AND `state_i18n`.`title` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
