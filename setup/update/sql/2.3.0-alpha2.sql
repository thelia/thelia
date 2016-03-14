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
(@max_id + 2, 'enable_lost_admin_password_recovery', '1', 0, 0, NOW(), NOW()),
(@max_id + 3, 'notify_newsletter_subscription', '1', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
    (@max_id + 1, 'de_DE', NULL, NULL, NULL, NULL),
    (@max_id + 2, 'de_DE', NULL, NULL, NULL, NULL),
    (@max_id + 3, 'de_DE', NULL, NULL, NULL, NULL),    (@max_id + 1, 'en_US', 'The minimum length required for an administrator password', NULL, NULL, NULL),
    (@max_id + 2, 'en_US', 'Allow an administrator to recreate a lost password (1 = yes, 0 = no)', NULL, NULL, NULL),
    (@max_id + 3, 'en_US', 'Send a confirmation email to newsletter subscribers (1 = yes, 0 = no)', NULL, NULL, NULL),    (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
    (@max_id + 2, 'es_ES', NULL, NULL, NULL, NULL),
    (@max_id + 3, 'es_ES', NULL, NULL, NULL, NULL),    (@max_id + 1, 'fr_FR', 'The minimum length required for an administrator password', NULL, NULL, NULL),
    (@max_id + 2, 'fr_FR', NULL, NULL, NULL, NULL),
    (@max_id + 3, 'fr_FR', NULL, NULL, NULL, NULL);

-- Additional hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
    (@max_id+1, 'sale.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+2, 'sale.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+3, 'sale.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+4, 'sale.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+5, 'sale.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+6, 'sale.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+7, 'sale.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+8, 'sale.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+9, 'sale.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+10, 'account-order.invoice-address-bottom', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+11, 'account-order.delivery-address-bottom', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+12, 'newsletter-unsubscribe.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+13, 'newsletter-unsubscribe.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+14, 'newsletter-unsubscribe.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+15, 'newsletter-unsubscribe.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
    (@max_id+16, 'newsletter-unsubscribe.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id+1, 'de_DE', NULL, NULL, NULL),
    (@max_id+2, 'de_DE', NULL, NULL, NULL),
    (@max_id+3, 'de_DE', NULL, NULL, NULL),
    (@max_id+4, 'de_DE', NULL, NULL, NULL),
    (@max_id+5, 'de_DE', NULL, NULL, NULL),
    (@max_id+6, 'de_DE', NULL, NULL, NULL),
    (@max_id+7, 'de_DE', NULL, NULL, NULL),
    (@max_id+8, 'de_DE', NULL, NULL, NULL),
    (@max_id+9, 'de_DE', NULL, NULL, NULL),
    (@max_id+10, 'de_DE', NULL, NULL, NULL),
    (@max_id+11, 'de_DE', NULL, NULL, NULL),
    (@max_id+12, 'de_DE', NULL, NULL, NULL),
    (@max_id+13, 'de_DE', NULL, NULL, NULL),
    (@max_id+14, 'de_DE', NULL, NULL, NULL),
    (@max_id+15, 'de_DE', NULL, NULL, NULL),
    (@max_id+16, 'de_DE', NULL, NULL, NULL),    (@max_id+1, 'en_US', 'Sale - at the top', NULL, NULL),
    (@max_id+2, 'en_US', 'Sale - at the bottom', NULL, NULL),
    (@max_id+3, 'en_US', 'Sale - at the top of the main area', NULL, NULL),
    (@max_id+4, 'en_US', 'Sale - at the bottom of the main area', NULL, NULL),
    (@max_id+5, 'en_US', 'Sale - before the main content area', NULL, NULL),
    (@max_id+6, 'en_US', 'Sale - after the main content area', NULL, NULL),
    (@max_id+7, 'en_US', 'Sale - CSS stylesheet', NULL, NULL),
    (@max_id+8, 'en_US', 'Sale - after javascript include', NULL, NULL),
    (@max_id+9, 'en_US', 'Sale - javascript initialization', NULL, NULL),
    (@max_id+10, 'en_US', 'Order details - after invoice address', NULL, NULL),
    (@max_id+11, 'en_US', 'Order details - after delivery address', NULL, NULL),
    (@max_id+12, 'en_US', 'Newsletter unsubscribe page - at the top', NULL, NULL),
    (@max_id+13, 'en_US', 'Newsletter unsubscribe page - at the bottom', NULL, NULL),
    (@max_id+14, 'en_US', 'Newsletter unsubscribe page - CSS stylesheet', NULL, NULL),
    (@max_id+15, 'en_US', 'Newsletter unsubscribe page - after javascript include', NULL, NULL),
    (@max_id+16, 'en_US', 'Newsletter unsubscribe page - after javascript initialisation', NULL, NULL),    (@max_id+1, 'es_ES', NULL, NULL, NULL),
    (@max_id+2, 'es_ES', NULL, NULL, NULL),
    (@max_id+3, 'es_ES', NULL, NULL, NULL),
    (@max_id+4, 'es_ES', NULL, NULL, NULL),
    (@max_id+5, 'es_ES', NULL, NULL, NULL),
    (@max_id+6, 'es_ES', NULL, NULL, NULL),
    (@max_id+7, 'es_ES', NULL, NULL, NULL),
    (@max_id+8, 'es_ES', NULL, NULL, NULL),
    (@max_id+9, 'es_ES', NULL, NULL, NULL),
    (@max_id+10, 'es_ES', NULL, NULL, NULL),
    (@max_id+11, 'es_ES', NULL, NULL, NULL),
    (@max_id+12, 'es_ES', NULL, NULL, NULL),
    (@max_id+13, 'es_ES', NULL, NULL, NULL),
    (@max_id+14, 'es_ES', NULL, NULL, NULL),
    (@max_id+15, 'es_ES', NULL, NULL, NULL),
    (@max_id+16, 'es_ES', NULL, NULL, NULL),    (@max_id+1, 'fr_FR', NULL, NULL, NULL),
    (@max_id+2, 'fr_FR', NULL, NULL, NULL),
    (@max_id+3, 'fr_FR', NULL, NULL, NULL),
    (@max_id+4, 'fr_FR', NULL, NULL, NULL),
    (@max_id+5, 'fr_FR', NULL, NULL, NULL),
    (@max_id+6, 'fr_FR', NULL, NULL, NULL),
    (@max_id+7, 'fr_FR', NULL, NULL, NULL),
    (@max_id+8, 'fr_FR', NULL, NULL, NULL),
    (@max_id+9, 'fr_FR', NULL, NULL, NULL),
    (@max_id+10, 'fr_FR', NULL, NULL, NULL),
    (@max_id+11, 'fr_FR', NULL, NULL, NULL),
    (@max_id+12, 'fr_FR', NULL, NULL, NULL),
    (@max_id+13, 'fr_FR', NULL, NULL, NULL),
    (@max_id+14, 'fr_FR', NULL, NULL, NULL),
    (@max_id+15, 'fr_FR', NULL, NULL, NULL),
    (@max_id+16, 'fr_FR', NULL, NULL, NULL);

-- Update module version column
ALTER TABLE `module` MODIFY `version` varchar(25) NOT NULL DEFAULT '';

-- Add new column in coupon table
ALTER TABLE `coupon` ADD `start_date` DATETIME AFTER`is_enabled`;
ALTER TABLE `coupon` ADD INDEX `idx_start_date` (`start_date`);

-- Add new column in coupon version table
ALTER TABLE `coupon_version` ADD `start_date` DATETIME AFTER`is_enabled`;

-- Add new column in order coupon table
ALTER TABLE `order_coupon` ADD `start_date` DATETIME AFTER`description`;

-- Add new column in attribute combination table
ALTER TABLE `attribute_combination` ADD `position` INT NULL AFTER `product_sale_elements_id`;

-- Add newsletter subscription confirmation message

SELECT @max := MAX(`id`) FROM `message`;
SET @max := @max+1;

INSERT INTO `message` (`id`, `name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
(@max, 'newsletter_subscription_confirmation', NULL, NULL, 'newsletter_subscription_confirmation.txt', NULL, 'newsletter_subscription_confirmation.html', NOW(), NOW());

INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
    (@max, 'de_DE', NULL, NULL, NULL, NULL),    (@max, 'en_US', 'Mail sent after a subscription to newsletter', 'Your subscription to {config key=\"store_name\"} newsletter', NULL, NULL),    (@max, 'es_ES', NULL, NULL, NULL, NULL),    (@max, 'fr_FR', NULL, NULL, NULL, NULL);
>>>>>>> Newsletter: mail when subscribing, unsubscription page

SET FOREIGN_KEY_CHECKS = 1;
