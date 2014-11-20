SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

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
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

SET FOREIGN_KEY_CHECKS = 1;