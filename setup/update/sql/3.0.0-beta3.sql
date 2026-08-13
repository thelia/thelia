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

-- ---------------------------------------------------------------------
-- order_postage_tax (#3701)
--
-- `order.postage_tax` is a single blended figure, and `postage_tax_rule_title`
-- names one rule. A cart holding goods at two rates pays one postage, and
-- nothing recorded how its tax split. This table holds one row per tax rule
-- the postage follows, with the share of the untaxed postage it applies to
-- and the tax due on that share.
--
-- No foreign key to `tax_rule`: the title is frozen on the order, the same
-- snapshot discipline `order_product_tax` and `postage_tax_rule_title` follow.
--
-- Existing orders need no backfill. An order with no row reads as what it is,
-- one rate named in `postage_tax_rule_title`, which is also what every order
-- placed under the default `single_rule` strategy keeps looking like.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `order_postage_tax`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `title` VARCHAR(255) NOT NULL COMMENT 'the tax rule this share of the postage follows, frozen the way postage_tax_rule_title is',
    `description` LONGTEXT,
    `untaxed_amount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the share of the untaxed postage this rule applies to',
    `amount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL COMMENT 'the tax due on that share',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_postage_tax_order_id` (`order_id`),
    CONSTRAINT `fk_order_postage_tax_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- product_sale_elements_virtual_document (#3520)
--
-- The file a virtual product is downloaded from was a meta_data row keyed
-- ('virtual', 'pse', sale element id), holding the document id as text. Nothing
-- tied it to the two tables it points at: deleting the document or the
-- combination left the row behind, and since InnoDB does not persist
-- AUTO_INCREMENT across a restart, a sale element created later could be given
-- a freed id and inherit an association nobody set. The pair was not unique
-- either, so two writes could leave two rows and the reader picked one.
--
-- The association now has its own table, with a foreign key on each side and a
-- unique pair. `position` is there so that offering several files per
-- combination becomes a second row rather than a second migration; the service
-- layer keeps writing a single row until that product decision is taken.
--
-- Existing rows are moved over, then dropped: nothing reads the key any more.
-- `value` is a CLOB any module can write through MetaDataQuery::setVal(), so a
-- serialized payload would be cast to 0 - hence the two guards. Rows pointing
-- at a document or a combination that no longer exists cannot be carried over
-- and are reported by the SELECT below before they go.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `product_sale_elements_virtual_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_document_id` INTEGER NOT NULL,
    `position` INTEGER DEFAULT 1 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `pse_virtual_document_unique_idx` (`product_sale_elements_id`, `product_document_id`),
    INDEX `fk_pse_virtual_document_product_document_idx` (`product_document_id`),
    CONSTRAINT `fk_pse_virtual_document_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_pse_virtual_document_product_document_id`
        FOREIGN KEY (`product_document_id`)
        REFERENCES `product_document` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The associations that will not be carried over, listed rather than discarded
-- in silence. An empty result means everything moved.
SELECT `meta_data`.`element_id` AS `product_sale_elements_id`, `meta_data`.`value` AS `product_document_id`
FROM `meta_data`
LEFT JOIN `product_sale_elements` ON `product_sale_elements`.`id` = `meta_data`.`element_id`
LEFT JOIN `product_document` ON `product_document`.`id` = CAST(`meta_data`.`value` AS UNSIGNED)
WHERE `meta_data`.`meta_key` = 'virtual' AND `meta_data`.`element_key` = 'pse'
  AND (`product_sale_elements`.`id` IS NULL OR `product_document`.`id` IS NULL
       OR `meta_data`.`is_serialized` <> 0 OR `meta_data`.`value` NOT REGEXP '^[0-9]+$');

INSERT IGNORE INTO `product_sale_elements_virtual_document`
    (`product_sale_elements_id`, `product_document_id`, `position`, `created_at`, `updated_at`)
SELECT `meta_data`.`element_id`, `product_document`.`id`, 1, NOW(), NOW()
FROM `meta_data`
INNER JOIN `product_sale_elements` ON `product_sale_elements`.`id` = `meta_data`.`element_id`
INNER JOIN `product_document` ON `product_document`.`id` = CAST(`meta_data`.`value` AS UNSIGNED)
WHERE `meta_data`.`meta_key` = 'virtual'
  AND `meta_data`.`element_key` = 'pse'
  AND `meta_data`.`is_serialized` = 0
  AND `meta_data`.`value` REGEXP '^[0-9]+$';

DELETE FROM `meta_data` WHERE `meta_key` = 'virtual' AND `element_key` = 'pse';

-- ---------------------------------------------------------------------
-- Company identifiers of the store
--
-- The legal identifiers of a shop used to be typed in a single free-text
-- key, `store_business_id`. Electronic invoicing needs each of them on its
-- own, so one key per identifier is created here. Existing rows are left
-- alone, and `store_business_id` is kept: the PHP script of this version
-- reads it, and the PDF templates still fall back to it.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('store_siret', '', 0, 1, NOW(), NOW()),
('store_vat_intracom', '', 0, 1, NOW(), NOW()),
('store_ape_code', '', 0, 1, NOW(), NOW()),
('store_eori', '', 0, 1, NOW(), NOW()),
('store_vat_exempt', '0', 0, 1, NOW(), NOW()),
('store_registration_exempt', '0', 0, 1, NOW(), NOW()),
('store_legal_mentions', '', 0, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;

-- Some mail subjects were seeded with a `%store` placeholder nothing ever
-- replaced, so the shop name never reached the mail. Bring them to the Twig
-- call the other locales already use. REPLACE leaves the other rows alone.
UPDATE `message_i18n`
SET `subject` = REPLACE(`subject`, '%store', '{{ config("store_name") }}')
WHERE `subject` IS NOT NULL;
