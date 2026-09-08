-- ---------------------------------------------------------------------
-- Product returns (RMA) feature: tables, reference data and settings.
-- Brings an existing shop to the same state as a fresh install, with
-- the feature disabled by default.
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `order_return_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(45) NOT NULL,
  `equivalent_code` varchar(45) DEFAULT NULL,
  `color` char(7) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `protected_status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_status_code_UNIQUE` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_reason` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(45) DEFAULT NULL,
  `position` int(11) DEFAULT NULL,
  `visible` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_reason_code_UNIQUE` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ref` varchar(45) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `reason_id` int(11) DEFAULT NULL COMMENT 'the merchant-managed reason, NULL once that reason is deleted',
  `reason_title` varchar(255) DEFAULT NULL COMMENT 'the reason label snapshot, kept when the reason is deleted',
  `expected_resolution` varchar(45) DEFAULT NULL COMMENT 'what the customer expects: refund, credit or exchange',
  `customer_comment` text DEFAULT NULL,
  `refusal_reason` text DEFAULT NULL COMMENT 'the motive given by the merchant when the request is refused',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the amount to refund, computed on the paid prices of the returned lines',
  `include_postage` tinyint(1) DEFAULT 0 COMMENT 'whether the postage is included in the refundable amount',
  `created_by_admin` tinyint(1) DEFAULT 0 COMMENT 'true when the merchant opened the return without a customer request',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `version` int(11) DEFAULT 0,
  `version_created_at` timestamp NULL DEFAULT NULL,
  `version_created_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_return_ref_UNIQUE` (`ref`),
  KEY `idx_order_return_order_id` (`order_id`),
  KEY `idx_order_return_customer_id` (`customer_id`),
  KEY `idx_order_return_status_id` (`status_id`),
  KEY `idx_order_return_reason_id` (`reason_id`),
  CONSTRAINT `fk_order_return_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customer` (`id`),
  CONSTRAINT `fk_order_return_order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`),
  CONSTRAINT `fk_order_return_reason_id` FOREIGN KEY (`reason_id`) REFERENCES `order_return_reason` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_order_return_status_id` FOREIGN KEY (`status_id`) REFERENCES `order_return_status` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_line` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_return_id` int(11) NOT NULL,
  `order_product_id` int(11) NOT NULL,
  `product_sale_elements_id` int(11) DEFAULT NULL COMMENT 'the sale element to restock, snapshot from the order product',
  `quantity` float NOT NULL COMMENT 'the quantity the customer asks to return',
  `quantity_received` float DEFAULT 0 COMMENT 'the quantity actually received by the merchant',
  `received_condition` varchar(45) DEFAULT NULL COMMENT 'the condition the returned goods were received in',
  `resellable` tinyint(1) DEFAULT 0 COMMENT 'whether the received goods can be sold again',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the paid taxed price for the returned quantity of this line',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_return_line_order_return_id` (`order_return_id`),
  KEY `idx_order_return_line_order_product_id` (`order_product_id`),
  CONSTRAINT `fk_order_return_line_order_product_id` FOREIGN KEY (`order_product_id`) REFERENCES `order_product` (`id`),
  CONSTRAINT `fk_order_return_line_order_return_id` FOREIGN KEY (`order_return_id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_status_i18n` (
  `id` int(11) NOT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en_US',
  `title` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `chapo` text DEFAULT NULL,
  `postscriptum` text DEFAULT NULL,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `order_return_status_i18n_fk_2bef6d` FOREIGN KEY (`id`) REFERENCES `order_return_status` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_reason_i18n` (
  `id` int(11) NOT NULL,
  `locale` varchar(5) NOT NULL DEFAULT 'en_US',
  `title` varchar(255) DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `order_return_reason_i18n_fk_c07ca8` FOREIGN KEY (`id`) REFERENCES `order_return_reason` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
CREATE TABLE IF NOT EXISTS `order_return_version` (
  `id` int(11) NOT NULL,
  `ref` varchar(45) DEFAULT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `status_id` int(11) NOT NULL,
  `reason_id` int(11) DEFAULT NULL COMMENT 'the merchant-managed reason, NULL once that reason is deleted',
  `reason_title` varchar(255) DEFAULT NULL COMMENT 'the reason label snapshot, kept when the reason is deleted',
  `expected_resolution` varchar(45) DEFAULT NULL COMMENT 'what the customer expects: refund, credit or exchange',
  `customer_comment` text DEFAULT NULL,
  `refusal_reason` text DEFAULT NULL COMMENT 'the motive given by the merchant when the request is refused',
  `refund_amount` decimal(16,6) DEFAULT 0.000000 COMMENT 'the amount to refund, computed on the paid prices of the returned lines',
  `include_postage` tinyint(1) DEFAULT 0 COMMENT 'whether the postage is included in the refundable amount',
  `created_by_admin` tinyint(1) DEFAULT 0 COMMENT 'true when the merchant opened the return without a customer request',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 0,
  `version_created_at` timestamp NULL DEFAULT NULL,
  `version_created_by` varchar(100) DEFAULT NULL,
  `order_id_version` int(11) DEFAULT 0,
  `customer_id_version` int(11) DEFAULT 0,
  PRIMARY KEY (`id`,`version`),
  CONSTRAINT `order_return_version_fk_6cd0c8` FOREIGN KEY (`id`) REFERENCES `order_return` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- The versionable behaviour of order_return adds a referrer snapshot to the
-- version tables of its versionable relations (order and customer). Without
-- these columns Propel selects a column that does not exist.
ALTER TABLE `customer_version`
  ADD COLUMN IF NOT EXISTS `order_return_ids` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `order_return_versions` TEXT DEFAULT NULL;
ALTER TABLE `order_version`
  ADD COLUMN IF NOT EXISTS `order_return_ids` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `order_return_versions` TEXT DEFAULT NULL;

-- ---------------------------------------------------------------------
-- Reference data for the product returns feature.
-- The tables are new, so the status and reason ids are fixed; config,
-- message and resource rows are auto-incremented to avoid colliding with
-- rows an existing shop already owns.
-- ---------------------------------------------------------------------

INSERT INTO `order_return_status` (`id`, `code`, `color`, `position`, `protected_status`, `created_at`, `updated_at`) VALUES
(1, 'requested', '#f39922', 1, 1, NOW(), NOW()),
(2, 'info_awaited', '#5bc0de', 2, 1, NOW(), NOW()),
(3, 'accepted', '#5cb85c', 3, 1, NOW(), NOW()),
(4, 'refused', '#dc3545', 4, 1, NOW(), NOW()),
(5, 'received', '#986dff', 5, 1, NOW(), NOW()),
(6, 'settled', '#20c997', 6, 1, NOW(), NOW()),
(7, 'expired', '#6c757d', 7, 1, NOW(), NOW());

INSERT INTO `order_return_status_i18n` (`id`, `locale`, `title`) VALUES
(1, 'en_US', 'Requested'), (1, 'fr_FR', 'Demandé'),
(2, 'en_US', 'Information awaited'), (2, 'fr_FR', 'Information attendue'),
(3, 'en_US', 'Accepted'), (3, 'fr_FR', 'Accepté'),
(4, 'en_US', 'Refused'), (4, 'fr_FR', 'Refusé'),
(5, 'en_US', 'Received'), (5, 'fr_FR', 'Reçu'),
(6, 'en_US', 'Settled'), (6, 'fr_FR', 'Soldé'),
(7, 'en_US', 'Expired'), (7, 'fr_FR', 'Expiré');

INSERT INTO `order_return_reason` (`id`, `code`, `position`, `visible`, `created_at`, `updated_at`) VALUES
(1, 'not_conform', 1, 1, NOW(), NOW()),
(2, 'defective', 2, 1, NOW(), NOW()),
(3, 'wrong_item', 3, 1, NOW(), NOW()),
(4, 'no_longer_needed', 4, 1, NOW(), NOW()),
(5, 'other', 5, 1, NOW(), NOW());

INSERT INTO `order_return_reason_i18n` (`id`, `locale`, `title`) VALUES
(1, 'en_US', 'Product not as described'), (1, 'fr_FR', 'Produit non conforme'),
(2, 'en_US', 'Defective product'), (2, 'fr_FR', 'Produit défectueux'),
(3, 'en_US', 'Wrong item received'), (3, 'fr_FR', 'Article erroné reçu'),
(4, 'en_US', 'No longer needed'), (4, 'fr_FR', 'Plus nécessaire'),
(5, 'en_US', 'Other'), (5, 'fr_FR', 'Autre');

-- The feature ships disabled on an updated shop, exactly like on a new one.
INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('order_return_enabled', '0', 0, 0, NOW(), NOW()),
('order_return_window_days', '14', 0, 0, NOW(), NOW()),
('order_return_restock_mode', 'resellable', 0, 0, NOW(), NOW());

INSERT INTO `message` (`name`, `secured`, `text_template_file_name`, `html_template_file_name`, `created_at`, `updated_at`)
VALUES ('order_return_status_changed', NULL, 'order_return_status_changed.txt', 'order_return_status_changed.html', NOW(), NOW());
SET @order_return_message_id = LAST_INSERT_ID();
INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`) VALUES
(@order_return_message_id, 'en_US', 'Return status update sent to the customer', 'Update on your return {{ return_ref }}'),
(@order_return_message_id, 'fr_FR', 'Notification de changement de statut de retour envoyée au client', 'Suivi de votre retour {{ return_ref }}');

INSERT INTO resource (`code`, `created_at`, `updated_at`) VALUES
('admin.order-return', NOW(), NOW()),
('admin.configuration.order-return-reason', NOW(), NOW());
INSERT INTO `resource_i18n` (`id`, `locale`, `title`)
SELECT id, 'en_US', 'Product returns' FROM resource WHERE code = 'admin.order-return'
UNION ALL SELECT id, 'fr_FR', 'Retours produits' FROM resource WHERE code = 'admin.order-return'
UNION ALL SELECT id, 'en_US', 'Return reasons' FROM resource WHERE code = 'admin.configuration.order-return-reason'
UNION ALL SELECT id, 'fr_FR', 'Motifs de retour' FROM resource WHERE code = 'admin.configuration.order-return-reason';

SET FOREIGN_KEY_CHECKS = 1;
