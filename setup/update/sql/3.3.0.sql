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

SET FOREIGN_KEY_CHECKS = 1;
