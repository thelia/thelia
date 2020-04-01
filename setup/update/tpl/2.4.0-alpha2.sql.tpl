SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

ALTER TABLE `feature_product` ADD `is_free_text` TINYINT(1) NOT NULL DEFAULT '0' AFTER `free_text_value`;
ALTER TABLE `feature_product` MODIFY COLUMN `free_text_value` TEXT COMMENT 'deprecated';
UPDATE `feature_product` SET `is_free_text`=IF(`free_text_value` IS NULL, 0, 1);

DELIMITER $$
CREATE TRIGGER `remove_free_text_feature_av` AFTER DELETE ON `feature_product`
 FOR EACH ROW IF OLD.`is_free_text` = 1 THEN
  DELETE FROM `feature_av` WHERE `id` = OLD.`feature_av_id`;
END IF
$$
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;
