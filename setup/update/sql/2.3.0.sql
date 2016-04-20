SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

-- Fix lang date/time format for fr_FR
UPDATE `lang` SET datetime_format = 'd/m/Y H:i:s' WHERE locale = 'fr_FR' and datetime_format = 'd/m/y H:i:s';

SET FOREIGN_KEY_CHECKS = 1;
