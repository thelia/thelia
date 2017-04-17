SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

-- Add column position in the product_category and content_folder table
ALTER TABLE `product_category` ADD `position` INT(11) NOT NULL AFTER `default_category`;
ALTER TABLE `content_folder` ADD `position` INT(11) NOT NULL AFTER `default_folder`;

UPDATE `product_category` INNER JOIN `product` ON `product_category`.`product_id`=`product`.`id` SET `product_category`.`position`=`product`.`position`;

ALTER TABLE `product` CHANGE `position` `position` INT(11) COMMENT 'This column is deprecated since 2.3, and will be removed in 2.5';
ALTER TABLE `content` CHANGE `position` `position` INT(11) COMMENT 'This column is deprecated since 2.3, and will be removed in 2.5';

SET FOREIGN_KEY_CHECKS = 1;
