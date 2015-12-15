SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Add new column in module_hook table
ALTER TABLE `module_hook` ADD `templates` TEXT AFTER`position`;

-- Add new columns in currency table
ALTER TABLE `currency` ADD  `format` CHAR( 10 ) NOT NULL AFTER  `symbol`;
ALTER TABLE `currency` ADD  `visible` TINYINT( 1 ) NOT NULL DEFAULT '0' AFTER  `rate`;

-- Update currencies
UPDATE `currency` SET `visible` = 1 WHERE 1;
UPDATE `currency` SET `format` = '%n %s' WHERE `code` NOT IN ('USD', 'GBP');
UPDATE `currency` SET `format` = '%s%n' WHERE `code` IN ('USD', 'GBP');

-- Update admin profile
CREATE TABLE `admin_profile`
(
`admin_id` INTEGER NOT NULL,
`profile_id` INTEGER NOT NULL,
`created_at` DATETIME,
`updated_at` DATETIME,
PRIMARY KEY (`admin_id`,`profile_id`),
INDEX `idx_admin_profile_profile_id` (`profile_id`),
INDEX `idx_admin_profile_admin_id` (`admin_id`),
CONSTRAINT `fk_admin_profile_profile_id`
FOREIGN KEY (`profile_id`)
REFERENCES `profile` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE,
CONSTRAINT `fk_admin_profile_admin_id`
FOREIGN KEY (`admin_id`)
REFERENCES `admin` (`id`)
ON UPDATE RESTRICT
ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

INSERT INTO `admin_profile` (`admin_id`,`profile_id`,`created_at`,`updated_at`) (SELECT id, profile_id ,NOW(), NOW() FROM admin WHERE profile_id IS NOT NULL);

ALTER TABLE `admin` DROP FOREIGN KEY  `fk_admin_profile_id`;
ALTER TABLE `admin` DROP `profile_id`;

SET FOREIGN_KEY_CHECKS = 1;