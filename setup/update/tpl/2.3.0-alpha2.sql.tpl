SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

-- Add column unsubscribed in newsletter table
ALTER TABLE `newsletter` ADD `unsubscribed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `locale`;

SET FOREIGN_KEY_CHECKS = 1;
