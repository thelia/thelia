SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.3' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

-- Add the session_config.lifetime configuration variable
INSERT IGNORE INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'form.secret', 'ThisIsNotASecret', 0, 0, NOW(), NOW());


SET FOREIGN_KEY_CHECKS = 1;