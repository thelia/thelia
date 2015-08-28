SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.2.0-beta3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta3' WHERE `name`='thelia_extra_version';

-- fix hook --
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'tab-seo.update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'order-edit.order-product-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'order-edit.order-product-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'administrators.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'administrators.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'advanced-configuration', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 7, 'advanced-configuration.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='Tab SEO - update form' locale=$locale}, '', ''),
    (@max_id + 2, '{$locale}', {intl l='Order edit - order product table header' locale=$locale}, '', ''),
    (@max_id + 3, '{$locale}', {intl l='Order edit - order product table row' locale=$locale}, '', ''),
    (@max_id + 4, '{$locale}', {intl l='Administrators - header' locale=$locale}, '', ''),
    (@max_id + 5, '{$locale}', {intl l='Administrators - row' locale=$locale}, '', ''),
    (@max_id + 6, '{$locale}', {intl l='Advanced Configuration' locale=$locale}, '', ''),
    (@max_id + 7, '{$locale}', {intl l='Advanced Configuration - Javascript' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'main.topbar-top' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'main.topbar-bottom' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'product.combinations-row' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.top' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.table-header' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.table-row' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brands.bottom' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.create-form' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.delete-form' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.js' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.tab-content' AND `type` = 2;
UPDATE `hook` SET  `block` =  '0', `updated_at` =  NOW() WHERE `code` = 'brand.edit-js' AND `type` = 2;

-- add index --
ALTER TABLE `rewriting_url` ADD INDEX `idx_rewriting_url` (`view_locale`, `view`, `view_id`, `redirected`);

SET FOREIGN_KEY_CHECKS = 1;
