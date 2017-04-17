SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-beta1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='beta1' WHERE `name`='thelia_extra_version';

DELETE FROM `config` WHERE `name`='session_config.handlers';

CREATE TABLE IF NOT EXISTS `api`
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
(@max_id + 1, 'session_config.lifetime', '0', 0, 0, NOW(), NOW()),
(@max_id + 2, 'error_message.show', '1', 0, 0, NOW(), NOW()),
(@max_id + 3, 'error_message.page_name', 'error.html', 0, 0, NOW(), NOW())
;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(@max_id + 1, 'de_DE', 'Dauer der Session-Cookie in dem Kunden-Browser, in Sekunden', NULL, NULL, NULL),
(@max_id + 2, 'de_DE', 'Fehlermeldung zeigen anstatt einer weißen Seite im Falle eines eines Serverfehlers', NULL, NULL, NULL),
(@max_id + 3, 'de_DE', 'Dateiname der Fehlerseite', NULL, NULL, NULL),
(@max_id + 1, 'en_US', 'Life time of the session cookie in the customer browser, in seconds', NULL, NULL, NULL),
(@max_id + 2, 'en_US', 'Show error message instead of a white page on a server error', NULL, NULL, NULL),
(@max_id + 3, 'en_US', 'Filename of the error page', NULL, NULL, NULL),
(@max_id + 1, 'es_ES', 'Tiempo de vida de la cookie de la sesión en el navegador del cliente, en segundos', NULL, NULL, NULL),
(@max_id + 2, 'es_ES', 'Mostrar mensaje de error en lugar de una página en blanco cuando ocurre un error de servidor', NULL, NULL, NULL),
(@max_id + 3, 'es_ES', 'Nombre de archivo de la página de error', NULL, NULL, NULL),
(@max_id + 1, 'fr_FR', 'Durée de vie du cookie de la session dans le navigateur du client, en secondes', NULL, NULL, NULL),
(@max_id + 2, 'fr_FR', 'Afficher un message d\'erreur à la place d\'une page blanche lors d\'une erreur serveur', NULL, NULL, NULL),
(@max_id + 3, 'fr_FR', 'Nom du fichier de la page d\'erreur', NULL, NULL, NULL)
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
(@max_id + 18, 'brand.sidebar-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 19, 'account-order.top', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 20, 'account-order.information', 1, 0, 1, 1, 1, 1, NOW(), NOW()),
(@max_id + 21, 'account-order.after-information', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 22, 'account-order.delivery-information', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 23, 'account-order.delivery-address', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 24, 'account-order.invoice-information', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 25, 'account-order.invoice-address', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 26, 'account-order.after-addresses', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 27, 'account-order.products-top', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 28, 'account-order.product-extra', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 29, 'account-order.products-bottom', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 30, 'account-order.after-products', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 31, 'account-order.bottom', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 32, 'account-order.stylesheet', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 33, 'account-order.after-javascript-include', 1,  0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 34, 'account-order.javascript-initialization', 1,  0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'de_DE', 'Kategorieseite - vor dem Hauptinhaltsbereich', '', ''),
(@max_id + 2, 'de_DE', 'Kategorieseite - nach dem Hauptinhaltsbereich', '', ''),
(@max_id + 3, 'de_DE', 'Inhaltseite- vor dem Hauptinhaltsbereich', '', ''),
(@max_id + 4, 'de_DE', 'Inhaltseite - nach dem Hauptinhaltsbereich', '', ''),
(@max_id + 5, 'de_DE', 'Ordnerseite - vor dem Hauptinhaltsbereich', '', ''),
(@max_id + 6, 'de_DE', 'Ordnerseite - nach dem Hauptinhaltsbereich', '', ''),
(@max_id + 7, 'de_DE', 'Marken Seite - oben', '', ''),
(@max_id + 8, 'de_DE', 'Marken Seite - unten', '', ''),
(@max_id + 9, 'de_DE', 'Marken Seite - oben des Hauptbereichs', '', ''),
(@max_id + 10, 'de_DE', 'Marken Seite - unten des Hauptbereichs', '', ''),
(@max_id + 11, 'de_DE', 'Marken Seite - vor dem Hauptinhaltsbereich', '', ''),
(@max_id + 12, 'de_DE', 'Marken Seite - Nach dem Hauptinhalt Bereich', '', ''),
(@max_id + 13, 'de_DE', 'Marken Seite - CSS-Stylesheet', '', ''),
(@max_id + 14, 'de_DE', 'Marken Seite - Nach der Integration von Javascript', '', ''),
(@max_id + 15, 'de_DE', 'Marken Seite - Javascript Initialisation', '', ''),
(@max_id + 16, 'de_DE', 'Marken Seite - oben der Sidebar', '', ''),
(@max_id + 17, 'de_DE', 'Marken Seite - Sidebars Body', '', ''),
(@max_id + 18, 'de_DE', 'Marken Seite - unten der Sidebar', '', ''),
(@max_id + 19, 'de_DE', 'Bestellungsdetails - oben', '', ''),
(@max_id + 20, 'de_DE', 'Bestellungsdetails - weitere Informationen', '', ''),
(@max_id + 21, 'de_DE', 'Bestellungsdetails - nach den allgemeinen Informationen', '', ''),
(@max_id + 22, 'de_DE', 'Bestellungsdetails - weitere Informationen für den Versand', '', ''),
(@max_id + 23, 'de_DE', 'Bestellungsdetails - Lieferadresse', '', ''),
(@max_id + 24, 'de_DE', 'Bestellungsdetails - weitere Informationen für die Rechnung', '', ''),
(@max_id + 25, 'de_DE', 'Bestellungsdetails - Rechnungsadresse', '', ''),
(@max_id + 26, 'de_DE', 'Bestellungsdetails - Nach den Adressen', '', ''),
(@max_id + 27, 'de_DE', 'Bestellungsdetails - vor der Produktliste', '', ''),
(@max_id + 28, 'de_DE', 'Bestellungsdetails - weitere Informationen für ein Produkt', '', ''),
(@max_id + 29, 'de_DE', 'Bestellungsdetails - nach der Produktliste', '', ''),
(@max_id + 30, 'de_DE', 'Bestellungsdetails - nach den Produkten', '', ''),
(@max_id + 31, 'de_DE', 'Bestellungsdetails - unten', '', ''),
(@max_id + 32, 'de_DE', 'Bestellungsdetails - CSS-Stylesheet', '', ''),
(@max_id + 33, 'de_DE', 'Bestellungsdetails - nach Integration von JavaScript', '', ''),
(@max_id + 34, 'de_DE', 'Bestellungsdetails - Initialisierung von Javascript', '', ''),
(@max_id + 1, 'en_US', 'Category page - before the main content area', '', ''),
(@max_id + 2, 'en_US', 'Category page - after the main content area', '', ''),
(@max_id + 3, 'en_US', 'Content page - before the main content area', '', ''),
(@max_id + 4, 'en_US', 'Content page - after the main content area', '', ''),
(@max_id + 5, 'en_US', 'Folder page - before the main content area', '', ''),
(@max_id + 6, 'en_US', 'Folder page - after the main content area', '', ''),
(@max_id + 7, 'en_US', 'Brands page - at the top', '', ''),
(@max_id + 8, 'en_US', 'Brands page - at the bottom', '', ''),
(@max_id + 9, 'en_US', 'Brands page - at the top of the main area', '', ''),
(@max_id + 10, 'en_US', 'Brands page - at the bottom of the main area', '', ''),
(@max_id + 11, 'en_US', 'Brands page - before the main content area', '', ''),
(@max_id + 12, 'en_US', 'Brands page - after the main content area', '', ''),
(@max_id + 13, 'en_US', 'Brands page - CSS stylesheet', '', ''),
(@max_id + 14, 'en_US', 'Brands page - after javascript include', '', ''),
(@max_id + 15, 'en_US', 'Brands page - javascript initialization', '', ''),
(@max_id + 16, 'en_US', 'Brands page - at the top of the sidebar', '', ''),
(@max_id + 17, 'en_US', 'Brands page - the body of the sidebar', '', ''),
(@max_id + 18, 'en_US', 'Brands page - at the bottom of the sidebar', '', ''),
(@max_id + 19, 'en_US', 'Order details - at the top', '', ''),
(@max_id + 20, 'en_US', 'Order details - additional information', '', ''),
(@max_id + 21, 'en_US', 'Order details - after global information', '', ''),
(@max_id + 22, 'en_US', 'Order details - additional delivery information', '', ''),
(@max_id + 23, 'en_US', 'Order details - delivery address', '', ''),
(@max_id + 24, 'en_US', 'Order details - additional invoice information', '', ''),
(@max_id + 25, 'en_US', 'Order details - invoice address', '', ''),
(@max_id + 26, 'en_US', 'Order details - after addresses', '', ''),
(@max_id + 27, 'en_US', 'Order details - before products list', '', ''),
(@max_id + 28, 'en_US', 'Order details - additional product information', '', ''),
(@max_id + 29, 'en_US', 'Order details - after products list', '', ''),
(@max_id + 30, 'en_US', 'Order details - after products', '', ''),
(@max_id + 31, 'en_US', 'Order details - at the bottom', '', ''),
(@max_id + 32, 'en_US', 'Order details - CSS stylesheet', '', ''),
(@max_id + 33, 'en_US', 'Order details - after javascript include', '', ''),
(@max_id + 34, 'en_US', 'Order details - javascript initialization', '', ''),
(@max_id + 1, 'es_ES', 'Página de categoría - antes el área de contenido principal', '', ''),
(@max_id + 2, 'es_ES', 'Página de la categoría - después el área de contenido principal', '', ''),
(@max_id + 3, 'es_ES', 'Página de contenido - antes del área de contenido principal', '', ''),
(@max_id + 4, 'es_ES', 'Página de contenido - después del área de contenido principal', '', ''),
(@max_id + 5, 'es_ES', 'Carpeta de página - antes del área de contenido principal', '', ''),
(@max_id + 6, 'es_ES', 'Carpeta de página - después del área de contenido principal', '', ''),
(@max_id + 7, 'es_ES', 'Página de las marcas - en la parte superior', '', ''),
(@max_id + 8, 'es_ES', 'Página de las marcas - en la parte inferior', '', ''),
(@max_id + 9, 'es_ES', 'Página de las marcas - en la parte inferior del área principal', '', ''),
(@max_id + 10, 'es_ES', 'Página de las marcas - en la parte inferior del área principal', '', ''),
(@max_id + 11, 'es_ES', 'Página de marcas - antes del área de contenido principal', '', ''),
(@max_id + 12, 'es_ES', 'Página de marcas - después el área de contenido principal', '', ''),
(@max_id + 13, 'es_ES', 'Página de marcas - hoja de estilos CSS', '', ''),
(@max_id + 14, 'es_ES', 'Página de marcas - después de inclusión de javascript', '', ''),
(@max_id + 15, 'es_ES', 'Página de marcas - inicialización de javascript', '', ''),
(@max_id + 16, 'es_ES', 'Página de las marcas - en la parte inferior de la barra lateral', '', ''),
(@max_id + 17, 'es_ES', 'Página de marcas - el cuerpo de la barra lateral', '', ''),
(@max_id + 18, 'es_ES', 'Página de las marcas - en la parte inferior de la barra lateral', '', ''),
(@max_id + 19, 'es_ES', 'Detalles de la orden - en la parte superior', '', ''),
(@max_id + 20, 'es_ES', 'Detalles de la Orden - información adicional', '', ''),
(@max_id + 21, 'es_ES', 'Detalles de la orden - después de la información global', '', ''),
(@max_id + 22, 'es_ES', 'Detalles de la Orden - información adicional del envío', '', ''),
(@max_id + 23, 'es_ES', 'Pedir detalles - dirección de envío', '', ''),
(@max_id + 24, 'es_ES', 'Detalles de la Orden - información adicional de la factura', '', ''),
(@max_id + 25, 'es_ES', 'Detalles de la orden - dirección de factura', '', ''),
(@max_id + 26, 'es_ES', 'Detalles de la Orden - después de direcciones', '', ''),
(@max_id + 27, 'es_ES', 'Detalles de la orden - antes de lista de productos', '', ''),
(@max_id + 28, 'es_ES', 'Detalles de la Orden - información adicional del producto', '', ''),
(@max_id + 29, 'es_ES', 'Detalles de la orden - después de la lista de productos', '', ''),
(@max_id + 30, 'es_ES', 'Detalles de la orden - después de los productos', '', ''),
(@max_id + 31, 'es_ES', 'Detalles de la orden - en la parte inferior', '', ''),
(@max_id + 32, 'es_ES', 'Detalles de la Orden - hoja de estilos CSS', '', ''),
(@max_id + 33, 'es_ES', 'Detalles de la orden - después de incluir JavaScript', '', ''),
(@max_id + 34, 'es_ES', 'Detalles de la Orden - inicialización de JavaScript', '', ''),
(@max_id + 1, 'fr_FR', 'Page catégorie - au dessus de la zone de contenu principale', '', ''),
(@max_id + 2, 'fr_FR', 'Page catégorie - en dessous de la zone de contenu principale', '', ''),
(@max_id + 3, 'fr_FR', 'Page de contenu - au dessus de la zone de contenu principale', '', ''),
(@max_id + 4, 'fr_FR', 'Page de contenu - en dessous de la zone de contenu principale', '', ''),
(@max_id + 5, 'fr_FR', 'Page dossier - au dessus de la zone de contenu principale', '', ''),
(@max_id + 6, 'fr_FR', 'Page dossier - en dessous de la zone de contenu principale', '', ''),
(@max_id + 7, 'fr_FR', 'Page des marques - en haut', '', ''),
(@max_id + 8, 'fr_FR', 'Page des marques - en bas', '', ''),
(@max_id + 9, 'fr_FR', 'Page des marques - en haut de la zone principal', '', ''),
(@max_id + 10, 'fr_FR', 'Page des marques - en bas de la zone principal', '', ''),
(@max_id + 11, 'fr_FR', 'Page des marques - au dessus de la zone de contenu principale', '', ''),
(@max_id + 12, 'fr_FR', 'Page des marques - en dessous de la zone de contenu principale', '', ''),
(@max_id + 13, 'fr_FR', 'Page des marques - feuille de style CSS', '', ''),
(@max_id + 14, 'fr_FR', 'Page des marques - après l\'inclusion du JavaScript', '', ''),
(@max_id + 15, 'fr_FR', 'Page des marques - initialisation du JavaScript', '', ''),
(@max_id + 16, 'fr_FR', 'Page des marques - en haut de la sidebar', '', ''),
(@max_id + 17, 'fr_FR', 'Page des marques - le corps de la sidebar', '', ''),
(@max_id + 18, 'fr_FR', 'Page des marques - en bas de la sidebar', '', ''),
(@max_id + 19, 'fr_FR', 'Détail d\'une commande - en haut', '', ''),
(@max_id + 20, 'fr_FR', 'Détail d\'une commande - informations additionnelles', '', ''),
(@max_id + 21, 'fr_FR', 'Détail d\'une commande - après les informations générales', '', ''),
(@max_id + 22, 'fr_FR', 'Détail d\'une commande - informations additionnelles pour l\'expédition', '', ''),
(@max_id + 23, 'fr_FR', 'Détail d\'une commande - adresse de livraison', '', ''),
(@max_id + 24, 'fr_FR', 'Détail d\'une commande - informations additionnelles pour la facturation', '', ''),
(@max_id + 25, 'fr_FR', 'Détail d\'une commande - adresse de facturation', '', ''),
(@max_id + 26, 'fr_FR', 'Détail d\'une commande - Après les adresses', '', ''),
(@max_id + 27, 'fr_FR', 'Détail d\'une commande - avant la liste des produits', '', ''),
(@max_id + 28, 'fr_FR', 'Détail d\'une commande - informations additionnelles pour un produit', '', ''),
(@max_id + 29, 'fr_FR', 'Détail d\'une commande - après la liste des produits', '', ''),
(@max_id + 30, 'fr_FR', 'Détail d\'une commande - Après les produits', '', ''),
(@max_id + 31, 'fr_FR', 'Détail d\'une commande - en bas', '', ''),
(@max_id + 32, 'fr_FR', 'Détail d\'une commande - feuille de style CSS', '', ''),
(@max_id + 33, 'fr_FR', 'Détail d\'une commande - après l\'inclusion du JavaScript', '', ''),
(@max_id + 34, 'fr_FR', 'Détail d\'une commande - initialisation du JavaScript', '', '')
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
(@max_id + 1, 'de_DE', 'Kategorie - Tab', '', ''),
(@max_id + 2, 'de_DE', 'Produkt - Tab', '', ''),
(@max_id + 3, 'de_DE', 'Ordner - Tab', '', ''),
(@max_id + 4, 'de_DE', 'Inhalt - Tab', '', ''),
(@max_id + 5, 'de_DE', 'Marke - Tab', '', ''),
(@max_id + 6, 'de_DE', 'Bestellungs-Änderung - Lieferadresse', '', ''),
(@max_id + 1, 'en_US', 'Category - Tab', '', ''),
(@max_id + 2, 'en_US', 'Product - Tab', '', ''),
(@max_id + 3, 'en_US', 'Folder - Tab', '', ''),
(@max_id + 4, 'en_US', 'Content - Tab', '', ''),
(@max_id + 5, 'en_US', 'Brand - Tab', '', ''),
(@max_id + 6, 'en_US', 'Order edit - delivery address', '', ''),
(@max_id + 1, 'es_ES', 'Categoría - Tab', '', ''),
(@max_id + 2, 'es_ES', 'Producto - Pestaña', '', ''),
(@max_id + 3, 'es_ES', 'Carpeta - Pestaña', '', ''),
(@max_id + 4, 'es_ES', 'Contenido - Pestaña', '', ''),
(@max_id + 5, 'es_ES', 'Marca - Tab', '', ''),
(@max_id + 6, 'es_ES', 'Editar Orden - dirección de envío', '', ''),
(@max_id + 1, 'fr_FR', 'Catégorie - Onglet', '', ''),
(@max_id + 2, 'fr_FR', 'Produit - Onglet', '', ''),
(@max_id + 3, 'fr_FR', 'Dossier - Onglet', '', ''),
(@max_id + 4, 'fr_FR', 'Contenu - Onglet', '', ''),
(@max_id + 5, 'fr_FR', 'Marque - Onglet', '', ''),
(@max_id + 6, 'fr_FR', 'Modification commande - adresse de livraison', '', '')
;


SET FOREIGN_KEY_CHECKS = 1;

