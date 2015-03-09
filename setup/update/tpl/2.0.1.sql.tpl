# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('front_cart_country_cookie_name','fcccn', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('front_cart_country_cookie_expires','2592000', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('sitemap_ttl','7200', 1, 1, NOW(), NOW());
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('feed_ttl','7200', 1, 1, NOW(), NOW());

ALTER TABLE `module` ADD INDEX `idx_module_activate` (`activate`);

SELECT @max := MAX(`id`) FROM `resource`;
SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.configuration.store', NOW(), NOW()),
(@max+1, 'admin.configuration.variable', NOW(), NOW()),
(@max+2, 'admin.configuration.admin-logs', NOW(), NOW()),
(@max+3, 'admin.configuration.system-logs', NOW(), NOW()),
(@max+4, 'admin.configuration.advanced', NOW(), NOW()),
(@max+5, 'admin.configuration.translations', NOW(), NOW()),
(@max+6, 'admin.tools', NOW(), NOW()),
(@max+7, 'admin.export', NOW(), NOW()),
(@max+8, 'admin.export.customer.newsletter', NOW(), NOW())
;

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
(@max, '{$locale}', {intl l='Store information configuration' locale=$locale}),
(@max+1, '{$locale}', {intl l='Configuration variables' locale=$locale}),
(@max+2, '{$locale}', {intl l='View administration logs' locale=$locale}),
(@max+3, '{$locale}', {intl l='Logging system configuration' locale=$locale}),
(@max+4, '{$locale}', {intl l='Advanced configuration' locale=$locale}),
(@max+5, '{$locale}', {intl l='Translations' locale=$locale}),
(@max+6, '{$locale}', {intl l='Tools panel' locale=$locale}),
(@max+7, '{$locale}', {intl l='Back-office export management' locale=$locale}),
(@max+8, '{$locale}', {intl l='export of newsletter subscribers' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

SELECT @max := MAX(`id`) FROM `lang`;
SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Russian', 'ru', 'ru_RU', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());

SET @max := @max+1;

INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`date_format`,`time_format`,`datetime_format`,`decimal_separator`,`thousands_separator`,`decimals`,`by_default`,`created_at`,`updated_at`)VALUES
(@max, 'Czech', 'cs', 'cs_CZ', '', 'j.n.Y', 'H:i:s', 'j.n.Y H:i:s', ',', ' ', '2', 0,  NOW(), NOW());


SET FOREIGN_KEY_CHECKS = 1;
