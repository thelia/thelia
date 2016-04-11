# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.0.3-beta' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='3' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta' WHERE `name`='thelia_extra_version';

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`,  `created_at`, `updated_at`) VALUES
('store_description', '', 0, 0, NOW(), NOW());

# default available stock

SELECT @max := MAX(`id`) FROM `config`;

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`,  `created_at`, `updated_at`) VALUES
('default_available_stock', '100', 0, 0, NOW(), NOW()),
('information_folder_id', '', 0, 0, NOW(), NOW()),
('terms_conditions_content_id', '', 0, 0, NOW(), NOW());

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max + 1, 'de_DE', 'Standart verfügbaren Bestand wenn check-available-stock gleich 0.', NULL, NULL, NULL),
(@max + 2, 'de_DE', 'Die ID des Ordners mit Ihren Informations-Seiten: AGB, Impressum, ...', NULL, NULL, NULL),
(@max + 3, 'de_DE', 'Ihr \'Allgemeine Geschäftsbedingungen \' ID.', NULL, NULL, NULL),
(@max + 1, 'en_US', 'Default available stock when check-available-stock is set to 0.', NULL, NULL, NULL),
(@max + 2, 'en_US', 'The ID of the folder containing your information pages : terms, imprint, ...', NULL, NULL, NULL),
(@max + 3, 'en_US', 'The ID of the \'Terms & Conditions\' content.', NULL, NULL, NULL),
(@max + 1, 'es_ES', 'Cuando check-available-stock es 0 stock disponible por defecto.', NULL, NULL, NULL),
(@max + 2, 'es_ES', 'El ID de la carpeta que contiene sus páginas de información: términos, impresión,...', NULL, NULL, NULL),
(@max + 3, 'es_ES', 'El ID de los contenidos de \'Términos y condiciones\'.', NULL, NULL, NULL),
(@max + 1, 'fr_FR', 'Stock disponible par défaut quand check-available-stock est à 0.', NULL, NULL, NULL),
(@max + 2, 'fr_FR', 'L\'ID du dossier contenant vos pages d\'informations : CGV, mentions légales, ...', NULL, NULL, NULL),
(@max + 3, 'fr_FR', 'L\'ID du contenu de vos \'CGV\'.', NULL, NULL, NULL)
;

# Add new column to order (version, version_created_at, version_created_by)

ALTER TABLE `order` ADD `version` INT DEFAULT 0 AFTER `updated_at`;
ALTER TABLE `order` ADD `version_created_at` DATE AFTER `version`;
ALTER TABLE `order` ADD `version_created_by` VARCHAR(100) AFTER `version_created_at`;

ALTER TABLE `order_address`
  ADD CONSTRAINT `fk_order_address_customer_title_id`
  FOREIGN KEY (`customer_title_id`)
    REFERENCES `customer_title` (`id`)
      ON UPDATE RESTRICT
      ON DELETE RESTRICT
;
ALTER TABLE `order_address`
  ADD CONSTRAINT `fk_order_address_country_id`
  FOREIGN KEY (`country_id`)
    REFERENCES `country` (`id`)
      ON UPDATE RESTRICT
      ON DELETE RESTRICT
;

DROP TABLE IF EXISTS `order_version`;

