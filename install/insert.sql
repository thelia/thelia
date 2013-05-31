INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`by_default`,`created_at`,`updated_at`)VALUES
(1, 'Français', 'fr', 'fr_FR', '','1', NOW(), NOW()),
(2, 'English', 'en', 'en_EN', '', '0', NOW(), NOW()),
(3, 'Espanol', 'es', 'es_ES', '', '0', NOW(), NOW()),
(4, 'Italiano', 'it', 'it_IT', '','0', NOW(), NOW());

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('session_config.default', '1', 1, 1, NOW(), NOW());

INSERT INTO `thelia`.`module` (`code`, `type`, `activate`, `position`, `created_at`, `updated_at`) VALUES ('test', '1', '1', '1', NOW(), NOW());
