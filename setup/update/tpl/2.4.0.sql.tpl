SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

# New configuration variables
# ---------------------------

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'cdn.documents-base-url', '', 0, 0, NOW(), NOW()),
(@max_id + 2, 'cdn.assets-base-url', '', 0, 0, NOW(), NOW()),
(@max_id + 3, 'apply_customer_discount_on_promo_prices', '1', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
 (@max_id + 1, '{$locale}', {intl l='The URL of the assets CDN (leave empty is you\'re not using a CDN for assets).' locale=$locale}, NULL, NULL, NULL),
 (@max_id + 2, '{$locale}', {intl l='The URL of the images and documents CDN (leave empty is you\'re not using a CDN for assets).' locale=$locale}, NULL, NULL, NULL),
 (@max_id + 3, '{$locale}', {intl l='Set this variable to 1 to avoid cumulating the customer discount with the sale products.' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}
{/foreach}
;

SET FOREIGN_KEY_CHECKS = 1;
