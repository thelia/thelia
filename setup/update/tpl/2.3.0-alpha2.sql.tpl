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
{foreach $locales as $locale}
    (@max, '{$locale}', {intl l='Mail sent to an administrator who requested a new password' locale=$locale}, {intl l='New password request on %store' store={config key="store_name"} locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

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
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='The minimum length required for an administrator password' locale=$locale}, NULL, NULL, NULL),
    (@max_id + 2, '{$locale}', {intl l='Allow an administrator to recreate a lost password (1 = yes, 0 = no)' locale=$locale}, NULL, NULL, NULL),
    (@max_id + 3, '{$locale}', {intl l='Send a confirmation email to newsletter subscribers (1 = yes, 0 = no)' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

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
{foreach $locales as $locale}
    (@max_id+1, '{$locale}', {intl l='Sale - at the top' locale=$locale}, NULL, NULL),
    (@max_id+2, '{$locale}', {intl l='Sale - at the bottom' locale=$locale}, NULL, NULL),
    (@max_id+3, '{$locale}', {intl l='Sale - at the top of the main area' locale=$locale}, NULL, NULL),
    (@max_id+4, '{$locale}', {intl l='Sale - at the bottom of the main area' locale=$locale}, NULL, NULL),
    (@max_id+5, '{$locale}', {intl l='Sale - before the main content area' locale=$locale}, NULL, NULL),
    (@max_id+6, '{$locale}', {intl l='Sale - after the main content area' locale=$locale}, NULL, NULL),
    (@max_id+7, '{$locale}', {intl l='Sale - CSS stylesheet' locale=$locale}, NULL, NULL),
    (@max_id+8, '{$locale}', {intl l='Sale - after javascript include' locale=$locale}, NULL, NULL),
    (@max_id+9, '{$locale}', {intl l='Sale - javascript initialization' locale=$locale}, NULL, NULL),
    (@max_id+10, '{$locale}', {intl l='Order details - after invoice address' locale=$locale}, NULL, NULL),
    (@max_id+11, '{$locale}', {intl l='Order details - after delivery address' locale=$locale}, NULL, NULL),
    (@max_id+12, '{$locale}', {intl l='Newsletter unsubscribe page - at the top' locale=$locale}, NULL, NULL),
    (@max_id+13, '{$locale}', {intl l='Newsletter unsubscribe page - at the bottom' locale=$locale}, NULL, NULL),
    (@max_id+14, '{$locale}', {intl l='Newsletter unsubscribe page - CSS stylesheet' locale=$locale}, NULL, NULL),
    (@max_id+15, '{$locale}', {intl l='Newsletter unsubscribe page - after javascript include' locale=$locale}, NULL, NULL),
    (@max_id+16, '{$locale}', {intl l='Newsletter unsubscribe page - after javascript initialisation' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

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
{foreach $locales as $locale}
    (@max, '{$locale}', {intl l='Mail sent after a subscription to newsletter' locale=$locale}, {intl l='Your subscription to %store newsletter' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- add new config variables number_default_results_per_page
SELECT @max := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (@max+1, 'number_default_results_per_page.product_list', '20', '0', '0', NOW(), NOW());
INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (@max+2, 'number_default_results_per_page.order_list', '20', '0', '0', NOW(), NOW());
INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES (@max+3, 'number_default_results_per_page.customer_list', '20', '0', '0', NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
    (@max+1, '{$locale}', {intl l='Number by default of results per page for product list' locale=$locale}, NUll, NULL, NULL),
    (@max+2, '{$locale}', {intl l='Number by default of results per page for order list' locale=$locale}, NUll, NULL, NULL),
    (@max+3, '{$locale}', {intl l='Number by default of results per page for customer list' locale=$locale}, NUll, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Add module HookAdminHome
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `module`;
SELECT @max_classic_position := IFNULL(MAX(`position`),0) FROM `module` WHERE `type`=1;

INSERT INTO `module` (`id`, `code`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'HookAdminHome', 1, 1, @max_classic_position+1, 'HookAdminHome\\HookAdminHome', NOW(), NOW())
;

INSERT INTO  `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
(@max_id+1, '{$locale}', {intl l='Displays the default blocks on the homepage of the administration' locale=$locale}, NULL,  NULL,  NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Update customer lang FK
ALTER TABLE `customer` CHANGE `lang` `lang_id` INT(11)  NULL  DEFAULT NULL;
ALTER TABLE `customer` ADD INDEX `idx_email` (`email`);
ALTER TABLE `customer` ADD INDEX `idx_customer_lang_id` (`lang_id`);
ALTER TABLE `customer` ADD CONSTRAINT `fk_customer_lang_id` FOREIGN KEY (`lang_id`) REFERENCES `lang` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT;

OPTIMIZE TABLE `customer`;


-- Update customer version
ALTER TABLE `customer_version` CHANGE `lang` `lang_id` INT(11)  NULL  DEFAULT NULL;


-- Update newletter index
ALTER TABLE `newsletter` ADD INDEX `idx_unsubscribed` (`unsubscribed`);

OPTIMIZE TABLE `newsletter`;

SET FOREIGN_KEY_CHECKS = 1;
