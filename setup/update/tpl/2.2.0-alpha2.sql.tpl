SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

-- Add cellphone column in order_address table
ALTER TABLE `order_address` ADD COLUMN `cellphone` VARCHAR (20) AFTER  `phone`;

-- new hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'order-edit.customer-information-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'order-edit.payment-module-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'order-edit.delivery-module-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'invoice.after-payment-module', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'invoice.after-delivery-module', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'delivery.after-delivery-module', 3, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
(@max_id + 1, '{$locale}', {intl l='Order - customer information bottom' locale=$locale}, '', ''),
(@max_id + 2, '{$locale}', {intl l='Order - payment module bottom' locale=$locale}, '', ''),
(@max_id + 3, '{$locale}', {intl l='Order - delivery module bottom' locale=$locale}, '', ''),
(@max_id + 4, '{$locale}', {intl l='Invoice - After payment module' locale=$locale}, NULL, NULL),
(@max_id + 5, '{$locale}', {intl l='Invoice - After delivery module' locale=$locale}, NULL, NULL),
(@max_id + 6, '{$locale}', {intl l='Delivery - After delivery module' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;
