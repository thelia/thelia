SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.3.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';

-- Update order.invoice_date column to datetime
ALTER TABLE `order` MODIFY COLUMN invoice_date DATETIME
ALTER TABLE `order_version` MODIFY COLUMN invoice_date DATETIME

-- Add new column in module_hook table
ALTER TABLE `module_hook` ADD `templates` TEXT AFTER`position`;

-- Add new columns in currency table
ALTER TABLE `currency` ADD  `format` CHAR( 10 ) NOT NULL AFTER  `symbol`;
ALTER TABLE `currency` ADD  `visible` TINYINT( 1 ) NOT NULL DEFAULT '0' AFTER  `rate`;

-- Update currencies
UPDATE `currency` SET `visible` = 1 WHERE 1;
UPDATE `currency` SET `format` = '%n %s' WHERE `code` NOT IN ('USD', 'GBP');
UPDATE `currency` SET `format` = '%s%n' WHERE `code` IN ('USD', 'GBP');

-- Additional hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id+1,  'brand.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+2,  'brand.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+3,  'category.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+4,  'category.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+5,  'content.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+6,  'content.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+7,  'folder.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+8,  'folder.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+9,  'product.modification.form-right.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id+10, 'product.modification.form-right.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
    (@max_id+1,  'de_DE', NULL, NULL, NULL),
    (@max_id+2,  'de_DE', NULL, NULL, NULL),
    (@max_id+3,  'de_DE', NULL, NULL, NULL),
    (@max_id+4,  'de_DE', NULL, NULL, NULL),
    (@max_id+5,  'de_DE', NULL, NULL, NULL),
    (@max_id+6,  'de_DE', NULL, NULL, NULL),
    (@max_id+7,  'de_DE', NULL, NULL, NULL),
    (@max_id+8,  'de_DE', NULL, NULL, NULL),
    (@max_id+9,  'de_DE', NULL, NULL, NULL),
    (@max_id+10, 'de_DE', NULL, NULL, NULL),
    (@max_id+1,  'en_US', 'Brand edit - right column top', NULL, NULL),
    (@max_id+2,  'en_US', 'Brand edit - right column bottom', NULL, NULL),
    (@max_id+3,  'en_US', 'Category edit - right column top', NULL, NULL),
    (@max_id+4,  'en_US', 'Category edit - right column bottom', NULL, NULL),
    (@max_id+5,  'en_US', 'Content edit - right column top', NULL, NULL),
    (@max_id+6,  'en_US', 'Content edit - right column bottom', NULL, NULL),
    (@max_id+7,  'en_US', 'Folder edit - right column top', NULL, NULL),
    (@max_id+8,  'en_US', 'Folder edit - right column bottom', NULL, NULL),
    (@max_id+9,  'en_US', 'Product edit - right column top', NULL, NULL),
    (@max_id+10, 'en_US', 'Product edit - right column bottom', NULL, NULL),
    (@max_id+1,  'es_ES', NULL, NULL, NULL),
    (@max_id+2,  'es_ES', NULL, NULL, NULL),
    (@max_id+3,  'es_ES', NULL, NULL, NULL),
    (@max_id+4,  'es_ES', NULL, NULL, NULL),
    (@max_id+5,  'es_ES', NULL, NULL, NULL),
    (@max_id+6,  'es_ES', NULL, NULL, NULL),
    (@max_id+7,  'es_ES', NULL, NULL, NULL),
    (@max_id+8,  'es_ES', NULL, NULL, NULL),
    (@max_id+9,  'es_ES', NULL, NULL, NULL),
    (@max_id+10, 'es_ES', NULL, NULL, NULL),
    (@max_id+1,  'fr_FR', 'Édition d\'une marque - en haut de la colonne de droite', NULL, NULL),
    (@max_id+2,  'fr_FR', 'Édition d\'une marque - en bas de la colonne de droite', NULL, NULL),
    (@max_id+3,  'fr_FR', 'Édition d\'une catégorie - en haut de la colonne de droite', NULL, NULL),
    (@max_id+4,  'fr_FR', 'Édition d\'une catégorie - en bas de la colonne de droite', NULL, NULL),
    (@max_id+5,  'fr_FR', 'Édition d\'un contenu - en haut de la colonne de droite', NULL, NULL),
    (@max_id+6,  'fr_FR', 'Édition d\'un contenu  - en bas de la colonne de droite', NULL, NULL),
    (@max_id+7,  'fr_FR', 'Édition d\'un dossier - en haut de la colonne de droite', NULL, NULL),
    (@max_id+8,  'fr_FR', 'Édition d\'un dossier - en bas de la colonne de droite', NULL, NULL),
    (@max_id+9,  'fr_FR', 'Édition d\'un produit - en haut de la colonne de droite', NULL, NULL),
    (@max_id+10, 'fr_FR', 'Édition d\'un produit - en bas de la colonne de droite', NULL, NULL)
;

SET FOREIGN_KEY_CHECKS = 1;