
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
    `parent` INTEGER DEFAULT 0 NOT NULL,
    `visible` TINYINT NOT NULL,
    `position` INTEGER NOT NULL,
    `default_template_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    INDEX `idx_parent` (`parent`),
    INDEX `idx_parent_position` (`parent`, `position`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product`;

CREATE TABLE `product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tax_rule_id` INTEGER,
    `ref` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `template_id` INTEGER,
    `brand_id` INTEGER,
    `virtual` TINYINT DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_product_tax_rule_id` (`tax_rule_id`),
    INDEX `fk_product_template_id` (`template_id`),
    INDEX `fk_product_brand1_idx` (`brand_id`),
    CONSTRAINT `fk_product_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_product_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `template` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_product_brand`
        FOREIGN KEY (`brand_id`)
        REFERENCES `brand` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_category`;

CREATE TABLE `product_category`
(
    `product_id` INTEGER NOT NULL,
    `category_id` INTEGER NOT NULL,
    `default_category` TINYINT(1),
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`product_id`,`category_id`),
    INDEX `idx_product_has_category_category1` (`category_id`),
    INDEX `idx_product_has_category_product1` (`product_id`),
    INDEX `idx_product_has_category_default` (`default_category`),
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `country`;

CREATE TABLE `country`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `isocode` VARCHAR(4) NOT NULL,
    `isoalpha2` VARCHAR(2),
    `isoalpha3` VARCHAR(4),
    `has_states` TINYINT DEFAULT 0,
    `need_zip_code` TINYINT DEFAULT 0,
    `zip_code_format` VARCHAR(20),
    `by_default` TINYINT DEFAULT 0,
    `shop_country` TINYINT(1) DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_country_by_default` (`by_default`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- tax
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax`;

CREATE TABLE `tax`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(255) NOT NULL,
    `serialized_requirements` TEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- tax_rule
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule`;

CREATE TABLE `tax_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `is_default` TINYINT(1) DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- tax_rule_country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule_country`;

CREATE TABLE `tax_rule_country`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tax_rule_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    `state_id` INTEGER,
    `tax_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_tax_rule_country_tax_id` (`tax_id`),
    INDEX `idx_tax_rule_country_tax_rule_id` (`tax_rule_id`),
    INDEX `idx_tax_rule_country_country_id` (`country_id`),
    INDEX `idx_tax_rule_country_tax_rule_id_country_id_position` (`tax_rule_id`, `country_id`, `position`),
    INDEX `idx_tax_rule_country_state_id` (`state_id`),
    CONSTRAINT `fk_tax_rule_country_tax_id`
        FOREIGN KEY (`tax_id`)
        REFERENCES `tax` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_tax_rule_country_tax_rule_id`
        FOREIGN KEY (`tax_rule_id`)
        REFERENCES `tax_rule` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_tax_rule_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_tax_rule_country_state_id`
        FOREIGN KEY (`state_id`)
        REFERENCES `state` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- feature
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature`;

CREATE TABLE `feature`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` INTEGER DEFAULT 0,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- feature_av
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_av`;

CREATE TABLE `feature_av`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feature_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_av_feature_id` (`feature_id`),
    CONSTRAINT `fk_feature_av_feature_id`
        FOREIGN KEY (`feature_id`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- feature_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_product`;

CREATE TABLE `feature_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `feature_id` INTEGER NOT NULL,
    `feature_av_id` INTEGER,
    `free_text_value` TEXT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_prod_product_id` (`product_id`),
    INDEX `idx_feature_prod_feature_id` (`feature_id`),
    INDEX `idx_feature_prod_feature_av_id` (`feature_av_id`),
    INDEX `idx_feature_product_product_id_feature_id_position` (`product_id`, `feature_id`, `position`),
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- feature_template
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `feature_template`;

