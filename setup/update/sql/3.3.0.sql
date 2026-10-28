SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tax types of a shop migrated from Thelia 2
--
-- A tax records the class of its type in `tax`.`type`, and the three types
-- moved from Thelia\TaxEngine\TaxType to Thelia\Domain\Taxation\TaxEngine\TaxType
-- in Thelia 3. Until this release the update only carried the percentage type
-- over, so a fixed amount tax or a tax on a product feature kept its Thelia 2
-- class name, which no tax type answers to any more: computing the price of a
-- product under such a rule failed with "Recorded type ... does not exists".
--
-- Each statement only matches a row still on the Thelia 2 name, so the
-- script can be replayed.
-- ---------------------------------------------------------------------

UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\PricePercentTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\PricePercentTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FixAmountTaxType';
UPDATE `tax` SET `type` = 'Thelia\\Domain\\Taxation\\TaxEngine\\TaxType\\FeatureFixAmountTaxType'
             WHERE `type` = 'Thelia\\TaxEngine\\TaxType\\FeatureFixAmountTaxType';

-- The manufacturer part number and the manufacturer brand of a combination, beside its GTIN
-- kept in `ean_code`. Null on every combination that predates them: no part number, and the
-- brand of the product stands for the manufacturer. The order line freezes the part number
-- sold, as it already does for the GTIN.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `COLUMN_NAME` = 'manufacturer_brand_id');
SET @statement := IF(@add_column, 'ALTER TABLE `product_sale_elements` ADD `manufacturer_brand_id` INTEGER NULL AFTER `mpn`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The search by part number and the manufacturer brand filter read these columns: without an
-- index they scan the whole table. The index on `ean_code` is added by 3.2.0.sql, for the quick order.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_mpn');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_mpn` (`mpn`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `INDEX_NAME` = 'idx_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_index, 'ALTER TABLE `product_sale_elements` ADD INDEX `idx_product_sale_elements_manufacturer_brand_id` (`manufacturer_brand_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_sale_elements' AND `CONSTRAINT_NAME` = 'fk_product_sale_elements_manufacturer_brand_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `product_sale_elements` ADD CONSTRAINT `fk_product_sale_elements_manufacturer_brand_id` FOREIGN KEY (`manufacturer_brand_id`) REFERENCES `brand` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'mpn');
SET @statement := IF(@add_column, 'ALTER TABLE `order_product` ADD `mpn` VARCHAR(255) NULL AFTER `ean_code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Back-office hooks of the default Twig back-office template
--
-- The templates of the back office call these hooks, and its documentation
-- offers some of them to modules (customer.tab, customer.tab-content,
-- customer-edit.actions), but no seed declared them. A module subscribing to
-- one was dropped when the container was built ("Hook customer.tab is
-- unknown."), and its section never showed.
--
-- A module may have declared some of them already, with an id of its own:
-- each hook is only added when no back-office hook has its code, and its
-- titles only for the languages it has none in. The script can be replayed.
-- ---------------------------------------------------------------------

INSERT INTO `hook` (`code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`)
SELECT `missing`.`code`, 2, 0, `missing`.`block`, 1, 1, 1, NOW(), NOW()
FROM (
    SELECT 'advanced-configuration.bottom' AS `code`, 0 AS `block`
    UNION ALL SELECT 'advanced-configuration.top', 0
    UNION ALL SELECT 'attribute.update-form', 0
    UNION ALL SELECT 'brand.seo.update-form', 0
    UNION ALL SELECT 'catalog-price-rule-edit.bottom', 0
    UNION ALL SELECT 'catalog-price-rule-edit.top', 0
    UNION ALL SELECT 'catalog-price-rule.bottom', 0
    UNION ALL SELECT 'catalog-price-rule.create-form', 0
    UNION ALL SELECT 'catalog-price-rule.js', 0
    UNION ALL SELECT 'catalog-price-rule.top', 0
    UNION ALL SELECT 'catalog.tree.bottom', 0
    UNION ALL SELECT 'catalog.tree.top', 0
    UNION ALL SELECT 'category.seo.update-form', 0
    UNION ALL SELECT 'category.update-form', 0
    UNION ALL SELECT 'checkout-step.bottom', 0
    UNION ALL SELECT 'checkout-step.js', 0
    UNION ALL SELECT 'checkout-step.top', 0
    UNION ALL SELECT 'configuration.customers.bottom', 0
    UNION ALL SELECT 'configuration.customers.top', 0
    UNION ALL SELECT 'configuration.store.bottom', 0
    UNION ALL SELECT 'configuration.store.top', 0
    UNION ALL SELECT 'consent-edit.bottom', 0
    UNION ALL SELECT 'consent-edit.top', 0
    UNION ALL SELECT 'consent.bottom', 0
    UNION ALL SELECT 'consent.edit-js', 0
    UNION ALL SELECT 'consent.js', 0
    UNION ALL SELECT 'consent.top', 0
    UNION ALL SELECT 'consent.update-form', 0
    UNION ALL SELECT 'content.seo.update-form', 0
    UNION ALL SELECT 'content.update-form', 0
    UNION ALL SELECT 'coupon.edit-bottom', 0
    UNION ALL SELECT 'coupon.edit-top', 0
    UNION ALL SELECT 'currency.edit-form', 0
    UNION ALL SELECT 'customer-edit.actions', 0
    UNION ALL SELECT 'customer.personal-data', 0
    UNION ALL SELECT 'customer.tab', 1
    UNION ALL SELECT 'customer.tab-content', 0
    UNION ALL SELECT 'feature.update-form', 0
    UNION ALL SELECT 'folder.seo.update-form', 0
    UNION ALL SELECT 'folder.update-form', 0
    UNION ALL SELECT 'gift-wrapping-edit.bottom', 0
    UNION ALL SELECT 'gift-wrapping-edit.top', 0
    UNION ALL SELECT 'gift-wrapping.bottom', 0
    UNION ALL SELECT 'gift-wrapping.edit-js', 0
    UNION ALL SELECT 'gift-wrapping.js', 0
    UNION ALL SELECT 'gift-wrapping.top', 0
    UNION ALL SELECT 'gift-wrapping.update-form', 0
    UNION ALL SELECT 'main.top-menu-reports', 1
    UNION ALL SELECT 'message.update-form', 0
    UNION ALL SELECT 'order-return-edit.bottom', 0
    UNION ALL SELECT 'order-return-edit.top', 0
    UNION ALL SELECT 'order-return-reason-edit.bottom', 0
    UNION ALL SELECT 'order-return-reason-edit.top', 0
    UNION ALL SELECT 'order-return-reason.bottom', 0
    UNION ALL SELECT 'order-return-reason.js', 0
    UNION ALL SELECT 'order-return-reason.top', 0
    UNION ALL SELECT 'order-return-reason.update-form', 0
    UNION ALL SELECT 'order-returns.bottom', 0
    UNION ALL SELECT 'order-returns.js', 0
    UNION ALL SELECT 'order-returns.top', 0
    UNION ALL SELECT 'order-status-edit.bottom', 0
    UNION ALL SELECT 'order-status-edit.top', 0
    UNION ALL SELECT 'order-status.edit-js', 0
    UNION ALL SELECT 'order-status.tab', 1
    UNION ALL SELECT 'order-status.tab-content', 0
    UNION ALL SELECT 'order-status.update-form', 0
    UNION ALL SELECT 'product-association-type-edit.bottom', 0
    UNION ALL SELECT 'product-association-type-edit.top', 0
    UNION ALL SELECT 'product-association-type.bottom', 0
    UNION ALL SELECT 'product-association-type.edit-js', 0
    UNION ALL SELECT 'product-association-type.js', 0
    UNION ALL SELECT 'product-association-type.top', 0
    UNION ALL SELECT 'product-association-type.update-form', 0
    UNION ALL SELECT 'product-edit.attributes-tab', 0
    UNION ALL SELECT 'product-edit.related-tab', 0
    UNION ALL SELECT 'product.clone-form', 0
    UNION ALL SELECT 'product.combinations-tab.bottom', 0
    UNION ALL SELECT 'product.combinations-tab.top', 0
    UNION ALL SELECT 'product.seo.update-form', 0
    UNION ALL SELECT 'product.update-form', 0
    UNION ALL SELECT 'products.bottom', 0
    UNION ALL SELECT 'products.top', 0
    UNION ALL SELECT 'reports.conversion.bottom', 0
    UNION ALL SELECT 'reports.conversion.top', 0
    UNION ALL SELECT 'system-information.bottom', 0
    UNION ALL SELECT 'system-information.js', 0
    UNION ALL SELECT 'system-information.top', 0
    UNION ALL SELECT 'tools.col2-bottom', 0
    UNION ALL SELECT 'tools.col2-top', 0
    UNION ALL SELECT 'variable.edit-form', 0
    UNION ALL SELECT 'administrator.edit-form', 0
    UNION ALL SELECT 'catalog-price-rule.delete-form', 0
    UNION ALL SELECT 'catalog-price-rule.table-header', 0
    UNION ALL SELECT 'catalog-price-rule.table-row', 0
    UNION ALL SELECT 'checkout-step.table-header', 0
    UNION ALL SELECT 'checkout-step.table-row', 0
    UNION ALL SELECT 'consent.table-header', 0
    UNION ALL SELECT 'consent.table-row', 0
    UNION ALL SELECT 'customer-title.delete-form', 0
    UNION ALL SELECT 'customer.anonymize-form', 0
    UNION ALL SELECT 'gift-wrapping.table-header', 0
    UNION ALL SELECT 'gift-wrapping.table-row', 0
    UNION ALL SELECT 'product-association-type.table-header', 0
    UNION ALL SELECT 'product-association-type.table-row', 0
    UNION ALL SELECT 'tag.delete-form', 0
) AS `missing`
WHERE NOT EXISTS (SELECT 1 FROM `hook` WHERE `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2);

-- The English title, as the fresh install seeds it; the other languages have no wording yet.
INSERT INTO `hook_i18n` (`id`, `locale`, `title`, `chapo`, `description`)
SELECT `hook`.`id`, `lang`.`locale`, IF(`lang`.`locale` = 'en_US', `missing`.`title`, NULL), NULL, NULL
FROM (
    SELECT 'advanced-configuration.bottom' AS `code`, 'Advanced configuration - bottom' AS `title`
    UNION ALL SELECT 'advanced-configuration.top', 'Advanced configuration - top'
    UNION ALL SELECT 'attribute.update-form', 'Attribute - update form'
    UNION ALL SELECT 'brand.seo.update-form', 'Brand - SEO update form'
    UNION ALL SELECT 'catalog-price-rule-edit.bottom', 'Catalog price rule edit - bottom'
    UNION ALL SELECT 'catalog-price-rule-edit.top', 'Catalog price rule edit - top'
    UNION ALL SELECT 'catalog-price-rule.bottom', 'Catalog price rule - bottom'
    UNION ALL SELECT 'catalog-price-rule.create-form', 'Catalog price rule - create form'
    UNION ALL SELECT 'catalog-price-rule.js', 'Catalog price rule - JavaScript'
    UNION ALL SELECT 'catalog-price-rule.top', 'Catalog price rule - top'
    UNION ALL SELECT 'catalog.tree.bottom', 'Catalog - tree bottom'
    UNION ALL SELECT 'catalog.tree.top', 'Catalog - tree top'
    UNION ALL SELECT 'category.seo.update-form', 'Category - SEO update form'
    UNION ALL SELECT 'category.update-form', 'Category - update form'
    UNION ALL SELECT 'checkout-step.bottom', 'Checkout step - bottom'
    UNION ALL SELECT 'checkout-step.js', 'Checkout step - JavaScript'
    UNION ALL SELECT 'checkout-step.top', 'Checkout step - top'
    UNION ALL SELECT 'configuration.customers.bottom', 'Configuration - customers bottom'
    UNION ALL SELECT 'configuration.customers.top', 'Configuration - customers top'
    UNION ALL SELECT 'configuration.store.bottom', 'Configuration - store bottom'
    UNION ALL SELECT 'configuration.store.top', 'Configuration - store top'
    UNION ALL SELECT 'consent-edit.bottom', 'Consent edit - bottom'
    UNION ALL SELECT 'consent-edit.top', 'Consent edit - top'
    UNION ALL SELECT 'consent.bottom', 'Consent - bottom'
    UNION ALL SELECT 'consent.edit-js', 'Consent - edit JavaScript'
    UNION ALL SELECT 'consent.js', 'Consent - JavaScript'
    UNION ALL SELECT 'consent.top', 'Consent - top'
    UNION ALL SELECT 'consent.update-form', 'Consent - update form'
    UNION ALL SELECT 'content.seo.update-form', 'Content - SEO update form'
    UNION ALL SELECT 'content.update-form', 'Content - update form'
    UNION ALL SELECT 'coupon.edit-bottom', 'Coupon - edit bottom'
    UNION ALL SELECT 'coupon.edit-top', 'Coupon - edit top'
    UNION ALL SELECT 'currency.edit-form', 'Currency - edit form'
    UNION ALL SELECT 'customer-edit.actions', 'Customer edit - actions'
    UNION ALL SELECT 'customer.personal-data', 'Customer - personal data'
    UNION ALL SELECT 'customer.tab', 'Customer - tab'
    UNION ALL SELECT 'customer.tab-content', 'Customer - tab content'
    UNION ALL SELECT 'feature.update-form', 'Feature - update form'
    UNION ALL SELECT 'folder.seo.update-form', 'Folder - SEO update form'
    UNION ALL SELECT 'folder.update-form', 'Folder - update form'
    UNION ALL SELECT 'gift-wrapping-edit.bottom', 'Gift wrapping edit - bottom'
    UNION ALL SELECT 'gift-wrapping-edit.top', 'Gift wrapping edit - top'
    UNION ALL SELECT 'gift-wrapping.bottom', 'Gift wrapping - bottom'
    UNION ALL SELECT 'gift-wrapping.edit-js', 'Gift wrapping - edit JavaScript'
    UNION ALL SELECT 'gift-wrapping.js', 'Gift wrapping - JavaScript'
    UNION ALL SELECT 'gift-wrapping.top', 'Gift wrapping - top'
    UNION ALL SELECT 'gift-wrapping.update-form', 'Gift wrapping - update form'
    UNION ALL SELECT 'main.top-menu-reports', 'Main - top menu reports'
    UNION ALL SELECT 'message.update-form', 'Message - update form'
    UNION ALL SELECT 'order-return-edit.bottom', 'Order return edit - bottom'
    UNION ALL SELECT 'order-return-edit.top', 'Order return edit - top'
    UNION ALL SELECT 'order-return-reason-edit.bottom', 'Order return reason edit - bottom'
    UNION ALL SELECT 'order-return-reason-edit.top', 'Order return reason edit - top'
    UNION ALL SELECT 'order-return-reason.bottom', 'Order return reason - bottom'
    UNION ALL SELECT 'order-return-reason.js', 'Order return reason - JavaScript'
    UNION ALL SELECT 'order-return-reason.top', 'Order return reason - top'
    UNION ALL SELECT 'order-return-reason.update-form', 'Order return reason - update form'
    UNION ALL SELECT 'order-returns.bottom', 'Order returns - bottom'
    UNION ALL SELECT 'order-returns.js', 'Order returns - JavaScript'
    UNION ALL SELECT 'order-returns.top', 'Order returns - top'
    UNION ALL SELECT 'order-status-edit.bottom', 'Order status edit - bottom'
    UNION ALL SELECT 'order-status-edit.top', 'Order status edit - top'
    UNION ALL SELECT 'order-status.edit-js', 'Order status - edit JavaScript'
    UNION ALL SELECT 'order-status.tab', 'Order status - tab'
    UNION ALL SELECT 'order-status.tab-content', 'Order status - tab content'
    UNION ALL SELECT 'order-status.update-form', 'Order status - update form'
    UNION ALL SELECT 'product-association-type-edit.bottom', 'Product association type edit - bottom'
    UNION ALL SELECT 'product-association-type-edit.top', 'Product association type edit - top'
    UNION ALL SELECT 'product-association-type.bottom', 'Product association type - bottom'
    UNION ALL SELECT 'product-association-type.edit-js', 'Product association type - edit JavaScript'
    UNION ALL SELECT 'product-association-type.js', 'Product association type - JavaScript'
    UNION ALL SELECT 'product-association-type.top', 'Product association type - top'
    UNION ALL SELECT 'product-association-type.update-form', 'Product association type - update form'
    UNION ALL SELECT 'product-edit.attributes-tab', 'Product edit - attributes tab'
    UNION ALL SELECT 'product-edit.related-tab', 'Product edit - related tab'
    UNION ALL SELECT 'product.clone-form', 'Product - clone form'
    UNION ALL SELECT 'product.combinations-tab.bottom', 'Product - combinations tab bottom'
    UNION ALL SELECT 'product.combinations-tab.top', 'Product - combinations tab top'
    UNION ALL SELECT 'product.seo.update-form', 'Product - SEO update form'
    UNION ALL SELECT 'product.update-form', 'Product - update form'
    UNION ALL SELECT 'products.bottom', 'Products - bottom'
    UNION ALL SELECT 'products.top', 'Products - top'
    UNION ALL SELECT 'reports.conversion.bottom', 'Reports - conversion bottom'
    UNION ALL SELECT 'reports.conversion.top', 'Reports - conversion top'
    UNION ALL SELECT 'system-information.bottom', 'System information - bottom'
    UNION ALL SELECT 'system-information.js', 'System information - JavaScript'
    UNION ALL SELECT 'system-information.top', 'System information - top'
    UNION ALL SELECT 'tools.col2-bottom', 'Tools - second column bottom'
    UNION ALL SELECT 'tools.col2-top', 'Tools - second column top'
    UNION ALL SELECT 'variable.edit-form', 'Variable - edit form'
    UNION ALL SELECT 'administrator.edit-form', 'Administrator - edit form'
    UNION ALL SELECT 'catalog-price-rule.delete-form', 'Catalog price rule - delete form'
    UNION ALL SELECT 'catalog-price-rule.table-header', 'Catalog price rule - table header'
    UNION ALL SELECT 'catalog-price-rule.table-row', 'Catalog price rule - table row'
    UNION ALL SELECT 'checkout-step.table-header', 'Checkout step - table header'
    UNION ALL SELECT 'checkout-step.table-row', 'Checkout step - table row'
    UNION ALL SELECT 'consent.table-header', 'Consent - table header'
    UNION ALL SELECT 'consent.table-row', 'Consent - table row'
    UNION ALL SELECT 'customer-title.delete-form', 'Customer title - delete form'
    UNION ALL SELECT 'customer.anonymize-form', 'Customer - anonymize form'
    UNION ALL SELECT 'gift-wrapping.table-header', 'Gift wrapping - table header'
    UNION ALL SELECT 'gift-wrapping.table-row', 'Gift wrapping - table row'
    UNION ALL SELECT 'product-association-type.table-header', 'Product association type - table header'
    UNION ALL SELECT 'product-association-type.table-row', 'Product association type - table row'
    UNION ALL SELECT 'tag.delete-form', 'Tag - delete form'
) AS `missing`
JOIN `hook` ON `hook`.`code` = `missing`.`code` AND `hook`.`type` = 2
JOIN (SELECT DISTINCT `locale` FROM `lang`) AS `lang`
WHERE NOT EXISTS (SELECT 1 FROM `hook_i18n` WHERE `hook_i18n`.`id` = `hook`.`id` AND `hook_i18n`.`locale` = `lang`.`locale`);

-- ---------------------------------------------------------------------
-- Shipping e-mail
--
-- The customer is told their order has left, with the carrier, the tracking
-- number and the tracking page, when the order enters the "sent" status. No
-- e-mail existed for it, so a shop that upgrades loses nothing by receiving it
-- switched on; INSERT IGNORE leaves alone a shop that already chose a value.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_shipped_email_enabled', '1', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_shipped_email_enabled' AS `name`, 'en_US' AS `locale`, 'Send the customer an e-mail when their order is shipped (1 = yes, 0 = no)' AS `title`
    UNION ALL SELECT 'order_shipped_email_enabled', 'fr_FR', 'Envoyer un e-mail au client quand sa commande est expédiée (1 = oui, 0 = non)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

INSERT IGNORE INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
    ('order_shipped', NULL, 'order_shipped.txt', 'order_shipped.html', NOW(), NOW());

-- Read back by name rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @order_shipped_message_id := (SELECT `id` FROM `message` WHERE `name` = 'order_shipped');

INSERT IGNORE INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
    (@order_shipped_message_id, 'cs_CZ', NULL, 'Vaše objednávka {{ order_ref }} byla odeslána'),
    (@order_shipped_message_id, 'de_DE', 'Versandbestätigung an den Kunden gesendet', 'Ihre Bestellung {{ order_ref }} wurde versandt'),
    (@order_shipped_message_id, 'en_US', 'Shipping notice sent to the customer', 'Your order {{ order_ref }} has been shipped'),
    (@order_shipped_message_id, 'es_ES', 'Aviso de envío enviado al cliente', 'Tu pedido {{ order_ref }} ha sido enviado'),
    (@order_shipped_message_id, 'fr_FR', 'Avis d''expédition envoyé au client', 'Votre commande {{ order_ref }} a été expédiée'),
    (@order_shipped_message_id, 'it_IT', NULL, 'Il tuo ordine {{ order_ref }} è stato spedito'),
    (@order_shipped_message_id, 'nl_NL', 'Verzendbericht naar de klant verzonden', 'Je bestelling {{ order_ref }} is verzonden'),
    (@order_shipped_message_id, 'ru_RU', 'Уведомление об отправке отправлено клиенту', 'Ваш заказ {{ order_ref }} отправлен');

-- ---------------------------------------------------------------------
-- Delivery day and slot picked by the buyer
--
-- A carrier that accepts dates lets the buyer pick a day, and a slot of that
-- day when it offers slots. The settings live in four tables; the choice is
-- held on the cart and copied on the order. A shop that updates gets the
-- tables empty: no carrier has a rule, so the delivery step is unchanged until
-- the merchant sets one.
--
-- Tables are created only if missing and every column, index and key is
-- guarded, so the script can be replayed.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `delivery_date_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `choice_mode` VARCHAR(16) DEFAULT 'none' NOT NULL COMMENT 'what the buyer picks: none, date or slot',
    `minimum_delay_days` INTEGER DEFAULT 0 NOT NULL COMMENT 'how many days after today the first day offered is',
    `horizon_days` INTEGER DEFAULT 30 NOT NULL COMMENT 'how many days after today the last day offered is',
    `closed_weekdays` VARCHAR(20) COMMENT 'the days of the week the carrier does not deliver, ISO numbers from 1 (Monday) to 7 (Sunday) separated by commas; NULL follows the shop days',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `delivery_date_rule_module_id_UNIQUE` (`module_id`),
    CONSTRAINT `fk_delivery_date_rule_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `start_time` TIME NOT NULL COMMENT 'the local hour the slot starts at',
    `end_time` TIME NOT NULL COMMENT 'the local hour the slot ends at',
    `capacity` INTEGER COMMENT 'how many orders the slot takes on a given day, NULL for no limit',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_delivery_slot_module_id` (`module_id`),
    CONSTRAINT `fk_delivery_slot_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `delivery_slot_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `delivery_slot` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_closure`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER COMMENT 'the carrier the closure applies to, NULL for the whole shop',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `label` VARCHAR(255) COMMENT 'a note for the merchant, never shown to the buyer',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_delivery_closure_module_id_end_date` (`module_id`, `end_date`),
    CONSTRAINT `fk_delivery_closure_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `delivery_slot_booking`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `delivery_slot_id` INTEGER NOT NULL,
    `delivery_date` DATE NOT NULL,
    `booked` INTEGER DEFAULT 0 NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `delivery_slot_booking_slot_date_UNIQUE` (`delivery_slot_id`, `delivery_date`),
    CONSTRAINT `fk_delivery_slot_booking_delivery_slot_id`
        FOREIGN KEY (`delivery_slot_id`)
        REFERENCES `delivery_slot` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `delivery_date` DATE NULL COMMENT ''the delivery day the buyer picked for the selected carrier, NULL while they picked none'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot of that day the buyer picked, when the carrier offers slots'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_delivery_slot_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_delivery_slot_id` (`delivery_slot_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_delivery_slot_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_delivery_slot_id` FOREIGN KEY (`delivery_slot_id`) REFERENCES `delivery_slot` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

-- The order keeps the day and the hours of the slot: a slot edited or deleted from the
-- carrier settings afterwards does not change what the order says. `order_version`
-- mirrors every column of `order`.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_date` DATE NULL COMMENT ''the day the buyer asked for, in the shop time zone, NULL when the carrier offered no date'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot the buyer picked, NULL once that slot is deleted from the carrier settings'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_start');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_start` TIME NULL COMMENT ''the local hour the picked slot started at, copied off the slot'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'delivery_slot_end');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `delivery_slot_end` TIME NULL COMMENT ''the local hour the picked slot ended at, copied off the slot'' AFTER `delivery_slot_start`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_date');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_date` DATE NULL COMMENT ''the day the buyer asked for, in the shop time zone, NULL when the carrier offered no date'' AFTER `gift_message`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_id` INTEGER NULL COMMENT ''the slot the buyer picked, NULL once that slot is deleted from the carrier settings'' AFTER `delivery_date`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_start');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_start` TIME NULL COMMENT ''the local hour the picked slot started at, copied off the slot'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'delivery_slot_end');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `delivery_slot_end` TIME NULL COMMENT ''the local hour the picked slot ended at, copied off the slot'' AFTER `delivery_slot_start`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'idx_order_delivery_slot_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `idx_order_delivery_slot_id` (`delivery_slot_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_constraint := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_delivery_slot_id');
SET @statement := IF(@add_constraint, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_delivery_slot_id` FOREIGN KEY (`delivery_slot_id`) REFERENCES `delivery_slot` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT', 'DO 0');
PREPARE add_constraint_statement FROM @statement;
EXECUTE add_constraint_statement;
DEALLOCATE PREPARE add_constraint_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.delivery-date', NOW(), NOW());

SET @delivery_date_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.delivery-date');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@delivery_date_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'en_US', 'Configuration delivery dates', NULL, NULL, NULL),
    (@delivery_date_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'fr_FR', 'Configuration des dates de livraison', NULL, NULL, NULL),
    (@delivery_date_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@delivery_date_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- German titles of the checkout steps
--
-- 3.1.0 named the four steps of the checkout in English, Spanish, French
-- and Italian only, while the shop ships German. A German storefront drew
-- its progress trail in the shop language instead ("Paiement" on a German
-- page of a French shop). The step is found by its code, as in 3.1.0, and
-- INSERT IGNORE keeps a German title the merchant already wrote, so the
-- statement can be replayed.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `checkout_step_i18n` (`id`, `locale`, `title`)
    SELECT `checkout_step`.`id`, 'de_DE', `wording`.`title`
    FROM `checkout_step`
    JOIN (
        SELECT 'cart' AS `code`, 'Ihr Warenkorb' AS `title`
        UNION ALL SELECT 'delivery', 'Lieferung'
        UNION ALL SELECT 'payment', 'Zahlung'
        UNION ALL SELECT 'confirmation', 'Bestätigung'
    ) AS `wording` ON `wording`.`code` = `checkout_step`.`code`;

-- ---------------------------------------------------------------------
-- Cumulative count of the returned quantity of an order line
--
-- 1, the rule until now: the returns already opened on a line hold part of
-- its quantity.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_return_cumulative_quantity', '1', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_return_cumulative_quantity' AS `name`, 'en_US' AS `locale`, 'Whether the returns already opened on an order line hold part of its quantity (1), or each return request is bounded by the ordered quantity alone (0)' AS `title`
    UNION ALL SELECT 'order_return_cumulative_quantity', 'fr_FR', 'Les retours déjà ouverts sur une ligne de commande retiennent une part de sa quantité (1), ou chaque demande de retour n''est bornée que par la quantité commandée (0)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Order statuses a return may be opened on
--
-- Empty: any paid status, the rule until now.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('order_return_order_statuses', '', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'order_return_order_statuses' AS `name`, 'en_US' AS `locale`, 'Order statuses a customer may open a return on, comma separated status codes (empty: any paid status)' AS `title`
    UNION ALL SELECT 'order_return_order_statuses', 'fr_FR', 'Statuts de commande sur lesquels un client peut ouvrir un retour, codes séparés par des virgules (vide : tout statut payé)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Shipments
--
-- An order may now leave in several parcels, each with its own lines,
-- tracking number and delivery note: a shipment. A shipment is a draft
-- while the merchant composes it, shipped once handed to the carrier,
-- cancelled when given up before it left. The tracking number the order
-- itself carries stays, the delivery modules keep writing it there.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `order_shipment`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `ref` VARCHAR(55) NOT NULL COMMENT 'the shipment number: the order reference followed by the rank of the shipment in the order',
    `status` VARCHAR(20) DEFAULT 'draft' NOT NULL COMMENT 'draft while being composed, shipped once handed to the carrier, cancelled when given up before it left',
    `delivery_module_id` INTEGER COMMENT 'the carrier module the parcel left with, NULL once that module is gone',
    `delivery_module_title` VARCHAR(255) COMMENT 'the carrier name snapshot, kept when the module is gone',
    `delivery_ref` VARCHAR(100) COMMENT 'the tracking number the carrier gave for this parcel',
    `shipped_at` DATETIME COMMENT 'when the parcel was handed to the carrier, NULL while it is a draft',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `order_shipment_ref_UNIQUE` (`ref`),
    INDEX `idx_order_shipment_order_id` (`order_id`),
    INDEX `idx_order_shipment_delivery_module_id` (`delivery_module_id`),
    CONSTRAINT `fk_order_shipment_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_shipment_delivery_module_id`
        FOREIGN KEY (`delivery_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `order_shipment_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_shipment_id` INTEGER NOT NULL,
    `order_product_id` INTEGER NOT NULL,
    `quantity` FLOAT NOT NULL COMMENT 'how much of the order line this shipment carries',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `order_shipment_item_line_UNIQUE` (`order_shipment_id`, `order_product_id`),
    INDEX `idx_order_shipment_item_order_shipment_id` (`order_shipment_id`),
    INDEX `idx_order_shipment_item_order_product_id` (`order_product_id`),
    CONSTRAINT `fk_order_shipment_item_order_shipment_id`
        FOREIGN KEY (`order_shipment_id`)
        REFERENCES `order_shipment` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_shipment_item_order_product_id`
        FOREIGN KEY (`order_product_id`)
        REFERENCES `order_product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- The partially shipped status
--
-- Where an order sits once some of its parcels left and some are still to
-- go. A custom status, declared equivalent to paid so that the stock, the
-- invoice and the documents keep behaving as on a paid order; the merchant
-- may rename it, recolour it or delete it. Listed after the existing ones.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `order_status` (`code`, `equivalent_code`, `color`, `position`, `protected_status`, `created_at`, `updated_at`)
SELECT 'partially_shipped', 'paid', '#20c997', `ranks`.`next_position`, 0, NOW(), NOW()
FROM (SELECT COALESCE(MAX(`position`), 0) + 1 AS `next_position` FROM `order_status`) AS `ranks`;

-- Read back by code rather than from LAST_INSERT_ID(): on a replay the insert
-- above is ignored and hands back no id at all.
SET @partially_shipped_status_id := (SELECT `id` FROM `order_status` WHERE `code` = 'partially_shipped');

INSERT IGNORE INTO `order_status_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
    (@partially_shipped_status_id, 'cs_CZ', 'Částečně odesláno', '', '', ''),
    (@partially_shipped_status_id, 'de_DE', 'Teilweise versandt', '', '', ''),
    (@partially_shipped_status_id, 'en_US', 'Partially shipped', '', '', ''),
    (@partially_shipped_status_id, 'es_ES', 'Enviado parcialmente', '', '', ''),
    (@partially_shipped_status_id, 'fr_FR', 'Partiellement expédiée', '', '', ''),
    (@partially_shipped_status_id, 'it_IT', 'Parzialmente spedito', '', '', ''),
    (@partially_shipped_status_id, 'nl_NL', 'Gedeeltelijk verzonden', '', '', ''),
    (@partially_shipped_status_id, 'ru_RU', 'Частично отправлен', '', '', '');

-- ---------------------------------------------------------------------
-- The orders that left before shipments existed
--
-- Each of them is shown as a single shipment carrying all its lines, so that
-- the customer account and the order sheet read the same whatever the age of
-- the order. An order in a sent status gets a shipped shipment, dated from its
-- last change; an order that only carries a tracking number, the label printed
-- but the order not marked sent, gets a draft, shipped when the order is.
--
-- Replayable: an order that already has a shipment is left alone, and only a
-- shipment with no line at all, which the application never writes, receives
-- the lines of its order.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `order_shipment` (`order_id`, `ref`, `status`, `delivery_module_id`, `delivery_module_title`, `delivery_ref`, `shipped_at`, `created_at`, `updated_at`)
SELECT
    `o`.`id`,
    CONCAT(`o`.`ref`, '-1'),
    IF(`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent', 'shipped', 'draft'),
    `m`.`id`,
    `o`.`delivery_module_title`,
    NULLIF(`o`.`delivery_ref`, ''),
    IF(`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent', `o`.`updated_at`, NULL),
    NOW(),
    NOW()
FROM `order` AS `o`
INNER JOIN `order_status` AS `s` ON `s`.`id` = `o`.`status_id`
LEFT JOIN `module` AS `m` ON `m`.`id` = `o`.`delivery_module_id`
WHERE (`s`.`code` = 'sent' OR `s`.`equivalent_code` = 'sent' OR (`o`.`delivery_ref` IS NOT NULL AND `o`.`delivery_ref` <> ''))
  AND NOT EXISTS (SELECT 1 FROM `order_shipment` AS `existing` WHERE `existing`.`order_id` = `o`.`id`);

INSERT IGNORE INTO `order_shipment_item` (`order_shipment_id`, `order_product_id`, `quantity`, `created_at`, `updated_at`)
SELECT `sh`.`id`, `op`.`id`, `op`.`quantity`, NOW(), NOW()
FROM `order_shipment` AS `sh`
INNER JOIN `order_product` AS `op` ON `op`.`order_id` = `sh`.`order_id`
WHERE NOT EXISTS (SELECT 1 FROM `order_shipment_item` AS `existing` WHERE `existing`.`order_shipment_id` = `sh`.`id`);

-- Shared cache for the public pages
--
-- A shop that upgrades keeps answering the way it did: the mode arrives
-- disabled, and INSERT IGNORE leaves alone a shop that already chose a value.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('shared_cache_mode', 'disabled', 0, 0, NOW(), NOW()),
    ('shared_cache_ttl', '300', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'shared_cache_mode' AS `name`, 'en_US' AS `locale`, 'Shared cache for anonymous public pages: disabled, announce (the page states how long a shared cache may keep it, cookies untouched) or enabled (also removes the cookies of an anonymous page so that a proxy can keep it)' AS `title`
    UNION ALL SELECT 'shared_cache_mode', 'fr_FR', 'Cache partagé des pages publiques anonymes : disabled (désactivé), announce (la page annonce combien de temps un cache partagé peut la garder, cookies inchangés) ou enabled (retire aussi les cookies d''une page anonyme pour qu''un proxy puisse la garder)'
    UNION ALL SELECT 'shared_cache_ttl', 'en_US', 'Seconds a shared cache may keep a public page (between 1 and 86400, 300 when invalid)'
    UNION ALL SELECT 'shared_cache_ttl', 'fr_FR', 'Secondes pendant lesquelles un cache partagé peut garder une page publique (entre 1 et 86400, 300 si invalide)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- The promotional price of an order line, a number again
--
-- `order_product`.`promo_price` is a VARCHAR(45) on a shop created by Thelia 2 and a
-- DECIMAL(16,6) on a fresh install, and no earlier script puts the first in the shape of
-- the second. The total of an order is computed by SQL, and ROUND() of a text reads it as
-- a floating point number: 63.325 rounds to 63.32, where the same price in the DECIMAL
-- column, and in the cart the buyer paid from, rounds to 63.33. An order whose promotional
-- line ends in a half cent was one cent short on its confirmation, its invoice and the
-- order list.
--
-- The values are numbers already, an empty text is read as no price. Nothing runs on a shop
-- whose column is a DECIMAL, so the script can be replayed.
--
-- The orders already placed keep the total of their invoice: the highest order id is stored
-- in last_text_promo_price_order_id before the conversion, and the orders up to it are
-- totalled with the promotional price read as a text, as they always were. The orders
-- placed afterwards read the DECIMAL. INSERT IGNORE keeps the first value when a
-- conversion that stopped halfway is run again.
-- ---------------------------------------------------------------------

SET @convert_column := (SELECT COUNT(*) > 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_product' AND `COLUMN_NAME` = 'promo_price' AND `DATA_TYPE` = 'varchar');
SET @statement := IF(@convert_column, 'INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) SELECT \'last_text_promo_price_order_id\', COALESCE(MAX(`id`), 0), 1, 1, NOW(), NOW() FROM `order`', 'DO 0');
PREPARE convert_column_statement FROM @statement;
EXECUTE convert_column_statement;
DEALLOCATE PREPARE convert_column_statement;

SET @statement := IF(@convert_column, 'UPDATE `order_product` SET `promo_price` = NULL WHERE `promo_price` = \'\'', 'DO 0');
PREPARE convert_column_statement FROM @statement;
EXECUTE convert_column_statement;
DEALLOCATE PREPARE convert_column_statement;

SET @statement := IF(@convert_column, 'ALTER TABLE `order_product` MODIFY `promo_price` DECIMAL(16,6) DEFAULT 0.000000', 'DO 0');
PREPARE convert_column_statement FROM @statement;
EXECUTE convert_column_statement;
DEALLOCATE PREPARE convert_column_statement;

-- ---------------------------------------------------------------------
-- Sale units
--
-- A sale unit is a place the shop sells from: a storefront on its own
-- domain, a pick-up store behind a shared one, a logical outlet an API
-- consumer addresses by header. The catalog stays national and shared;
-- a unit owns its prices, its stock and the orders placed on it.
--
-- The tables alone change nothing: with no active row, every code path
-- behaves exactly as before. The multi-sale-unit mode is on as soon as
-- one row is active.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `sale_unit`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'stable machine name a deployment references a sale unit by; never shown to a customer',
    `url` VARCHAR(255) COMMENT 'the domain that resolves to this sale unit, scheme-less (shop-a.example.com); null for a unit resolved by session or API header only',
    `active` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'an inactive unit is never resolved; the multi-sale-unit mode is on as soon as one unit is active',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_code_unique` (`code`),
    UNIQUE INDEX `sale_unit_url_unique` (`url`),
    INDEX `idx_sale_unit_active` (`active`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `sale_unit_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `sale_unit_i18n_fk_e2d6ae`
        FOREIGN KEY (`id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- What a sale unit sells at when it does not sell at the national price: one row
-- per (unit, sale element, currency), read with the exact semantics of
-- `product_price` — same columns, same from_default_currency conversion, same
-- obedience to `product_sale_elements.promo`. A sale element without a row keeps
-- the national price: the fallback is the mode of compatibility, not an error.
CREATE TABLE IF NOT EXISTS `sale_unit_product_price`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `promo_price` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `from_default_currency` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_product_price_unique` (`sale_unit_id`, `product_sale_elements_id`, `currency_id`),
    INDEX `idx_sale_unit_product_price_product_sale_elements_id` (`product_sale_elements_id`),
    INDEX `idx_sale_unit_product_price_currency_id` (`currency_id`),
    CONSTRAINT `fk_sale_unit_product_price_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_product_price_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_product_price_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The stock a sale unit keeps of its own: one row per (unit, sale element). A
-- sale element without a row for the unit decrements the shared national counter
-- `product_sale_elements.quantity` — right for a dematerialised or centralised
-- stock, a compatibility mode rather than the target for units with a stock of
-- their own, whose sale elements are expected to each carry a row.
CREATE TABLE IF NOT EXISTS `sale_unit_product_sale_elements`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `product_sale_elements_id` INTEGER NOT NULL,
    `quantity` DECIMAL(16,6) DEFAULT 0.000000 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_pse_unique` (`sale_unit_id`, `product_sale_elements_id`),
    INDEX `idx_sale_unit_pse_product_sale_elements_id` (`product_sale_elements_id`),
    CONSTRAINT `fk_sale_unit_pse_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_pse_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The store settings a sale unit sets for itself (its name, its sender, the
-- identity printed on its invoices), read instead of the national `config` row
-- of the same name. A setting without a row keeps the national value.
CREATE TABLE IF NOT EXISTS `sale_unit_config`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'one of the store settings a sale unit may set for itself (ConfigQuery::SALE_UNIT_SCOPED_NAMES)',
    `value` TEXT NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_config_unique` (`sale_unit_id`, `name`),
    CONSTRAINT `fk_sale_unit_config_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The values a sale unit gives the settings of a module, read by the module
-- instead of its national `module_config` row: the account of a payment
-- provider, the sender of a carrier. A secret value is never read back by the API.
CREATE TABLE IF NOT EXISTS `sale_unit_module_config`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_unit_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `value` TEXT,
    `secret` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'a secret value (a key, a password) is written and never read back by the API',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `sale_unit_module_config_unique` (`sale_unit_id`, `module_id`, `name`),
    INDEX `idx_sale_unit_module_config_module_id` (`module_id`),
    CONSTRAINT `fk_sale_unit_module_config_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_unit_module_config_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The sale unit an order was placed on, so a shop selling from many units can
-- tell whose order is whose. NULL for a national (single-shop) order, so an existing shop is
-- untouched. `order` is versionable: the column is mirrored on `order_version`.
-- Added with the information_schema guard MySQL 8 needs to replay the script.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit this order was placed on, NULL for a national (single-shop) order'' AFTER `delivery_slot_end`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `INDEX_NAME` = 'fi_order_sale_unit_id');
SET @statement := IF(@add_index, 'ALTER TABLE `order` ADD INDEX `fi_order_sale_unit_id` (`sale_unit_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_fk := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `CONSTRAINT_NAME` = 'fk_order_sale_unit_id');
SET @statement := IF(@add_fk, 'ALTER TABLE `order` ADD CONSTRAINT `fk_order_sale_unit_id` FOREIGN KEY (`sale_unit_id`) REFERENCES `sale_unit` (`id`) ON UPDATE RESTRICT ON DELETE RESTRICT', 'DO 0');
PREPARE add_fk_statement FROM @statement;
EXECUTE add_fk_statement;
DEALLOCATE PREPARE add_fk_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit this order was placed on, NULL for a national (single-shop) order'' AFTER `delivery_slot_end`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.sale-unit', NOW(), NOW());

-- One row per language the fresh install seeds, so the profile screen falls
-- back on the default language instead of showing no row at all.
SET @sale_unit_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.sale-unit');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@sale_unit_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@sale_unit_resource_id, 'de_DE', 'Verkaufseinheiten', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'en_US', 'Sale units', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'es_ES', 'Unidades de venta', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'fr_FR', 'Unités de vente', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'it_IT', 'Unità di vendita', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'nl_NL', 'Verkoopeenheden', NULL, NULL, NULL),
    (@sale_unit_resource_id, 'ru_RU', 'Точки продаж', NULL, NULL, NULL);

-- The sale unit a cart was last priced and stocked for. When the visitor moves
-- to another unit, the cart is checked again against that unit's prices and
-- stock. NULL for a national cart, so an existing shop is untouched.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'sale_unit_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD COLUMN `sale_unit_id` INTEGER NULL DEFAULT NULL COMMENT ''the sale unit the cart was last priced and stocked for, NULL for a national cart'' AFTER `delivery_slot_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_sale_unit_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_sale_unit_id` (`sale_unit_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_fk := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_sale_unit_id');
SET @statement := IF(@add_fk, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_sale_unit_id` FOREIGN KEY (`sale_unit_id`) REFERENCES `sale_unit` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_fk_statement FROM @statement;
EXECUTE add_fk_statement;
DEALLOCATE PREPARE add_fk_statement;

-- The sale units an administrator is restricted to. An administrator with no
-- row reaches every unit, as before; a superadministrator is never restricted.
-- A unit some administrator is restricted to cannot be deleted (RESTRICT):
-- the administrator would be left with no row, which reads as every unit.
CREATE TABLE IF NOT EXISTS `admin_sale_unit`
(
    `admin_id` INTEGER NOT NULL,
    `sale_unit_id` INTEGER NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`admin_id`,`sale_unit_id`),
    INDEX `idx_admin_sale_unit_sale_unit_id` (`sale_unit_id`),
    CONSTRAINT `fk_admin_sale_unit_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_admin_sale_unit_sale_unit_id`
        FOREIGN KEY (`sale_unit_id`)
        REFERENCES `sale_unit` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- Days an unpaid order holds the coupon usages it was placed with
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('coupon_unpaid_order_hold_days', '7', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'coupon_unpaid_order_hold_days' AS `name`, 'en_US' AS `locale`, 'Days an unpaid order holds the coupon usages it was placed with (0: an unpaid order holds none)' AS `title`
    UNION ALL SELECT 'coupon_unpaid_order_hold_days', 'fr_FR', 'Nombre de jours pendant lesquels une commande impayée retient les usages de coupon avec lesquels elle a été passée (0 : une commande impayée n''en retient aucun)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Availability, customer rating and price facets of a product listing
--
-- The same reach as the promo and newness rows of 3.2.0: a row of
-- `choice_filter_other` is what lets the category and the template screens
-- of the back office show, place and draw these facets. A fresh install
-- seeds them (setup/insert.sql); an upgraded shop gets them here.
--
-- Matched by `type`, never by id: a row is inserted only when none of that
-- type exists and takes the next free id. INSERT IGNORE keeps a title the
-- merchant already typed, one statement per facet so that each one is seen
-- seeding every locale. The rating facet only shows when a review module
-- provides the ratings, whatever this row says.
-- ---------------------------------------------------------------------
INSERT INTO `choice_filter_other` (`type`, `visible`)
    SELECT 'availability', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `choice_filter_other` WHERE `type` = 'availability');

INSERT INTO `choice_filter_other` (`type`, `visible`)
    SELECT 'rating', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `choice_filter_other` WHERE `type` = 'rating');

INSERT INTO `choice_filter_other` (`type`, `visible`)
    SELECT 'price', 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `choice_filter_other` WHERE `type` = 'price');

SET @availability_filter_id := (SELECT MIN(`id`) FROM `choice_filter_other` WHERE `type` = 'availability');
SET @rating_filter_id := (SELECT MIN(`id`) FROM `choice_filter_other` WHERE `type` = 'rating');
SET @price_filter_id := (SELECT MIN(`id`) FROM `choice_filter_other` WHERE `type` = 'price');

INSERT IGNORE INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (@availability_filter_id, 'cs_CZ', 'Dostupnost', NULL),
    (@availability_filter_id, 'de_DE', 'Verfügbarkeit', NULL),
    (@availability_filter_id, 'en_US', 'Availability', NULL),
    (@availability_filter_id, 'es_ES', 'Disponibilidad', NULL),
    (@availability_filter_id, 'fr_FR', 'Disponibilité', NULL),
    (@availability_filter_id, 'it_IT', 'Disponibilità', NULL),
    (@availability_filter_id, 'nl_NL', 'Beschikbaarheid', NULL),
    (@availability_filter_id, 'ru_RU', 'Наличие', NULL);

INSERT IGNORE INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (@rating_filter_id, 'cs_CZ', 'Hodnocení zákazníků', NULL),
    (@rating_filter_id, 'de_DE', 'Kundenbewertung', NULL),
    (@rating_filter_id, 'en_US', 'Customer rating', NULL),
    (@rating_filter_id, 'es_ES', 'Valoración de clientes', NULL),
    (@rating_filter_id, 'fr_FR', 'Note client', NULL),
    (@rating_filter_id, 'it_IT', 'Valutazione dei clienti', NULL),
    (@rating_filter_id, 'nl_NL', 'Klantbeoordeling', NULL),
    (@rating_filter_id, 'ru_RU', 'Оценка покупателей', NULL);

INSERT IGNORE INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (@price_filter_id, 'cs_CZ', 'Cena', NULL),
    (@price_filter_id, 'de_DE', 'Preis', NULL),
    (@price_filter_id, 'en_US', 'Price', NULL),
    (@price_filter_id, 'es_ES', 'Precio', NULL),
    (@price_filter_id, 'fr_FR', 'Prix', NULL),
    (@price_filter_id, 'it_IT', 'Prezzo', NULL),
    (@price_filter_id, 'nl_NL', 'Prijs', NULL),
    (@price_filter_id, 'ru_RU', 'Цена', NULL);

-- ---------------------------------------------------------------------
-- Shipping rules of a product
--
-- A surcharge added to the carrier postage, the product left out of the postage,
-- and the carriers that cannot take the product. Every column defaults to no
-- rule, so the postage of an upgraded shop does not move. The versioned copy of
-- the product follows the product table.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'shipping_surcharge');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `shipping_surcharge` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT \'fixed amount added to the carrier postage, untaxed, in the default currency\' AFTER `guest_checkout_forbidden`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'shipping_surcharge_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `shipping_surcharge_mode` TINYINT DEFAULT 0 NOT NULL COMMENT \'0: the surcharge is charged once per cart line, 1: once per unit\' AFTER `shipping_surcharge`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product' AND `COLUMN_NAME` = 'free_shipping');
SET @statement := IF(@add_column, 'ALTER TABLE `product` ADD `free_shipping` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product is left out of the postage the carrier quotes\' AFTER `shipping_surcharge_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'shipping_surcharge');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `shipping_surcharge` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT \'fixed amount added to the carrier postage, untaxed, in the default currency\' AFTER `guest_checkout_forbidden`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'shipping_surcharge_mode');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `shipping_surcharge_mode` TINYINT DEFAULT 0 NOT NULL COMMENT \'0: the surcharge is charged once per cart line, 1: once per unit\' AFTER `shipping_surcharge`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'product_version' AND `COLUMN_NAME` = 'free_shipping');
SET @statement := IF(@add_column, 'ALTER TABLE `product_version` ADD `free_shipping` TINYINT DEFAULT 0 NOT NULL COMMENT \'the product is left out of the postage the carrier quotes\' AFTER `shipping_surcharge_mode`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

CREATE TABLE IF NOT EXISTS `product_excluded_delivery_module`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `module_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `product_excluded_delivery_module_UNIQUE` (`product_id`, `module_id`),
    INDEX `idx_product_excluded_delivery_module_module_id` (`module_id`),
    CONSTRAINT `fk_product_excluded_delivery_module_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_excluded_delivery_module_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- Country titles the seed left out or wrote in French. The first statement fills a title
-- that is NULL or empty and keeps one the merchant typed; the second rewrites the French
-- wording the seed used to put in the English and Spanish rows, and only a title still
-- equal to it, so a title edited since stays as it is.
INSERT INTO `country_i18n` (`id`, `locale`, `title`)
SELECT `country`.`id`, `missing`.`locale`, `missing`.`title`
FROM `country`
INNER JOIN (
    SELECT 'UG' AS `isoalpha2`, 'es_ES' AS `locale`, 'Uganda' AS `title`
    UNION ALL SELECT 'US', 'es_ES', 'Estados Unidos'
    UNION ALL SELECT 'CA', 'es_ES', 'Canadá'
) AS `missing` ON `missing`.`isoalpha2` = `country`.`isoalpha2`
ON DUPLICATE KEY UPDATE `title` = IF(`country_i18n`.`title` IS NULL OR `country_i18n`.`title` = '', VALUES(`title`), `country_i18n`.`title`);

UPDATE `country_i18n`
INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
INNER JOIN (
    SELECT 'GF' AS `isoalpha2`, 'en_US' AS `locale`, 'Guyane Française' AS `old_title`, 'French Guiana' AS `title`
    UNION ALL SELECT 'RE', 'en_US', 'Réunion(La)', 'Réunion'
    UNION ALL SELECT 'NC', 'en_US', 'Nouvelle-Calédonie', 'New Caledonia'
    UNION ALL SELECT 'PF', 'en_US', 'Polynésie française', 'French Polynesia'
    UNION ALL SELECT 'RE', 'es_ES', 'Réunion(la)', 'Reunión'
    UNION ALL SELECT 'NC', 'es_ES', 'Nouvelle-Calédonie', 'Nueva Caledonia'
) AS `wording` ON `wording`.`isoalpha2` = `country`.`isoalpha2`
    AND `wording`.`locale` = `country_i18n`.`locale`
    AND `wording`.`old_title` = `country_i18n`.`title`
SET `country_i18n`.`title` = `wording`.`title`;

-- ---------------------------------------------------------------------
-- Discount of a public sale switched to a reserved audience
-- ---------------------------------------------------------------------
-- Until this release, a sale that ran publicly and was then reserved for named
-- customers set `promo` back to 0 on its products but left the discounted
-- `promo_price`, which the front API and the loops serve to every visitor. The
-- products of a reserved sale that no public promotion flags get their promo
-- price back to the catalog price, the value of a product nothing discounts.
-- The statement only matches a row still holding another value, so the
-- script can be replayed.

UPDATE `product_price` `pp`
INNER JOIN `product_sale_elements` `pse` ON `pse`.`id` = `pp`.`product_sale_elements_id`
SET `pp`.`promo_price` = `pp`.`price`
WHERE `pse`.`promo` = 0
  AND `pp`.`promo_price` <> `pp`.`price`
  AND `pse`.`product_id` IN (
      SELECT `sp`.`product_id`
      FROM `sale_product` `sp`
      INNER JOIN `sale` `s` ON `s`.`id` = `sp`.`sale_id`
      WHERE `s`.`audience_mode` <> 0
  );

-- ---------------------------------------------------------------------
-- Postal code rules of a delivery zone
--
-- A zone holds countries and states; these rules narrow it down to postal codes:
-- an exact code, a prefix or pattern with *, or a range "from...to", each one
-- including or excluding, optionally for one country of the zone only. A zone
-- without a rule accepts every postal code, as before.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `area_zip_code_rule`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `area_id` INTEGER NOT NULL,
    `country_id` INTEGER,
    `pattern` VARCHAR(50) NOT NULL,
    `excluded` TINYINT(1) DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_area_zip_code_rule_area_id` (`area_id`),
    INDEX `fi_area_zip_code_rule_country_id` (`country_id`),
    CONSTRAINT `fk_area_zip_code_rule_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_area_zip_code_rule_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- What the customer wishes to receive when a return expects an exchange, in their own words, apart
-- from the comment that explains the return. Null on every return that predates it: the wish, if
-- any, is in the comment. The versioned copy of a return carries it too.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_return' AND `COLUMN_NAME` = 'expected_exchange');
SET @statement := IF(@add_column, 'ALTER TABLE `order_return` ADD `expected_exchange` TEXT NULL COMMENT ''what the customer wishes to receive in exchange, in their own words'' AFTER `customer_comment`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_return_version' AND `COLUMN_NAME` = 'expected_exchange');
SET @statement := IF(@add_column, 'ALTER TABLE `order_return_version` ADD `expected_exchange` TEXT NULL COMMENT ''what the customer wishes to receive in exchange, in their own words'' AFTER `customer_comment`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ---------------------------------------------------------------------
-- Destination restrictions
--
-- A group of products that cannot ship to some countries or regions, or only to
-- some. A shop that upgrades has no group, so nothing it sells is refused.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `destination_restriction`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'the name the import and the API refer this group by',
    `mode` TINYINT DEFAULT 0 NOT NULL COMMENT '0: the listed destinations are refused, 1: only the listed destinations are served',
    `active` TINYINT DEFAULT 1 NOT NULL COMMENT 'a group turned off refuses nothing, and is kept with its products',
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `destination_restriction_code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `destination_restriction_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `reason` TEXT COMMENT 'why the products of the group do not ship there, shown to the buyer',
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `destination_restriction_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `destination_restriction` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `destination_restriction_country`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `destination_restriction_id` INTEGER NOT NULL,
    `country_id` INTEGER NOT NULL,
    `state_id` INTEGER COMMENT 'null: the whole country, its regions included',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_destination_restriction_country_restriction_id` (`destination_restriction_id`),
    INDEX `idx_destination_restriction_country_country_id` (`country_id`),
    INDEX `idx_destination_restriction_country_state_id` (`state_id`),
    CONSTRAINT `fk_destination_restriction_country_restriction_id`
        FOREIGN KEY (`destination_restriction_id`)
        REFERENCES `destination_restriction` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_destination_restriction_country_country_id`
        FOREIGN KEY (`country_id`)
        REFERENCES `country` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_destination_restriction_country_state_id`
        FOREIGN KEY (`state_id`)
        REFERENCES `state` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `product_destination_restriction`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_id` INTEGER NOT NULL,
    `destination_restriction_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `product_destination_restriction_UNIQUE` (`product_id`, `destination_restriction_id`),
    INDEX `idx_product_destination_restriction_restriction_id` (`destination_restriction_id`),
    CONSTRAINT `fk_product_destination_restriction_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_product_destination_restriction_restriction_id`
        FOREIGN KEY (`destination_restriction_id`)
        REFERENCES `destination_restriction` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.destination-restriction', NOW(), NOW());

SET @destination_restriction_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.destination-restriction');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@destination_restriction_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'de_DE', 'Konfiguration der Lieferbeschränkungen', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'en_US', 'Configuration destination restrictions', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'es_ES', 'Configuración de las restricciones de destino', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'fr_FR', 'Configuration des restrictions de destination', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'it_IT', 'Configurazione delle restrizioni di destinazione', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'nl_NL', 'Configuratie van de bestemmingsbeperkingen', NULL, NULL, NULL),
    (@destination_restriction_resource_id, 'ru_RU', 'Настройка ограничений по направлениям доставки', NULL, NULL, NULL);

-- The queue of the background jobs, and the jobs that failed for good. Read and
-- written by the Doctrine transport of Symfony Messenger, never by Propel. Nothing
-- is queued in it until MESSENGER_TRANSPORT_DSN names a transport: without one,
-- every job runs at once, as before.
CREATE TABLE IF NOT EXISTS `messenger_messages`
(
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `body` LONGTEXT NOT NULL,
    `headers` LONGTEXT NOT NULL,
    `queue_name` VARCHAR(190) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `available_at` DATETIME NOT NULL,
    `delivered_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_messenger_messages_queue_name_available_at` (`queue_name`, `available_at`, `delivered_at`, `id`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- An export asked for in the back office, run in the background: what it is
-- written with, how far it got, and where its file is once done.
CREATE TABLE IF NOT EXISTS `export_job`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `export_id` INTEGER NOT NULL,
    `admin_id` INTEGER COMMENT 'the admin who asked for the export, NULL once that admin is gone',
    `status` VARCHAR(20) DEFAULT 'queued' NOT NULL COMMENT 'queued, running, done or failed',
    `serializer` VARCHAR(100) NOT NULL COMMENT 'the id of the serializer the file is written with',
    `archiver` VARCHAR(100) COMMENT 'the id of the archiver the file is packed with, NULL for none',
    `lang_id` INTEGER,
    `include_images` TINYINT DEFAULT 0 NOT NULL,
    `include_documents` TINYINT DEFAULT 0 NOT NULL,
    `range_start` DATETIME,
    `range_end` DATETIME,
    `processed_rows` INTEGER DEFAULT 0 NOT NULL COMMENT 'the rows written so far',
    `file_path` VARCHAR(255) COMMENT 'where the finished file is, on the server',
    `file_name` VARCHAR(255) COMMENT 'the name the file is downloaded under',
    `error` TEXT COMMENT 'why the export failed, as shown to the administrator',
    `started_at` DATETIME,
    `finished_at` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_export_job_export_id` (`export_id`),
    INDEX `idx_export_job_created_at` (`created_at`),
    INDEX `fi_export_job_admin_id` (`admin_id`),
    INDEX `fi_export_job_lang_id` (`lang_id`),
    CONSTRAINT `fk_export_job_export_id`
        FOREIGN KEY (`export_id`)
        REFERENCES `export` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_export_job_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_export_job_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- An import asked for in the back office, run in the background: the uploaded
-- file it reads, how many rows it changed and which ones it refused.
CREATE TABLE IF NOT EXISTS `import_job`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `import_id` INTEGER NOT NULL,
    `admin_id` INTEGER COMMENT 'the admin who asked for the import, NULL once that admin is gone',
    `status` VARCHAR(20) DEFAULT 'queued' NOT NULL COMMENT 'queued, running, done or failed',
    `lang_id` INTEGER,
    `file_path` VARCHAR(255) NOT NULL COMMENT 'where the uploaded file waits for the import, on the server',
    `file_name` VARCHAR(255) NOT NULL COMMENT 'the name the file was uploaded under',
    `imported_rows` INTEGER DEFAULT 0 NOT NULL COMMENT 'the rows the import changed',
    `row_errors` TEXT COMMENT 'the rows the import refused and why, as a JSON list',
    `error` TEXT COMMENT 'why the import failed, as shown to the administrator',
    `started_at` DATETIME,
    `finished_at` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_import_job_import_id` (`import_id`),
    INDEX `idx_import_job_created_at` (`created_at`),
    INDEX `fi_import_job_admin_id` (`admin_id`),
    INDEX `fi_import_job_lang_id` (`lang_id`),
    CONSTRAINT `fk_import_job_import_id`
        FOREIGN KEY (`import_id`)
        REFERENCES `import` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_import_job_admin_id`
        FOREIGN KEY (`admin_id`)
        REFERENCES `admin` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_import_job_lang_id`
        FOREIGN KEY (`lang_id`)
        REFERENCES `lang` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The background jobs screen shows why a job failed, and the reason may carry
-- personal data: it answers to a resource of its own, granted to a profile.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.background-jobs', NOW(), NOW());

SET @background_jobs_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.background-jobs');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@background_jobs_resource_id, 'cs_CZ', 'Konfigurace úloh na pozadí', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'de_DE', 'Konfiguration der Hintergrundaufgaben', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'en_US', 'Configuration background jobs', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'es_ES', 'Configuración de tareas en segundo plano', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'fr_FR', 'Configuration des tâches en arrière-plan', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'it_IT', 'Configurazione delle attività in background', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'nl_NL', 'Configuratie van achtergrondtaken', NULL, NULL, NULL),
    (@background_jobs_resource_id, 'ru_RU', 'Настройка фоновых задач', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Stored payment methods
--
-- A customer can ask the shop to keep a payment method for the next order.
-- The provider keeps the card or the mandate; the shop keeps the opaque
-- reference the provider handed back and a label the payment module wrote
-- ("Visa ending 4242"), never a card number nor a security code. A module
-- cannot be removed while a method names it: removing the module asks it to
-- forget its methods at the provider, then drops them.
--
-- Replayable: the table is created only when missing.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `customer_payment_method`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `customer_id` INTEGER NOT NULL,
    `payment_module_id` INTEGER NOT NULL,
    `token` TEXT NOT NULL COMMENT 'opaque provider reference, never a card number',
    `label` VARCHAR(255) NOT NULL COMMENT 'what the customer sees, written by the payment module, never a full card number',
    `expires_at` DATE COMMENT 'the last day the provider accepts the method, NULL when it does not expire',
    `is_default` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'the method offered first; at most one per customer',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_customer_payment_method_customer_id` (`customer_id`),
    INDEX `fk_customer_payment_method_payment_module_id_idx` (`payment_module_id`),
    CONSTRAINT `fk_customer_payment_method_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `customer` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_customer_payment_method_payment_module_id`
        FOREIGN KEY (`payment_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The stored method a buyer chose to pay a cart with, NULL for a new one. Chosen with
-- the payment module, and reset with it; a method that goes away leaves the cart
-- paying with a new one.
--
-- Replayable: the column, its index and its foreign key are each added only when missing.
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `COLUMN_NAME` = 'customer_payment_method_id');
SET @statement := IF(@add_column, 'ALTER TABLE `cart` ADD `customer_payment_method_id` INTEGER NULL AFTER `payment_module_id`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `INDEX_NAME` = 'idx_cart_customer_payment_method_id');
SET @statement := IF(@add_index, 'ALTER TABLE `cart` ADD INDEX `idx_cart_customer_payment_method_id` (`customer_payment_method_id`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_foreign_key := (SELECT COUNT(*) = 0 FROM `information_schema`.`TABLE_CONSTRAINTS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'cart' AND `CONSTRAINT_NAME` = 'fk_cart_customer_payment_method_id');
SET @statement := IF(@add_foreign_key, 'ALTER TABLE `cart` ADD CONSTRAINT `fk_cart_customer_payment_method_id` FOREIGN KEY (`customer_payment_method_id`) REFERENCES `customer_payment_method` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL', 'DO 0');
PREPARE add_foreign_key_statement FROM @statement;
EXECUTE add_foreign_key_statement;
DEALLOCATE PREPARE add_foreign_key_statement;

-- The back office needs the resource to exist before a profile can be granted it.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.customer.payment-method', NOW(), NOW());

-- One row per language the fresh install seeds, so the profile screen falls
-- back on the default language instead of showing no row at all.
SET @customer_payment_method_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.customer.payment-method');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@customer_payment_method_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'de_DE', 'Gespeicherte Zahlungsmethoden', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'en_US', 'Saved payment methods', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'es_ES', 'Métodos de pago guardados', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'fr_FR', 'Moyens de paiement enregistrés', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'it_IT', 'Metodi di pagamento salvati', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'nl_NL', 'Opgeslagen betaalmethoden', NULL, NULL, NULL),
    (@customer_payment_method_resource_id, 'ru_RU', 'Сохранённые способы оплаты', NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- order_payment_part
--
-- One share of what an order is paid with, one row per payment means in
-- settlement order, so a gift card or a credit note can settle part of an
-- order before the payment module charges the balance. Removing a module
-- keeps the part and releases the module reference; the module deletion
-- first writes the module title on the parts that name it.
--
-- Replayable: the table is created only when missing.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `order_payment_part`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `payment_module_id` INTEGER,
    `payment_module_title` VARCHAR(255) COMMENT 'the name the payment module had when it was deleted, NULL while the module is still installed',
    `amount` DECIMAL(16,6) NOT NULL,
    `state` VARCHAR(16) DEFAULT 'pending' NOT NULL COMMENT 'one of OrderPaymentPartState',
    `is_primary` TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'the part that carries the order''s payment module; one per order',
    `position` INTEGER DEFAULT 0 NOT NULL COMMENT 'settlement order, ascending',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_order_payment_part_order_id_position` (`order_id`, `position`),
    INDEX `fk_order_payment_part_payment_module_id_idx` (`payment_module_id`),
    CONSTRAINT `fk_order_payment_part_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_order_payment_part_payment_module_id`
        FOREIGN KEY (`payment_module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- ---------------------------------------------------------------------
-- Visitor country
--
-- The shop can detect the country of an anonymous visitor, from a header set by
-- the CDN or from an IP database, and offer it or apply it. Off by default, so
-- a shop that upgrades changes nothing; INSERT IGNORE leaves alone a shop that
-- already chose a value.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
    ('visitor_country_mode', 'off', 0, 0, NOW(), NOW()),
    ('visitor_country_source', 'header', 0, 0, NOW(), NOW()),
    ('visitor_country_header', 'CF-IPCountry', 0, 0, NOW(), NOW()),
    ('visitor_country_database', '', 0, 0, NOW(), NOW());

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'visitor_country_mode' AS `name`, 'en_US' AS `locale`, 'Country detection of the visitor: off, suggest (offer the detected country to the visitor) or apply (use the detected country for taxes and address forms)' AS `title`
    UNION ALL SELECT 'visitor_country_mode', 'fr_FR', 'Détection du pays du visiteur : off (désactivée), suggest (proposer le pays détecté au visiteur) ou apply (utiliser le pays détecté pour les taxes et les formulaires d''adresse)'
    UNION ALL SELECT 'visitor_country_mode', 'de_DE', 'Länderermittlung des Besuchers: off (aus), suggest (dem Besucher das erkannte Land vorschlagen) oder apply (das erkannte Land für Steuern und Adressformulare verwenden)'
    UNION ALL SELECT 'visitor_country_mode', 'es_ES', 'Detección del país del visitante: off (desactivada), suggest (proponer al visitante el país detectado) o apply (usar el país detectado para los impuestos y los formularios de dirección)'
    UNION ALL SELECT 'visitor_country_mode', 'nl_NL', 'Landdetectie van de bezoeker: off (uit), suggest (het gedetecteerde land aan de bezoeker voorstellen) of apply (het gedetecteerde land gebruiken voor belastingen en adresformulieren)'
    UNION ALL SELECT 'visitor_country_mode', 'ru_RU', 'Определение страны посетителя: off (выключено), suggest (предложить посетителю найденную страну) или apply (использовать найденную страну для налогов и форм адреса)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'visitor_country_source' AS `name`, 'en_US' AS `locale`, 'Where the country of the visitor is read: header (a header set by the CDN or the proxy) or ip_database (a MaxMind database that the shop installs in local/geoip)' AS `title`
    UNION ALL SELECT 'visitor_country_source', 'fr_FR', 'Origine du pays du visiteur : header (un en-tête posé par le CDN ou le proxy) ou ip_database (une base MaxMind que la boutique installe dans local/geoip)'
    UNION ALL SELECT 'visitor_country_source', 'de_DE', 'Quelle des Besucherlands: header (ein vom CDN oder Proxy gesetzter Header) oder ip_database (eine MaxMind-Datenbank, die der Shop in local/geoip ablegt)'
    UNION ALL SELECT 'visitor_country_source', 'es_ES', 'Origen del país del visitante: header (una cabecera añadida por el CDN o el proxy) o ip_database (una base MaxMind que la tienda instala en local/geoip)'
    UNION ALL SELECT 'visitor_country_source', 'nl_NL', 'Bron van het land van de bezoeker: header (een header van de CDN of de proxy) of ip_database (een MaxMind-database die de winkel in local/geoip plaatst)'
    UNION ALL SELECT 'visitor_country_source', 'ru_RU', 'Источник страны посетителя: header (заголовок, добавленный CDN или прокси) или ip_database (база MaxMind, которую магазин размещает в local/geoip)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'visitor_country_header' AS `name`, 'en_US' AS `locale`, 'Name of the HTTP header that carries the country code of the visitor when the source is header (e.g. CF-IPCountry)' AS `title`
    UNION ALL SELECT 'visitor_country_header', 'fr_FR', 'Nom de l''en-tête HTTP qui porte le code pays du visiteur quand la source est header (ex. CF-IPCountry)'
    UNION ALL SELECT 'visitor_country_header', 'de_DE', 'Name des HTTP-Headers mit dem Ländercode des Besuchers, wenn die Quelle header ist (z. B. CF-IPCountry)'
    UNION ALL SELECT 'visitor_country_header', 'es_ES', 'Nombre de la cabecera HTTP que lleva el código de país del visitante cuando el origen es header (p. ej. CF-IPCountry)'
    UNION ALL SELECT 'visitor_country_header', 'nl_NL', 'Naam van de HTTP-header met de landcode van de bezoeker wanneer de bron header is (bijv. CF-IPCountry)'
    UNION ALL SELECT 'visitor_country_header', 'ru_RU', 'Имя HTTP-заголовка с кодом страны посетителя, если источник header (например, CF-IPCountry)'
) AS `labels` ON `labels`.`name` = `config`.`name`;

INSERT IGNORE INTO `config_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `config`.`id`, `labels`.`locale`, `labels`.`title`, NULL, NULL, NULL
FROM `config`
INNER JOIN (
    SELECT 'visitor_country_database' AS `name`, 'en_US' AS `locale`, 'File name of the MaxMind country database (.mmdb) inside local/geoip, when the source is ip_database' AS `title`
    UNION ALL SELECT 'visitor_country_database', 'fr_FR', 'Nom du fichier de la base pays MaxMind (.mmdb) dans local/geoip, quand la source est ip_database'
    UNION ALL SELECT 'visitor_country_database', 'de_DE', 'Dateiname der MaxMind-Länderdatenbank (.mmdb) in local/geoip, wenn die Quelle ip_database ist'
    UNION ALL SELECT 'visitor_country_database', 'es_ES', 'Nombre del archivo de la base de países MaxMind (.mmdb) dentro de local/geoip, cuando el origen es ip_database'
    UNION ALL SELECT 'visitor_country_database', 'nl_NL', 'Bestandsnaam van de MaxMind-landendatabase (.mmdb) in local/geoip, wanneer de bron ip_database is'
    UNION ALL SELECT 'visitor_country_database', 'ru_RU', 'Имя файла базы стран MaxMind (.mmdb) в local/geoip, если источник ip_database'
) AS `labels` ON `labels`.`name` = `config`.`name`;

-- ---------------------------------------------------------------------
-- Publication of contents and folders
--
-- A content or a folder is a draft, scheduled, published or unpublished.
-- The `visible` column stays the public truth; `publish_at` and
-- `unpublish_at` carry the dates the state is read from, on the row and on
-- its versions. Every row the shop already holds gets a state once: a
-- visible row is published since its creation, a hidden row is unpublished
-- since its last change. No row is ever turned into a draft, and the dates
-- are only written on the run that adds the columns, so a replay leaves a
-- draft written since then alone.
-- ---------------------------------------------------------------------

SET @content_publication_added := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content' AND `COLUMN_NAME` = 'publish_at');
SET @folder_publication_added := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder' AND `COLUMN_NAME` = 'publish_at');

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content' AND `COLUMN_NAME` = 'publish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `content` ADD `publish_at` DATETIME NULL COMMENT ''when the content went or goes online; NULL on a draft'' AFTER `position`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content' AND `COLUMN_NAME` = 'unpublish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `content` ADD `unpublish_at` DATETIME NULL COMMENT ''when the content went or goes offline; NULL while nothing takes it offline'' AFTER `publish_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_version' AND `COLUMN_NAME` = 'publish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `content_version` ADD `publish_at` DATETIME NULL COMMENT ''when the content went or goes online; NULL on a draft'' AFTER `position`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content_version' AND `COLUMN_NAME` = 'unpublish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `content_version` ADD `unpublish_at` DATETIME NULL COMMENT ''when the content went or goes offline; NULL while nothing takes it offline'' AFTER `publish_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder' AND `COLUMN_NAME` = 'publish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `folder` ADD `publish_at` DATETIME NULL COMMENT ''when the folder went or goes online; NULL on a draft'' AFTER `position`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder' AND `COLUMN_NAME` = 'unpublish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `folder` ADD `unpublish_at` DATETIME NULL COMMENT ''when the folder went or goes offline; NULL while nothing takes it offline'' AFTER `publish_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_version' AND `COLUMN_NAME` = 'publish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `folder_version` ADD `publish_at` DATETIME NULL COMMENT ''when the folder went or goes online; NULL on a draft'' AFTER `position`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder_version' AND `COLUMN_NAME` = 'unpublish_at');
SET @statement := IF(@add_column, 'ALTER TABLE `folder_version` ADD `unpublish_at` DATETIME NULL COMMENT ''when the folder went or goes offline; NULL while nothing takes it offline'' AFTER `publish_at`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- The scheduled publication command and the back-office filter read the rows by their dates.
SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content' AND `INDEX_NAME` = 'idx_content_publish_at');
SET @statement := IF(@add_index, 'ALTER TABLE `content` ADD INDEX `idx_content_publish_at` (`publish_at`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'content' AND `INDEX_NAME` = 'idx_content_unpublish_at');
SET @statement := IF(@add_index, 'ALTER TABLE `content` ADD INDEX `idx_content_unpublish_at` (`unpublish_at`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder' AND `INDEX_NAME` = 'idx_folder_publish_at');
SET @statement := IF(@add_index, 'ALTER TABLE `folder` ADD INDEX `idx_folder_publish_at` (`publish_at`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

SET @add_index := (SELECT COUNT(*) = 0 FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'folder' AND `INDEX_NAME` = 'idx_folder_unpublish_at');
SET @statement := IF(@add_index, 'ALTER TABLE `folder` ADD INDEX `idx_folder_unpublish_at` (`unpublish_at`)', 'DO 0');
PREPARE add_index_statement FROM @statement;
EXECUTE add_index_statement;
DEALLOCATE PREPARE add_index_statement;

-- The state of what the shop already holds: visible rows are published since their
-- creation, hidden rows unpublished since their last change. Only on the run that
-- adds the columns, so a draft or a schedule written since then survives a replay.
SET @statement := IF(@content_publication_added, 'UPDATE `content` SET `publish_at` = COALESCE(`created_at`, NOW()) WHERE `visible` = 1 AND `publish_at` IS NULL', 'DO 0');
PREPARE migrate_statement FROM @statement;
EXECUTE migrate_statement;
DEALLOCATE PREPARE migrate_statement;

SET @statement := IF(@content_publication_added, 'UPDATE `content` SET `unpublish_at` = COALESCE(`updated_at`, `created_at`, NOW()) WHERE (`visible` IS NULL OR `visible` <> 1) AND `unpublish_at` IS NULL', 'DO 0');
PREPARE migrate_statement FROM @statement;
EXECUTE migrate_statement;
DEALLOCATE PREPARE migrate_statement;

SET @statement := IF(@folder_publication_added, 'UPDATE `folder` SET `publish_at` = COALESCE(`created_at`, NOW()) WHERE `visible` = 1 AND `publish_at` IS NULL', 'DO 0');
PREPARE migrate_statement FROM @statement;
EXECUTE migrate_statement;
DEALLOCATE PREPARE migrate_statement;

SET @statement := IF(@folder_publication_added, 'UPDATE `folder` SET `unpublish_at` = COALESCE(`updated_at`, `created_at`, NOW()) WHERE (`visible` IS NULL OR `visible` <> 1) AND `unpublish_at` IS NULL', 'DO 0');
PREPARE migrate_statement FROM @statement;
EXECUTE migrate_statement;
DEALLOCATE PREPARE migrate_statement;
-- Webhook subscriptions send data to a third-party address: they answer to a
-- resource of their own, granted to a profile and to no existing one.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.webhook', NOW(), NOW());

SET @webhook_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.webhook');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@webhook_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@webhook_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@webhook_resource_id, 'en_US', 'Configuration webhooks', NULL, NULL, NULL),
    (@webhook_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@webhook_resource_id, 'fr_FR', 'Configuration des webhooks', NULL, NULL, NULL),
    (@webhook_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@webhook_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@webhook_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- Webhook subscriptions and their delivery log
--
-- `webhook_subscription` is an address, the published events it asks for, a
-- secret and a state; `webhook_delivery` is one row per request sent, with
-- the answer and the body that was signed so that a delivery can be replayed.
-- A shop that updates has no subscription, so nothing is sent until an
-- administrator declares one.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `webhook_subscription`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL COMMENT 'what the administrator calls this subscription',
    `url` VARCHAR(2048) NOT NULL COMMENT 'the address that receives the signed requests',
    `secret` VARCHAR(128) NOT NULL COMMENT 'the shared secret that signs every request, shown once at creation',
    `events` TEXT NOT NULL COMMENT 'JSON list of the published event names this subscription asks for',
    `payload_mode` VARCHAR(16) DEFAULT 'full' NOT NULL COMMENT 'full: the object as the admin API shows it; identifiers: its identifiers only',
    `status` VARCHAR(16) DEFAULT 'active' NOT NULL COMMENT 'active, inactive, or error once the retries of a delivery ran out',
    `failure_reason` VARCHAR(255) COMMENT 'why the subscription fell into error, never a secret',
    `failed_at` DATETIME,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_webhook_subscription_status` (`status`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `webhook_delivery`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `subscription_id` INTEGER NOT NULL,
    `event` VARCHAR(64) NOT NULL COMMENT 'the published event name',
    `delivery_uuid` VARCHAR(36) NOT NULL COMMENT 'the unique identifier sent in the headers, so a receiver can drop a duplicate',
    `status` VARCHAR(16) DEFAULT 'pending' NOT NULL COMMENT 'pending, succeeded, failed, or cancelled when the subscription stopped receiving before the request was sent',
    `attempts` INTEGER DEFAULT 0 NOT NULL,
    `response_code` INTEGER COMMENT 'the HTTP code the receiver answered, empty when it never answered',
    `duration_ms` INTEGER COMMENT 'how long the last attempt took, in milliseconds',
    `response_body` TEXT COMMENT 'the beginning of the answer, truncated',
    `payload` LONGTEXT NOT NULL COMMENT 'the body that was signed, kept so that a delivery can be replayed and purged with the log',
    `delivered_at` DATETIME,
    `replayed_at` DATETIME COMMENT 'when an administrator last replayed this delivery, so a failure of the replay does not put the subscription in error',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `webhook_delivery_uuid_UNIQUE` (`delivery_uuid`),
    INDEX `idx_webhook_delivery_subscription_created` (`subscription_id`, `created_at`),
    INDEX `idx_webhook_delivery_created_at` (`created_at`),
    INDEX `idx_webhook_delivery_event_created` (`event`, `created_at`),
    INDEX `idx_webhook_delivery_status_created` (`status`, `created_at`),
    INDEX `idx_webhook_delivery_subscription_status_delivered` (`subscription_id`, `status`, `delivered_at`),
    CONSTRAINT `fk_webhook_delivery_subscription_id`
        FOREIGN KEY (`subscription_id`)
        REFERENCES `webhook_subscription` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- The AI layer spends money and sends shop data to a provider: it answers to a
-- resource of its own, granted to a profile and to no existing one.
INSERT IGNORE INTO `resource` (`code`, `created_at`, `updated_at`) VALUES
    ('admin.configuration.ai', NOW(), NOW());

SET @ai_resource_id := (SELECT `id` FROM `resource` WHERE `code` = 'admin.configuration.ai');

INSERT IGNORE INTO `resource_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`) VALUES
    (@ai_resource_id, 'cs_CZ', NULL, NULL, NULL, NULL),
    (@ai_resource_id, 'de_DE', NULL, NULL, NULL, NULL),
    (@ai_resource_id, 'en_US', 'Configuration AI', NULL, NULL, NULL),
    (@ai_resource_id, 'es_ES', NULL, NULL, NULL, NULL),
    (@ai_resource_id, 'fr_FR', 'Configuration de l''IA', NULL, NULL, NULL),
    (@ai_resource_id, 'it_IT', NULL, NULL, NULL, NULL),
    (@ai_resource_id, 'nl_NL', NULL, NULL, NULL, NULL),
    (@ai_resource_id, 'ru_RU', NULL, NULL, NULL, NULL);

-- ---------------------------------------------------------------------
-- AI layer: connections, model catalogue, tiers, usage settings, usage log
--
-- `ai_connection` holds a provider endpoint and its encrypted key; `ai_model`
-- the catalogue with prices per million tokens; `ai_tier_assignment` the model
-- the merchant picked for each tier; `ai_usage_setting` what the merchant set
-- for a usage a module declares; `ai_usage_log` one row per call or external
-- cost; `ai_budget_reservation` the cost held by the calls in progress.
-- A shop that updates has no connection, so no usage runs until an
-- administrator adds one.
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `ai_connection`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(64) NOT NULL COMMENT 'the stable name modules and the log refer to',
    `title` VARCHAR(255) NOT NULL COMMENT 'what the administrator calls this connection',
    `provider` VARCHAR(32) NOT NULL COMMENT 'mistral, anthropic, openai, openai_compatible, scaleway, ovhcloud, albert, ollama or thelia_ai',
    `jurisdiction` VARCHAR(8) NOT NULL COMMENT 'eu or non_eu: derived from the provider and the base URL, declared by the administrator for openai_compatible and ollama',
    `base_url` VARCHAR(2048) COMMENT 'the endpoint, empty for the default one of the provider',
    `api_key_encrypted` TEXT COMMENT 'the API key encrypted with a key derived from the application secret, never returned by the API',
    `api_key_env` VARCHAR(128) COMMENT 'the environment variable whose value overrides the stored key',
    `allow_private_network` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '1 lets the endpoint resolve to a private network, for a self-hosted server',
    `active` TINYINT(1) DEFAULT 1 NOT NULL,
    `checked_at` DATETIME COMMENT 'when the connection test last ran',
    `check_error` VARCHAR(255) COMMENT 'what the last connection test answered when it failed, never the key',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ai_connection_code_UNIQUE` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `ai_model`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `provider` VARCHAR(32) NOT NULL COMMENT 'the provider that serves the model, as in ai_connection.provider',
    `name` VARCHAR(128) NOT NULL COMMENT 'the identifier the provider gives the model',
    `title` VARCHAR(255),
    `input_price` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT 'price of a million input tokens, in currency',
    `output_price` DECIMAL(16,6) DEFAULT 0 NOT NULL COMMENT 'price of a million output tokens, in currency',
    `cache_read_price` DECIMAL(16,6) COMMENT 'price of a million input tokens read from the cache, empty when the provider has no cache',
    `cache_write_price` DECIMAL(16,6) COMMENT 'price of a million input tokens written to the cache',
    `currency` VARCHAR(3) DEFAULT 'USD' NOT NULL,
    `context_window` INTEGER COMMENT 'tokens the model reads at most',
    `max_output_tokens` INTEGER COMMENT 'tokens the model writes at most',
    `supports_structured_output` TINYINT(1) DEFAULT 0 NOT NULL,
    `supports_tools` TINYINT(1) DEFAULT 0 NOT NULL,
    `supports_vision` TINYINT(1) DEFAULT 0 NOT NULL,
    `supports_streaming` TINYINT(1) DEFAULT 1 NOT NULL,
    `source` VARCHAR(16) DEFAULT 'catalog' NOT NULL COMMENT 'catalog: from the embedded price list; manual: added or edited by the administrator',
    `active` TINYINT(1) DEFAULT 1 NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ai_model_provider_name_UNIQUE` (`provider`, `name`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `ai_tier_assignment`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `tier` VARCHAR(16) NOT NULL COMMENT 'fast, standard or advanced',
    `connection_id` INTEGER NOT NULL,
    `model_id` INTEGER NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ai_tier_assignment_tier_UNIQUE` (`tier`),
    INDEX `idx_ai_tier_assignment_connection_id` (`connection_id`),
    INDEX `idx_ai_tier_assignment_model_id` (`model_id`),
    CONSTRAINT `fk_ai_tier_assignment_connection_id`
        FOREIGN KEY (`connection_id`)
        REFERENCES `ai_connection` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,
    CONSTRAINT `fk_ai_tier_assignment_model_id`
        FOREIGN KEY (`model_id`)
        REFERENCES `ai_model` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `ai_usage_setting`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `usage_id` VARCHAR(128) NOT NULL COMMENT 'the identifier a module declares, like theliacms.page_generation',
    `enabled` TINYINT(1) DEFAULT 1 NOT NULL,
    `monthly_budget` DECIMAL(16,6) COMMENT 'what the usage may spend in a calendar month, in currency; empty: no budget of its own, a public usage stays inactive',
    `currency` VARCHAR(3) DEFAULT 'USD' NOT NULL,
    `requests_per_minute` INTEGER COMMENT 'requests an actor may send in a minute, empty: the default of the audience',
    `requests_per_day` INTEGER COMMENT 'requests an actor may send in a day, empty: the default of the audience',
    `max_output_tokens` INTEGER COMMENT 'the output limit of every request, empty: the limit of the model',
    `pinned_connection_id` INTEGER COMMENT 'a connection that replaces the one of the tier for this usage',
    `pinned_model_id` INTEGER COMMENT 'a model that replaces the one of the tier for this usage',
    `guardrails` TEXT COMMENT 'JSON object of the optional guardrails: name to block or annotate, empty: those the usage declares',
    `transparency_notice` TINYINT(1) DEFAULT 1 NOT NULL COMMENT '0 hides the notice of an interactive usage (AI Act 50(1)): allowed, the usage then shows as not compliant',
    `provenance_mark` TINYINT(1) DEFAULT 1 NOT NULL COMMENT '0 drops the provenance mark of a generative usage (AI Act 50(2)): allowed, the usage then shows as not compliant',
    `store_prompt` TINYINT(1) DEFAULT 0 NOT NULL COMMENT '1 keeps the prompt of each call in the log',
    `prompt_retention_days` INTEGER COMMENT 'days a stored prompt is kept before the maintenance purge removes it',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `ai_usage_setting_usage_id_UNIQUE` (`usage_id`),
    INDEX `idx_ai_usage_setting_pinned_connection_id` (`pinned_connection_id`),
    INDEX `idx_ai_usage_setting_pinned_model_id` (`pinned_model_id`),
    CONSTRAINT `fk_ai_usage_setting_pinned_connection_id`
        FOREIGN KEY (`pinned_connection_id`)
        REFERENCES `ai_connection` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL,
    CONSTRAINT `fk_ai_usage_setting_pinned_model_id`
        FOREIGN KEY (`pinned_model_id`)
        REFERENCES `ai_model` (`id`)
        ON UPDATE RESTRICT
        ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `ai_usage_log`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `usage_id` VARCHAR(128) NOT NULL,
    `module` VARCHAR(55) NOT NULL COMMENT 'the namespace of the usage identifier, the module code in lower case',
    `kind` VARCHAR(16) NOT NULL COMMENT 'completion, stream, or external for a cost a module paid outside the gateway',
    `connection_code` VARCHAR(64) COMMENT 'the code of the connection, kept as text so that the log outlives the connection',
    `provider` VARCHAR(32) COMMENT 'the provider, or the paid service of an external cost',
    `model` VARCHAR(128) COMMENT 'the model, or the operation of an external cost',
    `input_tokens` INTEGER DEFAULT 0 NOT NULL,
    `output_tokens` INTEGER DEFAULT 0 NOT NULL,
    `cache_read_tokens` INTEGER DEFAULT 0 NOT NULL,
    `cache_write_tokens` INTEGER DEFAULT 0 NOT NULL,
    `cost` DECIMAL(16,8) DEFAULT 0 NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'USD' NOT NULL,
    `actor_type` VARCHAR(16) NOT NULL COMMENT 'admin, customer, visitor or system',
    `actor_hash` VARCHAR(64) NOT NULL COMMENT 'HMAC-SHA256 of the actor with the application secret: counts an actor, never names one',
    `status` VARCHAR(16) NOT NULL COMMENT 'succeeded, failed, blocked, rejected or aborted',
    `error` VARCHAR(255) COMMENT 'why the call was refused or failed, never the prompt',
    `finish_reason` VARCHAR(16) COMMENT 'completed, max_tokens, tool_calls or content_filter',
    `latency_ms` INTEGER,
    `guardrail_verdicts` TEXT COMMENT 'JSON list of the guardrail verdicts',
    `correlation_id` VARCHAR(64) COMMENT 'groups the turns of one task, like the steps of a tool loop',
    `prompt` LONGTEXT COMMENT 'the request as sent, only when the usage stores prompts',
    `prompt_expires_at` DATETIME COMMENT 'when the maintenance purge empties the prompt',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_ai_usage_log_usage_created` (`usage_id`, `created_at`, `cost`),
    INDEX `idx_ai_usage_log_created` (`created_at`, `cost`),
    INDEX `idx_ai_usage_log_status_created` (`status`, `created_at`),
    INDEX `idx_ai_usage_log_correlation_id` (`correlation_id`),
    INDEX `idx_ai_usage_log_prompt_expires_at` (`prompt_expires_at`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `ai_budget_reservation`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `usage_id` VARCHAR(128) NOT NULL,
    `amount` DECIMAL(16,8) NOT NULL COMMENT 'the highest cost the call can reach, held until the call is logged',
    `currency` VARCHAR(3) DEFAULT 'USD' NOT NULL,
    `expires_at` DATETIME NOT NULL COMMENT 'after this date the reservation no longer counts, so that a crashed call does not hold the budget',
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_ai_budget_reservation_usage_expires` (`usage_id`, `expires_at`),
    INDEX `idx_ai_budget_reservation_expires_at` (`expires_at`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
