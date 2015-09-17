SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.6' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

UPDATE hook SET by_module = '1' WHERE hook.code = 'module.configuration';

SET FOREIGN_KEY_CHECKS = 1;