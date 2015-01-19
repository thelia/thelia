
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- carousel
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `carousel`;

CREATE TABLE `carousel`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `file` VARCHAR(255),
    `position` INTEGER,
    `url` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- carousel_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `carousel_i18n`;

CREATE TABLE `carousel_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `alt` VARCHAR(255),
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `carousel_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `carousel` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;

# ----------------------------------------------------------------------------------------
# Insert sample values
# ----------------------------------------------------------------------------------------

INSERT INTO `carousel` (`id`, `file`, `position`, `url`, `created_at`, `updated_at`) VALUES
(1, 'sample-carousel-image-1.png', NULL, NULL, NOW(), NOW()),
(2, 'sample-carousel-image-2.png', NULL, NULL, NOW(), NOW());


INSERT INTO `carousel_i18n` (`id`, `locale`, `alt`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(1, 'en_US', 'Thelia e-commerce', 'A sample carousel image', NULL, NULL, NULL),
(1, 'fr_FR', 'Thelia e-commerce', 'Une image de démonstration', NULL, NULL, NULL),
(2, 'en_US', 'Based on Symfony 2', 'Another sample carousle image', NULL, NULL, NULL),
(2, 'fr_FR', 'Construit avec Symphony 2', 'Un autre image de démonstration', NULL, NULL, NULL);