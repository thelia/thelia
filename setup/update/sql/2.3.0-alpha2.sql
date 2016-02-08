SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

-- Add column unsubscribed in newsletter table
ALTER TABLE `newsletter` ADD `unsubscribed` TINYINT(1) NOT NULL DEFAULT '0' AFTER `locale`;

-- add admin email
ALTER TABLE  `admin` ADD  `email` VARCHAR(255) NOT NULL AFTER `remember_me_serial` ;
ALTER TABLE  `admin` ADD  `password_renew_token` VARCHAR(255) NOT NULL AFTER `email` ;

-- add admin password renew message

SELECT @max := MAX(`id`) FROM `message`;
SET @max := @max+1;

INSERT INTO `message` (`id`, `name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
(@max, 'new_admin_password', NULL, NULL, 'admin_password.txt', NULL, 'admin_password.html', NOW(), NOW());

INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
    (@max, 'de_DE', NULL, NULL, NULL, NULL),    (@max, 'en_US', 'Mail sent to an administrator who requested a new password', 'New password request on {config key=\"store_name\"}', NULL, NULL),    (@max, 'es_ES', NULL, NULL, NULL, NULL),    (@max, 'fr_FR', 'Mail sent to an administrator who requested a new password', 'New password request on {config key=\"store_name\"}', NULL, NULL);

-- Insert a fake email address for administrators, to trigger the admin update dialog
-- at next admin login.

UPDATE `admin` set email = CONCAT('CHANGE_ME_', ID);

ALTER TABLE `admin` ADD UNIQUE `email_UNIQUE` (`email`);

-- additional config variables

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'minimum_admin_password_length', '4', 0, 0, NOW(), NOW()),
(@max_id + 2, 'enable_lost_admin_password_recovery', '1', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
    (@max_id + 1, 'de_DE', NULL, NULL, NULL, NULL),
    (@max_id + 2, 'de_DE', NULL, NULL, NULL, NULL),    (@max_id + 1, 'en_US', 'The minimum length required for an administrator password', NULL, NULL, NULL),
    (@max_id + 2, 'en_US', 'Allow an administrator to recreate a lost password', NULL, NULL, NULL),    (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
    (@max_id + 2, 'es_ES', NULL, NULL, NULL, NULL),    (@max_id + 1, 'fr_FR', 'The minimum length required for an administrator password', NULL, NULL, NULL),
    (@max_id + 2, 'fr_FR', 'Autoriser les administrateurs à recréer leur mot de passe', NULL, NULL, NULL);


-- Update module version column

ALTER TABLE `module` MODIFY `version` varchar(25) NOT NULL DEFAULT '';

SET FOREIGN_KEY_CHECKS = 1;
