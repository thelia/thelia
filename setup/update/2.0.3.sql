# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_release_version';
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
UPDATE `order` SET
  `version` = 1,
  `version_created_at` = NOW(),
  `version_created_by` = 'Thelia'
WHERE `version` = 0;

INSERT INTO `order_version`(
  `id`,
  `ref`,
  `customer_id`,
  `invoice_order_address_id`,
  `delivery_order_address_id`,
  `invoice_date`,
  `currency_id`,
  `currency_rate`,
  `transaction_ref`,
  `delivery_ref`,
  `invoice_ref`,
  `discount`,
  `postage`,
  `payment_module_id`,
  `delivery_module_id`,
  `status_id`,
  `lang_id`,
  `created_at`,
  `updated_at`,
  `version`,
  `version_created_at`,
  `version_created_by`)
  SELECT
    `id`,
    `ref`,
    `customer_id`,
    `invoice_order_address_id`,
    `delivery_order_address_id`,
    `invoice_date`,
    `currency_id`,
    `currency_rate`,
    `transaction_ref`,
    `delivery_ref`,
    `invoice_ref`,
    `discount`,
    `postage`,
    `payment_module_id`,
    `delivery_module_id`,
    `status_id`,
    `lang_id`,
    `created_at`,
    `updated_at`,
    `version`,
    `version_created_at`,
    `version_created_by`
  FROM `order`;


# Add missing columns to coupon (version_created_at, version_created_by)
ALTER TABLE `coupon` ADD `version_created_at` DATE AFTER `version`;
ALTER TABLE `coupon` ADD `version_created_by` VARCHAR(100) AFTER `version_created_at`;

# Add Brand tables and related resources

# Add the "brand" resource
INSERT INTO resource (`code`, `created_at`, `updated_at`) VALUES ('admin.brand', NOW(), NOW());

-- ---------------------------------------------------------------------
-- brand
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand`;

CREATE TABLE `brand`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `visible` TINYINT,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  `meta_title` VARCHAR(255),
  `meta_description` TEXT,
  `meta_keywords` TEXT,
  `logo_image_id` INTEGER,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `fk_brand_brand_image1_idx` (`logo_image_id`),
  CONSTRAINT `fk_logo_image_id_brand_image`
  FOREIGN KEY (`logo_image_id`)
  REFERENCES `brand_image` (`id`)
    ON UPDATE RESTRICT
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_document`;

CREATE TABLE `brand_document`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `brand_id` INTEGER NOT NULL,
  `file` VARCHAR(255) NOT NULL,
  `position` INTEGER,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `idx_brand_document_brand_id` (`brand_id`),
  CONSTRAINT `fk_brand_document_brand_id`
  FOREIGN KEY (`brand_id`)
  REFERENCES `brand` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_image`;

CREATE TABLE `brand_image`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `brand_id` INTEGER NOT NULL,
  `file` VARCHAR(255) NOT NULL,
  `position` INTEGER,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `idx_brand_image_brand_id` (`brand_id`),
  INDEX `idx_brand_image_brand_id_is_brand_logo` (`brand_id`),
  CONSTRAINT `fk_brand_image_brand_id`
  FOREIGN KEY (`brand_id`)
  REFERENCES `brand` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
) ENGINE=InnoDB;


-- ---------------------------------------------------------
-- Add brand field to product table, and related constraint.
-- ---------------------------------------------------------

ALTER TABLE `product` ADD `brand_id` INTEGER DEFAULT 0 AFTER `template_id`;
ALTER TABLE `product` ADD CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;