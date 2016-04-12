SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-beta2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta2' WHERE `name`='thelia_extra_version';

-- Fix content folder position
UPDATE `content_folder` INNER JOIN `content` ON `content_folder`.`content_id`=`content`.`id` SET `content_folder`.`position`=`content`.`position`;

SET FOREIGN_KEY_CHECKS = 1;
