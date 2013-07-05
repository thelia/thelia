INSERT INTO `lang`(`id`,`title`,`code`,`locale`,`url`,`by_default`,`created_at`,`updated_at`)VALUES
(1, 'Français', 'fr', 'fr_FR', '','1', NOW(), NOW()),
(2, 'English', 'en', 'en_EN', '', '0', NOW(), NOW()),
(3, 'Espanol', 'es', 'es_ES', '', '0', NOW(), NOW()),
(4, 'Italiano', 'it', 'it_IT', '','0', NOW(), NOW());

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('session_config.default', '1', 1, 1, NOW(), NOW());

INSERT INTO `module` (`code`, `type`, `activate`, `position`, `created_at`, `updated_at`) VALUES ('test', '1', '1', '1', NOW(), NOW());

INSERT INTO `customer_title`(`id`, `by_default`, `position`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NOW(), NOW()),
(2, 0, 2, NOW(), NOW()),
(3, 0, 3, NOW(), NOW());

INSERT INTO `customer_title_i18n` (`id`, `locale`, `short`, `long`) VALUES
(1, 'en_US', 'Mr', 'Mister'),
(1, 'fr_FR', 'M', 'Monsieur'),
(2, 'en_US', 'Mrs', 'Misses'),
(2, 'fr_FR', 'Mme', 'Madame'),
(3, 'en_US', 'Miss', 'Miss'),
(3, 'fr_FR', 'Mlle', 'Madamemoiselle');

