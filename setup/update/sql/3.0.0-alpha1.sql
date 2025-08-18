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


SET FOREIGN_KEY_CHECKS = 1;