CREATE TABLE `feature_template`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feature_id` INTEGER NOT NULL,
    `template_id` INTEGER NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_feature_template_id` (`feature_id`),
    INDEX `fk_feature_template_idx` (`template_id`),
    INDEX `idx_feature_template_template_id_position` (`template_id`, `position`),
    CONSTRAINT `fk_feature_template_id`
        FOREIGN KEY (`feature_id`)
        REFERENCES `feature` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_feature_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `template` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- attribute_combination
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_combination`;

CREATE TABLE `attribute_combination`
(
    `attribute_id` INTEGER NOT NULL,
    `attribute_av_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`attribute_id`,`attribute_av_id`,`product_sale_elements_id`),
    INDEX `idx_attribute_combination_attribute_id` (`attribute_id`),
    INDEX `idx_attribute_combination_attribute_av_id` (`attribute_av_id`),
    INDEX `idx_attribute_combination_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_attribute_combination_attribute_id`
        FOREIGN KEY (`attribute_id`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_attribute_combination_attribute_av_id`
        FOREIGN KEY (`attribute_av_id`)
        REFERENCES `attribute_av` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_attribute_combination_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_sale_elements
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_sale_elements`;

CREATE TABLE `product_sale_elements`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `ref` VARCHAR(255) NOT NULL,
    `quantity` FLOAT NOT NULL,
    `promo` TINYINT DEFAULT 0,
    `newness` TINYINT DEFAULT 0,
    `weight` FLOAT DEFAULT 0,
    `is_default` TINYINT(1) DEFAULT 0,
    `ean_code` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_product_sale_element_product_id` (`product_id`),
    INDEX `ref` (`ref`),
    INDEX `idx_product_elements_product_id_promo_is_default` (`product_id`, `promo`, `is_default`),
    CONSTRAINT `fk_product_sale_element_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- attribute_template
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `attribute_template`;

CREATE TABLE `attribute_template`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `attribute_id` INTEGER NOT NULL,
    `template_id` INTEGER NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_attribute_template_id` (`attribute_id`),
    INDEX `fk_attribute_template_idx` (`template_id`),
    CONSTRAINT `fk_attribute_template_id`
        FOREIGN KEY (`attribute_id`)
        REFERENCES `attribute` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_attribute_template`
        FOREIGN KEY (`template_id`)
        REFERENCES `template` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- config
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `config`;

CREATE TABLE `config`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `value` TEXT NOT NULL,
    `secured` TINYINT DEFAULT 1 NOT NULL,
    `hidden` TINYINT DEFAULT 1 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name_UNIQUE` (`name`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- customer
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer`;

CREATE TABLE `customer`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title_id` INTEGER NOT NULL,
    `lang_id` INTEGER,
    `ref` VARCHAR(50),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `password` VARCHAR(255),
    `algo` VARCHAR(128),
    `reseller` TINYINT,
    `sponsor` VARCHAR(50),
    `discount` DECIMAL(16,6) DEFAULT 0.000000,
    `remember_me_token` VARCHAR(255),
    `remember_me_serial` VARCHAR(255),
    `enable` TINYINT DEFAULT 0,
    `confirmation_token` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_customer_customer_title_id` (`title_id`),
    INDEX `idx_customer_lang_id` (`lang_id`),
    INDEX `idx_email` (`email`),
    CONSTRAINT `fk_customer_customer_title_id`
        FOREIGN KEY (`title_id`)
        REFERENCES `customer_title` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_customer_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- address
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `address`;

CREATE TABLE `address`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255),
    `customer_id` INTEGER NOT NULL,
    `title_id` INTEGER NOT NULL,
    `company` VARCHAR(255),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `address1` VARCHAR(255) NOT NULL,
    `address2` VARCHAR(255) NOT NULL,
    `address3` VARCHAR(255) NOT NULL,
    `zipcode` VARCHAR(10) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `country_id` INTEGER NOT NULL,
    `state_id` INTEGER,
    `phone` VARCHAR(20),
    `cellphone` VARCHAR(20),
    `is_default` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_address_customer_id` (`customer_id`),
    INDEX `idx_address_customer_title_id` (`title_id`),
    INDEX `idx_address_country_id` (`country_id`),
    INDEX `fk_address_state_id_idx` (`state_id`),
    CONSTRAINT `fk_address_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_address_customer_title_id`
        FOREIGN KEY (`title_id`)
        REFERENCES `customer_title` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_address_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_address_state_id`
        FOREIGN KEY (`state_id`)
        REFERENCES `state` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `date_format` VARCHAR(45),
    `time_format` VARCHAR(45),
    `datetime_format` VARCHAR(45),
    `decimal_separator` VARCHAR(45),
    `thousands_separator` VARCHAR(45),
    `active` TINYINT(1) DEFAULT 0,
    `visible` TINYINT DEFAULT 0,
    `decimals` VARCHAR(45),
    `by_default` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_lang_by_default` (`by_default`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- folder
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder`;

