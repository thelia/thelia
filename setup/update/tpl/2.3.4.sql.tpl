SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.4' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

-- Additional hooks on order-invoice page

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'order-invoice.coupon-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2, 'order-invoice.payment-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3, 'delivery.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4, 'invoice.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5, 'email-html.order-confirmation.product-list', 4, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6, 'email-txt.order-confirmation.product-list', 4, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7, 'account-order.product-list', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
    (@max_id+1, '{$locale}', {intl l='Order invoice page - bottom of coupon form' locale=$locale}, NULL, NULL),
    (@max_id+2, '{$locale}', {intl l='Order invoice page - bottom of payment form' locale=$locale}, NULL, NULL),
    (@max_id+3, '{$locale}', {intl l='Delivery - after product information' locale=$locale}, NULL, NULL),
    (@max_id+4, '{$locale}', {intl l='Invoice - after product information' locale=$locale}, NULL, NULL),
    (@max_id+5, '{$locale}', {intl l='Email html - order notification - after product information' locale=$locale}, NULL, NULL),
    (@max_id+6, '{$locale}', {intl l='Email txt - order notification - after product information' locale=$locale}, NULL, NULL),
    (@max_id+7, '{$locale}', {intl l='Account order - after product information' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Customer confirmation

ALTER TABLE `customer` ADD `enable` TINYINT DEFAULT 0 AFTER `remember_me_serial`;
ALTER TABLE `customer` ADD `confirmation_token` VARCHAR(255) AFTER `enable`;
ALTER TABLE `customer_version` ADD `enable` TINYINT DEFAULT 0 AFTER `remember_me_serial`;
ALTER TABLE `customer_version` ADD `confirmation_token` VARCHAR(255) AFTER `enable`;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'customer_email_confirmation', '0', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='Customer account creation should be confirmed by email (1: yes, 0: no)' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

SELECT @max_id :=IFNULL(MAX(`id`),0) FROM `message`;
INSERT INTO `message` (`id`, `name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'customer_confirmation', NULL, NULL, 'customer_confirmation.txt', NULL, 'customer_confirmation.html', NOW(), NOW());

INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
{foreach $locales as $locale}
    (@max_id+1, '{$locale}', {intl l='Mail sent to the customer to confirm its account' locale=$locale}, {intl l='Confirm your %store account' store={config key="store_name"} locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Order status improvement

ALTER TABLE `order_status` ADD `color` CHAR(7) NOT NULL AFTER `code`;
ALTER TABLE `order_status` ADD `position` INT(11) NOT NULL AFTER `color`;
ALTER TABLE `order_status` ADD `protected_status` TINYINT(1) NOT NULL DEFAULT '1' AFTER `position`;

UPDATE `order_status` SET `position` = `id` WHERE 1;
UPDATE `order_status` SET `color` = '#f0ad4e' WHERE `code` = 'not_paid';
UPDATE `order_status` SET `color` = '#5cb85c' WHERE `code` = 'paid';
UPDATE `order_status` SET `color` = '#f39922' WHERE `code` = 'processing';
UPDATE `order_status` SET `color` = '#5bc0de' WHERE `code` = 'sent';
UPDATE `order_status` SET `color` = '#d9534f' WHERE `code` = 'canceled';
UPDATE `order_status` SET `color` = '#986dff' WHERE `code` = 'refunded';
UPDATE `order_status` SET `color` = '#777777' WHERE `code` NOT IN ('not_paid', 'paid', 'processing', 'sent', 'canceled', 'refunded');
UPDATE `order_status` SET `protected_status` = 1 WHERE `code` IN ('not_paid', 'paid', 'processing', 'sent', 'canceled', 'refunded');

SELECT @max_id := MAX(`id`) FROM `resource`;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES (@max_id+1, 'admin.configuration.order-status', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
    (@max_id+1, '{$locale}', {intl l='Configuration order status' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1,  'configuration.order-path.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2,  'configuration.order-path.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3,  'order-status.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4,  'order-status.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5,  'order-status.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6,  'order-status.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7,  'order-status.form.creation', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+8,  'order-status.form.modification', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+9,  'order-status.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
    (@max_id+1,  '{$locale}', {intl l='Configuration - Order path - top' locale=$locale}, NULL, NULL),
    (@max_id+2,  '{$locale}', {intl l='Configuration - Order path - bottom' locale=$locale}, NULL, NULL),
    (@max_id+3,  '{$locale}', {intl l='Order status - top' locale=$locale}, NULL, NULL),
    (@max_id+4,  '{$locale}', {intl l='Order status - bottom' locale=$locale}, NULL, NULL),
    (@max_id+5,  '{$locale}', {intl l='Order status - table header' locale=$locale}, NULL, NULL),
    (@max_id+6,  '{$locale}', {intl l='Order status - table row' locale=$locale}, NULL, NULL),
    (@max_id+7,  '{$locale}', {intl l='Order status - form creation' locale=$locale}, NULL, NULL),
    (@max_id+8,  '{$locale}', {intl l='Order status - form modification' locale=$locale}, NULL, NULL),
    (@max_id+9,  '{$locale}', {intl l='Order status - JavaScript' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Additional usage_canceled column in order_coupon table
ALTER TABLE `order_coupon` ADD `usage_canceled` TINYINT(1) DEFAULT '0' AFTER `per_customer_usage_count`;

-- add new config variables number_default_results_per_page
SELECT @max := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max+1, 'number_default_results_per_page.coupon_list', '20', '0', '0', NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
    (@max+1, '{$locale}', {intl l='Default number of coupons per page on coupon list' locale=$locale}, NUll, NULL, NULL){if ! $locale@last},{/if}
{/foreach}
;

ALTER TABLE `module` ADD `mandatory` TINYINT NOT NULL DEFAULT '0' AFTER `full_namespace`, ADD `hidden` TINYINT NOT NULL DEFAULT '0' AFTER `mandatory`;
UPDATE `module` SET `mandatory` = 0, `hidden` = 0;
UPDATE `module` SET `hidden` = 1 WHERE `code` = 'Front';
UPDATE `module` SET `mandatory` = 1, `hidden` = 1 WHERE `code` = 'TheliaSmarty';

SET FOREIGN_KEY_CHECKS = 1;
