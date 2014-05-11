# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

# order table
ALTER TABLE `order` ADD `discount` FLOAT AFTER `invoice_ref` ;

# coupon table
ALTER TABLE `coupon` DROP INDEX `idx_amount`;
ALTER TABLE `coupon` DROP `amount`;
ALTER TABLE `coupon` ADD `serialized_effects` TEXT AFTER `type` ;

ALTER TABLE `coupon_version` DROP `amount`;
ALTER TABLE `coupon_version` ADD `serialized_effects` TEXT AFTER `type` ;

DROP TABLE IF EXISTS `coupon_order`;

# cart_item table
ALTER TABLE `cart_item` DROP `discount`;

DROP TABLE IF EXISTS `order_coupon`;

CREATE TABLE `order_coupon`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `code` VARCHAR(45) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `amount` FLOAT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `short_description` TEXT NOT NULL,
    `description` LONGTEXT NOT NULL,
    `expiration_date` DATETIME NOT NULL,
    `is_cumulative` TINYINT(1) NOT NULL,
    `is_removing_postage` TINYINT(1) NOT NULL,
    `is_available_on_special_offers` TINYINT(1) NOT NULL,
    `serialized_conditions` TEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_coupon_order_id` (`order_id`),
    CONSTRAINT `fk_order_coupon_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE `config` SET `value`='2.0.0-beta3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='beta3' WHERE `name`='thelia_extra_version';

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
