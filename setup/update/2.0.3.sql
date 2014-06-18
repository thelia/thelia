# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

# Add new column to order (version, version_created_at, version_created_by)

ALTER TABLE `order` ADD `version` INT DEFAULT 0 AFTER `updated_at`;
ALTER TABLE `order` ADD `version_created_at` DATE AFTER `version`;
ALTER TABLE `order` ADD `version_created_by` VARCHAR(100) AFTER `version_created_at`;

DROP TABLE IF EXISTS `order_version`;

CREATE TABLE `order_version`
(
    `id` INTEGER NOT NULL,
    `ref` VARCHAR(45),
    `customer_id` INTEGER NOT NULL,
    `invoice_order_address_id` INTEGER NOT NULL,
    `delivery_order_address_id` INTEGER NOT NULL,
    `invoice_date` DATE,
    `currency_id` INTEGER NOT NULL,
    `currency_rate` FLOAT NOT NULL,
    `transaction_ref` VARCHAR(100) COMMENT 'transaction reference - usually use to identify a transaction with banking modules',
    `delivery_ref` VARCHAR(100) COMMENT 'delivery reference - usually use to identify a delivery progress on a distant delivery tracker website',
    `invoice_ref` VARCHAR(100) COMMENT 'the invoice reference',
    `discount` FLOAT,
    `postage` FLOAT NOT NULL,
    `payment_module_id` INTEGER NOT NULL,
    `delivery_module_id` INTEGER NOT NULL,
    `status_id` INTEGER NOT NULL,
    `lang_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `order_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `order` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

SET FOREIGN_KEY_CHECKS = 1;