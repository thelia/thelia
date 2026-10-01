-- The current shape of the table: the update below has already been folded in.

DROP TABLE IF EXISTS `absent_drop_probe`;

CREATE TABLE `absent_drop_probe`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB;
