-- Generated the way module:generate:sql writes it: every table is dropped before it is created.

DROP TABLE IF EXISTS `kept_data_probe`;

CREATE TABLE `kept_data_probe`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;
