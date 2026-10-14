SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 3.2.3 is a maintenance release of the 3.2 line without any schema
-- change. This script only exists so that the update records the new
-- version: the update moves the database to the last script it finds, not
-- to the code version.
-- ---------------------------------------------------------------------

UPDATE `config` SET `value`='3.2.3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;
