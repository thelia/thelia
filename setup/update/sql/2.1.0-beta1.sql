SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

CREATE TABLE `api`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `label` VARCHAR(255),
    `api_key` VARCHAR(100),
    `profile_id` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_api_profile_id` (`profile_id`),
    CONSTRAINT `fk_api_profile_id`
        FOREIGN KEY (`profile_id`)
        REFERENCES `profile` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

-- Add the session_config.lifetime configuration variable
INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'session_config.lifetime', '0', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max_id + 1, 'en_US', 'Life time of the session cookie in the customer browser, in seconds', NULL, NULL, NULL),
(@max_id + 1, 'fr_FR', 'Durée de vie du cookie de la session dans le navigateur du client, en secondes', NULL, NULL, NULL)
;

-- Hide the session_config.handlers configuration variable
UPDATE `config` SET `secured`=1, `hidden`=1 where `name`='session_config.handlers';

-- Hooks

-- front hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
  VALUES
(@max_id + 1, 'category.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'category.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'content.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'content.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'folder.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'folder.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 7, 'brand.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 8, 'brand.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 9, 'brand.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 10, 'brand.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 11, 'brand.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 12, 'brand.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 13, 'brand.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 14, 'brand.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 15, 'brand.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 16, 'brand.sidebar-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 17, 'brand.sidebar-body', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 18, 'brand.sidebar-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'fr_FR', 'Page catégorie - au dessus de la zone de contenu principale', '', ''),
(@max_id + 1, 'en_US', 'Category page - before the main content area', '', ''),
(@max_id + 2, 'fr_FR', 'Page catégorie - en dessous de la zone de contenu principale', '', ''),
(@max_id + 2, 'en_US', 'Category page - after the main content area', '', ''),
(@max_id + 3, 'fr_FR', 'Page de contenu - au dessus de la zone de contenu principale', '', ''),
(@max_id + 3, 'en_US', 'Content page - before the main content area', '', ''),
(@max_id + 4, 'fr_FR', 'Page de contenu - en dessous de la zone de contenu principale', '', ''),
(@max_id + 4, 'en_US', 'Content page - after the main content area', '', ''),
(@max_id + 5, 'fr_FR', 'Page dossier - au dessus de la zone de contenu principale', '', ''),
(@max_id + 5, 'en_US', 'Folder page - before the main content area', '', ''),
(@max_id + 6, 'fr_FR', 'Page dossier - en dessous de la zone de contenu principale', '', ''),
(@max_id + 6, 'en_US', 'Folder page - after the main content area', '', ''),
(@max_id + 7, 'fr_FR', 'Page des marques - en haut', '', ''),
(@max_id + 7, 'en_US', 'Brands page - at the top', '', ''),
(@max_id + 8, 'fr_FR', 'Page des marques - en bas', '', ''),
(@max_id + 8, 'en_US', 'Brands page - at the bottom', '', ''),
(@max_id + 9, 'fr_FR', 'Page des marques - en haut de la zone principal', '', ''),
(@max_id + 9, 'en_US', 'Brands page - at the top of the main area', '', ''),
(@max_id + 10, 'fr_FR', 'Page des marques - en bas de la zone principal', '', ''),
(@max_id + 10, 'en_US', 'Brands page - at the bottom of the main area', '', ''),
(@max_id + 11, 'fr_FR', 'Page des marques - au dessus de la zone de contenu principale', '', ''),
(@max_id + 11, 'en_US', 'Brands page - before the main content area', '', ''),
(@max_id + 12, 'fr_FR', 'Page des marques - en dessous de la zone de contenu principale', '', ''),
(@max_id + 12, 'en_US', 'Brands page - after the main content area', '', ''),
(@max_id + 13, 'fr_FR', 'Page des marques - feuille de style CSS', '', ''),
(@max_id + 13, 'en_US', 'Brands page - CSS stylesheet', '', ''),
(@max_id + 14, 'fr_FR', 'Page des marques - après l\'inclusion des javascript', '', ''),
(@max_id + 14, 'en_US', 'Brands page - after javascript include', '', ''),
(@max_id + 15, 'fr_FR', 'Page des marques - initialisation du javascript', '', ''),
(@max_id + 15, 'en_US', 'Brands page - javascript initialization', '', ''),
(@max_id + 16, 'fr_FR', 'Page des marques - en haut de la sidebar', '', ''),
(@max_id + 16, 'en_US', 'Brands page - at the top of the sidebar', '', ''),
(@max_id + 17, 'fr_FR', 'Page des marques - le corps de la sidebar', '', ''),
(@max_id + 17, 'en_US', 'Brands page - the body of the sidebar', '', ''),
(@max_id + 18, 'fr_FR', 'Page des marques - en bas de la sidebar', '', ''),
(@max_id + 18, 'en_US', 'Brands page - at the bottom of the sidebar', '', '')
;

-- admin hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'category.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 2, 'product.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'folder.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 4, 'content.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 5, 'brand.tab', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 6, 'order-edit.bill-delivery-address', 2, 1, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'fr_FR', 'Catégorie - Onglet', '', ''),
(@max_id + 1, 'en_US', 'Category - Tab', '', ''),
(@max_id + 2, 'fr_FR', 'Produit - Onglet', '', ''),
(@max_id + 2, 'en_US', 'Product - Tab', '', ''),
(@max_id + 3, 'fr_FR', 'Dossier - Onglet', '', ''),
(@max_id + 3, 'en_US', 'Folder - Tab', '', ''),
(@max_id + 4, 'fr_FR', 'Contenu - Onglet', '', ''),
(@max_id + 4, 'en_US', 'Content - Tab', '', ''),
(@max_id + 5, 'fr_FR', 'Marque - Onglet', '', ''),
(@max_id + 5, 'en_US', 'Brand - Tab', '', ''),
(@max_id + 6, 'fr_FR', 'Modification commande - adresse de livraison', '', ''),
(@max_id + 6, 'en_US', 'Order edit - delivery address', '', '')
;


SET FOREIGN_KEY_CHECKS = 1;

