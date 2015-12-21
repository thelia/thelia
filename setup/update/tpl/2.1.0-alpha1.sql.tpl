SET FOREIGN_KEY_CHECKS = 0;

UPDATE `config` SET `value`='2.1.0-alpha1' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='1' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='alpha1' WHERE `name`='thelia_extra_version';


# ======================================================================================================================
# Add sale related tables
# ======================================================================================================================

-- ---------------------------------------------------------------------
-- sale
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale`;

CREATE TABLE `sale`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `active` TINYINT(1) DEFAULT 0 NOT NULL,
    `display_initial_price` TINYINT(1) DEFAULT 1 NOT NULL,
    `start_date` DATETIME,
    `end_date` DATETIME,
    `price_offset_type` TINYINT,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `idx_sales_active_start_end_date` (`active`, `start_date`, `end_date`),
    INDEX `idx_sales_active` (`active`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_i18n`;

CREATE TABLE `sale_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    `postscriptum` TEXT,
    `sale_label` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `sale_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `sale` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_offset_currency
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_offset_currency`;

CREATE TABLE `sale_offset_currency`
(
    `sale_id` INTEGER NOT NULL,
    `currency_id` INTEGER NOT NULL,
    `price_offset_value` FLOAT DEFAULT 0,
    PRIMARY KEY (`sale_id`,`currency_id`),
    INDEX `fk_sale_offset_currency_currency1_idx` (`currency_id`),
    CONSTRAINT `fk_sale_offset_currency_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_offset_currency_currency_id`
        FOREIGN KEY (`currency_id`)
        REFERENCES `currency` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- sale_product
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `sale_product`;

CREATE TABLE `sale_product`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `sale_id` INTEGER NOT NULL,
    `product_id` INTEGER NOT NULL,
    `attribute_av_id` INTEGER,
    PRIMARY KEY (`id`),
    INDEX `fk_sale_product_product_idx` (`product_id`),
    INDEX `fk_sale_product_attribute_av_idx` (`attribute_av_id`),
    INDEX `idx_sale_product_sales_id_product_id` (`sale_id`, `product_id`),
    CONSTRAINT `fk_sale_product_sales_id`
        FOREIGN KEY (`sale_id`)
        REFERENCES `sale` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_product_product_id`
        FOREIGN KEY (`product_id`)
        REFERENCES `product` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_sale_product_attribute_av_id`
        FOREIGN KEY (`attribute_av_id`)
        REFERENCES `attribute_av` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';


# ======================================================================================================================
# Product sale elements images and documents
# ======================================================================================================================

-- ---------------------------------------------------------------------
-- product_sale_elements_product_image
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_sale_elements_product_image`;

CREATE TABLE `product_sale_elements_product_image`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_image_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_pse_product_image_product_image_id_idx` (`product_image_id`),
    INDEX `fk_pse_product_image_product_sale_element_idx` (`product_sale_elements_id`),
    CONSTRAINT `fk_pse_product_image_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`),
    CONSTRAINT `fk_pse_product_image_product_image_id`
        FOREIGN KEY (`product_image_id`)
        REFERENCES `product_image` (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- product_sale_elements_product_document
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `product_sale_elements_product_document`;

CREATE TABLE `product_sale_elements_product_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `product_sale_elements_id` INTEGER NOT NULL,
    `product_document_id` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `fk_pse_product_document_product_document__idx` (`product_document_id`),
    INDEX `fk_pse_product_document_product_sale_elem_idx` (`product_sale_elements_id`),
    CONSTRAINT `fk_pse_product_document_product_sale_elements_id`
        FOREIGN KEY (`product_sale_elements_id`)
        REFERENCES `product_sale_elements` (`id`),
    CONSTRAINT `fk_pse_product_document_product_document_id`
        FOREIGN KEY (`product_document_id`)
        REFERENCES `product_document` (`id`)
) ENGINE=InnoDB CHARACTER SET='utf8';


# ======================================================================================================================
# Hooks
# ======================================================================================================================

SELECT @max_pos := IFNULL(MAX(`position`),0) FROM `module`;
SELECT @max_id := IFNULL(MAX(`id`),0) FROM `module`;