CREATE TABLE `folder`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `parent` INTEGER DEFAULT 0 NOT NULL,
    `visible` TINYINT,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_image`;

CREATE TABLE `product_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_product_image_product_id` (`product_id`),
    INDEX `idx_product_image_product_id_position` (`product_id`, `position`),
    CONSTRAINT `fk_product_image_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_document`;

CREATE TABLE `product_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_product_document_product_id` (`product_id`),
    CONSTRAINT `fk_product_document_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order`;

CREATE TABLE `order`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(45),
    `customer_id` INTEGER NOT NULL,
    `invoice_order_address_id` INTEGER NOT NULL,
    `delivery_order_address_id` INTEGER NOT NULL,
    `invoice_date` DATETIME,
    `currency_id` INTEGER NOT NULL,
    `currency_rate` FLOAT NOT NULL,
    `transaction_ref` VARCHAR(100) COMMENT 'transaction reference - usually use to identify a transaction with banking modules',
    `delivery_ref` VARCHAR(100) COMMENT 'delivery reference - usually use to identify a delivery progress on a distant delivery tracker website',
    `invoice_ref` VARCHAR(100) COMMENT 'the invoice reference',
    `discount` DECIMAL(16,6) DEFAULT 0.000000,
    `postage` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `postage_tax` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `postage_tax_rule_title` VARCHAR(255),
    `payment_module_id` INTEGER NOT NULL,
    `delivery_module_id` INTEGER NOT NULL,
    `status_id` INTEGER NOT NULL,
    `lang_id` INTEGER NOT NULL,
    `cart_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_order_currency_id` (`currency_id`),
    INDEX `idx_order_customer_id` (`customer_id`),
    INDEX `idx_order_invoice_order_address_id` (`invoice_order_address_id`),
    INDEX `idx_order_delivery_order_address_id` (`delivery_order_address_id`),
    INDEX `idx_order_status_id` (`status_id`),
    INDEX `fk_order_payment_module_id_idx` (`payment_module_id`),
    INDEX `fk_order_delivery_module_id_idx` (`delivery_module_id`),
    INDEX `fk_order_lang_id_idx` (`lang_id`),
    INDEX `idx_order_cart_fk` (`cart_id`),
    CONSTRAINT `fk_order_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_invoice_order_address_id`
        FOREIGN KEY (`invoice_order_address_id`)
        REFERENCES `order_address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_delivery_order_address_id`
        FOREIGN KEY (`delivery_order_address_id`)
        REFERENCES `order_address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_status_id`
        FOREIGN KEY (`status_id`)
        REFERENCES `order_status` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_payment_module_id`
        FOREIGN KEY (`payment_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- currency
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `currency`;

CREATE TABLE `currency`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45),
    `symbol` VARCHAR(45),
    `format` CHAR(10),
    `rate` FLOAT,
    `visible` TINYINT DEFAULT 0,
    `position` INTEGER,
    `by_default` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_currency_by_default` (`by_default`),
    INDEX `idx_currency_code` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `cellphone` VARCHAR(20),
    `country_id` INTEGER NOT NULL,
    `state_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_order_address_customer_title_id_idx` (`customer_title_id`),
    INDEX `fk_order_address_country_id_idx` (`country_id`),
    INDEX `fk_order_address_state_id_idx` (`state_id`),
    CONSTRAINT `fk_order_address_customer_title_id`
        FOREIGN KEY (`customer_title_id`)
        REFERENCES `customer_title` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_address_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_order_address_state_id`
        FOREIGN KEY (`state_id`)
        REFERENCES `state` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_product`;

CREATE TABLE `order_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `product_ref` VARCHAR(255) NOT NULL,
    `product_sale_elements_ref` VARCHAR(255) NOT NULL,
    `product_sale_elements_id` INTEGER,
    `title` VARCHAR(255),
    `chapo` TEXT,
    `description` LONGTEXT,
    `postscriptum` TEXT,
    `quantity` FLOAT NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000,
    `was_new` TINYINT NOT NULL,
    `was_in_promo` TINYINT NOT NULL,
    `weight` VARCHAR(45),
    `ean_code` VARCHAR(255),
    `tax_rule_title` VARCHAR(255),
    `tax_rule_description` LONGTEXT,
    `parent` INTEGER COMMENT 'not managed yet',
    `virtual` TINYINT DEFAULT 0 NOT NULL,
    `virtual_document` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_product_order_id` (`order_id`),
    CONSTRAINT `fk_order_product_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_status
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_status`;

CREATE TABLE `order_status`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45) NOT NULL,
    `color` CHAR(7),
    `position` INTEGER,
    `protected_status` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_product_attribute_combination
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_product_attribute_combination`;

CREATE TABLE `order_product_attribute_combination`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_product_id` INTEGER NOT NULL,
    `attribute_title` VARCHAR(255) NOT NULL,
    `attribute_chapo` TEXT,
    `attribute_description` LONGTEXT,
    `attribute_postscriptum` TEXT,
    `attribute_av_title` VARCHAR(255) NOT NULL,
    `attribute_av_chapo` TEXT,
    `attribute_av_description` LONGTEXT,
    `attribute_av_postscriptum` TEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_product_attribute_combination_order_product_id` (`order_product_id`),
    CONSTRAINT `fk_order_product_attribute_combination_order_product_id`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module`;

