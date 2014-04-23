# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';


ALTER TABLE `module` ADD INDEX `idx_module_position` (`position`);


SET FOREIGN_KEY_CHECKS = 1;