INSERT INTO `module` (`id`, `code`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES
  (@max_id+1, 'HookNavigation', 1, 1, @max_pos+1, 'HookNavigation\\HookNavigation', NOW(), NOW()),
  (@max_id+2, 'HookCurrency', 1, 1, @max_pos+2, 'HookCurrency\\HookCurrency', NOW(), NOW()),
  (@max_id+3, 'HookLang', 1, 1, @max_pos+3, 'HookLang\\HookLang', NOW(), NOW()),
  (@max_id+4, 'HookSearch', 1, 1, @max_pos+4, 'HookSearch\\HookSearch', NOW(), NOW()),
  (@max_id+5, 'HookCustomer', 1, 1, @max_pos+5, 'HookCustomer\\HookCustomer', NOW(), NOW()),
  (@max_id+6, 'HookCart', 1, 1, @max_pos+6, 'HookCart\\HookCart', NOW(), NOW()),
  (@max_id+7, 'HookAnalytics', 1, 1, @max_pos+7, 'HookAnalytics\\HookAnalytics', NOW(), NOW()),
  (@max_id+8, 'HookContact', 1, 1, @max_pos+8, 'HookContact\\HookContact', NOW(), NOW()),
  (@max_id+9, 'HookLinks', 1, 1, @max_pos+9, 'HookLinks\\HookLinks', NOW(), NOW()),
  (@max_id+10, 'HookNewsletter', 1, 1, @max_pos+10, 'HookNewsletter\\HookNewsletter', NOW(), NOW()),
  (@max_id+11, 'HookSocial', 1, 1, @max_pos+11, 'HookSocial\\HookSocial', NOW(), NOW()),
  (@max_id+12, 'HookProductsNew', 1, 1, @max_pos+12, 'HookProductsNew\\HookProductsNew', NOW(), NOW()),
  (@max_id+13, 'HookProductsOffer', 1, 1, @max_pos+13, 'HookProductsOffer\\HookProductsOffer', NOW(), NOW())
;

INSERT INTO  `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
  (@max_id+1, '{$locale}', {intl l='Navigation block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+2, '{$locale}', {intl l='Currency block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+3, '{$locale}', {intl l='Languages block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+4, '{$locale}', {intl l='Search block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+5, '{$locale}', {intl l='Customer account block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+6, '{$locale}', {intl l='Cart block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+7, '{$locale}', {intl l='Google Analytics block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+8, '{$locale}', {intl l='Contact block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+9, '{$locale}', {intl l='Links block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+10, '{$locale}', {intl l='Newsletter block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+11, '{$locale}', {intl l='Social Networks block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+12, '{$locale}', {intl l='New Products block' locale=$locale}, NULL,  NULL,  NULL),
  (@max_id+13, '{$locale}', {intl l='Products offer block' locale=$locale}, NULL,  NULL,  NULL){if ! $locale@last},{/if}

{/foreach}
;



-- ---------------------------------------------------------------------
-- hook
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `hook`;

CREATE TABLE `hook`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(255) NOT NULL,
    `type` TINYINT,
    `by_module` TINYINT(1),
    `native` TINYINT(1),
    `activate` TINYINT(1),
    `block` TINYINT(1),
    `position` INTEGER,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `code_UNIQUE` (`code`, `type`),
    INDEX `idx_module_activate` (`activate`)
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_hook
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_hook`;

CREATE TABLE `module_hook`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `module_id` INTEGER NOT NULL,
    `hook_id` INTEGER NOT NULL,
    `classname` VARCHAR(255),
    `method` VARCHAR(255),
    `active` TINYINT(1) NOT NULL,
    `hook_active` TINYINT(1) NOT NULL,
    `module_active` TINYINT(1) NOT NULL,
    `position` INTEGER NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_module_hook_active` (`active`),
    INDEX `fk_module_hook_module_id_idx` (`module_id`),
    INDEX `fk_module_hook_hook_id_idx` (`hook_id`),
    CONSTRAINT `fk_module_hook_module_id`
        FOREIGN KEY (`module_id`)
        REFERENCES `module` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE,
    CONSTRAINT `fk_module_hook_hook_id`
        FOREIGN KEY (`hook_id`)
        REFERENCES `hook` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';


-- ---------------------------------------------------------------------
-- hook_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `hook_i18n`;

CREATE TABLE `hook_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` LONGTEXT,
    `chapo` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `hook_i18n_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `hook` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';


INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
  (1, 'order-invoice.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2, 'order-invoice.delivery-address', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (3, 'order-invoice.payment-extra', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (4, 'order-invoice.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (5, 'order-invoice.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (6, 'order-invoice.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (7, 'order-invoice.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (8, 'order-payment-gateway.body', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (9, 'order-payment-gateway.javascript', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (10, 'order-payment-gateway.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (11, 'order-payment-gateway.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (12, 'order-payment-gateway.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (13, 'sitemap.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (14, 'currency.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (15, 'currency.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (16, 'currency.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (17, 'currency.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (18, 'currency.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (19, 'login.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (20, 'login.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (21, 'login.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (22, 'login.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (23, 'login.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (24, 'login.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (25, 'login.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (26, 'login.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (27, 'login.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (28, 'account-update.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (29, 'account-update.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (30, 'account-update.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (31, 'account-update.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (32, 'account-update.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (33, 'account-update.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (34, 'account-update.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (35, 'cart.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (36, 'cart.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (37, 'cart.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (38, 'cart.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (39, 'cart.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (40, 'contact.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (41, 'contact.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (42, 'contact.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (43, 'contact.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (44, 'contact.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (45, 'contact.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (46, 'contact.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (47, 'order-placed.body', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (48, 'order-placed.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (49, 'order-placed.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (50, 'order-placed.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (51, 'search.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (52, 'search.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (53, 'search.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (54, 'register.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (55, 'register.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (56, 'register.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (57, 'register.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (58, 'register.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (59, 'register.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (60, 'register.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (61, 'password.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (62, 'password.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (63, 'password.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (64, 'password.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (65, 'password.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (66, 'password.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (67, 'password.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (68, 'language.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (69, 'language.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (70, 'language.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (71, 'language.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (72, 'language.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (73, 'contact.success', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (74, 'newsletter.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (75, 'newsletter.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (76, 'newsletter.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (77, 'newsletter.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (78, 'newsletter.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (79, 'badresponseorder.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (80, 'badresponseorder.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (81, 'badresponseorder.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (82, 'content.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (83, 'content.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (84, 'content.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (85, 'content.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (86, 'content.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (87, 'content.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (88, 'content.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (89, 'main.head-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (90, 'main.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (91, 'main.head-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (92, 'main.body-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (93, 'main.header-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (94, 'main.navbar-secondary', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (95, 'main.navbar-primary', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (96, 'main.header-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (97, 'main.content-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (98, 'main.content-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (99, 'main.footer-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (100, 'main.footer-body', 1, 0, 1, 1, 1, 1, NOW(), NOW()),
  (101, 'main.footer-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (102, 'main.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (103, 'main.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (104, 'main.body-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (105, '404.content', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (106, '404.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (107, '404.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (108, '404.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (109, 'order-delivery.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (110, 'order-delivery.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (111, 'order-delivery.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (112, 'order-delivery.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (113, 'order-delivery.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (114, 'order-delivery.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (115, 'order-delivery.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (116, 'address-create.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (117, 'address-create.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (118, 'address-create.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (119, 'address-create.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (120, 'address-create.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (121, 'address-create.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (122, 'address-create.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (123, 'folder.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (124, 'folder.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (125, 'folder.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (126, 'folder.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (127, 'folder.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (128, 'folder.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (129, 'folder.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (130, 'order-failed.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (131, 'order-failed.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (132, 'order-failed.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (133, 'order-failed.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (134, 'order-failed.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (135, 'category.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (136, 'category.main-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (137, 'category.main-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (138, 'category.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (139, 'category.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (140, 'category.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (141, 'category.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (142, 'address-update.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (143, 'address-update.form-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (144, 'address-update.form-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (145, 'address-update.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (146, 'address-update.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (147, 'address-update.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (148, 'address-update.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (149, 'home.body', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (150, 'home.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (151, 'home.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (152, 'home.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (153, 'account-password.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (154, 'account-password.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (155, 'account-password.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (156, 'account-password.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (157, 'account-password.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (158, 'product.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (159, 'product.gallery', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (160, 'product.details-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (161, 'product.details-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (162, 'product.additional', 1, 0, 1, 1, 1, 1, NOW(), NOW()),
  (163, 'product.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (164, 'product.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (165, 'product.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (166, 'product.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (167, 'account.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (168, 'account.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (169, 'account.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (170, 'account.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (171, 'account.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (172, 'viewall.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (173, 'viewall.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (174, 'viewall.stylesheet', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (175, 'viewall.after-javascript-include', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (176, 'viewall.javascript-initialization', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (177, 'singleproduct.top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (178, 'singleproduct.bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (179, 'category.sidebar-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (180, 'category.sidebar-body', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (181, 'category.sidebar-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (182, 'content.sidebar-top', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (183, 'content.sidebar-body', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (184, 'content.sidebar-bottom', 1, 0, 0, 1, 1, 1, NOW(), NOW()),
  (185, 'order-delivery.extra', 1, 1, 0, 1, 1, 1, NOW(), NOW()),
  (186, 'order-delivery.javascript', 1, 1, 0, 1, 1, 1, NOW(), NOW()),

  (1000, 'category.tab-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1001, 'content.tab-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1002, 'folder.tab-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1003, 'order.tab-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1004, 'product.tab-content', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1005, 'features-value.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1006, 'features-value.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1007, 'feature.value-create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1008, 'feature.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1009, 'product.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1010, 'coupon.create-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1011, 'taxes.update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1012, 'tax-rule.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1013, 'tools.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1014, 'tools.col1-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1015, 'tools.col1-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1016, 'tools.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1017, 'tools.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1018, 'messages.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1019, 'messages.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1020, 'messages.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1021, 'messages.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1022, 'message.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1023, 'message.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1024, 'messages.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1025, 'taxes-rules.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1026, 'taxes-rules.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1027, 'tax.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1028, 'tax.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1029, 'tax-rule.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1030, 'tax-rule.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1031, 'taxes-rules.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1032, 'exports.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1033, 'exports.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1034, 'exports.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1035, 'exports.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1036, 'export.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1037, 'product.folders-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1038, 'product.folders-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1039, 'product.details-pricing-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1040, 'product.details-details-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1041, 'product.details-promotion-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1042, 'product.before-combinations', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1043, 'product.combinations-list-caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1044, 'product.after-combinations', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1045, 'product.combination-delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1046, 'modules.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1047, 'modules.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1048, 'currency.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1049, 'category.contents-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1050, 'category.contents-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1051, 'category.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1052, 'document.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1053, 'customer.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1054, 'customers.caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1055, 'customers.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1056, 'customers.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1057, 'customer.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1058, 'customer.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1059, 'customer.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1060, 'customers.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1061, 'product.contents-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1062, 'product.contents-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1063, 'product.accessories-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1064, 'product.accessories-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1065, 'product.categories-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1066, 'product.categories-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1067, 'product.attributes-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1068, 'product.attributes-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1069, 'product.features-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1070, 'product.features-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1071, 'template.attributes-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1072, 'template.attributes-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1073, 'template.features-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1074, 'template.features-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1075, 'templates.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1076, 'templates.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1077, 'templates.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1078, 'templates.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1079, 'template.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1080, 'template.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1081, 'templates.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1082, 'configuration.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1083, 'configuration.catalog-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1084, 'configuration.catalog-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1085, 'configuration.shipping-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1086, 'configuration.shipping-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1087, 'configuration.system-top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1088, 'configuration.system-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1089, 'configuration.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1090, 'configuration.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1091, 'index.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1092, 'index.middle', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1093, 'index.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1094, 'orders.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1095, 'orders.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1096, 'orders.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1097, 'orders.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1098, 'orders.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1099, 'shipping-zones.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1100, 'shipping-zones.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1101, 'shipping-zones.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1102, 'shipping-zones.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1103, 'shipping-zones.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1104, 'content.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1105, 'home.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1106, 'home.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1107, 'home.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1108, 'modules.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1109, 'modules.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1110, 'modules.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1111, 'languages.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1112, 'languages.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1113, 'language.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1114, 'languages.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1115, 'languages.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1116, 'zone.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1117, 'shipping-zones.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1118, 'system.logs-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1119, 'search.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1120, 'search.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1121, 'search.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1122, 'administrators.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1123, 'administrators.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1124, 'administrator.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1125, 'administrator.update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1126, 'administrator.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1127, 'administrators.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1128, 'module-hook.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1129, 'shipping-configuration.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1130, 'shipping-configuration.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1131, 'shipping-configuration.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1132, 'shipping-configuration.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1133, 'shipping-configuration.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1134, 'shipping-configuration.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1135, 'shipping-configuration.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1136, 'features.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1137, 'features.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1138, 'features.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1139, 'features.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1140, 'feature.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1141, 'feature.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1142, 'feature.add-to-all-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1143, 'feature.remove-to-all-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1144, 'features.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1145, 'module.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1146, 'module-hook.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1147, 'module-hook.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1148, 'module-hook.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1149, 'shipping-configuration.edit', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1150, 'shipping-configuration.country-delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1151, 'shipping-configuration.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1152, 'mailing-system.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1153, 'mailing-system.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1154, 'mailing-system.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1155, 'categories.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1156, 'categories.caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1157, 'categories.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1158, 'categories.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1159, 'products.caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1160, 'products.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1161, 'products.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1162, 'categories.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1163, 'categories.catalog-bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1164, 'category.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1165, 'product.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1166, 'category.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1167, 'product.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1168, 'categories.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1169, 'variables.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1170, 'variables.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1171, 'variables.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1172, 'variables.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1173, 'variable.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1174, 'variable.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1175, 'variables.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1176, 'order.product-list', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1177, 'order.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1178, 'config-store.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1179, 'translations.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1180, 'folders.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1181, 'folders.caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1182, 'folders.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1183, 'folders.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1184, 'contents.caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1185, 'contents.header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1186, 'contents.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1187, 'folders.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1188, 'folder.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1189, 'content.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1190, 'folder.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1191, 'content.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1192, 'folders.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1193, 'template.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1194, 'tax.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1195, 'hook.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1196, 'countries.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1197, 'countries.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1198, 'countries.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1199, 'countries.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1200, 'country.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1201, 'country.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1202, 'countries.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1203, 'currencies.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1204, 'currencies.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1205, 'currencies.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1206, 'currencies.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1207, 'currency.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1208, 'currency.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1209, 'currencies.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1210, 'customer.edit', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1211, 'customer.address-create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1212, 'customer.address-update-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1213, 'customer.address-delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1214, 'customer.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1215, 'attributes-value.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1216, 'attributes-value.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1217, 'attribute-value.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1218, 'attribute.id-delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1219, 'attribute.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1220, 'profiles.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1221, 'profiles.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1222, 'profile.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1223, 'profile.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1224, 'profiles.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1225, 'country.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1226, 'profile.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1227, 'variable.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1228, 'coupon.update-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1229, 'coupon.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1230, 'coupon.list-caption', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1231, 'coupon.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1232, 'coupon.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1233, 'coupon.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1234, 'coupon.list-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1235, 'module.configuration', 2, 1, 0, 1, 1, 1, NOW(), NOW()),
  (1236, 'module.config-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1237, 'message.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1238, 'image.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1239, 'attributes.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1240, 'attributes.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1241, 'attributes.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1242, 'attributes.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1243, 'attribute.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1244, 'attribute.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1245, 'attribute.add-to-all-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1246, 'attribute.remove-to-all-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1247, 'attributes.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1248, 'admin-logs.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1249, 'admin-logs.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1250, 'admin-logs.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1251, 'folder.edit-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1252, 'hooks.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1253, 'hooks.table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1254, 'hooks.table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1255, 'hooks.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1256, 'hook.create-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1257, 'hook.delete-form', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1258, 'hooks.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1259, 'main.head-css', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1260, 'main.before-topbar', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1261, 'main.inside-topbar', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1262, 'main.after-topbar', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1263, 'main.before-top-menu', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1264, 'main.in-top-menu-items', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1265, 'main.after-top-menu', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1266, 'main.before-footer', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1267, 'main.in-footer', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1268, 'main.after-footer', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1269, 'main.footer-js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1270, 'main.topbar-top', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1271, 'main.topbar-bottom', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1272, 'main.top-menu-customer', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1273, 'main.top-menu-order', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1274, 'main.top-menu-catalog', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1275, 'main.top-menu-content', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1276, 'main.top-menu-tools', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1277, 'main.top-menu-modules', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1278, 'main.top-menu-configuration', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1279, 'brand.edit-js', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1280, 'home.block', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1281, 'brands.top', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1282, 'brands.table-header', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1283, 'brands.table-row', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1284, 'brands.bottom', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1285, 'brand.create-form', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1286, 'brand.delete-form', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1287, 'brand.js', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1288, 'imports.top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1289, 'imports.row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1290, 'imports.bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1291, 'imports.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1292, 'import.js', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1293, 'brand.tab-content', 2, 0, 1, 1, 1, 1, NOW(), NOW()),
  (1294, 'customer.orders-table-header', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
  (1295, 'customer.orders-table-row', 2, 0, 0, 1, 1, 1, NOW(), NOW()),

  (2001, 'invoice.css', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2002, 'invoice.header', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2003, 'invoice.footer-top', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2004, 'invoice.imprint', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2005, 'invoice.footer-bottom', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2006, 'invoice.information', 3, 0, 1, 1, 1, 1, NOW(), NOW()),
  (2007, 'invoice.after-information', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2008, 'invoice.delivery-address', 3, 1, 0, 1, 1, 1, NOW(), NOW()),
  (2009, 'invoice.after-addresses', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2010, 'invoice.after-products', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2011, 'invoice.after-summary', 3, 0, 0, 1, 1, 1, NOW(), NOW()),

  (2012, 'delivery.css', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2013, 'delivery.header', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2014, 'delivery.footer-top', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2015, 'delivery.imprint', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2016, 'delivery.footer-bottom', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2017, 'delivery.information', 3, 0, 1, 1, 1, 1, NOW(), NOW()),
  (2018, 'delivery.after-information', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2019, 'delivery.delivery-address', 3, 1, 0, 1, 1, 1, NOW(), NOW()),
  (2020, 'delivery.after-addresses', 3, 0, 0, 1, 1, 1, NOW(), NOW()),
  (2021, 'delivery.after-summary', 3, 0, 0, 1, 1, 1, NOW(), NOW()),

  (2022, 'order-placed.additional-payment-info', 1, 1, 0, 1, 1, 1, NOW(), NOW()),

  (2023, 'wysiwyg.js', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;


INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
{foreach $locales as $locale}
  (1, '{$locale}', {intl l='Invoice choice - at the top' locale=$locale}, '', ''),
  (2, '{$locale}', {intl l='Invoice choice - delivery address' locale=$locale}, '', ''),
  (3, '{$locale}', {intl l='Invoice choice - extra payment zone' locale=$locale}, '', ''),
  (4, '{$locale}', {intl l='Invoice choice - at the bottom' locale=$locale}, '', ''),
  (5, '{$locale}', {intl l='Invoice choice - after javascript initialisation' locale=$locale}, '', ''),
  (6, '{$locale}', {intl l='Invoice choice - CSS stylesheet' locale=$locale}, '', ''),
  (7, '{$locale}', {intl l='Invoice choice - after javascript include' locale=$locale}, '', ''),
  (8, '{$locale}', {intl l='Payment gateway - main area' locale=$locale}, '', ''),
  (9, '{$locale}', {intl l='Payment gateway - javascript' locale=$locale}, '', ''),
  (10, '{$locale}', {intl l='Payment gateway - after javascript initialisation' locale=$locale}, '', ''),
  (11, '{$locale}', {intl l='Payment gateway - CSS stylesheet' locale=$locale}, '', ''),
  (12, '{$locale}', {intl l='Payment gateway - after javascript include' locale=$locale}, '', ''),
  (13, '{$locale}', {intl l='Sitemap - at the bottom' locale=$locale}, '', ''),
  (14, '{$locale}', {intl l='Curency selection page - at the top' locale=$locale}, '', ''),
  (15, '{$locale}', {intl l='Curency selection page - at the bottom' locale=$locale}, '', ''),
  (16, '{$locale}', {intl l='Curency selection page - CSS stylesheet' locale=$locale}, '', ''),
  (17, '{$locale}', {intl l='Curency selection page - after javascript include' locale=$locale}, '', ''),
  (18, '{$locale}', {intl l='Curency selection page - after javascript initialisation' locale=$locale}, '', ''),
  (19, '{$locale}', {intl l='Login page - at the top' locale=$locale}, '', ''),
  (20, '{$locale}', {intl l='Login page - at the top of the main area' locale=$locale}, '', ''),
  (21, '{$locale}', {intl l='Login page - at the top of the form' locale=$locale}, '', ''),
  (22, '{$locale}', {intl l='Login page - at the bottom of the form' locale=$locale}, '', ''),
  (23, '{$locale}', {intl l='Login page - at the bottom of the main area' locale=$locale}, '', ''),
  (24, '{$locale}', {intl l='Login page - at the bottom' locale=$locale}, '', ''),
  (25, '{$locale}', {intl l='Login page - CSS stylesheet' locale=$locale}, '', ''),
  (26, '{$locale}', {intl l='Login page - after javascript include' locale=$locale}, '', ''),
  (27, '{$locale}', {intl l='Login page - after javascript initialisation' locale=$locale}, '', ''),
  (28, '{$locale}', {intl l='Update customer account - at the top' locale=$locale}, '', ''),
  (29, '{$locale}', {intl l='Update customer account - at the top of the form' locale=$locale}, '', ''),
  (30, '{$locale}', {intl l='Update customer account - at the bottom of the form' locale=$locale}, '', ''),
  (31, '{$locale}', {intl l='Update customer account - at the bottom' locale=$locale}, '', ''),
  (32, '{$locale}', {intl l='Update customer account - CSS stylesheet' locale=$locale}, '', ''),
  (33, '{$locale}', {intl l='Update customer account - after javascript include' locale=$locale}, '', ''),
  (34, '{$locale}', {intl l='Update customer account - after javascript initialisation' locale=$locale}, '', ''),
  (35, '{$locale}', {intl l='Cart - at the top' locale=$locale}, '', ''),
  (36, '{$locale}', {intl l='Cart - at the bottom' locale=$locale}, '', ''),
  (37, '{$locale}', {intl l='Cart - after javascript include' locale=$locale}, '', ''),
  (38, '{$locale}', {intl l='Cart - CSS stylesheet' locale=$locale}, '', ''),
  (39, '{$locale}', {intl l='Cart - javascript initialization' locale=$locale}, '', ''),
  (40, '{$locale}', {intl l='Contact page - at the top' locale=$locale}, '', ''),
  (41, '{$locale}', {intl l='Contact page - at the top of the form' locale=$locale}, '', ''),
  (42, '{$locale}', {intl l='Contact page - at the bottom of the form' locale=$locale}, '', ''),
  (43, '{$locale}', {intl l='Contact page - at the bottom' locale=$locale}, '', ''),
  (44, '{$locale}', {intl l='Contact page - CSS stylesheet' locale=$locale}, '', ''),
  (45, '{$locale}', {intl l='Contact page - after javascript include' locale=$locale}, '', ''),
  (46, '{$locale}', {intl l='Contact page - after javascript initialisation' locale=$locale}, '', ''),
  (47, '{$locale}', {intl l='Placed order - main area' locale=$locale}, '', ''),
  (48, '{$locale}', {intl l='Placed order - CSS stylesheet' locale=$locale}, '', ''),
  (49, '{$locale}', {intl l='Placed order - after javascript include' locale=$locale}, '', ''),
  (50, '{$locale}', {intl l='Placed order - after javascript initialisation' locale=$locale}, '', ''),
  (51, '{$locale}', {intl l='Search page - CSS stylesheet' locale=$locale}, '', ''),
  (52, '{$locale}', {intl l='Search page - after javascript include' locale=$locale}, '', ''),
  (53, '{$locale}', {intl l='Search page - after javascript initialisation' locale=$locale}, '', ''),
  (54, '{$locale}', {intl l='Register - at the top' locale=$locale}, '', ''),
  (55, '{$locale}', {intl l='Register - at the top of the form' locale=$locale}, '', ''),
  (56, '{$locale}', {intl l='Register - at the bottom of the form' locale=$locale}, '', ''),
  (57, '{$locale}', {intl l='Register - at the bottom' locale=$locale}, '', ''),
  (58, '{$locale}', {intl l='Register - CSS stylesheet' locale=$locale}, '', ''),
  (59, '{$locale}', {intl l='Register - after javascript include' locale=$locale}, '', ''),
  (60, '{$locale}', {intl l='Register - after javascript initialisation' locale=$locale}, '', ''),
  (61, '{$locale}', {intl l='Lost password - at the top' locale=$locale}, '', ''),
  (62, '{$locale}', {intl l='Lost password - at the top of the form' locale=$locale}, '', ''),
  (63, '{$locale}', {intl l='Lost password - at the bottom of the form' locale=$locale}, '', ''),
  (64, '{$locale}', {intl l='Lost password - at the bottom' locale=$locale}, '', ''),
  (65, '{$locale}', {intl l='Lost password - CSS stylesheet' locale=$locale}, '', ''),
  (66, '{$locale}', {intl l='Lost password - after javascript include' locale=$locale}, '', ''),
  (67, '{$locale}', {intl l='Lost password - javascript initialization' locale=$locale}, '', ''),
  (68, '{$locale}', {intl l='language selection page - at the top' locale=$locale}, '', ''),
  (69, '{$locale}', {intl l='language selection page - at the bottom' locale=$locale}, '', ''),
  (70, '{$locale}', {intl l='language selection page - CSS stylesheet' locale=$locale}, '', ''),
  (71, '{$locale}', {intl l='language selection page - after javascript include' locale=$locale}, '', ''),
  (72, '{$locale}', {intl l='language selection page - after javascript initialisation' locale=$locale}, '', ''),
  (73, '{$locale}', {intl l='Contact page - if successful response' locale=$locale}, '', ''),
  (74, '{$locale}', {intl l='Newsletter page - at the top' locale=$locale}, '', ''),
  (75, '{$locale}', {intl l='Newsletter page - at the bottom' locale=$locale}, '', ''),
  (76, '{$locale}', {intl l='Newsletter page - CSS stylesheet' locale=$locale}, '', ''),
  (77, '{$locale}', {intl l='Newsletter page - after javascript include' locale=$locale}, '', ''),
  (78, '{$locale}', {intl l='Newsletter page - after javascript initialisation' locale=$locale}, '', ''),
  (79, '{$locale}', {intl l='Payment failed - CSS stylesheet' locale=$locale}, '', ''),
  (80, '{$locale}', {intl l='Payment failed - after javascript include' locale=$locale}, '', ''),
  (81, '{$locale}', {intl l='Payment failed - javascript initialization' locale=$locale}, '', ''),
  (82, '{$locale}', {intl l='Content page - at the top' locale=$locale}, '', ''),
  (83, '{$locale}', {intl l='Content page - at the top of the main area' locale=$locale}, '', ''),
  (84, '{$locale}', {intl l='Content page - at the bottom of the main area' locale=$locale}, '', ''),
  (85, '{$locale}', {intl l='Content page - at the bottom' locale=$locale}, '', ''),
  (86, '{$locale}', {intl l='Content page - CSS stylesheet' locale=$locale}, '', ''),
  (87, '{$locale}', {intl l='Content page - after javascript include' locale=$locale}, '', ''),
  (88, '{$locale}', {intl l='Content page - after javascript initialisation' locale=$locale}, '', ''),
  (89, '{$locale}', {intl l='HTML layout - after the opening of the head tag' locale=$locale}, '', ''),
  (90, '{$locale}', {intl l='HTML layout - CSS stylesheet' locale=$locale}, '', ''),
  (91, '{$locale}', {intl l='HTML layout - before the end of the head tag' locale=$locale}, '', ''),
  (92, '{$locale}', {intl l='HTML layout - after the opening of the body tag' locale=$locale}, '', ''),
  (93, '{$locale}', {intl l='HTML layout - at the top of the header' locale=$locale}, '', ''),
  (94, '{$locale}', {intl l='HTML layout - secondary navigation' locale=$locale}, '', ''),
  (95, '{$locale}', {intl l='HTML layout - primary navigation' locale=$locale}, '', ''),
  (96, '{$locale}', {intl l='HTML layout - at the bottom of the header' locale=$locale}, '', ''),
  (97, '{$locale}', {intl l='HTML layout - before the main content area' locale=$locale}, '', ''),
  (98, '{$locale}', {intl l='HTML layout - after the main content area' locale=$locale}, '', ''),
  (99, '{$locale}', {intl l='HTML layout - at the top of the footer' locale=$locale}, '', ''),
  (100, '{$locale}', {intl l='HTML layout - footer body' locale=$locale}, '', ''),
  (101, '{$locale}', {intl l='HTML layout - bottom of the footer' locale=$locale}, '', ''),
  (102, '{$locale}', {intl l='HTML layout - after javascript include' locale=$locale}, '', ''),
  (103, '{$locale}', {intl l='HTML layout - javascript initialization' locale=$locale}, '', ''),
  (104, '{$locale}', {intl l='HTML layout - before the end body tag' locale=$locale}, '', ''),
  (105, '{$locale}', {intl l='Page 404 - content area' locale=$locale}, '', ''),
  (106, '{$locale}', {intl l='Page 404 - CSS stylesheet' locale=$locale}, '', ''),
  (107, '{$locale}', {intl l='Page 404 - after javascript include' locale=$locale}, '', ''),
  (108, '{$locale}', {intl l='Page 404 - after javascript initialisation' locale=$locale}, '', ''),
  (109, '{$locale}', {intl l='Delivery choice - at the top' locale=$locale}, '', ''),
  (110, '{$locale}', {intl l='Delivery choice - at the top of the form' locale=$locale}, '', ''),
  (111, '{$locale}', {intl l='Delivery choice - at the bottom of the form' locale=$locale}, '', ''),
  (112, '{$locale}', {intl l='Delivery choice - at the bottom' locale=$locale}, '', ''),
  (113, '{$locale}', {intl l='Delivery choice - after javascript initialisation' locale=$locale}, '', ''),
  (114, '{$locale}', {intl l='Delivery choice - CSS stylesheet' locale=$locale}, '', ''),
  (115, '{$locale}', {intl l='Delivery choice - after javascript include' locale=$locale}, '', ''),
  (116, '{$locale}', {intl l='Address creation - at the top' locale=$locale}, '', ''),
  (117, '{$locale}', {intl l='Address creation - at the top of the form' locale=$locale}, '', ''),
  (118, '{$locale}', {intl l='Address creation - at the bottom of the form' locale=$locale}, '', ''),
  (119, '{$locale}', {intl l='Address creation - at the bottom' locale=$locale}, '', ''),
  (120, '{$locale}', {intl l='Address creation - CSS stylesheet' locale=$locale}, '', ''),
  (121, '{$locale}', {intl l='Address creation - after javascript include' locale=$locale}, '', ''),
  (122, '{$locale}', {intl l='Address creation - after javascript initialisation' locale=$locale}, '', ''),
  (123, '{$locale}', {intl l='Folder page - at the top' locale=$locale}, '', ''),
  (124, '{$locale}', {intl l='Folder page - at the top of the main area' locale=$locale}, '', ''),
  (125, '{$locale}', {intl l='Folder page - at the bottom of the main area' locale=$locale}, '', ''),
  (126, '{$locale}', {intl l='Folder page - at the bottom' locale=$locale}, '', ''),
  (127, '{$locale}', {intl l='Folder page - CSS stylesheet' locale=$locale}, '', ''),
  (128, '{$locale}', {intl l='Folder page - after javascript include' locale=$locale}, '', ''),
  (129, '{$locale}', {intl l='Folder page - after javascript initialisation' locale=$locale}, '', ''),
  (130, '{$locale}', {intl l='Order failed - at the top' locale=$locale}, '', ''),
  (131, '{$locale}', {intl l='Order failed - at the bottom' locale=$locale}, '', ''),
  (132, '{$locale}', {intl l='Order failed - CSS stylesheet' locale=$locale}, '', ''),
  (133, '{$locale}', {intl l='Order failed - after javascript include' locale=$locale}, '', ''),
  (134, '{$locale}', {intl l='Order failed - after javascript initialisation' locale=$locale}, '', ''),
  (135, '{$locale}', {intl l='Category page - at the top' locale=$locale}, '', ''),
  (136, '{$locale}', {intl l='Category page - at the top of the main area' locale=$locale}, '', ''),
  (137, '{$locale}', {intl l='Category page - at the bottom of the main area' locale=$locale}, '', ''),
  (138, '{$locale}', {intl l='Category page - at the bottom' locale=$locale}, '', ''),
  (139, '{$locale}', {intl l='Category page - CSS stylesheet' locale=$locale}, '', ''),
  (140, '{$locale}', {intl l='Category page - after javascript include' locale=$locale}, '', ''),
  (141, '{$locale}', {intl l='Category page - after javascript initialisation' locale=$locale}, '', ''),
  (142, '{$locale}', {intl l='Address update - at the top' locale=$locale}, '', ''),
  (143, '{$locale}', {intl l='Address update - at the top of the form' locale=$locale}, '', ''),
  (144, '{$locale}', {intl l='Address update - at the bottom of the form' locale=$locale}, '', ''),
  (145, '{$locale}', {intl l='Address update - at the bottom' locale=$locale}, '', ''),
  (146, '{$locale}', {intl l='Address update - CSS stylesheet' locale=$locale}, '', ''),
  (147, '{$locale}', {intl l='Address update - after javascript include' locale=$locale}, '', ''),
  (148, '{$locale}', {intl l='Address update - after javascript initialisation' locale=$locale}, '', ''),
  (149, '{$locale}', {intl l='Home page - main area' locale=$locale}, '', ''),
  (150, '{$locale}', {intl l='Home page - CSS stylesheet' locale=$locale}, '', ''),
  (151, '{$locale}', {intl l='Home page - after javascript include' locale=$locale}, '', ''),
  (152, '{$locale}', {intl l='Home page - after javascript initialisation' locale=$locale}, '', ''),
  (153, '{$locale}', {intl l='Change password - at the top' locale=$locale}, '', ''),
  (154, '{$locale}', {intl l='Change password - at the bottom' locale=$locale}, '', ''),
  (155, '{$locale}', {intl l='Change password - CSS stylesheet' locale=$locale}, '', ''),
  (156, '{$locale}', {intl l='Change password - after javascript include' locale=$locale}, '', ''),
  (157, '{$locale}', {intl l='Change password - after javascript initialisation' locale=$locale}, '', ''),
  (158, '{$locale}', {intl l='Product page - at the top' locale=$locale}, '', ''),
  (159, '{$locale}', {intl l='Product page - photo gallery' locale=$locale}, '', ''),
  (160, '{$locale}', {intl l='Product page - at the top of the detail' locale=$locale}, '', ''),
  (161, '{$locale}', {intl l='Product page - at the bottom of the detail area' locale=$locale}, '', ''),
  (162, '{$locale}', {intl l='Product page - additional information' locale=$locale}, '', ''),
  (163, '{$locale}', {intl l='Product page - at the bottom' locale=$locale}, '', ''),
  (164, '{$locale}', {intl l='Product page - CSS stylesheet' locale=$locale}, '', ''),
  (165, '{$locale}', {intl l='Product page - after javascript include' locale=$locale}, '', ''),
  (166, '{$locale}', {intl l='Product page - after javascript initialisation' locale=$locale}, '', ''),
  (167, '{$locale}', {intl l='customer account - at the top' locale=$locale}, '', ''),
  (168, '{$locale}', {intl l='customer account - at the bottom' locale=$locale}, '', ''),
  (169, '{$locale}', {intl l='customer account - CSS stylesheet' locale=$locale}, '', ''),
  (170, '{$locale}', {intl l='customer account - after javascript include' locale=$locale}, '', ''),
  (171, '{$locale}', {intl l='customer account - after javascript initialisation' locale=$locale}, '', ''),
  (172, '{$locale}', {intl l='All Products - at the top' locale=$locale}, '', ''),
  (173, '{$locale}', {intl l='All Products - at the bottom' locale=$locale}, '', ''),
  (174, '{$locale}', {intl l='All Products - CSS stylesheet' locale=$locale}, '', ''),
  (175, '{$locale}', {intl l='All Products - after javascript include' locale=$locale}, '', ''),
  (176, '{$locale}', {intl l='All Products - after javascript initialisation' locale=$locale}, '', ''),
  (177, '{$locale}', {intl l='Product loop - at the top' locale=$locale}, '', ''),
  (178, '{$locale}', {intl l='Product loop - at the bottom' locale=$locale}, '', ''),
  (179, '{$locale}', {intl l='Category page - at the top of the sidebar' locale=$locale}, '', ''),
  (180, '{$locale}', {intl l='Category page - the body of the sidebar' locale=$locale}, '', ''),
  (181, '{$locale}', {intl l='Category page - at the bottom of the sidebar' locale=$locale}, '', ''),
  (182, '{$locale}', {intl l='Content page - at the top of the sidebar' locale=$locale}, '', ''),
  (183, '{$locale}', {intl l='Content page - the body of the sidebar' locale=$locale}, '', ''),
  (184, '{$locale}', {intl l='Content page - at the bottom of the sidebar' locale=$locale}, '', ''),
  (185, '{$locale}', {intl l='Delivery choice - extra area' locale=$locale}, '', ''),
  (186, '{$locale}', {intl l='Delivery choice - javascript' locale=$locale}, '', ''),
  (1000, '{$locale}', {intl l='Category - content' locale=$locale}, '', ''),
  (1001, '{$locale}', {intl l='Content - content' locale=$locale}, '', ''),
  (1002, '{$locale}', {intl l='Folder - content' locale=$locale}, '', ''),
  (1003, '{$locale}', {intl l='Order - content' locale=$locale}, '', ''),
  (1004, '{$locale}', {intl l='Product - content' locale=$locale}, '', ''),
  (1005, '{$locale}', {intl l='Features value - table header' locale=$locale}, '', ''),
  (1006, '{$locale}', {intl l='Features value - table row' locale=$locale}, '', ''),
  (1007, '{$locale}', {intl l='Feature - Value create form' locale=$locale}, '', ''),
  (1008, '{$locale}', {intl l='Feature - Edit JavaScript' locale=$locale}, '', ''),
  (1009, '{$locale}', {intl l='Product - Edit JavaScript' locale=$locale}, '', ''),
  (1010, '{$locale}', {intl l='Coupon - create JavaScript' locale=$locale}, '', ''),
  (1011, '{$locale}', {intl l='Taxes - update form' locale=$locale}, '', ''),
  (1012, '{$locale}', {intl l='tax rule - Edit JavaScript' locale=$locale}, '', ''),
  (1013, '{$locale}', {intl l='Tools - at the top' locale=$locale}, '', ''),
  (1014, '{$locale}', {intl l='Tools - at the top of the column' locale=$locale}, '', ''),
  (1015, '{$locale}', {intl l='Tools - at the bottom of column 1' locale=$locale}, '', ''),
  (1016, '{$locale}', {intl l='Tools - bottom' locale=$locale}, '', ''),
  (1017, '{$locale}', {intl l='Tools - JavaScript' locale=$locale}, '', ''),
  (1018, '{$locale}', {intl l='Messages - at the top' locale=$locale}, '', ''),
  (1019, '{$locale}', {intl l='Messages - table header' locale=$locale}, '', ''),
  (1020, '{$locale}', {intl l='Messages - table row' locale=$locale}, '', ''),
  (1021, '{$locale}', {intl l='Messages - bottom' locale=$locale}, '', ''),
  (1022, '{$locale}', {intl l='Message - create form' locale=$locale}, '', ''),
  (1023, '{$locale}', {intl l='Message - delete form' locale=$locale}, '', ''),
  (1024, '{$locale}', {intl l='Messages - JavaScript' locale=$locale}, '', ''),
  (1025, '{$locale}', {intl l='Taxes rules - at the top' locale=$locale}, '', ''),
  (1026, '{$locale}', {intl l='Taxes rules - bottom' locale=$locale}, '', ''),
  (1027, '{$locale}', {intl l='Tax - create form' locale=$locale}, '', ''),
  (1028, '{$locale}', {intl l='Tax - delete form' locale=$locale}, '', ''),
  (1029, '{$locale}', {intl l='tax rule - create form' locale=$locale}, '', ''),
  (1030, '{$locale}', {intl l='tax rule - delete form' locale=$locale}, '', ''),
  (1031, '{$locale}', {intl l='Taxes rules - JavaScript' locale=$locale}, '', ''),
  (1032, '{$locale}', {intl l='Exports - at the top' locale=$locale}, '', ''),
  (1033, '{$locale}', {intl l='Exports - at the bottom of a category' locale=$locale}, '', ''),
  (1034, '{$locale}', {intl l='Exports - at the bottom of column 1' locale=$locale}, '', ''),
  (1035, '{$locale}', {intl l='Exports - JavaScript' locale=$locale}, '', ''),
  (1036, '{$locale}', {intl l='Export - JavaScript' locale=$locale}, '', ''),
  (1037, '{$locale}', {intl l='Product - folders table header' locale=$locale}, '', ''),
  (1038, '{$locale}', {intl l='Product - folders table row' locale=$locale}, '', ''),
  (1039, '{$locale}', {intl l='Product - details pricing form' locale=$locale}, '', ''),
  (1040, '{$locale}', {intl l='Product - stock edit form' locale=$locale}, '', ''),
  (1041, '{$locale}', {intl l='Product - details promotion form' locale=$locale}, '', ''),
  (1042, '{$locale}', {intl l='Product - before combinations' locale=$locale}, '', ''),
  (1043, '{$locale}', {intl l='Product - combinations list caption' locale=$locale}, '', ''),
  (1044, '{$locale}', {intl l='Product - after combinations' locale=$locale}, '', ''),
  (1045, '{$locale}', {intl l='Product - combination delete form' locale=$locale}, '', ''),
  (1046, '{$locale}', {intl l='Modules - table header' locale=$locale}, '', ''),
  (1047, '{$locale}', {intl l='Modules - table row' locale=$locale}, '', ''),
  (1048, '{$locale}', {intl l='Currency - Edit JavaScript' locale=$locale}, '', ''),
  (1049, '{$locale}', {intl l='Category - contents table header' locale=$locale}, '', ''),
  (1050, '{$locale}', {intl l='Category - contents table row' locale=$locale}, '', ''),
  (1051, '{$locale}', {intl l='Category - Edit JavaScript' locale=$locale}, '', ''),
  (1052, '{$locale}', {intl l='Document - Edit JavaScript' locale=$locale}, '', ''),
  (1053, '{$locale}', {intl l='Customer - at the top' locale=$locale}, '', ''),
  (1054, '{$locale}', {intl l='Customers - caption' locale=$locale}, '', ''),
  (1055, '{$locale}', {intl l='Customers - header' locale=$locale}, '', ''),
  (1056, '{$locale}', {intl l='Customers - row' locale=$locale}, '', ''),
  (1057, '{$locale}', {intl l='Customer - bottom' locale=$locale}, '', ''),
  (1058, '{$locale}', {intl l='Customer - create form' locale=$locale}, '', ''),
  (1059, '{$locale}', {intl l='Customer - delete form' locale=$locale}, '', ''),
  (1060, '{$locale}', {intl l='Customers - JavaScript' locale=$locale}, '', ''),
  (1061, '{$locale}', {intl l='Product - contents table header' locale=$locale}, '', ''),
  (1062, '{$locale}', {intl l='Product - contents table row' locale=$locale}, '', ''),
  (1063, '{$locale}', {intl l='Product - accessories table header' locale=$locale}, '', ''),
  (1064, '{$locale}', {intl l='Product - accessories table row' locale=$locale}, '', ''),
  (1065, '{$locale}', {intl l='Product - categories table header' locale=$locale}, '', ''),
  (1066, '{$locale}', {intl l='Product - categories table row' locale=$locale}, '', ''),
  (1067, '{$locale}', {intl l='Product - attributes table header' locale=$locale}, '', ''),
  (1068, '{$locale}', {intl l='Product - attributes table row' locale=$locale}, '', ''),
  (1069, '{$locale}', {intl l='Product - features-table-header' locale=$locale}, '', ''),
  (1070, '{$locale}', {intl l='Product - features table row' locale=$locale}, '', ''),
  (1071, '{$locale}', {intl l='Template - attributes table header' locale=$locale}, '', ''),
  (1072, '{$locale}', {intl l='Template - attributes table row' locale=$locale}, '', ''),
  (1073, '{$locale}', {intl l='Template - features-table-header' locale=$locale}, '', ''),
  (1074, '{$locale}', {intl l='Template - features table row' locale=$locale}, '', ''),
  (1075, '{$locale}', {intl l='Templates - at the top' locale=$locale}, '', ''),
  (1076, '{$locale}', {intl l='Templates - table header' locale=$locale}, '', ''),
  (1077, '{$locale}', {intl l='Templates - table row' locale=$locale}, '', ''),
  (1078, '{$locale}', {intl l='Templates - bottom' locale=$locale}, '', ''),
  (1079, '{$locale}', {intl l='Template - create form' locale=$locale}, '', ''),
  (1080, '{$locale}', {intl l='Template - delete form' locale=$locale}, '', ''),
  (1081, '{$locale}', {intl l='Templates - JavaScript' locale=$locale}, '', ''),
  (1082, '{$locale}', {intl l='Configuration - at the top' locale=$locale}, '', ''),
  (1083, '{$locale}', {intl l='Configuration - at the top of the catalog area' locale=$locale}, '', ''),
  (1084, '{$locale}', {intl l='Configuration - at the bottom of the catalog' locale=$locale}, '', ''),
  (1085, '{$locale}', {intl l='Configuration - at the top of the shipping area' locale=$locale}, '', ''),
  (1086, '{$locale}', {intl l='Configuration - at the bottom of the shipping area' locale=$locale}, '', ''),
  (1087, '{$locale}', {intl l='Configuration - at the top of the system area' locale=$locale}, '', ''),
  (1088, '{$locale}', {intl l='Configuration - at the bottom of the system area' locale=$locale}, '', ''),
  (1089, '{$locale}', {intl l='Configuration - bottom' locale=$locale}, '', ''),
  (1090, '{$locale}', {intl l='Configuration - JavaScript' locale=$locale}, '', ''),
  (1091, '{$locale}', {intl l='Dashboard - at the top' locale=$locale}, '', ''),
  (1092, '{$locale}', {intl l='Dashboard - middle' locale=$locale}, '', ''),
  (1093, '{$locale}', {intl l='Dashboard - bottom' locale=$locale}, '', ''),
  (1094, '{$locale}', {intl l='Orders - at the top' locale=$locale}, '', ''),
  (1095, '{$locale}', {intl l='Orders - table header' locale=$locale}, '', ''),
  (1096, '{$locale}', {intl l='Orders - table row' locale=$locale}, '', ''),
  (1097, '{$locale}', {intl l='Orders - bottom' locale=$locale}, '', ''),
  (1098, '{$locale}', {intl l='Orders - JavaScript' locale=$locale}, '', ''),
  (1099, '{$locale}', {intl l='Delivery zone - at the top' locale=$locale}, '', ''),
  (1100, '{$locale}', {intl l='Delivery zone - table header' locale=$locale}, '', ''),
  (1101, '{$locale}', {intl l='Delivery zone - table row' locale=$locale}, '', ''),
  (1102, '{$locale}', {intl l='Delivery zone - bottom' locale=$locale}, '', ''),
  (1103, '{$locale}', {intl l='Delivery zone - JavaScript' locale=$locale}, '', ''),
  (1104, '{$locale}', {intl l='Content - Edit JavaScript' locale=$locale}, '', ''),
  (1105, '{$locale}', {intl l='Home - at the top' locale=$locale}, '', ''),
  (1106, '{$locale}', {intl l='Home - bottom' locale=$locale}, '', ''),
  (1107, '{$locale}', {intl l='Home - JavaScript' locale=$locale}, '', ''),
  (1108, '{$locale}', {intl l='Modules - at the top' locale=$locale}, '', ''),
  (1109, '{$locale}', {intl l='Modules - bottom' locale=$locale}, '', ''),
  (1110, '{$locale}', {intl l='Modules - JavaScript' locale=$locale}, '', ''),
  (1111, '{$locale}', {intl l='Languages - at the top' locale=$locale}, '', ''),
  (1112, '{$locale}', {intl l='Languages - bottom' locale=$locale}, '', ''),
  (1113, '{$locale}', {intl l='Language - create form' locale=$locale}, '', ''),
  (1114, '{$locale}', {intl l='Languages - delete form' locale=$locale}, '', ''),
  (1115, '{$locale}', {intl l='Languages - JavaScript' locale=$locale}, '', ''),
  (1116, '{$locale}', {intl l='Zone - delete form' locale=$locale}, '', ''),
  (1117, '{$locale}', {intl l='Delivery zone - Edit JavaScript' locale=$locale}, '', ''),
  (1118, '{$locale}', {intl l='System - logs JavaScript' locale=$locale}, '', ''),
  (1119, '{$locale}', {intl l='Search - at the top' locale=$locale}, '', ''),
  (1120, '{$locale}', {intl l='Search - bottom' locale=$locale}, '', ''),
  (1121, '{$locale}', {intl l='Search - JavaScript' locale=$locale}, '', ''),
  (1122, '{$locale}', {intl l='Administrators - at the top' locale=$locale}, '', ''),
  (1123, '{$locale}', {intl l='Administrators - bottom' locale=$locale}, '', ''),
  (1124, '{$locale}', {intl l='Administrator - create form' locale=$locale}, '', ''),
  (1125, '{$locale}', {intl l='Administrator - update form' locale=$locale}, '', ''),
  (1126, '{$locale}', {intl l='Administrator - delete form' locale=$locale}, '', ''),
  (1127, '{$locale}', {intl l='Administrators - JavaScript' locale=$locale}, '', ''),
  (1128, '{$locale}', {intl l='Module hook - Edit JavaScript' locale=$locale}, '', ''),
  (1129, '{$locale}', {intl l='Shipping configuration - at the top' locale=$locale}, '', ''),
  (1130, '{$locale}', {intl l='Shipping configuration - table header' locale=$locale}, '', ''),
  (1131, '{$locale}', {intl l='Shipping configuration - table row' locale=$locale}, '', ''),
  (1132, '{$locale}', {intl l='Shipping configuration - bottom' locale=$locale}, '', ''),
  (1133, '{$locale}', {intl l='Shipping configuration - create form' locale=$locale}, '', ''),
  (1134, '{$locale}', {intl l='Shipping configuration - delete form' locale=$locale}, '', ''),
  (1135, '{$locale}', {intl l='Shipping configuration - JavaScript' locale=$locale}, '', ''),
  (1136, '{$locale}', {intl l='Features - at the top' locale=$locale}, '', ''),
  (1137, '{$locale}', {intl l='Features - table header' locale=$locale}, '', ''),
  (1138, '{$locale}', {intl l='Features - table row' locale=$locale}, '', ''),
  (1139, '{$locale}', {intl l='Features - bottom' locale=$locale}, '', ''),
  (1140, '{$locale}', {intl l='Feature - create form' locale=$locale}, '', ''),
  (1141, '{$locale}', {intl l='Feature - delete form' locale=$locale}, '', ''),
  (1142, '{$locale}', {intl l='Feature - add to all form' locale=$locale}, '', ''),
  (1143, '{$locale}', {intl l='Feature - remove to all form' locale=$locale}, '', ''),
  (1144, '{$locale}', {intl l='Features - JavaScript' locale=$locale}, '', ''),
  (1145, '{$locale}', {intl l='Module - Edit JavaScript' locale=$locale}, '', ''),
  (1146, '{$locale}', {intl l='Module hook - create form' locale=$locale}, '', ''),
  (1147, '{$locale}', {intl l='Module hook - delete form' locale=$locale}, '', ''),
  (1148, '{$locale}', {intl l='Module hook - JavaScript' locale=$locale}, '', ''),
  (1149, '{$locale}', {intl l='Shipping configuration - Edit' locale=$locale}, '', ''),
  (1150, '{$locale}', {intl l='Shipping configuration - country delete form' locale=$locale}, '', ''),
  (1151, '{$locale}', {intl l='Shipping configuration - Edit JavaScript' locale=$locale}, '', ''),
  (1152, '{$locale}', {intl l='Mailing system - at the top' locale=$locale}, '', ''),
  (1153, '{$locale}', {intl l='Mailing system - bottom' locale=$locale}, '', ''),
  (1154, '{$locale}', {intl l='Mailing system - JavaScript' locale=$locale}, '', ''),
  (1155, '{$locale}', {intl l='Categories - at the top' locale=$locale}, '', ''),
  (1156, '{$locale}', {intl l='Categories - caption' locale=$locale}, '', ''),
  (1157, '{$locale}', {intl l='Categories - header' locale=$locale}, '', ''),
  (1158, '{$locale}', {intl l='Categories - row' locale=$locale}, '', ''),
  (1159, '{$locale}', {intl l='Products - caption' locale=$locale}, '', ''),
  (1160, '{$locale}', {intl l='Products - header' locale=$locale}, '', ''),
  (1161, '{$locale}', {intl l='Products - row' locale=$locale}, '', ''),
  (1162, '{$locale}', {intl l='Categories - bottom' locale=$locale}, '', ''),
  (1163, '{$locale}', {intl l='Categories - at the bottom of the catalog' locale=$locale}, '', ''),
  (1164, '{$locale}', {intl l='Category - create form' locale=$locale}, '', ''),
  (1165, '{$locale}', {intl l='Product - create form' locale=$locale}, '', ''),
  (1166, '{$locale}', {intl l='Category - delete form' locale=$locale}, '', ''),
  (1167, '{$locale}', {intl l='Product - delete form' locale=$locale}, '', ''),
  (1168, '{$locale}', {intl l='Categories - JavaScript' locale=$locale}, '', ''),
  (1169, '{$locale}', {intl l='Variables - at the top' locale=$locale}, '', ''),
  (1170, '{$locale}', {intl l='Variables - table header' locale=$locale}, '', ''),
  (1171, '{$locale}', {intl l='Variables - table row' locale=$locale}, '', ''),
  (1172, '{$locale}', {intl l='Variables - bottom' locale=$locale}, '', ''),
  (1173, '{$locale}', {intl l='Variable - create form' locale=$locale}, '', ''),
  (1174, '{$locale}', {intl l='Variable - delete form' locale=$locale}, '', ''),
  (1175, '{$locale}', {intl l='Variables - JavaScript' locale=$locale}, '', ''),
  (1176, '{$locale}', {intl l='Order - product list' locale=$locale}, '', ''),
  (1177, '{$locale}', {intl l='Order - Edit JavaScript' locale=$locale}, '', ''),
  (1178, '{$locale}', {intl l='Store Information - JavaScript' locale=$locale}, '', ''),
  (1179, '{$locale}', {intl l='Translations - JavaScript' locale=$locale}, '', ''),
  (1180, '{$locale}', {intl l='Folder - at the top' locale=$locale}, '', ''),
  (1181, '{$locale}', {intl l='Folder - caption' locale=$locale}, '', ''),
  (1182, '{$locale}', {intl l='Folder - header' locale=$locale}, '', ''),
  (1183, '{$locale}', {intl l='Folder - row' locale=$locale}, '', ''),
  (1184, '{$locale}', {intl l='Contents - caption' locale=$locale}, '', ''),
  (1185, '{$locale}', {intl l='Contents - header' locale=$locale}, '', ''),
  (1186, '{$locale}', {intl l='Contents - row' locale=$locale}, '', ''),
  (1187, '{$locale}', {intl l='Folder - bottom' locale=$locale}, '', ''),
  (1188, '{$locale}', {intl l='Folder - create form' locale=$locale}, '', ''),
  (1189, '{$locale}', {intl l='Content - create form' locale=$locale}, '', ''),
  (1190, '{$locale}', {intl l='Folder - delete form' locale=$locale}, '', ''),
  (1191, '{$locale}', {intl l='Content - delete form' locale=$locale}, '', ''),
  (1192, '{$locale}', {intl l='Folder - JavaScript' locale=$locale}, '', ''),
  (1193, '{$locale}', {intl l='Template - Edit JavaScript' locale=$locale}, '', ''),
  (1194, '{$locale}', {intl l='Tax - Edit JavaScript' locale=$locale}, '', ''),
  (1195, '{$locale}', {intl l='Hook - Edit JavaScript' locale=$locale}, '', ''),
  (1196, '{$locale}', {intl l='Countries - at the top' locale=$locale}, '', ''),
  (1197, '{$locale}', {intl l='Countries - table header' locale=$locale}, '', ''),
  (1198, '{$locale}', {intl l='Countries - table row' locale=$locale}, '', ''),
  (1199, '{$locale}', {intl l='Countries - bottom' locale=$locale}, '', ''),
  (1200, '{$locale}', {intl l='Country - create form' locale=$locale}, '', ''),
  (1201, '{$locale}', {intl l='Country - delete form' locale=$locale}, '', ''),
  (1202, '{$locale}', {intl l='Countries - JavaScript' locale=$locale}, '', ''),
  (1203, '{$locale}', {intl l='Currencies - at the top' locale=$locale}, '', ''),
  (1204, '{$locale}', {intl l='Currencies - table header' locale=$locale}, '', ''),
  (1205, '{$locale}', {intl l='Currencies - table row' locale=$locale}, '', ''),
  (1206, '{$locale}', {intl l='Currencies - bottom' locale=$locale}, '', ''),
  (1207, '{$locale}', {intl l='Currency - create form' locale=$locale}, '', ''),
  (1208, '{$locale}', {intl l='Currency - delete form' locale=$locale}, '', ''),
  (1209, '{$locale}', {intl l='Currencies - JavaScript' locale=$locale}, '', ''),
  (1210, '{$locale}', {intl l='Customer - Edit' locale=$locale}, '', ''),
  (1211, '{$locale}', {intl l='Customer - address create form' locale=$locale}, '', ''),
  (1212, '{$locale}', {intl l='Customer - address update form' locale=$locale}, '', ''),
  (1213, '{$locale}', {intl l='Customer - address delete form' locale=$locale}, '', ''),
  (1214, '{$locale}', {intl l='Customer - Edit JavaScript' locale=$locale}, '', ''),
  (1215, '{$locale}', {intl l='Attributes value - table header' locale=$locale}, '', ''),
  (1216, '{$locale}', {intl l='Attributes value - table row' locale=$locale}, '', ''),
  (1217, '{$locale}', {intl l='Attribute value - create form' locale=$locale}, '', ''),
  (1218, '{$locale}', {intl l='Attribut - id delete form' locale=$locale}, '', ''),
  (1219, '{$locale}', {intl l='Attribut - Edit JavaScript' locale=$locale}, '', ''),
  (1220, '{$locale}', {intl l='Profiles - at the top' locale=$locale}, '', ''),
  (1221, '{$locale}', {intl l='Profiles - bottom' locale=$locale}, '', ''),
  (1222, '{$locale}', {intl l='Profile - create form' locale=$locale}, '', ''),
  (1223, '{$locale}', {intl l='Profile - delete form' locale=$locale}, '', ''),
  (1224, '{$locale}', {intl l='Profiles - JavaScript' locale=$locale}, '', ''),
  (1225, '{$locale}', {intl l='Country - Edit JavaScript' locale=$locale}, '', ''),
  (1226, '{$locale}', {intl l='Profile - Edit JavaScript' locale=$locale}, '', ''),
  (1227, '{$locale}', {intl l='Variable - Edit JavaScript' locale=$locale}, '', ''),
  (1228, '{$locale}', {intl l='Coupon - update JavaScript' locale=$locale}, '', ''),
  (1229, '{$locale}', {intl l='Coupon - at the top' locale=$locale}, '', ''),
  (1230, '{$locale}', {intl l='Coupon - list caption' locale=$locale}, '', ''),
  (1231, '{$locale}', {intl l='Coupon - table header' locale=$locale}, '', ''),
  (1232, '{$locale}', {intl l='Coupon - table row' locale=$locale}, '', ''),
  (1233, '{$locale}', {intl l='Coupon - bottom' locale=$locale}, '', ''),
  (1234, '{$locale}', {intl l='Coupon - list JavaScript' locale=$locale}, '', ''),
  (1235, '{$locale}', {intl l='Module - configuration' locale=$locale}, '', ''),
  (1236, '{$locale}', {intl l='Module - configuration JavaScript' locale=$locale}, '', ''),
  (1237, '{$locale}', {intl l='Message - Edit JavaScript' locale=$locale}, '', ''),
  (1238, '{$locale}', {intl l='Image - Edit JavaScript' locale=$locale}, '', ''),
  (1239, '{$locale}', {intl l='Attributes - at the top' locale=$locale}, '', ''),
  (1240, '{$locale}', {intl l='Attributes - table header' locale=$locale}, '', ''),
  (1241, '{$locale}', {intl l='Attributes - table row' locale=$locale}, '', ''),
  (1242, '{$locale}', {intl l='Attributes - bottom' locale=$locale}, '', ''),
  (1243, '{$locale}', {intl l='Attribut - create form' locale=$locale}, '', ''),
  (1244, '{$locale}', {intl l='Attribut - delete form' locale=$locale}, '', ''),
  (1245, '{$locale}', {intl l='Attribut - add to all form' locale=$locale}, '', ''),
  (1246, '{$locale}', {intl l='Attribut - remove to all form' locale=$locale}, '', ''),
  (1247, '{$locale}', {intl l='Attributes - JavaScript' locale=$locale}, '', ''),
  (1248, '{$locale}', {intl l='Logs - at the top' locale=$locale}, '', ''),
  (1249, '{$locale}', {intl l='Logs - bottom' locale=$locale}, '', ''),
  (1250, '{$locale}', {intl l='Logs - JavaScript' locale=$locale}, '', ''),
  (1251, '{$locale}', {intl l='Folder - Edit JavaScript' locale=$locale}, '', ''),
  (1252, '{$locale}', {intl l='Hooks - at the top' locale=$locale}, '', ''),
  (1253, '{$locale}', {intl l='Hooks - table header' locale=$locale}, '', ''),
  (1254, '{$locale}', {intl l='Hooks - table row' locale=$locale}, '', ''),
  (1255, '{$locale}', {intl l='Hooks - bottom' locale=$locale}, '', ''),
  (1256, '{$locale}', {intl l='Hook - create form' locale=$locale}, '', ''),
  (1257, '{$locale}', {intl l='Hook - delete form' locale=$locale}, '', ''),
  (1258, '{$locale}', {intl l='Hooks - JavaScript' locale=$locale}, '', ''),
  (1259, '{$locale}', {intl l='Layout - CSS' locale=$locale}, '', ''),
  (1260, '{$locale}', {intl l='Layout - before topbar' locale=$locale}, '', ''),
  (1261, '{$locale}', {intl l='Layout - inside top bar' locale=$locale}, '', ''),
  (1262, '{$locale}', {intl l='Layout - after top bar' locale=$locale}, '', ''),
  (1263, '{$locale}', {intl l='Layout - before top menu' locale=$locale}, '', ''),
  (1264, '{$locale}', {intl l='Layout - in top menu items' locale=$locale}, '', ''),
  (1265, '{$locale}', {intl l='Layout - after top menu' locale=$locale}, '', ''),
  (1266, '{$locale}', {intl l='Layout - before footer' locale=$locale}, '', ''),
  (1267, '{$locale}', {intl l='Layout - in footer' locale=$locale}, '', ''),
  (1268, '{$locale}', {intl l='Layout - after footer' locale=$locale}, '', ''),
  (1269, '{$locale}', {intl l='Layout - JavaScript' locale=$locale}, '', ''),
  (1270, '{$locale}', {intl l='Layout - at the top of the top bar' locale=$locale}, '', ''),
  (1271, '{$locale}', {intl l='Layout - at the bottom of the top bar' locale=$locale}, '', ''),
  (1272, '{$locale}', {intl l='Layout - in the menu customers' locale=$locale}, '', ''),
  (1273, '{$locale}', {intl l='Layout - in the menu orders' locale=$locale}, '', ''),
  (1274, '{$locale}', {intl l='Layout - in the menu catalog' locale=$locale}, '', ''),
  (1275, '{$locale}', {intl l='Layout - in the menu folders' locale=$locale}, '', ''),
  (1276, '{$locale}', {intl l='Layout - in the menu tools' locale=$locale}, '', ''),
  (1277, '{$locale}', {intl l='Layout - in the menu modules' locale=$locale}, '', ''),
  (1278, '{$locale}', {intl l='Layout - in the menu configuration' locale=$locale}, '', ''),
  (1279, '{$locale}', {intl l='Brand - Edit JavaScript' locale=$locale}, '', ''),
  (1280, '{$locale}', {intl l='Home - block' locale=$locale}, '', ''),
  (1281, '{$locale}', {intl l='Brands - at the top' locale=$locale}, '', ''),
  (1282, '{$locale}', {intl l='Brands - table header' locale=$locale}, '', ''),
  (1283, '{$locale}', {intl l='Brands - table row' locale=$locale}, '', ''),
  (1284, '{$locale}', {intl l='Brands - bottom' locale=$locale}, '', ''),
  (1285, '{$locale}', {intl l='Brand - create form' locale=$locale}, '', ''),
  (1286, '{$locale}', {intl l='Brand - delete form' locale=$locale}, '', ''),
  (1287, '{$locale}', {intl l='Brand - JavaScript' locale=$locale}, '', ''),
  (1288, '{$locale}', {intl l='Exports - at the top' locale=$locale}, '', ''),
  (1289, '{$locale}', {intl l='Exports - at the bottom of a category' locale=$locale}, '', ''),
  (1290, '{$locale}', {intl l='Exports - at the bottom of column 1' locale=$locale}, '', ''),
  (1291, '{$locale}', {intl l='Exports - JavaScript' locale=$locale}, '', ''),
  (1292, '{$locale}', {intl l='Export - JavaScript' locale=$locale}, '', ''),
  (1293, '{$locale}', {intl l='Brand - content' locale=$locale}, '', ''),
  (1294, '{$locale}', {intl l='Customer - order table header' locale=$locale}, '', ''),
  (1295, '{$locale}', {intl l='Customer - order table row' locale=$locale}, '', ''),

  (2001, '{$locale}', {intl l='Invoice - CSS' locale=$locale}, '', ''),
  (2002, '{$locale}', {intl l='Invoice - in the header' locale=$locale}, '', ''),
  (2003, '{$locale}', {intl l='Invoice - at the top of the footer' locale=$locale}, '', ''),
  (2004, '{$locale}', {intl l='Invoice - imprint' locale=$locale}, '', ''),
  (2005, '{$locale}', {intl l='Invoice - at the bottom of the footer' locale=$locale}, '', ''),
  (2006, '{$locale}', {intl l='Invoice - at the bottom of information area' locale=$locale}, '', ''),
  (2007, '{$locale}', {intl l='Invoice - after the information area' locale=$locale}, '', ''),
  (2008, '{$locale}', {intl l='Invoice - delivery address' locale=$locale}, '', ''),
  (2009, '{$locale}', {intl l='Invoice - after addresse area' locale=$locale}, '', ''),
  (2010, '{$locale}', {intl l='Invoice - after product listing' locale=$locale}, '', ''),
  (2011, '{$locale}', {intl l='Invoice - after the order summary' locale=$locale}, '', ''),
  (2012, '{$locale}', {intl l='Delivery - CSS' locale=$locale}, '', ''),
  (2013, '{$locale}', {intl l='Delivery - in the header' locale=$locale}, '', ''),
  (2014, '{$locale}', {intl l='Delivery - at the top of the footer' locale=$locale}, '', ''),
  (2015, '{$locale}', {intl l='Delivery - imprint' locale=$locale}, '', ''),
  (2016, '{$locale}', {intl l='Delivery - at the bottom of the footer' locale=$locale}, '', ''),
  (2017, '{$locale}', {intl l='Delivery - at the bottom of information area' locale=$locale}, '', ''),
  (2018, '{$locale}', {intl l='Delivery - after the information area' locale=$locale}, '', ''),
  (2019, '{$locale}', {intl l='Delivery - delivery address' locale=$locale}, '', ''),
  (2020, '{$locale}', {intl l='Delivery - after addresse area' locale=$locale}, '', ''),
  (2021, '{$locale}', {intl l='Delivery - after the order summary' locale=$locale}, '', ''),

  (2022, '{$locale}', {intl l='Order confirmation - after the order summary' locale=$locale}, '', ''),

  (2023, '{$locale}', {intl l='Where the WYSIWYG editor is required' locale=$locale}, '', ''){if ! $locale@last},{/if}

{/foreach}
;

# ======================================================================================================================
# Image / Document visible
# ======================================================================================================================

ALTER TABLE `product_document`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `product_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `category_document`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `category_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `content_document`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `content_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `folder_document`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `folder_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `module_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;
ALTER TABLE `brand_document`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

ALTER TABLE `brand_image`
  ADD COLUMN `visible` TINYINT DEFAULT 1 NOT NULL
  AFTER `file`
;

-- Add version to customer
ALTER TABLE `customer`
  ADD COLUMN `version` INTEGER DEFAULT 0
;

ALTER TABLE `customer`
  ADD COLUMN `version_created_at` DATETIME
;

ALTER TABLE `customer`
  ADD COLUMN `version_created_by` VARCHAR(100)
;

-- ---------------------------------------------------------------------
-- customer_version
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `customer_version`;

CREATE TABLE `customer_version`
(
    `id` INTEGER NOT NULL,
    `ref` VARCHAR(50),
    `title_id` INTEGER NOT NULL,
    `firstname` VARCHAR(255) NOT NULL,
    `lastname` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255),
    `password` VARCHAR(255),
    `algo` VARCHAR(128),
    `reseller` TINYINT,
    `lang` VARCHAR(10),
    `sponsor` VARCHAR(50),
    `discount` FLOAT,
    `remember_me_token` VARCHAR(255),
    `remember_me_serial` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    `version` INTEGER DEFAULT 0 NOT NULL,
    `version_created_at` DATETIME,
    `version_created_by` VARCHAR(100),
    `order_ids` TEXT,
    `order_versions` TEXT,
    PRIMARY KEY (`id`,`version`),
    CONSTRAINT `customer_version_FK_1`
        FOREIGN KEY (`id`)
        REFERENCES `customer` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';



# ======================================================================================================================
# Order placed notification
# ======================================================================================================================

SELECT @store_email := `value` FROM `config` where name='store_email';

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('store_notification_emails', IFNULL(@store_email, ''), 1, 1, NOW(), NOW());

SELECT @max_id := MAX(`id`) FROM `message`;

INSERT INTO `message` (`id`, `name`, `secured`, `text_layout_file_name`, `text_template_file_name`, `html_layout_file_name`, `html_template_file_name`, `created_at`, `updated_at`) VALUES
  (@max_id+1, 'order_notification', NULL, NULL, 'order_notification.txt', NULL, 'order_notification.html', NOW(), NOW()),
  (@max_id+2, 'customer_account_changed', 0, NULL, 'account_changed_by_admin.txt', NULL, 'account_changed_by_admin.html', NOW(), NOW()),
  (@max_id+3, 'customer_account_created', 0, NULL, 'account_created_by_admin.txt', NULL, 'account_created_by_admin.html', NOW(), NOW());

INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
{foreach $locales as $locale}
  (@max_id+1, '{$locale}', {intl l='Message sent to the shop owner when a new order is placed' locale=$locale}, {intl l='New order {$order_ref} placed on {config key="store_name"}' locale=$locale}, NULL, NULL),
  (@max_id+2, '{$locale}', {intl l='Mail sent to the customer when its password or email is changed in the back-office' locale=$locale}, {intl l='Your account information on {config key="store_name"} has been changed.' locale=$locale}, NULL, NULL),
  (@max_id+3, '{$locale}', {intl l='Mail sent to the customer when its account is created by an administrator in the back-office' locale=$locale}, {intl l='A {config key="store_name"} account has been created for you' locale=$locale}, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

# ======================================================================================================================
# Add Virtual product
# ======================================================================================================================

ALTER TABLE  `product`
  ADD  `virtual` TINYINT DEFAULT 0 NOT NULL
  AFTER  `ref`;

ALTER TABLE  `product_version`
  ADD  `virtual` TINYINT DEFAULT 0 NOT NULL
  AFTER  `ref`;


ALTER TABLE  `order_product`
  ADD  `virtual` TINYINT DEFAULT 0 NOT NULL
  AFTER  `postscriptum`;

ALTER TABLE  `order_product`
  ADD  `virtual_document` VARCHAR(255)
  AFTER  `virtual`;


# ======================================================================================================================
# Add Meta data
# ======================================================================================================================

DROP TABLE IF EXISTS `meta_data`;

CREATE TABLE `meta_data`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `meta_key` VARCHAR(100) NOT NULL,
    `element_key` VARCHAR(100) NOT NULL,
    `element_id` INTEGER NOT NULL,
    `is_serialized` TINYINT(1) NOT NULL,
    `value` LONGTEXT NOT NULL,
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `meta_data_key_element_idx` (`meta_key`, `element_key`, `element_id`)
) ENGINE=InnoDB CHARACTER SET='utf8';


# ======================================================================================================================
# Allow negative stock
# ======================================================================================================================

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
  ('allow_negative_stock', '0', 0, 0, NOW(), NOW());

SELECT @max_id := MAX(`id`) FROM `config`;

INSERT INTO `config_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
{foreach $locales as $locale}
(@max_id, '{$locale}', {intl l='Allow negative product stock (1) or not (0, default)' locale=$locale}, NULL, NULL, NULL){if ! $locale@last},{/if}

{/foreach}
;

# ======================================================================================================================
# Module configuration
# ======================================================================================================================

-- ---------------------------------------------------------------------
-- module_config
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_config`;

CREATE TABLE `module_config`
(
  `id` INTEGER NOT NULL AUTO_INCREMENT,
  `module_id` INTEGER NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME,
  `updated_at` DATETIME,
  PRIMARY KEY (`id`),
  INDEX `idx_module_id_name` (`module_id`, `name`),
  CONSTRAINT `fk_module_config_module_id`
  FOREIGN KEY (`module_id`)
  REFERENCES `module` (`id`)
    ON UPDATE RESTRICT
    ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

-- ---------------------------------------------------------------------
-- module_config_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `module_config_i18n`;

CREATE TABLE `module_config_i18n`
(
  `id` INTEGER NOT NULL,
  `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
  `value` TEXT,
  PRIMARY KEY (`id`,`locale`),
  CONSTRAINT `module_config_i18n_FK_1`
  FOREIGN KEY (`id`)
  REFERENCES `module_config` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8';

# ======================================================================================================================
# Update of short title Mister
# ======================================================================================================================

-- en_US
UPDATE `customer_title_i18n`
  SET `short` = 'Mr.'
  WHERE `customer_title_i18n`.`id` = 1
    AND `customer_title_i18n`.`locale` = 'en_US';
-- fr_FR
UPDATE `customer_title_i18n`
  SET `short` = 'M.'
  WHERE `customer_title_i18n`.`id` = 1
    AND `customer_title_i18n`.`locale` = 'fr_FR';


# ======================================================================================================================
# Adding missing resources
# ======================================================================================================================

SELECT @max_id := MAX(`id`) FROM `resource`;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max_id+1, 'admin.hook', NOW(), NOW()),
(@max_id+2, 'admin.module-hook', NOW(), NOW()),
(@max_id+3, 'admin.sales', NOW(), NOW()),
(@max_id+4, 'admin.administrator', NOW(), NOW()),
(@max_id+5, 'admin.configuration.category', NOW(), NOW()),
(@max_id+6, 'admin.configuration.shipping-configuration', NOW(), NOW()),
(@max_id+7, 'admin.configuration.tax-rule', NOW(), NOW()),
(@max_id+8, 'admin.hooks', NOW(), NOW()),
(@max_id+9, 'admin.import', NOW(), NOW()),
(@max_id+10, 'admin.modules', NOW(), NOW()),
(@max_id+11, 'admin.profile', NOW(), NOW())
;

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
{foreach $locales as $locale}
  (@max_id+1, '{$locale}', {intl l='Hooks' locale=$locale}),
  (@max_id+2, '{$locale}', {intl l='Hook positions' locale=$locale}),
  (@max_id+3, '{$locale}', {intl l='Sales management' locale=$locale}),
  (@max_id+4, '{$locale}', {intl l='Administrator list' locale=$locale}),
  (@max_id+5, '{$locale}', {intl l='Category configuration' locale=$locale}),
  (@max_id+6, '{$locale}', {intl l='Shipping configuration' locale=$locale}),
  (@max_id+7, '{$locale}', {intl l='Tax rules configuration' locale=$locale}),
  (@max_id+8, '{$locale}', {intl l='Hooks management' locale=$locale}),
  (@max_id+9, '{$locale}', {intl l='Data import / export' locale=$locale}),
  (@max_id+10, '{$locale}', {intl l='Modules management' locale=$locale}),
  (@max_id+11, '{$locale}', {intl l='Administration profiles management' locale=$locale}){if ! $locale@last},{/if}

{/foreach}
;

# ======================================================================================================================
# Adding cart id in order table
# ======================================================================================================================

ALTER TABLE  `order_version`
  ADD  `customer_id_version` INTEGER DEFAULT 0
  AFTER  `version_created_by`;


# ======================================================================================================================
# End of changes
# ======================================================================================================================

SET FOREIGN_KEY_CHECKS = 1;
