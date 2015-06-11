SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.5' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

ALTER TABLE `category` CHANGE `parent` `parent` INT( 11 ) NULL DEFAULT '0';
ALTER TABLE `category_version` CHANGE `parent` `parent` INT( 11 ) NULL DEFAULT '0';

SET FOREIGN_KEY_CHECKS = 1;