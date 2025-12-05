SET FOREIGN_KEY_CHECKS = 0;
-- ---------------------------------------------------------------------
-- choice_filter_other
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `choice_filter_other`;

CREATE TABLE `choice_filter_other`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `type` VARCHAR(55),
    `visible` TINYINT(1),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- choice_filter
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `choice_filter`;

CREATE TABLE `choice_filter`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `feature_id` INTEGER,
    `attribute_id` INTEGER,
    `other_id` INTEGER,
    `category_id` INTEGER,
    `template_id` INTEGER,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `visible` TINYINT(1),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `choice_filter_FI_1` (`attribute_id`),
    INDEX `choice_filter_FI_2` (`feature_id`),
    INDEX `choice_filter_FI_3` (`other_id`),
    INDEX `choice_filter_FI_4` (`category_id`),
    INDEX `choice_filter_FI_5` (`template_id`),
    CONSTRAINT `choice_filter_FK_1`
        FOREIGN KEY (`attribute_id`)
            REFERENCES `attribute` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `choice_filter_FK_2`
        FOREIGN KEY (`feature_id`)
            REFERENCES `feature` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `choice_filter_FK_3`
        FOREIGN KEY (`other_id`)
            REFERENCES `choice_filter_other` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `choice_filter_FK_4`
        FOREIGN KEY (`category_id`)
            REFERENCES `category` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE,
    CONSTRAINT `choice_filter_FK_5`
        FOREIGN KEY (`template_id`)
            REFERENCES `template` (`id`)
            ON UPDATE RESTRICT
            ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- choice_filter_other_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `choice_filter_other_i18n`;

CREATE TABLE `choice_filter_other_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `choice_filter_other_i18n_FK_1`
        FOREIGN KEY (`id`)
            REFERENCES `choice_filter_other` (`id`)
            ON DELETE CASCADE
) ENGINE=InnoDB;


--
-- Contenu de la table `choice_filter_other`
--

INSERT INTO `choice_filter_other` (`id`, `type`, `visible`) VALUES
(2, 'brand', 1),
(3, 'category', 1);

--
-- Contenu de la table `choice_filter_other_i18n`
--

INSERT INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
(2, 'en_US', 'Brand', NULL),
(2, 'fr_FR', 'Marque', NULL),
(3, 'en_US', 'Category', NULL),
(3, 'fr_FR', 'Catégorie', NULL);


ALTER TABLE `address`
    MODIFY COLUMN `address2` VARCHAR(255) NULL,
    MODIFY COLUMN `address3` VARCHAR(255) NULL;

ALTER TABLE `choice_filter` ADD COLUMN `type` VARCHAR(255);

--
-- Migration : Ajout des champs de postage et modules de paiement/livraison à la table cart
-- Ajoute les colonnes postage, postage_tax, payment_module_id et delivery_module_id
--

ALTER TABLE `cart`
    ADD COLUMN `postage_tax_rule_title` VARCHAR(255) DEFAULT NULL after address_invoice_id,
    ADD COLUMN `postage_tax` DECIMAL(10,2) DEFAULT NULL after address_invoice_id,
    ADD COLUMN `postage` DECIMAL(10,2) DEFAULT NULL after address_invoice_id,
    ADD COLUMN `payment_module_id` INT DEFAULT NULL after address_invoice_id,
    ADD COLUMN `delivery_module_id` INT DEFAULT NULL after address_invoice_id,
    ADD CONSTRAINT `fk_cart_payment_module_id`
        FOREIGN KEY (`payment_module_id`) REFERENCES `module`(`id`)
            ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_cart_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`) REFERENCES `module`(`id`)
            ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE customer ADD COLUMN confirmation_token_expires_at DATETIME NULL after confirmation_token;
ALTER TABLE customer_version ADD COLUMN confirmation_token_expires_at DATETIME NULL after confirmation_token;

