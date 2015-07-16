SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `image`;

CREATE TABLE `image`
(
`id` INTEGER NOT NULL AUTO_INCREMENT,
`source` VARCHAR(50) NOT NULL,
`source_id` INTEGER NOT NULL,
`file` VARCHAR(255) NOT NULL,
`visible` TINYINT DEFAULT 1 NOT NULL,
`position` INTEGER,
`created_at` DATETIME,
`updated_at` DATETIME,
PRIMARY KEY (`id`),
INDEX `idx_image_source_source_id` (`source`, `source_id`),
INDEX `idx_image_source_source_id_position` (`source`, `source_id`, `position`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- create table image_i18n
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
) ENGINE=InnoDB CHARACTER SET='utf8';

SET FOREIGN_KEY_CHECKS = 1;