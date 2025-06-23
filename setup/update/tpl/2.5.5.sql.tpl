SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.5.5' WHERE `name`='thelia_version';

UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'order_rounding_mode', '1', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
{foreach $locales as $locale}
    (@max_id + 1, '{$locale}', {intl l='Rounding mode for calculating the order total (1: sums of roundings, 2: rounding of sums).' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},
    {/if}
{/foreach}

SET FOREIGN_KEY_CHECKS = 1;