CREATE TABLE `order_version`
(
    `id` INTEGER NOT NULL,
    `ref` VARCHAR(45),
    `customer_id` INTEGER NOT NULL,
    `invoice_order_address_id` INTEGER NOT NULL,
    `delivery_order_address_id` INTEGER NOT NULL,
    `invoice_date` DATE,
    `currency_id` INTEGER NOT NULL,
    `currency_rate` FLOAT NOT NULL,
    `transaction_ref` VARCHAR(100) COMMENT 'transaction reference - usually use to identify a transaction with banking modules',
    `delivery_ref` VARCHAR(100) COMMENT 'delivery reference - usually use to identify a delivery progress on a distant delivery tracker website',
    `invoice_ref` VARCHAR(100) COMMENT 'the invoice reference',
    `discount` FLOAT,
    `postage` FLOAT NOT NULL,
    `payment_module_id` INTEGER NOT NULL,
    `delivery_module_id` INTEGER NOT NULL,
    `status_id` INTEGER NOT NULL,
    `lang_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `order_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `order` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';
UPDATE `order` SET
  `version` = 1,
  `version_created_at` = NOW(),
  `version_created_by` = 'Thelia'
WHERE `version` = 0;

INSERT INTO `order_version`(
  `id`,
  `ref`,
  `customer_id`,
  `invoice_order_address_id`,
  `delivery_order_address_id`,
  `invoice_date`,
  `currency_id`,
  `currency_rate`,
  `transaction_ref`,
  `delivery_ref`,
  `invoice_ref`,
  `discount`,
  `postage`,
  `payment_module_id`,
  `delivery_module_id`,
  `status_id`,
  `lang_id`,
  `created_at`,
  `updated_at`,
  `version`,
  `version_created_at`,
  `version_created_by`)
  SELECT
    `id`,
    `ref`,
    `customer_id`,
    `invoice_order_address_id`,
    `delivery_order_address_id`,
    `invoice_date`,
    `currency_id`,
    `currency_rate`,
    `transaction_ref`,
    `delivery_ref`,
    `invoice_ref`,
    `discount`,
    `postage`,
    `payment_module_id`,
    `delivery_module_id`,
    `status_id`,
    `lang_id`,
    `created_at`,
    `updated_at`,
    `version`,
    `version_created_at`,
    `version_created_by`
  FROM `order`;


# Add missing columns to coupon (version_created_at, version_created_by)
ALTER TABLE `coupon` ADD `version_created_at` DATE AFTER `version`;
ALTER TABLE `coupon` ADD `version_created_by` VARCHAR(100) AFTER `version_created_at`;

ALTER TABLE `coupon_version` ADD `version_created_at` DATE AFTER `version`;
ALTER TABLE `coupon_version` ADD `version_created_by` VARCHAR(100) AFTER `version_created_at`;

# Add coupon_customer_count table
# -------------------------------

ALTER TABLE `coupon_customer_count`
    DROP FOREIGN KEY `fk_coupon_customer_customer_id`;

ALTER TABLE `coupon_customer_count`
    DROP FOREIGN KEY `fk_coupon_customer_coupon_id`;

ALTER TABLE `coupon_customer_count`
  ADD CONSTRAINT `fk_coupon_customer_customer_id`
  FOREIGN KEY (`customer_id`)
  REFERENCES `customer` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE;

ALTER TABLE `coupon_customer_count`
  ADD CONSTRAINT `fk_coupon_customer_coupon_id`
  FOREIGN KEY (`coupon_id`)
  REFERENCES `coupon` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE;

# ---------------------------------------------------------------------
# Add Brand tables and related resources
# ---------------------------------------------------------------------

# Add the "brand" resource
INSERT INTO resource (`code`, `created_at`, `updated_at`) VALUES ('admin.brand', NOW(), NOW());

-- ---------------------------------------------------------------------
-- brand
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand`;

CREATE TABLE `brand`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `visible` TINYINT,
  `position` INTEGER,
  `logo_image_id` INTEGER,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `fk_brand_brand_image_idx` (`logo_image_id`),
  CONSTRAINT `fk_logo_image_id_brand_image`
  FOREIGN KEY (`logo_image_id`)
  REFERENCES `brand_image` (`id`)
    ON UPDATE RESTRICT
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_document`;

CREATE TABLE `brand_document`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `brand_id` INTEGER NOT NULL,
  `file` VARCHAR(255) NOT NULL,
  `position` INTEGER,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `idx_brand_document_brand_id` (`brand_id`),
  CONSTRAINT `fk_brand_document_brand_id`
  FOREIGN KEY (`brand_id`)
  REFERENCES `brand` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_image`;

CREATE TABLE `brand_image`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `brand_id` INTEGER NOT NULL,
  `file` VARCHAR(255) NOT NULL,
  `position` INTEGER,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `idx_brand_image_brand_id` (`brand_id`),
  CONSTRAINT `fk_brand_image_brand_id`
  FOREIGN KEY (`brand_id`)
  REFERENCES `brand` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_i18n`;

CREATE TABLE `brand_i18n`
(
  `id` INTEGER NOT NULL,
  `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  `meta_title` VARCHAR(255),
  `meta_description` TEXT,
  `meta_keywords` TEXT,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `brand_i18n_FK_1`
  FOREIGN KEY (`id`)
  REFERENCES `brand` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_document_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_document_i18n`;

CREATE TABLE `brand_document_i18n`
(
  `id` INTEGER NOT NULL,
  `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `brand_document_i18n_FK_1`
  FOREIGN KEY (`id`)
  REFERENCES `brand_document` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- brand_image_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `brand_image_i18n`;

CREATE TABLE `brand_image_i18n`
(
  `id` INTEGER NOT NULL,
  `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
  `title` VARCHAR(255),
  `description` LONGTEXT,
  `chapo` TEXT,
  `postscriptum` TEXT,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `brand_image_i18n_FK_1`
  FOREIGN KEY (`id`)
  REFERENCES `brand_image` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Add brand field to product table, and related constraint.
-- ---------------------------------------------------------

ALTER TABLE `product` ADD `brand_id` INTEGER AFTER `template_id`;
ALTER TABLE `product` ADD CONSTRAINT `fk_product_brand` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL;

ALTER TABLE `product_version` ADD `brand_id` INTEGER AFTER `template_id`;
ALTER TABLE `product_version` ADD CONSTRAINT `fk_product_version_brand` FOREIGN KEY (`brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL;


# Add html_output_trim_level config variable
# ------------------------------------------

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
  ('html_output_trim_level','1', 0, 0, NOW(), NOW());

SELECT @max := MAX(`id`) FROM `config`;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@max, 'en_US', 'Whitespace trim level of the generated HTML code (0 = none, 1 = medium, 2 = maximum)', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- form_firewall
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `form_firewall`;

CREATE TABLE `form_firewall`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `form_name` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(15) NOT NULL,
    `attempts` TINYINT DEFAULT 1,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_form_firewall_form_name` (`form_name`),
    INDEX `idx_form_firewall_ip_address` (`ip_address`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- import_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_category`;

CREATE TABLE `import_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- export_category
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_category`;

CREATE TABLE `export_category`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `position` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- import
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import`;

CREATE TABLE `import`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `import_category_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `handle_class` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_import_import_category_id` (`import_category_id`),
    CONSTRAINT `fk_import_import_category_id`
        FOREIGN KEY (`import_category_id`)
        REFERENCES `import_category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- export
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export`;

CREATE TABLE `export`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `ref` VARCHAR(255) NOT NULL,
    `export_category_id` INTEGER NOT NULL,
    `position` INTEGER NOT NULL,
    `handle_class` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ref_UNIQUE` (`ref`),
    INDEX `idx_export_export_category_id` (`export_category_id`),
    CONSTRAINT `fk_export_export_category_id`
        FOREIGN KEY (`export_category_id`)
        REFERENCES `export_category` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;



-- ---------------------------------------------------------------------
-- import_category_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_category_i18n`;

CREATE TABLE `import_category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `import_category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `import_category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- export_category_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_category_i18n`;

CREATE TABLE `export_category_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `export_category_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `export_category` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- import_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `import_i18n`;

CREATE TABLE `import_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `import_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `import` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- export_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `export_i18n`;

CREATE TABLE `export_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `export_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `export` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;



INSERT INTO `config`(`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('form_firewall_bruteforce_time_to_wait', '10', 0, 0, NOW(), NOW()),
('form_firewall_time_to_wait', '60', 0, 0, NOW(), NOW()),
('form_firewall_bruteforce_attempts', '10', 0, 0, NOW(), NOW()),
('form_firewall_attempts', '6', 0, 0, NOW(), NOW()),
('form_firewall_active', '1', 0, 0, NOW(), NOW())
;

SELECT @bf_time := `id` FROM `config` WHERE `name` =  'form_firewall_bruteforce_time_to_wait';
SELECT @time := `id` FROM `config` WHERE `name` =  'form_firewall_time_to_wait';
SELECT @bf_attempts := `id` FROM `config` WHERE `name` =  'form_firewall_bruteforce_attempts';
SELECT @attempts := `id` FROM `config` WHERE `name` =  'form_firewall_attempts';
SELECT @active := `id` FROM `config` WHERE `name` =  'form_firewall_active';


INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@time, 'en_US', '[Firewall] Time to wait between X attempts', NULL, NULL, NULL),
  (@time, 'fr_FR', '[Pare-feu] Temps à attendre entre X essais', NULL, NULL, NULL)
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@bf_time, 'en_US', '[Firewall/Bruteforce] Time to wait between X attempts', NULL, NULL, NULL),
  (@bf_time, 'fr_FR', '[Pare-feu/Bruteforce] Temps à attendre entre X essais', NULL, NULL, NULL)
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@attempts, 'en_US', '[Firewall] Number of allowed attemps', NULL, NULL, NULL),
  (@attempts, 'fr_FR', '[Pare-feu] Nombre de tentatives autorisées', NULL, NULL, NULL)
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@bf_attempts, 'en_US', '[Firewall/Bruteforce] Number of allowed attemps', NULL, NULL, NULL),
  (@bf_attempts, 'fr_FR', '[Pare-feu/Bruteforce] Nombre de tentatives autorisées', NULL, NULL, NULL)
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
  (@active, 'en_US', '[Firewall] Activate the firewall', NULL, NULL, NULL),
  (@active, 'fr_FR', '[Pare-feu] Activer le pare-feu', NULL, NULL, NULL)
;


-- ---------------------------------------------------------------------
-- missing config_i18n translation for standards variables.
-- ---------------------------------------------------------------------
INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(1, 'fr_FR', 'Nom de la classe du gestionnaire de session', NULL, NULL, NULL),
(2, 'fr_FR', 'Vérifier la présence de produits en stock (1) ou l''ignorer (0) lors de l''affichage et la modification des quantités commandées', NULL, NULL, NULL),
(3, 'fr_FR', 'Nom du modèle de front-office actif', NULL, NULL, NULL),
(4, 'fr_FR', 'Nom du modèle de back-office actif', NULL, NULL, NULL),
(5, 'fr_FR', 'Nom du modèle PDF actif', NULL, NULL, NULL),
(6, 'fr_FR', 'Nom du modèle d''e-mail actif', NULL, NULL, NULL),
(7, 'fr_FR', 'Activer (1) ou désactiver (0) la réécriture d''URL', NULL, NULL, NULL),
(8, 'fr_FR', 'Nom du pilote graphique utilisé par la bibliothèque Imagine (voir https://imagine.readthedocs.org)', NULL, NULL, NULL),
(9, 'fr_FR', 'La qualité par défaut (en %) dans les images générées', NULL, NULL, NULL),
(10, 'fr_FR', 'Comment les images originales (pleine résolution) sont-elles fournises dans l''espace web (lien symbolique ou copie)', NULL, NULL, NULL),
(11, 'fr_FR', 'Comment les documents sont-ils fournis dans l''espace web (lien symbolique ou copie)', NULL, NULL, NULL),
(12, 'fr_FR', 'Chemin vers le répertoire où les images sont stockées', NULL, NULL, NULL),
(13, 'fr_FR', 'Chemin vers le répertoire où sont stockés les documents', NULL, NULL, NULL),
(14, 'fr_FR', 'Chemin vers le répertoire de cache d''image dans l''espace web', NULL, NULL, NULL),
(15, 'fr_FR', 'Chemin d''accès au répertoire de cache de document dans l''espace web', NULL, NULL, NULL),
(16, 'fr_FR', 'L''URL pour mettre à jour les taux de change', NULL, NULL, NULL),
(17, 'fr_FR', 'Nom de la page 404 (introuvable) dans le modèle actuel (avec l''extension, par exemple, 404.html)', NULL, NULL, NULL),
(18, 'fr_FR', 'Nom de la page du modèle retournée lorsqu''une URL obsolète (ou inactive) est invoquée', NULL, NULL, NULL),
(19, 'fr_FR', 'Affiche et traite les prix avec(0) ou sans (1) les taxes', NULL, NULL, NULL),
(20, 'fr_FR', 'Compiler les resources du modèle actif à chaque changement (1 = oui, 2 = non)', NULL, NULL, NULL),
(21, 'fr_FR', 'Nom du cookie "Remember me" pour les utilisateurs d''administration', NULL, NULL, NULL),
(22, 'fr_FR', 'Délai d''expiration du cookie "Remember me", en secondes, pour les utilisateurs d''administration', NULL, NULL, NULL),
(23, 'fr_FR', 'Nom du cookie "Remember me" pour les clients', NULL, NULL, NULL),
(24, 'fr_FR', 'Délai d''expiration du cookie "Remember me", en secondes, pour les clients', NULL, NULL, NULL),
(25, 'fr_FR', 'URL de base pour la boutique (par exemple http://www.yourshopdomain.com)', NULL, NULL, NULL),
(26, 'fr_FR', 'Nom de la vue de la facture dans le modèle PDF en cours (sans extension)', NULL, NULL, NULL),
(27, 'fr_FR', 'Nom de la vue de la livraison dans le modèle PDF en cours (sans extension)', NULL, NULL, NULL),
(28, 'fr_FR', 'Le chemin (par rapport au modèle de back-office par défaut) vers l''image utilisée lorsque aucune image de drapeau ne peut être trouvée pour un pays', NULL, NULL, NULL),
(29, 'fr_FR', 'Niveau de découpe des espaces dans le code HTML généré (0 = aucun, 1 = moyen, 2 = maximum)', NULL, NULL, NULL);

# Done !
# ------
SET FOREIGN_KEY_CHECKS = 1;