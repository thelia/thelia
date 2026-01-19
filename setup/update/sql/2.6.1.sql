SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.6.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

ALTER TABLE product_image_i18n ADD COLUMN `file` VARCHAR(255) NOT NULL AFTER `locale`;

UPDATE product_image_i18n AS dest
INNER JOIN product_image AS src ON dest.id = src.id
SET dest.file = src.file;

ALTER TABLE product_image DROP COLUMN `file`;

SET FOREIGN_KEY_CHECKS = 1;
