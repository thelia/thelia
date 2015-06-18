SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

-- fix currency already created
update currency set by_default = 0 where by_default is NULL;

ALTER TABLE `category_version` ADD COLUMN `default_template_id` INTEGER AFTER  `position`;

SET FOREIGN_KEY_CHECKS = 1;