CREATE TABLE `module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(55) NOT NULL,
    `version` VARCHAR(25) DEFAULT '' NOT NULL,
    `type` TINYINT NOT NULL,
    `category` VARCHAR(50) DEFAULT 'classic' NOT NULL,
    `activate` TINYINT,
    `position` INTEGER,
    `full_namespace` VARCHAR(255),
    `mandatory` TINYINT DEFAULT 0,
    `hidden` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`),
    INDEX `idx_module_activate` (`activate`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- accessory
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `accessory`;

CREATE TABLE `accessory`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- area
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `area`;

CREATE TABLE `area`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `postage` FLOAT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- area_delivery_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `area_delivery_module`;

CREATE TABLE `area_delivery_module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `area_id` INTEGER NOT NULL,
    `delivery_module_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `area_id_delivery_module_id_UNIQUE` (`area_id`, `delivery_module_id`),
    INDEX `idx_area_delivery_module_area_id` (`area_id`),
    INDEX `idx_area_delivery_module_delivery_module_id_idx` (`delivery_module_id`),
    CONSTRAINT `fk_area_delivery_module_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `idx_area_delivery_module_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- profile
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `profile`;

CREATE TABLE `profile`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- resource
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `resource`;

CREATE TABLE `resource`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- admin
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `admin`;

CREATE TABLE `admin`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `profile_id` INTEGER,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `login` VARCHAR(100) NOT NULL,
    `password` VARCHAR(128) NOT NULL,
    `locale` VARCHAR(45) NOT NULL,
    `algo` VARCHAR(128),
    `salt` VARCHAR(128),
    `remember_me_token` VARCHAR(255),
    `remember_me_serial` VARCHAR(255),
    `email` VARCHAR(255) NOT NULL,
    `password_renew_token` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `login_UNIQUE` (`login`),
    UNIQUE INDEX `email_UNIQUE` (`email`),
    INDEX `idx_admin_profile_id` (`profile_id`),
    CONSTRAINT `fk_admin_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- profile_resource
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `profile_resource`;

CREATE TABLE `profile_resource`
(
    `profile_id` INTEGER NOT NULL,
    `resource_id` INTEGER NOT NULL,
    `access` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`profile_id`,`resource_id`),
    INDEX `idx_profile_resource_profile_id` (`profile_id`),
    INDEX `idx_profile_resource_resource_id` (`resource_id`),
    CONSTRAINT `fk_profile_resource_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_profile_resource_resource_id`
        FOREIGN KEY (`resource_id`)
        REFERENCES `resource` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- profile_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `profile_module`;

CREATE TABLE `profile_module`
(
    `profile_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    `access` TINYINT DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`profile_id`,`module_id`),
    INDEX `idx_profile_module_profile_id` (`profile_id`),
    INDEX `idx_profile_module_module_id` (`module_id`),
    CONSTRAINT `fk_profile_module_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT `fk_profile_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- message
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message`;

CREATE TABLE `message`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `secured` TINYINT,
    `text_layout_file_name` VARCHAR(255),
    `text_template_file_name` VARCHAR(255),
    `html_layout_file_name` VARCHAR(255),
    `html_template_file_name` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name_UNIQUE` (`name`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon`;

CREATE TABLE `coupon`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(45) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `serialized_effects` LONGTEXT NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL,
    `start_date` DATETIME,
    `expiration_date` DATETIME,
    `max_usage` INTEGER NOT NULL,
    `is_cumulative` TINYINT(1) NOT NULL,
    `is_removing_postage` TINYINT(1) NOT NULL,
    `is_available_on_special_offers` TINYINT(1) NOT NULL,
    `is_used` TINYINT(1) NOT NULL,
    `serialized_conditions` TEXT NOT NULL,
    `per_customer_usage_count` TINYINT(1) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`),
    INDEX `idx_is_enabled` (`is_enabled`),
    INDEX `idx_is_used` (`is_used`),
    INDEX `idx_type` (`type`),
    INDEX `idx_expiration_date` (`expiration_date`),
    INDEX `idx_is_cumulative` (`is_cumulative`),
    INDEX `idx_is_removing_postage` (`is_removing_postage`),
    INDEX `idx_max_usage` (`max_usage`),
    INDEX `idx_is_available_on_special_offers` (`is_available_on_special_offers`),
    INDEX `idx_start_date` (`start_date`)
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `resource` VARCHAR(255),
    `resource_id` INTEGER,
    `action` VARCHAR(255),
    `message` TEXT,
    `request` LONGTEXT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- content_folder
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_folder`;

CREATE TABLE `content_folder`
(
    `content_id` INTEGER NOT NULL,
    `folder_id` INTEGER NOT NULL,
    `default_folder` TINYINT(1),
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`content_id`,`folder_id`),
    INDEX `idx_content_folder_content_id` (`content_id`),
    INDEX `idx_content_folder_folder_id` (`folder_id`),
    INDEX `idx_content_folder_default` (`default_folder`),
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- cart
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cart`;

CREATE TABLE `cart`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `token` VARCHAR(255),
    `customer_id` INTEGER,
    `address_delivery_id` INTEGER,
    `address_invoice_id` INTEGER,
    `currency_id` INTEGER,
    `discount` DECIMAL(16,6) DEFAULT 0.000000,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `token_UNIQUE` (`token`),
    INDEX `idx_cart_customer_id` (`customer_id`),
    INDEX `idx_cart_address_delivery_id` (`address_delivery_id`),
    INDEX `idx_cart_address_invoice_id` (`address_invoice_id`),
    INDEX `idx_cart_currency_id` (`currency_id`),
    CONSTRAINT `fk_cart_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_cart_address_delivery_id`
        FOREIGN KEY (`address_delivery_id`)
        REFERENCES `address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_cart_address_invoice_id`
        FOREIGN KEY (`address_invoice_id`)
        REFERENCES `address` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_cart_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- cart_item
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cart_item`;

CREATE TABLE `cart_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `cart_id` INTEGER NOT NULL,
    `product_id` INTEGER NOT NULL,
    `quantity` FLOAT DEFAULT 1,
    `product_sale_elements_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000,
    `price_end_of_life` DATETIME,
    `promo` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_cart_item_cart_id` (`cart_id`),
    INDEX `idx_cart_item_product_id` (`product_id`),
    INDEX `idx_cart_item_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_cart_item_cart_id`
        FOREIGN KEY (`cart_id`)
        REFERENCES `cart` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_cart_item_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_cart_item_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_price
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_price`;

CREATE TABLE `product_price`
(
    `product_sale_elements_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `from_default_currency` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`product_sale_elements_id`,`currency_id`),
    INDEX `idx_product_price_product_sale_elements_id` (`product_sale_elements_id`),
    INDEX `idx_product_price_currency_id` (`currency_id`),
    CONSTRAINT `fk_product_price_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_price_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_image`;

