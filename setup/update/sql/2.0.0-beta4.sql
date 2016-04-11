# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `module` (`code`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES
( 'Tinymce', 1, 0, 1, 'Tinymce\\Tinymce', NOW(), NOW());

INSERT INTO  `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(LAST_INSERT_ID(), 'de_DE', 'Tinymce Wysiwyg Editor', NULL,  NULL,  NULL),
(LAST_INSERT_ID(), 'en_US', 'tinymce wysiwyg editor', NULL,  NULL,  NULL),
(LAST_INSERT_ID(), 'es_ES', 'editor tinymce wysiwyg', NULL,  NULL,  NULL),
(LAST_INSERT_ID(), 'fr_FR', 'Editeur TinyMCE', NULL,  NULL,  NULL)
;

UPDATE `config` SET `value`='2.0.0-beta4' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='beta4' WHERE `name`='thelia_extra_version';

-- Preferred locale for admin users
ALTER TABLE `admin` ADD `locale` VARCHAR(45) NOT NULL AFTER `password`;
UPDATE `admin` SET `locale`='en_US';

-- Unknown flag image path
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('unknown-flag-path','assets/img/flags/unknown.png', 1, 1, NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;