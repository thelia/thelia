SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 3.1.2 is a security release without any schema change. This script
-- only exists so that the update records the new version: the update
-- moves the database to the last script it finds, not to the code version,
-- and only protects the documents already published once it has a script
-- to run.
-- ---------------------------------------------------------------------

UPDATE `config` SET `value`='3.1.2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;
