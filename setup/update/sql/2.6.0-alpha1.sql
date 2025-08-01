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

SET FOREIGN_KEY_CHECKS = 1;
