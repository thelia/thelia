SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tax types of a shop migrated from Thelia 2
--
-- A tax records the class of its type in `tax`.`type`, and the three types
-- moved from Thelia\TaxEngine\TaxType to Thelia\Domain\Taxation\TaxEngine\TaxType
-- in Thelia 3. Until this release the update only carried the percentage type
-- over, so a fixed amount tax or a tax on a product feature kept its Thelia 2
-- class name, which no tax type answers to any more: computing the price of a
-- product under such a rule failed with "Recorded type ... does not exists".
--
-- Each statement only matches a row still on the Thelia 2 name, so the
-- script can be replayed.
-- ---------------------------------------------------------------------

UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\PricePercentTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\PricePercentTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FeatureFixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FeatureFixAmountTaxType';

-- The manufacturer part number and the manufacturer brand of a combination, beside its GTIN
-- kept in `ean_code`. Null on every combination that predates them: no part number, and the
-- brand of the product stands for the manufacturer. The order line freezes the part number
-- sold, as it already does for the GTIN.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'manufacturer_brand_id');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `manufacturer_brand_id` INTEGER NULL AFTER `mpn`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The search by part number and the manufacturer brand filter read these columns: without an
-- index they scan the whole table. The index on `ean_code` is added by 3.2.0.sql, for the quick order.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_mpn');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_mpn` (`mpn`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_manufacturer_brand_id` (`manufacturer_brand_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `CONSTRAINT_NAME` = 'fk_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `product_sale_elements` ADD CONSTRAINT `fk_product_sale_elements_manufacturer_brand_id` FOREIGN KEY (`manufacturer_brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Hooks of the return screens of the back office
--
-- The return list and the return sheet of the back office call these
-- insertion points, but no row declared them: a module could not register
-- on them, and a theme rendered them empty. INSERT IGNORE keeps a hook a
-- merchant already created under the same code.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    ('order-returns.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    ('order-returns.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    ('order-returns.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    ('order-return-edit.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
    ('order-return-edit.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW());

INSERT IGNORE INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`)
SELECT `hook`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL
FROM `hook`
INNER JOIN (
    SELECT 'order-returns.top' AS `code`, 'en_US' AS `locale`, 'Returns - at the top' AS `title`
    UNION ALL SELECT 'order-returns.top', 'fr_FR', 'Retours - en haut'
    UNION ALL SELECT 'order-returns.top', 'de_DE', 'Rücksendungen - oben'
    UNION ALL SELECT 'order-returns.bottom', 'en_US', 'Returns - at the bottom'
    UNION ALL SELECT 'order-returns.bottom', 'fr_FR', 'Retours - en bas'
    UNION ALL SELECT 'order-returns.bottom', 'de_DE', 'Rücksendungen - unten'
    UNION ALL SELECT 'order-returns.js', 'en_US', 'Returns - JavaScript'
    UNION ALL SELECT 'order-returns.js', 'fr_FR', 'Retours - JavaScript'
    UNION ALL SELECT 'order-returns.js', 'de_DE', 'Rücksendungen - JavaScript'
    UNION ALL SELECT 'order-return-edit.top', 'en_US', 'Return edit - at the top'
    UNION ALL SELECT 'order-return-edit.top', 'fr_FR', 'Édition d''un retour - en haut'
    UNION ALL SELECT 'order-return-edit.top', 'de_DE', 'Rücksendung bearbeiten - oben'
    UNION ALL SELECT 'order-return-edit.bottom', 'en_US', 'Return edit - at the bottom'
    UNION ALL SELECT 'order-return-edit.bottom', 'fr_FR', 'Édition d''un retour - en bas'
    UNION ALL SELECT 'order-return-edit.bottom', 'de_DE', 'Rücksendung bearbeiten - unten'
) AS `labels` ON `labels`.`code` = `hook`.`code`
WHERE `hook`.`type` = 2;

SET FOREIGN_KEY_CHECKS = 1;
