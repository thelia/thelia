SET FOREIGN_KEY_CHECKS = 0;

# ======================================================================================================================
# Add relation between order and cart
# ======================================================================================================================
ALTER TABLE `order`
  ADD COLUMN `cart_id` INTEGER NOT NULL
  AFTER `lang_id`
;

ALTER TABLE `order`
  ADD CONSTRAINT `fk_order_cart_id`
    FOREIGN KEY (`cart_id`) REFERENCES `cart`(`id`)
;

ALTER TABLE `order`
  ADD INDEX idx_order_cart_fk
    (`cart_id`)
;

-- Add version to customer
ALTER TABLE `customer`
  ADD COLUMN `version` INTEGER DEFAULT 0
;

ALTER TABLE `customer`
  ADD COLUMN `version_created_at` DATETIME
;

ALTER TABLE `customer`
  ADD COLUMN `version_created_by` VARCHAR(100)
;

-- ---------------------------------------------------------------------
-- customer_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer_version`;

CREATE TABLE `customer_version`
(
    `id` INTEGER NOT NULL,
    `ref` VARCHAR(50),
    `title_id` INTEGER NOT NULL,
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `password` VARCHAR(255),
    `algo` VARCHAR(128),
    `reseller` TINYINT,
    `lang` VARCHAR(10),
    `sponsor` VARCHAR(50),
    `discount` FLOAT,
    `remember_me_token` VARCHAR(255),
    `remember_me_serial` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    `order_ids` TEXT,
    `order_versions` TEXT,
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `customer_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `customer` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

SET FOREIGN_KEY_CHECKS = 1;