INSERT INTO `message` (`name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('customer_send_code', NULL, NULL, 'customer_send_code.txt', NULL, 'customer_send_code.html', NOW(), NOW());

UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\PricePercentTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\PricePercentTaxType';

ALTER TABLE config
    MODIFY COLUMN `value` TEXT NULL;


ALTER TABLE `cart` DROP FOREIGN KEY `fk_cart_address_delivery_id`;
ALTER TABLE `cart` DROP FOREIGN KEY `fk_cart_address_invoice_id`;
ALTER TABLE `cart` DROP FOREIGN KEY `fk_cart_payment_module_id`;
ALTER TABLE `cart` DROP FOREIGN KEY `fk_cart_delivery_module_id`;

ALTER TABLE `cart`
    ADD CONSTRAINT `fk_cart_address_delivery_id`
        FOREIGN KEY (`address_delivery_id`) REFERENCES `address` (`id`)
            ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE `cart`
    ADD CONSTRAINT `fk_cart_address_invoice_id`
        FOREIGN KEY (`address_invoice_id`) REFERENCES `address` (`id`)
            ON DELETE SET NULL ON UPDATE RESTRICT;

ALTER TABLE `cart`
    ADD CONSTRAINT `fk_cart_payment_module_id`
        FOREIGN KEY (`payment_module_id`) REFERENCES `module` (`id`)
            ON DELETE SET NULL;

ALTER TABLE `cart`
    ADD CONSTRAINT `fk_cart_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`) REFERENCES `module` (`id`)
            ON DELETE SET NULL;

CREATE TABLE `cart_address`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_title_id` INTEGER,
    `address_id` INTEGER,
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
    INDEX `fk_cart_address_customer_title_id_idx` (`customer_title_id`),
    INDEX `fk_addres_id_idx` (`customer_title_id`),
    INDEX `fk_cart_address_country_id_idx` (`country_id`),
    INDEX `fk_cart_address_state_id_idx` (`state_id`),
    CONSTRAINT `fk_cart_address_customer_title_id`
    FOREIGN KEY (`customer_title_id`)
    REFERENCES `customer_title` (`id`)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT,
    CONSTRAINT `fk_cart_address_country_id`
    FOREIGN KEY (`country_id`)
    REFERENCES `country` (`id`)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT,
    CONSTRAINT `fk_cart_address_state_id`
    FOREIGN KEY (`state_id`)
    REFERENCES `state` (`id`)
    ON UPDATE RESTRICT
    ON DELETE RESTRICT,
    CONSTRAINT `fk_address_id`
    FOREIGN KEY (`address_id`)
    REFERENCES `address` (`id`)
    ON UPDATE RESTRICT
    ON DELETE SET NULL
    ) ENGINE=InnoDB CHARACTER SET='utf8';


ALTER TABLE `cart`
DROP FOREIGN KEY fk_cart_address_delivery_id,
    DROP FOREIGN KEY fk_cart_address_invoice_id,
DROP INDEX idx_cart_address_delivery_id,
    DROP INDEX idx_cart_address_invoice_id;

ALTER TABLE `cart`
    ADD INDEX idx_cart_address_delivery_id (address_delivery_id),
    ADD INDEX idx_cart_address_invoice_id (address_invoice_id);

ALTER TABLE `cart`
    ADD CONSTRAINT fk_cart_address_delivery_id
    FOREIGN KEY (address_delivery_id)
    REFERENCES cart_address (id)
    ON UPDATE RESTRICT
       ON DELETE RESTRICT,
              ADD CONSTRAINT fk_cart_address_invoice_id
              FOREIGN KEY (address_invoice_id)
              REFERENCES cart_address (id)
          ON UPDATE RESTRICT
             ON DELETE RESTRICT;

ALTER TABLE `product_sale_elements` ADD COLUMN `position` INT NOT NULL DEFAULT 0 AFTER `quantity`;
ALTER TABLE `product_sale_elements` ADD COLUMN `visible` BOOLEAN NOT NULL DEFAULT TRUE AFTER `quantity`;

UPDATE `config` SET `value`='2.6.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='6' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SET FOREIGN_KEY_CHECKS = 1;
