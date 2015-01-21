SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- new column in admin_log
ALTER TABLE `admin_log` ADD `resource_id` INTEGER AFTER `resource` ;

SET FOREIGN_KEY_CHECKS = 1;