SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;
