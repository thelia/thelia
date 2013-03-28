
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category`;

CREATE TABLE `category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `parent` INTEGER,
    `link` VARCHAR(255),
    `visible` TINYINT NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product`;

CREATE TABLE `product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tax_rule_id` INTEGER,
    `ref` VARCHAR(255) NOT NULL,
    `price` FLOAT NOT NULL,
    `price2` FLOAT,
    `ecotax` FLOAT,
    `newness` TINYINT DEFAULT 0,
    `promo` TINYINT DEFAULT 0,
    `stock` INTEGER DEFAULT 0,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `weight` FLOAT,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_product_tax_rule_id` (`tax_rule_id`),
    CONSTRAINT `fk_product_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_category`;

CREATE TABLE `product_category`
(
    `product_id` INTEGER NOT NULL,
    `category_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`product_id`,`category_id`),
    INDEX `fk_product_has_category_category1_idx` (`category_id`),
    INDEX `fk_product_has_category_product1_idx` (`product_id`),
    CONSTRAINT `fk_product_has_category_product1`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_has_category_category1`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `country`;

CREATE TABLE `country`
(
    `id` INTEGER NOT NULL,
    `area_id` INTEGER,
    `isocode` VARCHAR(4) NOT NULL,
    `isoalpha2` VARCHAR(2),
    `isoalpha3` VARCHAR(4),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_country_area_id` (`area_id`),
    CONSTRAINT `fk_country_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- tax
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax`;

CREATE TABLE `tax`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `rate` FLOAT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- tax_rule
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule`;

CREATE TABLE `tax_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45),
    `title` VARCHAR(255),
    `description` TEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- tax_rule_country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule_country`;

CREATE TABLE `tax_rule_country`
(
    `id` INTEGER NOT NULL,
    `tax_rule_id` INTEGER,
    `country_id` INTEGER,
    `tax_id` INTEGER,
    `none` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_tax_rule_country_tax_id` (`tax_id`),
    INDEX `idx_tax_rule_country_tax_rule_id` (`tax_rule_id`),
    INDEX `idx_tax_rule_country_country_id` (`country_id`),
    CONSTRAINT `fk_tax_rule_country_tax_id`
        FOREIGN KEY (`tax_id`)
        REFERENCES `tax` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_tax_rule_country_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_tax_rule_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature`;

CREATE TABLE `feature`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` INTEGER DEFAULT 0,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature_av
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_av`;

CREATE TABLE `feature_av`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feature_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_av_feature_id` (`feature_id`),
    CONSTRAINT `fk_feature_av_feature_id`
        FOREIGN KEY (`feature_id`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature_prod
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_prod`;

CREATE TABLE `feature_prod`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `feature_id` INTEGER NOT NULL,
    `feature_av_id` INTEGER,
    `by_default` VARCHAR(255),
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_prod_product_id` (`product_id`),
    INDEX `idx_feature_prod_feature_id` (`feature_id`),
    INDEX `idx_feature_prod_feature_av_id` (`feature_av_id`),
    CONSTRAINT `fk_feature_prod_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_feature_prod_feature_id`
        FOREIGN KEY (`feature_id`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_feature_prod_feature_av_id`
        FOREIGN KEY (`feature_av_id`)
        REFERENCES `feature_av` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_category`;

CREATE TABLE `feature_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feature_id` INTEGER NOT NULL,
    `category_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_category_category_id` (`category_id`),
    INDEX `idx_feature_category_feature_id` (`feature_id`),
    CONSTRAINT `fk_feature_category_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_feature_category_feature_id`
        FOREIGN KEY (`feature_id`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute`;

CREATE TABLE `attribute`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute_av
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_av`;

CREATE TABLE `attribute_av`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `attribute_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_attribute_av_attribute_id` (`attribute_id`),
    CONSTRAINT `fk_attribute_av_attribute_id`
        FOREIGN KEY (`attribute_id`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- combination
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `combination`;

CREATE TABLE `combination`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute_combination
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_combination`;

CREATE TABLE `attribute_combination`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `attribute_id` INTEGER NOT NULL,
    `combination_id` INTEGER NOT NULL,
    `attribute_av_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`,`attribute_id`,`combination_id`,`attribute_av_id`),
    INDEX `idx_ attribute_combination_attribute_id` (`attribute_id`),
    INDEX `idx_ attribute_combination_attribute_av_id` (`attribute_av_id`),
    INDEX `idx_ attribute_combination_combination_id` (`combination_id`),
    CONSTRAINT `fk_ attribute_combination_attribute_id`
        FOREIGN KEY (`attribute_id`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_ attribute_combination_attribute_av_id`
        FOREIGN KEY (`attribute_av_id`)
        REFERENCES `attribute_av` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_ attribute_combination_combination_id`
        FOREIGN KEY (`combination_id`)
        REFERENCES `combination` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- stock
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `stock`;

CREATE TABLE `stock`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `combination_id` INTEGER,
    `product_id` INTEGER NOT NULL,
    `increase` FLOAT,
    `value` FLOAT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_stock_combination_id` (`combination_id`),
    INDEX `idx_stock_product_id` (`product_id`),
    CONSTRAINT `fk_stock_combination_id`
        FOREIGN KEY (`combination_id`)
        REFERENCES `combination` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_stock_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_category`;

CREATE TABLE `attribute_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER NOT NULL,
    `attribute_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_attribute_category_category_id` (`category_id`),
    INDEX `idx_attribute_category_attribute_id` (`attribute_id`),
    CONSTRAINT `fk_attribute_category_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_attribute_category_attribute_id`
        FOREIGN KEY (`attribute_id`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- config
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `config`;

CREATE TABLE `config`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    `secured` TINYINT DEFAULT 1 NOT NULL,
    `hidden` TINYINT DEFAULT 1 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- customer
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer`;

CREATE TABLE `customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(50) NOT NULL,
    `customer_title_id` INTEGER,
    `company` VARCHAR(255),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `address1` VARCHAR(255) NOT NULL,
    `address2` VARCHAR(255) COMMENT '    ',
    `address3` VARCHAR(255),
    `zipcode` VARCHAR(10),
    `city` VARCHAR(255) NOT NULL,
    `country_id` INTEGER NOT NULL,
    `phone` VARCHAR(20),
    `cellphone` VARCHAR(20),
    `email` VARCHAR(50),
    `password` VARCHAR(255),
    `algo` VARCHAR(128),
    `salt` VARCHAR(128),
    `reseller` TINYINT,
    `lang` VARCHAR(10),
    `sponsor` VARCHAR(50),
    `discount` FLOAT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_ customer_customer_title_id` (`customer_title_id`),
    CONSTRAINT `fk_ customer_customer_title_id`
        FOREIGN KEY (`customer_title_id`)
        REFERENCES `customer_title` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- address
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `address`;

CREATE TABLE `address`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255),
    `customer_id` INTEGER NOT NULL,
    `customer_title_id` INTEGER,
    `company` VARCHAR(255),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `address1` VARCHAR(255) NOT NULL,
    `address2` VARCHAR(255) NOT NULL,
    `address3` VARCHAR(255) NOT NULL,
    `zipcode` VARCHAR(10) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `country_id` INTEGER NOT NULL,
    `phone` VARCHAR(20),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_address_customer_id` (`customer_id`),
    INDEX `idx_address_customer_title_id` (`customer_title_id`),
    CONSTRAINT `fk_address_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_address_customer_title_id`
        FOREIGN KEY (`customer_title_id`)
        REFERENCES `customer_title` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- customer_title
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer_title`;

CREATE TABLE `customer_title`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `by_default` INTEGER DEFAULT 0 NOT NULL,
    `position` VARCHAR(45) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- lang
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `lang`;

CREATE TABLE `lang`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100),
    `code` VARCHAR(10),
    `locale` VARCHAR(45),
    `url` VARCHAR(255),
    `by_default` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- folder
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder`;

CREATE TABLE `folder`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `parent` INTEGER NOT NULL,
    `link` VARCHAR(255),
    `visible` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- content
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content`;

CREATE TABLE `content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- content_assoc
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_assoc`;

CREATE TABLE `content_assoc`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER,
    `product_id` INTEGER,
    `content_id` INTEGER,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_content_assoc_category_id` (`category_id`),
    INDEX `idx_content_assoc_product_id` (`product_id`),
    INDEX `idx_content_assoc_content_id` (`content_id`),
    CONSTRAINT `fk_content_assoc_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_content_assoc_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_content_assoc_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `image`;

CREATE TABLE `image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER,
    `category_id` INTEGER,
    `folder_id` INTEGER,
    `content_id` INTEGER,
    `file` VARCHAR(255) NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_image_product_id` (`product_id`),
    INDEX `idx_image_category_id` (`category_id`),
    INDEX `idx_image_content_id` (`content_id`),
    INDEX `idx_image_folder_id` (`folder_id`),
    CONSTRAINT `fk_image_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_image_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_image_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_image_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `document`;

CREATE TABLE `document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER,
    `category_id` INTEGER,
    `folder_id` INTEGER,
    `content_id` INTEGER,
    `file` VARCHAR(255) NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_document_product_id` (`product_id`),
    INDEX `idx_document_category_id` (`category_id`),
    INDEX `idx_document_content_id` (`content_id`),
    INDEX `idx_document_folder_id` (`folder_id`),
    CONSTRAINT `fk_document_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_document_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_document_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_document_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order`;

CREATE TABLE `order`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(45),
    `customer_id` INTEGER NOT NULL,
    `address_invoice` INTEGER,
    `address_delivery` INTEGER,
    `invoice_date` DATE,
    `currency_id` INTEGER,
    `currency_rate` FLOAT NOT NULL,
    `transaction` VARCHAR(100),
    `delivery_num` VARCHAR(100),
    `invoice` VARCHAR(100),
    `postage` FLOAT,
    `payment` VARCHAR(45) NOT NULL,
    `carrier` VARCHAR(45) NOT NULL,
    `status_id` INTEGER,
    `lang` VARCHAR(10) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_currency_id` (`currency_id`),
    INDEX `idx_order_customer_id` (`customer_id`),
    INDEX `idx_order_address_invoice` (`address_invoice`),
    INDEX `idx_order_address_delivery` (`address_delivery`),
    INDEX `idx_order_status_id` (`status_id`),
    CONSTRAINT `fk_order_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_order_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_address_invoice`
        FOREIGN KEY (`address_invoice`)
        REFERENCES `order_address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_order_address_delivery`
        FOREIGN KEY (`address_delivery`)
        REFERENCES `order_address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_order_status_id`
        FOREIGN KEY (`status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- currency
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `currency`;

CREATE TABLE `currency`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(45),
    `code` VARCHAR(45),
    `symbol` VARCHAR(45),
    `rate` FLOAT,
    `by_default` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_address
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_address`;

CREATE TABLE `order_address`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_title_id` INTEGER,
    `company` VARCHAR(255),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `address1` VARCHAR(255) NOT NULL,
    `address2` VARCHAR(255),
    `address3` VARCHAR(255),
    `zipcode` VARCHAR(10) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `country_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_product`;

CREATE TABLE `order_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `product_ref` VARCHAR(255),
    `title` VARCHAR(255),
    `description` TEXT,
    `chapo` TEXT,
    `quantity` FLOAT NOT NULL,
    `price` FLOAT NOT NULL,
    `tax` FLOAT,
    `parent` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_product_order_id` (`order_id`),
    CONSTRAINT `fk_order_product_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_status
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_status`;

CREATE TABLE `order_status`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_feature
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_feature`;

CREATE TABLE `order_feature`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_product_id` INTEGER NOT NULL,
    `feature_desc` VARCHAR(255),
    `feature_av_desc` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_feature_order_product_id` (`order_product_id`),
    CONSTRAINT `fk_order_feature_order_product_id`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module`;

CREATE TABLE `module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(55) NOT NULL,
    `type` TINYINT NOT NULL,
    `activate` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- accessory
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `accessory`;

CREATE TABLE `accessory`
(
    `id` INTEGER NOT NULL,
    `product_id` INTEGER NOT NULL,
    `accessory` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_address_product_id` (`product_id`),
    INDEX `idx_address_accessory` (`accessory`),
    CONSTRAINT `fk_accessory_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_accessory_accessory`
        FOREIGN KEY (`accessory`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- area
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `area`;

CREATE TABLE `area`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `unit` FLOAT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- delivzone
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `delivzone`;

CREATE TABLE `delivzone`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `area_id` INTEGER,
    `delivery` VARCHAR(45) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_delivzone_area_id` (`area_id`),
    CONSTRAINT `fk_delivzone_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- group
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `group`;

CREATE TABLE `group`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- resource
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `resource`;

CREATE TABLE `resource`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- admin
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `login` VARCHAR(100) NOT NULL,
    `password` VARCHAR(128) NOT NULL,
    `algo` VARCHAR(128),
    `salt` VARCHAR(128),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- admin_group
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `admin_group`;

CREATE TABLE `admin_group`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `group_id` INTEGER,
    `admin_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_admin_group_group_id` (`group_id`),
    INDEX `idx_admin_group_admin_id` (`admin_id`),
    CONSTRAINT `fk_admin_group_group_id`
        FOREIGN KEY (`group_id`)
        REFERENCES `group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_admin_group_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- group_resource
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `group_resource`;

CREATE TABLE `group_resource`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `group_id` INTEGER NOT NULL,
    `resource_id` INTEGER NOT NULL,
    `read` TINYINT DEFAULT 0,
    `write` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `id_idx` (`group_id`),
    INDEX `idx_group_resource_resource_id` (`resource_id`),
    CONSTRAINT `fk_group_resource_group_id`
        FOREIGN KEY (`group_id`)
        REFERENCES `group` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_group_resource_resource_id`
        FOREIGN KEY (`resource_id`)
        REFERENCES `resource` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- group_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `group_module`;

CREATE TABLE `group_module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `group_id` INTEGER NOT NULL,
    `module_id` INTEGER,
    `access` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `id_idx` (`group_id`),
    INDEX `id_idx1` (`module_id`),
    CONSTRAINT `fk_group_module_group_id`
        FOREIGN KEY (`group_id`)
        REFERENCES `group` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_group_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- message
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message`;

CREATE TABLE `message`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45) NOT NULL,
    `secured` TINYINT,
    `ref` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- rewriting
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `rewriting`;

CREATE TABLE `rewriting`
(
    `id` INTEGER NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `product_id` INTEGER,
    `category_id` INTEGER,
    `folder_id` INTEGER,
    `content_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_rewriting_product_id` (`product_id`),
    INDEX `idx_rewriting_category_id` (`category_id`),
    INDEX `idx_rewriting_folder_id` (`folder_id`),
    INDEX `idx_rewriting_content_id` (`content_id`),
    CONSTRAINT `fk_rewriting_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_rewriting_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_rewriting_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_rewriting_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- coupon
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon`;

CREATE TABLE `coupon`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45) NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `value` FLOAT NOT NULL,
    `used` TINYINT,
    `available_since` DATETIME,
    `date_limit` DATETIME,
    `activate` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- coupon_rule
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_rule`;

CREATE TABLE `coupon_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `coupon_id` INTEGER NOT NULL,
    `controller` VARCHAR(255),
    `operation` VARCHAR(255),
    `value` FLOAT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_coupon_rule_coupon_id` (`coupon_id`),
    CONSTRAINT `fk_coupon_rule_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- coupon_order
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_order`;

CREATE TABLE `coupon_order`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `code` VARCHAR(45) NOT NULL,
    `value` FLOAT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_coupon_order_order_id` (`order_id`),
    CONSTRAINT `fk_coupon_order_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- admin_log
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `admin_log`;

CREATE TABLE `admin_log`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `admin_login` VARCHAR(255),
    `admin_firstname` VARCHAR(255),
    `admin_lastname` VARCHAR(255),
    `action` VARCHAR(255),
    `request` TEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- content_folder
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_folder`;

CREATE TABLE `content_folder`
(
    `content_id` INTEGER NOT NULL,
    `folder_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`content_id`,`folder_id`),
    INDEX `fk__idx` (`content_id`),
    INDEX `fk_content_folder_folder_id_idx` (`folder_id`),
    CONSTRAINT `fk_content_folder_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_content_folder_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- category_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_i18n`;

CREATE TABLE `category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_i18n`;

CREATE TABLE `product_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- country_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `country_i18n`;

CREATE TABLE `country_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `country_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `country` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- tax_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_i18n`;

CREATE TABLE `tax_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `tax_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `tax` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- tax_rule_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule_i18n`;

CREATE TABLE `tax_rule_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `tax_rule_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `tax_rule` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_i18n`;

CREATE TABLE `feature_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `feature_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `feature` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- feature_av_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_av_i18n`;

CREATE TABLE `feature_av_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `feature_av_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `feature_av` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_i18n`;

CREATE TABLE `attribute_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `attribute_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `attribute` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- attribute_av_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_av_i18n`;

CREATE TABLE `attribute_av_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `attribute_av_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `attribute_av` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- config_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `config_i18n`;

CREATE TABLE `config_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `config_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `config` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- customer_title_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer_title_i18n`;

CREATE TABLE `customer_title_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `short` VARCHAR(10),
    `long` VARCHAR(45),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `customer_title_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `customer_title` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- folder_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_i18n`;

CREATE TABLE `folder_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `folder_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `folder` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- content_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_i18n`;

CREATE TABLE `content_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `content_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `content` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `image_i18n`;

CREATE TABLE `image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `document_i18n`;

CREATE TABLE `document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- order_status_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_status_i18n`;

CREATE TABLE `order_status_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `order_status_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `order_status` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- module_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_i18n`;

CREATE TABLE `module_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `module_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `module` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- group_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `group_i18n`;

CREATE TABLE `group_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `group_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `group` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- resource_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `resource_i18n`;

CREATE TABLE `resource_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `resource_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `resource` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- message_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message_i18n`;

CREATE TABLE `message_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` TEXT,
    `description` LONGTEXT,
    `description_html` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `message_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `message` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- category_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_version`;

CREATE TABLE `category_version`
(
    `id` INTEGER NOT NULL,
    `parent` INTEGER,
    `link` VARCHAR(255),
    `visible` TINYINT NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `category_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- product_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_version`;

CREATE TABLE `product_version`
(
    `id` INTEGER NOT NULL,
    `tax_rule_id` INTEGER,
    `ref` VARCHAR(255) NOT NULL,
    `price` FLOAT NOT NULL,
    `price2` FLOAT,
    `ecotax` FLOAT,
    `newness` TINYINT DEFAULT 0,
    `promo` TINYINT DEFAULT 0,
    `stock` INTEGER DEFAULT 0,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `weight` FLOAT,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `product_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- folder_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_version`;

CREATE TABLE `folder_version`
(
    `id` INTEGER NOT NULL,
    `parent` INTEGER NOT NULL,
    `link` VARCHAR(255),
    `visible` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `folder_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `folder` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- content_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_version`;

CREATE TABLE `content_version`
(
    `id` INTEGER NOT NULL,
    `visible` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `content_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `content` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- message_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message_version`;

CREATE TABLE `message_version`
(
    `id` INTEGER NOT NULL,
    `code` VARCHAR(45) NOT NULL,
    `secured` TINYINT,
    `ref` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `message_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `message` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
