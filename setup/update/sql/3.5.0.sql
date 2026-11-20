SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Purchase lists: decimal quantities
--
-- `customer_list_item.quantity` holds what a buyer saves for a sale element,
-- 1.25 kg of a product sold by measure as well as 3 pieces. It moves from an
-- INTEGER to a DOUBLE, as `cart_item.quantity` did in 3.4.0. A whole number
-- converts as it is: no saved list changes. The statement runs only while the
-- column is still an integer, so the script can be replayed.
--
SET @list_quantity_to_double := (SELECT COUNT(*) = 1 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'customer_list_item' AND `COLUMN_NAME` = 'quantity' AND `DATA_TYPE` = 'int');
SET @statement := IF(@list_quantity_to_double, 'ALTER TABLE `customer_list_item` MODIFY `quantity` DOUBLE NOT NULL', 'DO 0');
PREPARE list_quantity_to_double_statement FROM @statement;
EXECUTE list_quantity_to_double_statement;
DEALLOCATE PREPARE list_quantity_to_double_statement;

SET FOREIGN_KEY_CHECKS = 1;