CREATE TABLE `category_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_category_image_category_id` (`category_id`),
    INDEX `idx_category_image_category_id_position` (`category_id`, `position`),
    CONSTRAINT `fk_category_image_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- folder_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_image`;

CREATE TABLE `folder_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `folder_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_folder_image_folder_id` (`folder_id`),
    INDEX `idx_folder_image_folder_id_position` (`folder_id`, `position`),
    CONSTRAINT `fk_folder_image_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- content_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_image`;

CREATE TABLE `content_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `content_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_content_image_content_id` (`content_id`),
    INDEX `idx_content_image_content_id_position` (`content_id`, `position`),
    CONSTRAINT `fk_content_image_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_document`;

CREATE TABLE `category_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_category_document_category_id` (`category_id`),
    CONSTRAINT `fk_catgory_document_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- content_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_document`;

CREATE TABLE `content_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `content_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_content_document_content_id` (`content_id`),
    CONSTRAINT `fk_content_document_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- folder_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_document`;

CREATE TABLE `folder_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `folder_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_folder_document_folder_id` (`folder_id`),
    CONSTRAINT `fk_folder_document_folder_id`
        FOREIGN KEY (`folder_id`)
        REFERENCES `folder` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_associated_content
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_associated_content`;

CREATE TABLE `product_associated_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `content_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_product_associated_content_product_id` (`product_id`),
    INDEX `idx_product_associated_content_content_id` (`content_id`),
    CONSTRAINT `fk_product_associated_content_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_associated_content_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_associated_content
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_associated_content`;

CREATE TABLE `category_associated_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `category_id` INTEGER NOT NULL,
    `content_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_category_associated_content_category_id` (`category_id`),
    INDEX `idx_category_associated_content_content_id` (`content_id`),
    CONSTRAINT `fk_category_associated_content_category_id`
        FOREIGN KEY (`category_id`)
        REFERENCES `category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_category_associated_content_content_id`
        FOREIGN KEY (`content_id`)
        REFERENCES `content` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- rewriting_url
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `rewriting_url`;

CREATE TABLE `rewriting_url`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `url` VARBINARY(255) NOT NULL,
    `view` VARCHAR(255),
    `view_id` VARCHAR(255),
    `view_locale` VARCHAR(255),
    `redirected` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `url_UNIQUE` (`url`),
    INDEX `idx_rewriting_url_redirected` (`redirected`),
    INDEX `idx_rewriting_url` (`view_locale`, `view`, `view_id`, `redirected`),
    CONSTRAINT `fk_rewriting_url_redirected`
        FOREIGN KEY (`redirected`)
        REFERENCES `rewriting_url` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- rewriting_argument
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `rewriting_argument`;

CREATE TABLE `rewriting_argument`
(
    `rewriting_url_id` INTEGER NOT NULL,
    `parameter` VARCHAR(255) NOT NULL,
    `value` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`rewriting_url_id`,`parameter`,`value`),
    INDEX `idx_rewriting_argument_rewirting_url_id` (`rewriting_url_id`),
    CONSTRAINT `fk_rewriting_argument_rewirting_url_id`
        FOREIGN KEY (`rewriting_url_id`)
        REFERENCES `rewriting_url` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- template
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `template`;

CREATE TABLE `template`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_image`;

CREATE TABLE `module_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_module_image_module_id` (`module_id`),
    INDEX `idx_module_image_module_id_position` (`module_id`, `position`),
    CONSTRAINT `fk_module_image_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_product_tax
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_product_tax`;

CREATE TABLE `order_product_tax`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_product_id` INTEGER NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    `amount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `promo_amount` DECIMAL(16,6) DEFAULT 0.000000,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_ order_product_tax_order_product_id` (`order_product_id`),
    CONSTRAINT `fk_ order_product_tax_order_product_id0`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- newsletter
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `newsletter`;

CREATE TABLE `newsletter`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `firstname` VARCHAR(255),
    `lastname` VARCHAR(255),
    `locale` VARCHAR(5),
    `unsubscribed` TINYINT(1) DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `email_UNIQUE` (`email`),
    INDEX `idx_unsubscribed` (`unsubscribed`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_coupon
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_coupon`;

CREATE TABLE `order_coupon`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `code` VARCHAR(45) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `short_description` TEXT NOT NULL,
    `description` LONGTEXT NOT NULL,
    `start_date` DATETIME,
    `expiration_date` DATETIME NOT NULL,
    `is_cumulative` TINYINT(1) NOT NULL,
    `is_removing_postage` TINYINT(1) NOT NULL,
    `is_available_on_special_offers` TINYINT(1) NOT NULL,
    `serialized_conditions` TEXT NOT NULL,
    `per_customer_usage_count` TINYINT(1) NOT NULL,
    `usage_canceled` TINYINT(1) DEFAULT 0,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_coupon_order_id` (`order_id`),
    CONSTRAINT `fk_order_coupon_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon_country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_country`;

CREATE TABLE `coupon_country`
(
    `coupon_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`country_id`),
    INDEX `fk_country_id_idx` (`country_id`),
    CONSTRAINT `fk_coupon_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_country_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_module`;

