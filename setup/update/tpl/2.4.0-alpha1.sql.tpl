SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Additional hooks on order-invoice page

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'order-invoice.coupon-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2, 'order-invoice.payment-form', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3, 'delivery.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4, 'invoice.product-list', 3, 0, 0, 1, 1, 1, NOW(), NOW(),
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
    (@max_id+6, '{$locale}', {intl l='Email txt - order notification - after product information' locale=$locale}, NULL, NULL)
    (@max_id+7, '{$locale}', {intl l='Account order - after product information' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

-- Customer confirmation

ALTER TABLE `customer` ADD `enable` TINYINT DEFAULT 0 AFTER `remember_me_serial`;
ALTER TABLE `customer` ADD `confirmation_token` VARCHAR(255) AFTER `enable`;

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'customer_email_confirmation', '0', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='Enable (1) or disable (0) customer email confirmation' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

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

SET FOREIGN_KEY_CHECKS = 1;
