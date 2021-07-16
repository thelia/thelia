SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.5.0' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

REPLACE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`)
VALUES ('enable_seo_transliterator', 0, 0, 0, NOW(), NOW());

REPLACE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`)
VALUES ('enable_html_ending_in_urls', 1, 0, 0, NOW(), NOW());

REPLACE INTO `module` (`code`, `version`,  `type`, `activate`, `position`, `full_namespace`, `hidden`, `mandatory`, `created_at`, `updated_at`) VALUES
('WebProfiler', '2.5.0', 1, 1, 19, 'WebProfiler\\WebProfiler', 1, 0, NOW(), NOW())
;

SET FOREIGN_KEY_CHECKS = 1;
