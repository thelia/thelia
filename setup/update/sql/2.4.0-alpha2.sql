SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.4.0-alpha2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='2' WHERE `name`='thelia_major_version';
UPDATE `config` SET `value`='4' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha2' WHERE `name`='thelia_extra_version';

ALTER TABLE `feature_product` ADD `is_free_text` TINYINT(1) NOT NULL DEFAULT '0' AFTER `free_text_value`;
ALTER TABLE `feature_product` MODIFY COLUMN `free_text_value` TEXT COMMENT 'deprecated';
UPDATE `feature_product` SET `is_free_text`=IF(`free_text_value` IS NULL, 0, 1);

DELIMITER $$
CREATE TRIGGER `remove_free_text_feature_av` AFTER DELETE ON `feature_product`
 FOR EACH ROW IF OLD.`is_free_text` = 1 THEN
  DELETE FROM `feature_av` WHERE `id` = OLD.`feature_av_id`;
END IF
$$
DELIMITER ;

-- Missing timestamps in ignored_module_hook

ALTER TABLE `ignored_module_hook` ADD `created_at` DATETIME NOT NULL;
ALTER TABLE `ignored_module_hook` ADD `updated_at` DATETIME NOT NULL;

-- Add new configuration variables

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `config`;

INSERT INTO `config` (`id`, `name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'cdn.documents-base-url', '', 0, 0, NOW(), NOW()),
(@max_id + 2, 'cdn.assets-base-url', '', 0, 0, NOW(), NOW()),
(@max_id + 3, 'apply_customer_discount_on_promo_prices', '1', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
 (@max_id + 1, 'de_DE', NULL, NULL, NULL, NULL),
 (@max_id + 2, 'de_DE', NULL, NULL, NULL, NULL),
 (@max_id + 3, 'de_DE', NULL, NULL, NULL, NULL), (@max_id + 1, 'en_US', 'The URL of the assets CDN (leave empty is you\'re not using a CDN for assets).', NULL, NULL, NULL),
 (@max_id + 2, 'en_US', 'The URL of the images and documents CDN (leave empty is you\'re not using a CDN for assets).', NULL, NULL, NULL),
 (@max_id + 3, 'en_US', 'Apply customer discount percentage to sales and products in promotion', NULL, NULL, NULL), (@max_id + 1, 'es_ES', NULL, NULL, NULL, NULL),
 (@max_id + 2, 'es_ES', NULL, NULL, NULL, NULL),
 (@max_id + 3, 'es_ES', NULL, NULL, NULL, NULL), (@max_id + 1, 'fr_FR', 'URL du CDN des assets (CSS, JS, images statiques, ...). Laisser cette valeur vide si vous n\'utilisez pas de CDN pour vos assets.', NULL, NULL, NULL),
 (@max_id + 2, 'fr_FR', 'URL du CDN des images et des doucments des produits, contenus, dossiers, catégories, etc.. Laisser cette valeur vide si vous n\'utilisez pas de CDN pour vos images et documents', NULL, NULL, NULL),
 (@max_id + 3, 'fr_FR', 'Appliquer le pourcentage de remise client aux produits en promotion (Oui = 1, non = 0)', NULL, NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;
