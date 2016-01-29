SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Add new column in module_hook table
ALTER TABLE `module_hook` ADD `templates` TEXT AFTER`position`;

-- Add new columns in currency table
ALTER TABLE `currency` ADD  `format` CHAR( 10 ) NOT NULL AFTER  `symbol`;
ALTER TABLE `currency` ADD  `visible` TINYINT( 1 ) NOT NULL DEFAULT '0' AFTER  `rate`;

-- Update currencies
UPDATE `currency` SET `visible` = 1 WHERE 1;
UPDATE `currency` SET `format` = '%n %s' WHERE `code` NOT IN ('USD', 'GBP');
UPDATE `currency` SET `format` = '%s%n' WHERE `code` IN ('USD', 'GBP');

--Update product's position
ALTER TABLE  `product_category` ADD  `position` INT NOT NULL DEFAULT  '0' AFTER  `default_category` ;
UPDATE product_category AS t1 SET position = (SELECT position FROM product AS t2 WHERE t2.id=t1.product_id) WHERE default_category = 1;
DROP PROCEDURE IF EXISTS update_position;
DELIMITER //
CREATE PROCEDURE update_position()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE mproduct, mcategory,maxposition INT;
    DECLARE cur1 CURSOR FOR SELECT product_id, category_id FROM product_category WHERE default_category = NULL OR default_category = 0;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    OPEN cur1;
        read_loop: LOOP
            FETCH cur1 INTO mproduct,mcategory;
            IF done THEN
                LEAVE read_loop;
            END IF;
            SELECT MAX(position)+1 into maxposition FROM product_category WHERE category_id = mcategory;
            UPDATE product_category SET position = maxposition WHERE category_id = mcategory AND product_id = mproduct;
        END LOOP;
    CLOSE cur1;
END//
DELIMITER ;
CALL update_position();
DROP PROCEDURE IF EXISTS update_position;

SET FOREIGN_KEY_CHECKS = 1;