SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `order` DROP FOREIGN KEY `fk_order_cart_id`;

UPDATE `config` SET `value`='2.0.6' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;