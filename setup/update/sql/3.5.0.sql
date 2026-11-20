SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Sale units: low stock threshold
--
-- `sale_unit_product_sale_elements.low_stock_threshold` is the stock level of
-- a sale unit at or under which the shop owner is alerted. Empty, the sale unit
-- takes the threshold of its sale element, then the shop-wide one: no existing
-- shop changes behaviour and no row is rewritten. The column is added only when
-- missing, so the script can be replayed.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'sale_unit_product_sale_elements' AND `COLUMN_NAME` = 'low_stock_threshold');
SET @statement := IF(@add_column, 'ALTER TABLE `sale_unit_product_sale_elements` ADD `low_stock_threshold` DOUBLE COMMENT \'stock level at or under which the shop owner is alerted for this sale unit; null: the threshold of the sale element\' AFTER `quantity`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET FOREIGN_KEY_CHECKS = 1;