CREATE TABLE `coupon_module`
(
    `coupon_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`module_id`),
    INDEX `fk_module_id_idx` (`module_id`),
    CONSTRAINT `fk_coupon_module_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_coupon_country
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_coupon_country`;

CREATE TABLE `order_coupon_country`
(
    `coupon_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`country_id`),
    INDEX `fk_country_id_idx` (`country_id`),
    CONSTRAINT `fk_order_coupon_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_coupon_country_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `order_coupon` (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_coupon_module
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_coupon_module`;

CREATE TABLE `order_coupon_module`
(
    `coupon_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    PRIMARY KEY (`coupon_id`,`module_id`),
    INDEX `fk_module_id_idx` (`module_id`),
    CONSTRAINT `fk_coupon_module_coupon_id0`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `order_coupon` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_module_module_id0`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon_customer_count
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_customer_count`;

CREATE TABLE `coupon_customer_count`
(
    `coupon_id` INTEGER NOT NULL,
    `customer_id` INTEGER NOT NULL,
    `count` INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (`coupon_id`,`customer_id`),
    INDEX `fk_coupon_customer_customer_id_idx` (`customer_id`),
    INDEX `fk_coupon_customer_coupon_id_idx` (`coupon_id`),
    CONSTRAINT `fk_coupon_customer_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_coupon_customer_coupon_id`
        FOREIGN KEY (`coupon_id`)
        REFERENCES `coupon` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand`;

CREATE TABLE `brand`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` TINYINT,
    `position` INTEGER,
    `logo_image_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_brand_brand_image_idx` (`logo_image_id`),
    CONSTRAINT `fk_logo_image_id_brand_image`
        FOREIGN KEY (`logo_image_id`)
        REFERENCES `brand_image` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_document`;

CREATE TABLE `brand_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `brand_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_brand_document_brand_id` (`brand_id`),
    CONSTRAINT `fk_brand_document_brand_id`
        FOREIGN KEY (`brand_id`)
        REFERENCES `brand` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_image`;

CREATE TABLE `brand_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `brand_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 1 NOT NULL,
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_brand_image_brand_id` (`brand_id`),
    CONSTRAINT `fk_brand_image_brand_id`
        FOREIGN KEY (`brand_id`)
        REFERENCES `brand` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- form_firewall
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `form_firewall`;

CREATE TABLE `form_firewall`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `form_name` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(15) NOT NULL,
    `attempts` TINYINT DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_form_firewall_form_name` (`form_name`),
    INDEX `idx_form_firewall_ip_address` (`ip_address`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale`;

CREATE TABLE `sale`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `active` TINYINT(1) DEFAULT 0 NOT NULL,
    `display_initial_price` TINYINT(1) DEFAULT 1 NOT NULL,
    `start_date` DATETIME,
    `end_date` DATETIME,
    `price_offset_type` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_sales_active_start_end_date` (`active`, `start_date`, `end_date`),
    INDEX `idx_sales_active` (`active`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_offset_currency
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_offset_currency`;

CREATE TABLE `sale_offset_currency`
(
    `sale_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price_offset_value` FLOAT DEFAULT 0,
    PRIMARY KEY (`sale_id`,`currency_id`),
    INDEX `fk_sale_offset_currency_currency1_idx` (`currency_id`),
    CONSTRAINT `fk_sale_offset_currency_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_offset_currency_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_product`;

CREATE TABLE `sale_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_id` INTEGER NOT NULL,
    `product_id` INTEGER NOT NULL,
    `attribute_av_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `fk_sale_product_product_idx` (`product_id`),
    INDEX `fk_sale_product_attribute_av_idx` (`attribute_av_id`),
    INDEX `idx_sale_product_sales_id_product_id` (`sale_id`, `product_id`),
    CONSTRAINT `fk_sale_product_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_product_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_product_attribute_av_id`
        FOREIGN KEY (`attribute_av_id`)
        REFERENCES `attribute_av` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- export_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_category`;

CREATE TABLE `export_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- export
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export`;

CREATE TABLE `export`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `export_category_id` INTEGER NOT NULL,
    `handle_class` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `fk_export_1_idx` (`export_category_id`),
    CONSTRAINT `fk_export_export_category_id`
        FOREIGN KEY (`export_category_id`)
        REFERENCES `export_category` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- import_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_category`;

CREATE TABLE `import_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- import
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import`;

CREATE TABLE `import`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `import_category_id` INTEGER NOT NULL,
    `handle_class` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `fk_export_1_idx` (`import_category_id`),
    CONSTRAINT `fk_import_import_category_id`
        FOREIGN KEY (`import_category_id`)
        REFERENCES `import_category` (`id`)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_sale_elements_product_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_sale_elements_product_image`;

CREATE TABLE `product_sale_elements_product_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_image_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_pse_product_image_product_image_id_idx` (`product_image_id`),
    INDEX `fk_pse_product_image_product_sale_element_idx` (`product_sale_elements_id`),
    CONSTRAINT `fk_pse_product_image_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_pse_product_image_product_image_id`
        FOREIGN KEY (`product_image_id`)
        REFERENCES `product_image` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_sale_elements_product_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_sale_elements_product_document`;

CREATE TABLE `product_sale_elements_product_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_document_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_pse_product_document_product_document__idx` (`product_document_id`),
    INDEX `fk_pse_product_document_product_sale_elem_idx` (`product_sale_elements_id`),
    CONSTRAINT `fk_pse_product_document_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_pse_product_document_product_document_id`
        FOREIGN KEY (`product_document_id`)
        REFERENCES `product_document` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- hook
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `hook`;

CREATE TABLE `hook`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `type` TINYINT,
    `by_module` TINYINT(1),
    `native` TINYINT(1),
    `activate` TINYINT(1),
    `block` TINYINT(1),
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`, `type`),
    INDEX `idx_module_activate` (`activate`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_hook
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_hook`;

CREATE TABLE `module_hook`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `hook_id` INTEGER NOT NULL,
    `classname` VARCHAR(255),
    `method` VARCHAR(255),
    `active` TINYINT(1) NOT NULL,
    `hook_active` TINYINT(1) NOT NULL,
    `module_active` TINYINT(1) NOT NULL,
    `position` INTEGER NOT NULL,
    `templates` TEXT,
    PRIMARY KEY (`id`),
    INDEX `idx_module_hook_active` (`active`),
    INDEX `fk_module_hook_module_id_idx` (`module_id`),
    INDEX `fk_module_hook_hook_id_idx` (`hook_id`),
    CONSTRAINT `fk_module_hook_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_module_hook_hook_id`
        FOREIGN KEY (`hook_id`)
        REFERENCES `hook` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- meta_data
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `meta_data`;

CREATE TABLE `meta_data`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `meta_key` VARCHAR(100) NOT NULL,
    `element_key` VARCHAR(100) NOT NULL,
    `element_id` INTEGER NOT NULL,
    `is_serialized` TINYINT(1) NOT NULL,
    `value` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `meta_data_key_element_idx` (`meta_key`, `element_key`, `element_id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_config
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_config`;

CREATE TABLE `module_config`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_module_id_name` (`module_id`, `name`),
    CONSTRAINT `fk_module_config_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- api
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `api`;

CREATE TABLE `api`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255),
    `api_key` VARCHAR(100),
    `profile_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_api_profile_id` (`profile_id`),
    CONSTRAINT `fk_api_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- country_area
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `country_area`;

CREATE TABLE `country_area`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `country_id` INTEGER NOT NULL,
    `state_id` INTEGER,
    `area_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `country_area_area_id_idx` (`area_id`),
    INDEX `fk_country_area_country_id_idx` (`country_id`),
    INDEX `fk_country_area_state_id_idx` (`state_id`),
    CONSTRAINT `fk_country_area_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_country_area_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- ignored_module_hook
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `ignored_module_hook`;

CREATE TABLE `ignored_module_hook`
(
    `module_id` INTEGER NOT NULL,
    `hook_id` INTEGER NOT NULL,
    `method` VARCHAR(255),
    `classname` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`module_id`,`hook_id`),
    INDEX `fk_deleted_module_hook_module_id_idx` (`module_id`),
    INDEX `fk_deleted_module_hook_hook_id_idx` (`hook_id`),
    CONSTRAINT `fk_deleted_module_hook_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_deleted_module_hook_hook_id`
        FOREIGN KEY (`hook_id`)
        REFERENCES `hook` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- state
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `state`;

CREATE TABLE `state`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `isocode` VARCHAR(4),
    `country_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `fk_state_country_id_idx` (`country_id`),
    CONSTRAINT `fk_state_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- tax_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_i18n`;

CREATE TABLE `tax_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `tax_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `tax` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- tax_rule_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `tax_rule_i18n`;

CREATE TABLE `tax_rule_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `tax_rule_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `tax_rule` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `folder_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `folder` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `content_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `content` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_image_i18n`;

CREATE TABLE `product_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_document_i18n`;

CREATE TABLE `product_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `product_document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `product_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- currency_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `currency_i18n`;

CREATE TABLE `currency_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `name` VARCHAR(45),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `currency_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `currency` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- profile_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `profile_i18n`;

CREATE TABLE `profile_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `profile_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `profile` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- message_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message_i18n`;

CREATE TABLE `message_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` TEXT,
    `subject` TEXT,
    `text_message` LONGTEXT,
    `html_message` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `message_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `message` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_i18n`;

CREATE TABLE `coupon_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `short_description` TEXT NOT NULL,
    `description` LONGTEXT NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `coupon_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_image_i18n`;

CREATE TABLE `category_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `category_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `category_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- folder_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_image_i18n`;

CREATE TABLE `folder_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `folder_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `folder_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- content_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_image_i18n`;

CREATE TABLE `content_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `content_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `content_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_document_i18n`;

CREATE TABLE `category_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `category_document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `category_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- content_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `content_document_i18n`;

CREATE TABLE `content_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `content_document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `content_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- folder_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_document_i18n`;

CREATE TABLE `folder_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `folder_document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `folder_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- template_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `template_i18n`;

CREATE TABLE `template_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `name` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `template_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `template` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_image_i18n`;

CREATE TABLE `module_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `module_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `module_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_i18n`;

CREATE TABLE `brand_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `meta_keywords` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `brand_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `brand` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_document_i18n`;

CREATE TABLE `brand_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `brand_document_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `brand_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- brand_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_image_i18n`;

CREATE TABLE `brand_image_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `brand_image_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `brand_image` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_i18n`;

CREATE TABLE `sale_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `sale_label` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `sale_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `sale` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- export_category_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_category_i18n`;

CREATE TABLE `export_category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `export_category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `export_category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- export_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_i18n`;

CREATE TABLE `export_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `export_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `export` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- import_category_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_category_i18n`;

CREATE TABLE `import_category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `import_category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `import_category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- import_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_i18n`;

CREATE TABLE `import_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `import_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `import` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- hook_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `hook_i18n`;

CREATE TABLE `hook_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `hook_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `hook` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_config_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_config_i18n`;

CREATE TABLE `module_config_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `module_config_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `module_config` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- state_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `state_i18n`;

CREATE TABLE `state_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `state_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `state` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- category_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `category_version`;

CREATE TABLE `category_version`
(
    `id` INTEGER NOT NULL,
    `parent` INTEGER DEFAULT 0 NOT NULL,
    `visible` TINYINT NOT NULL,
    `position` INTEGER NOT NULL,
    `default_template_id` INTEGER,
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_version`;

CREATE TABLE `product_version`
(
    `id` INTEGER NOT NULL,
    `tax_rule_id` INTEGER,
    `ref` VARCHAR(255) NOT NULL,
    `visible` TINYINT DEFAULT 0 NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `template_id` INTEGER,
    `brand_id` INTEGER,
    `virtual` TINYINT DEFAULT 0 NOT NULL,
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- customer_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer_version`;

CREATE TABLE `customer_version`
(
    `id` INTEGER NOT NULL,
    `title_id` INTEGER NOT NULL,
    `lang_id` INTEGER,
    `ref` VARCHAR(50),
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `password` VARCHAR(255),
    `algo` VARCHAR(128),
    `reseller` TINYINT,
    `sponsor` VARCHAR(50),
    `discount` DECIMAL(16,6) DEFAULT 0.000000,
    `remember_me_token` VARCHAR(255),
    `remember_me_serial` VARCHAR(255),
    `enable` TINYINT DEFAULT 0,
    `confirmation_token` VARCHAR(255),
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

-- ---------------------------------------------------------------------
-- folder_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `folder_version`;

CREATE TABLE `folder_version`
(
    `id` INTEGER NOT NULL,
    `parent` INTEGER DEFAULT 0 NOT NULL,
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
) ENGINE=InnoDB CHARACTER SET='utf8';

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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- order_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `order_version`;

CREATE TABLE `order_version`
(
    `id` INTEGER NOT NULL,
    `ref` VARCHAR(45),
    `customer_id` INTEGER NOT NULL,
    `invoice_order_address_id` INTEGER NOT NULL,
    `delivery_order_address_id` INTEGER NOT NULL,
    `invoice_date` DATETIME,
    `currency_id` INTEGER NOT NULL,
    `currency_rate` FLOAT NOT NULL,
    `transaction_ref` VARCHAR(100) COMMENT 'transaction reference - usually use to identify a transaction with banking modules',
    `delivery_ref` VARCHAR(100) COMMENT 'delivery reference - usually use to identify a delivery progress on a distant delivery tracker website',
    `invoice_ref` VARCHAR(100) COMMENT 'the invoice reference',
    `discount` DECIMAL(16,6) DEFAULT 0.000000,
    `postage` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `postage_tax` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `postage_tax_rule_title` VARCHAR(255),
    `payment_module_id` INTEGER NOT NULL,
    `delivery_module_id` INTEGER NOT NULL,
    `status_id` INTEGER NOT NULL,
    `lang_id` INTEGER NOT NULL,
    `cart_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    `customer_id_version` INTEGER DEFAULT 0,
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `order_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `order` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- message_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `message_version`;

CREATE TABLE `message_version`
(
    `id` INTEGER NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `secured` TINYINT,
    `text_layout_file_name` VARCHAR(255),
    `text_template_file_name` VARCHAR(255),
    `html_layout_file_name` VARCHAR(255),
    `html_template_file_name` VARCHAR(255),
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
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- coupon_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `coupon_version`;

CREATE TABLE `coupon_version`
(
    `id` INTEGER NOT NULL,
    `code` VARCHAR(45) NOT NULL,
    `type` VARCHAR(255) NOT NULL,
    `serialized_effects` LONGTEXT NOT NULL,
    `is_enabled` TINYINT(1) NOT NULL,
    `start_date` DATETIME,
    `expiration_date` DATETIME,
    `max_usage` INTEGER NOT NULL,
    `is_cumulative` TINYINT(1) NOT NULL,
    `is_removing_postage` TINYINT(1) NOT NULL,
    `is_available_on_special_offers` TINYINT(1) NOT NULL,
    `is_used` TINYINT(1) NOT NULL,
    `serialized_conditions` TEXT NOT NULL,
    `per_customer_usage_count` TINYINT(1) NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `coupon_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `coupon` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
