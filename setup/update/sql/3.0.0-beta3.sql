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
-- order.payment_module_title and order.delivery_module_title (#1145)
--
-- An order names the method it used only through its module id, so the label
-- printed on an invoice is resolved at render time. Deleting the module - now
-- that both ids are nullable - would leave the order with nothing to show.
-- These two columns hold the name the module carried, in the language of the
-- order, and are written when the module is deleted; they stay NULL for as
-- long as the module is installed and can be read from the module table.
--
-- `order` is versionable, so order_version gets the same two columns.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'payment_module_title');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `payment_module_title` VARCHAR(255) COMMENT \'the name the payment module had when it was deleted, NULL while the module is still installed\' AFTER `payment_module_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_module_title');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_module_title` VARCHAR(255) COMMENT \'the name the delivery module had when it was deleted, NULL while the module is still installed\' AFTER `delivery_module_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'payment_module_title');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `payment_module_title` VARCHAR(255) COMMENT \'the name the payment module had when it was deleted, NULL while the module is still installed\' AFTER `payment_module_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_module_title');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_module_title` VARCHAR(255) COMMENT \'the name the delivery module had when it was deleted, NULL while the module is still installed\' AFTER `delivery_module_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Two outdated ISO 3166-2 codes in the Mexican state seed (#3168)
--
-- Aguascalientes was seeded as 'AGS' where ISO 3166-2:MX assigns 'AGU', and
-- the Federal District kept 'DIF' although the entity became Ciudad de Mexico
-- in 2016 and is coded 'CMX'. State.getIsoCode3166_2() concatenates the
-- country alpha-2 code with this value, so both rows produced a code that
-- does not exist in the standard.
--
-- Matched on the country alpha-3 code and on the outdated value only, so a
-- shop that already corrected them by hand is left untouched, and a second
-- run is a no-op.
-- ---------------------------------------------------------------------

UPDATE `state` INNER JOIN `country` ON `country`.`id` = `state`.`country_id`
SET `state`.`isocode` = 'AGU'
WHERE `country`.`isoalpha3` = 'MEX' AND `state`.`isocode` = 'AGS';

UPDATE `state` INNER JOIN `country` ON `country`.`id` = `state`.`country_id`
SET `state`.`isocode` = 'CMX'
WHERE `country`.`isoalpha3` = 'MEX' AND `state`.`isocode` = 'DIF';

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

-- ---------------------------------------------------------------------
-- Translations of the account activation code message (#3659)
--
-- The `customer_send_code` message was added by 3.0.0-alpha1.sql, and by
-- setup/insert.sql, without a single `message_i18n` row: the subject of a
-- mail comes from that table, so every activation code left the shop with
-- an empty subject line.
--
-- One row per language installed in the shop, then the English and French
-- wordings. Existing rows are left as they are, so a shop that already
-- typed its own subject in the back office keeps it.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`)
SELECT `message`.`id`, `lang`.`locale`, NULL, NULL, NULL, NULL
FROM `message`, `lang`
WHERE `message`.`name` = 'customer_send_code';

UPDATE `message_i18n` INNER JOIN `message` ON `message`.`id` = `message_i18n`.`id`
SET `message_i18n`.`title` = 'Mail sent to the customer with the code that activates the account',
    `message_i18n`.`subject` = 'Your {{ config(\"store_name\") }} activation code'
WHERE `message`.`name` = 'customer_send_code'
  AND `message_i18n`.`locale` = 'en_US'
  AND (`message_i18n`.`subject` IS NULL OR `message_i18n`.`subject` = '');

UPDATE `message_i18n` INNER JOIN `message` ON `message`.`id` = `message_i18n`.`id`
SET `message_i18n`.`title` = 'E-mail envoyé au client avec le code d\'activation de son compte',
    `message_i18n`.`subject` = 'Votre code d\'activation {{ config(\"store_name\") }}'
WHERE `message`.`name` = 'customer_send_code'
  AND `message_i18n`.`locale` = 'fr_FR'
  AND (`message_i18n`.`subject` IS NULL OR `message_i18n`.`subject` = '');

SET FOREIGN_KEY_CHECKS = 1;
