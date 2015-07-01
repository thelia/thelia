SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.9' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='9' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;