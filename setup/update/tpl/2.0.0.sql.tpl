# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

ALTER TABLE `country` ADD INDEX `idx_country_by_default` (`by_default`);
ALTER TABLE `currency` ADD INDEX `idx_currency_by_default` (`by_default`);
ALTER TABLE `lang` ADD INDEX `idx_lang_by_default` (`by_default`);

ALTER TABLE `tax_rule_country` ADD INDEX `idx_tax_rule_country_tax_rule_id_country_id_position` (`tax_rule_id`, `country_id`, `position`);

ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_elements_product_id_promo_is_default` (`product_id`, `promo`, `is_default`);

ALTER TABLE `product_image` ADD INDEX `idx_product_image_product_id_position` (`product_id`, `position`);
ALTER TABLE `category_image` ADD INDEX `idx_category_image_category_id_position` (`category_id`, `position`);
ALTER TABLE `content_image` ADD INDEX `idx_content_image_content_id_position` (`content_id`, `position`);
ALTER TABLE `folder_image` ADD INDEX `idx_folder_image_folder_id_position` (`folder_id`, `position`);
ALTER TABLE `module_image` ADD INDEX `idx_module_image_module_id_position` (`module_id`, `position`);

ALTER TABLE `rewriting_url` ADD INDEX `idx_rewriting_url_view_updated_at` (`view`, `updated_at`);
ALTER TABLE `rewriting_url` ADD INDEX `idx_rewriting_url_view_id_view_view_locale_updated_at` (`view_id`, `view`, `view_locale`, `updated_at`);
ALTER TABLE `rewriting_url` DROP INDEX `idx_view_id`;

ALTER TABLE `feature_product` ADD INDEX `idx_feature_product_product_id_feature_id_position` (`product_id`, `feature_id`, `position`);
ALTER TABLE `feature_template` ADD INDEX `idx_feature_template_template_id_position` (`template_id`, `position`);

ALTER TABLE `currency` ADD INDEX `idx_currency_code` (`code`);

ALTER TABLE `customer` CHANGE `ref` `ref` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;

ALTER TABLE `order` CHANGE `ref` `ref` VARCHAR( 45 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ;

SELECT @max := MAX(`id`) FROM `resource`;
SET @max := @max+1;

INSERT INTO `resource` (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.cache', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
(@max, '{$locale}', {intl l='Configuration / Cache' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

SET @max := @max+1;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max, 'admin.home', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
(@max, '{$locale}', {intl l='Back-office home page' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;