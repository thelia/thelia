# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

# config table
ALTER TABLE `config` CHANGE `value` `value` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ;

# admin table
ALTER TABLE `admin` ADD UNIQUE index `login_UNIQUE`(`login`);

# message table
ALTER TABLE `message` ADD `text_layout_file_name` VARCHAR( 255 ) AFTER `secured` ;
ALTER TABLE `message` ADD `text_template_file_name` VARCHAR( 255 ) AFTER `text_layout_file_name` ;
ALTER TABLE `message` ADD `html_layout_file_name` VARCHAR( 255 ) AFTER `text_template_file_name` ;
ALTER TABLE `message` ADD `html_template_file_name` VARCHAR( 255 ) AFTER `html_layout_file_name` ;

# message_version table
ALTER TABLE `message_version` ADD `text_layout_file_name` VARCHAR( 255 ) AFTER `secured` ;
ALTER TABLE `message_version` ADD `text_template_file_name` VARCHAR( 255 ) AFTER `text_layout_file_name` ;
ALTER TABLE `message_version` ADD `html_layout_file_name` VARCHAR( 255 ) AFTER `text_template_file_name` ;
ALTER TABLE `message_version` ADD `html_template_file_name` VARCHAR( 255 ) AFTER `html_layout_file_name` ;

# admin_log table
ALTER TABLE `admin_log` ADD `resource` VARCHAR( 255 ) AFTER `admin_lastname` ;
ALTER TABLE `admin_log` ADD `message` TEXT AFTER `action` ;
ALTER TABLE `admin_log` CHANGE `request` `request` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci ;

# category_i18n table
ALTER TABLE `category_i18n` ADD `meta_title` VARCHAR( 255 ) AFTER `postscriptum` ;
ALTER TABLE `category_i18n` ADD `meta_description` TEXT AFTER `meta_title` ;
ALTER TABLE `category_i18n` ADD `meta_keywords` TEXT AFTER `meta_description` ;

# product_i18n table
ALTER TABLE `product_i18n` ADD `meta_title` VARCHAR( 255 ) AFTER `postscriptum` ;
ALTER TABLE `product_i18n` ADD `meta_description` TEXT AFTER `meta_title` ;
ALTER TABLE `product_i18n` ADD `meta_keywords` TEXT AFTER `meta_description` ;

# folder_i18n table
ALTER TABLE `folder_i18n` ADD `meta_title` VARCHAR( 255 ) AFTER `postscriptum` ;
ALTER TABLE `folder_i18n` ADD `meta_description` TEXT AFTER `meta_title` ;
ALTER TABLE `folder_i18n` ADD `meta_keywords` TEXT AFTER `meta_description` ;

# content_i18n table
ALTER TABLE `content_i18n` ADD `meta_title` VARCHAR( 255 ) AFTER `postscriptum` ;
ALTER TABLE `content_i18n` ADD `meta_description` TEXT AFTER `meta_title` ;
ALTER TABLE `content_i18n` ADD `meta_keywords` TEXT AFTER `meta_description` ;


# config content

INSERT INTO `config` (`name`, `value`, `secured`, `hidden`, `created_at`, `updated_at`) VALUES
('active-admin-template', 'default', 0, 0, NOW(), NOW()),
('active-pdf-template', 'default', 0, 0, NOW(), NOW()),
('active-mail-template', 'default', 0, 0, NOW(), NOW()),
('pdf_invoice_file', 'invoice', 0, 0, NOW(), NOW()),
('pdf_delivery_file', 'delivery', 0, 0, NOW(), NOW())
;

UPDATE `config` SET `name`='active-front-template' WHERE `name`='active-template';
UPDATE `config` SET `name`='obsolete_rewriten_url_view', `value`='obsolete-rewritten-url' WHERE `name`='passed_url_view';
UPDATE `config` SET `name`='store_name' WHERE `name`='company_name';
UPDATE `config` SET `name`='store_email' WHERE `name`='company_email';
UPDATE `config` SET `value`='2.0.0-beta2' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='beta2' WHERE `name`='thelia_extra_version';

INSERT INTO `module` (`code`, `type`, `activate`, `position`, `full_namespace`, `created_at`, `updated_at`) VALUES
('Front', 1, 1, 2, 'Front\\Front', NOW(), NOW());

INSERT INTO  `module_i18n` (`id`, `locale`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(LAST_INSERT_ID(),  'en_US',  'Front office integration', NULL,  NULL,  NULL),
(LAST_INSERT_ID(),  'fr_FR',  'Module Front office', NULL,  NULL,  NULL);

TRUNCATE TABLE `area`;

INSERT INTO `area` (`id`, `name`, `postage`, `created_at`, `updated_at`) VALUES
(1, 'France', NULL, NOW(), NOW()),
(2, 'A Zone', NULL, NOW(), NOW()),
(3, 'B Zone', NULL, NOW(), NOW()),
(4, 'C Zone', NULL, NOW(), NOW()),
(5, 'D Zone', NULL, NOW(), NOW()),
(6, 'France OM1', NULL, NOW(), NOW()),
(7, 'France OM2', NULL, NOW(), NOW());

TRUNCATE TABLE `area_delivery_module`;

INSERT INTO `area_delivery_module` (`id`, `area_id`, `delivery_module_id`, `created_at`, `updated_at`) VALUES
(1, 1, 2, NOW(), NOW()),
(2, 2, 2, NOW(), NOW()),
(3, 3, 2, NOW(), NOW()),
(4, 4, 2, NOW(), NOW()),
(5, 5, 2, NOW(), NOW()),
(6, 6, 2, NOW(), NOW());

TRUNCATE TABLE `country`;

INSERT INTO `country` (`id`, `area_id`, `isocode`, `isoalpha2`, `isoalpha3`, `by_default`, `shop_country`, `created_at`, `updated_at`) VALUES
(1, 5, '4', 'AF', 'AFG', 0, 0, NOW(), NOW()),
(2, 4, '710', 'ZA', 'ZAF', 0, 0, NOW(), NOW()),
(3, 3, '8', 'AL', 'ALB', 0, 0, NOW(), NOW()),
(4, 3, '12', 'DZ', 'DZA', 0, 0, NOW(), NOW()),
(5, 2, '276', 'DE', 'DEU', 0, 0, NOW(), NOW()),
(6, 1, '20', 'AD', 'AND', 0, 0, NOW(), NOW()),
(7, 4, '24', 'AO', 'AGO', 0, 0, NOW(), NOW()),
(8, 5, '28', 'AG', 'ATG', 0, 0, NOW(), NOW()),
(9, 4, '682', 'SA', 'SAU', 0, 0, NOW(), NOW()),
(10, 5, '32', 'AR', 'ARG', 0, 0, NOW(), NOW()),
(11, 3, '51', 'AM', 'ARM', 0, 0, NOW(), NOW()),
(12, 5, '36', 'AU', 'AUS', 0, 0, NOW(), NOW()),
(13, 2, '40', 'AT', 'AUT', 0, 0, NOW(), NOW()),
(14, 3, '31', 'AZ', 'AZE', 0, 0, NOW(), NOW()),
(15, 5, '44', 'BS', 'BHS', 0, 0, NOW(), NOW()),
(16, 4, '48', 'BR', 'BHR', 0, 0, NOW(), NOW()),
(17, 5, '50', 'BD', 'BGD', 0, 0, NOW(), NOW()),
(18, 5, '52', 'BB', 'BRB', 0, 0, NOW(), NOW()),
(19, 3, '585', 'PW', 'PLW', 0, 0, NOW(), NOW()),
(20, 5, '56', 'BE', 'BEL', 0, 0, NOW(), NOW()),
(21, 5, '84', 'BL', 'BLZ', 0, 0, NOW(), NOW()),
(22, 4, '204', 'BJ', 'BEN', 0, 0, NOW(), NOW()),
(23, NULL, '64', 'BT', 'BTN', 0, 0, NOW(), NOW()),
(24, 3, '112', 'BY', 'BLR', 0, 0, NOW(), NOW()),
(25, 5, '104', 'MM', 'MMR', 0, 0, NOW(), NOW()),
(26, 5, '68', 'BO', 'BOL', 0, 0, NOW(), NOW()),
(27, 3, '70', 'BA', 'BIH', 0, 0, NOW(), NOW()),
(28, 4, '72', 'BW', 'BWA', 0, 0, NOW(), NOW()),
(29, 5, '76', 'BR', 'BRA', 0, 0, NOW(), NOW()),
(30, 5, '96', 'BN', 'BRN', 0, 0, NOW(), NOW()),
(31, 3, '100', 'BG', 'BGR', 0, 0, NOW(), NOW()),
(32, 5, '854', 'BF', 'BFA', 0, 0, NOW(), NOW()),
(33, 4, '108', 'BI', 'BDI', 0, 0, NOW(), NOW()),
(34, 5, '116', 'KH', 'KHM', 0, 0, NOW(), NOW()),
(35, 4, '120', 'CM', 'CMR', 0, 0, NOW(), NOW()),
(37, 4, '132', 'CV', 'CPV', 0, 0, NOW(), NOW()),
(38, 5, '152', 'CL', 'CHL', 0, 0, NOW(), NOW()),
(39, 5, '156', 'CN', 'CHN', 0, 0, NOW(), NOW()),
(40, 2, '196', 'CY', 'CYP', 0, 0, NOW(), NOW()),
(41, 5, '170', 'CO', 'COL', 0, 0, NOW(), NOW()),
(42, 4, '174', 'KM', 'COM', 0, 0, NOW(), NOW()),
(43, 4, '178', 'CG', 'COG', 0, 0, NOW(), NOW()),
(44, 5, '184', 'CK', 'COK', 0, 0, NOW(), NOW()),
(45, 5, '408', 'KP', 'PRK', 0, 0, NOW(), NOW()),
(46, 5, '410', 'KR', 'KOR', 0, 0, NOW(), NOW()),
(47, 5, '188', 'CR', 'CRI', 0, 0, NOW(), NOW()),
(48, 4, '384', 'CI', 'CIV', 0, 0, NOW(), NOW()),
(49, 2, '191', 'HR', 'HRV', 0, 0, NOW(), NOW()),
(50, 5, '192', 'CU', 'CUB', 0, 0, NOW(), NOW()),
(51, 2, '208', 'DK', 'DNK', 0, 0, NOW(), NOW()),
(52, 5, '262', 'DJ', 'DJI', 0, 0, NOW(), NOW()),
(53, 5, '212', 'DM', 'DMA', 0, 0, NOW(), NOW()),
(54, 4, '818', 'EG', 'EGY', 0, 0, NOW(), NOW()),
(55, 4, '784', 'AE', 'ARE', 0, 0, NOW(), NOW()),
(56, 5, '218', 'EC', 'ECU', 0, 0, NOW(), NOW()),
(57, 4, '232', 'ER', 'ERI', 0, 0, NOW(), NOW()),
(58, 2, '724', 'ES', 'ESP', 0, 0, NOW(), NOW()),
(59, 2, '233', 'EE', 'EST', 0, 0, NOW(), NOW()),
(61, 4, '231', 'ET', 'ETH', 0, 0, NOW(), NOW()),
(62, 5, '242', 'FJ', 'FJI', 0, 0, NOW(), NOW()),
(63, 2, '246', 'FI', 'FIN', 0, 0, NOW(), NOW()),
(64, 1, '250', 'FR', 'FRA', 1, 1, NOW(), NOW()),
(65, 4, '266', 'GA', 'GAB', 0, 0, NOW(), NOW()),
(66, 4, '270', 'GM', 'GMB', 0, 0, NOW(), NOW()),
(67, 3, '268', 'GE', 'GEO', 0, 0, NOW(), NOW()),
(68, 4, '288', 'GH', 'GHA', 0, 0, NOW(), NOW()),
(69, 2, '300', 'GR', 'GRC', 0, 0, NOW(), NOW()),
(70, 5, '308', 'GD', 'GRD', 0, 0, NOW(), NOW()),
(71, 5, '320', 'GT', 'GTM', 0, 0, NOW(), NOW()),
(72, 4, '324', 'GN', 'GIN', 0, 0, NOW(), NOW()),
(73, 4, '624', 'GW', 'GNB', 0, 0, NOW(), NOW()),
(74, 4, '226', 'GQ', 'GNQ', 0, 0, NOW(), NOW()),
(75, 5, '328', 'GY', 'GUY', 0, 0, NOW(), NOW()),
(76, 5, '332', 'HT', 'HTI', 0, 0, NOW(), NOW()),
(77, 5, '340', 'HN', 'HND', 0, 0, NOW(), NOW()),
(78, 2, '348', 'HU', 'HUN', 0, 0, NOW(), NOW()),
(79, 5, '356', 'IN', 'IND', 0, 0, NOW(), NOW()),
(80, 5, '360', 'ID', 'IDN', 0, 0, NOW(), NOW()),
(81, 4, '364', 'IR', 'IRN', 0, 0, NOW(), NOW()),
(82, 4, '368', 'IQ', 'IRQ', 0, 0, NOW(), NOW()),
(83, 2, '372', 'IE', 'IRL', 0, 0, NOW(), NOW()),
(84, 3, '352', 'IS', 'ISL', 0, 0, NOW(), NOW()),
(85, 4, '376', 'IL', 'ISR', 0, 0, NOW(), NOW()),
(86, 2, '380', 'IT', 'ITA', 0, 0, NOW(), NOW()),
(87, 5, '388', 'JM', 'JAM', 0, 0, NOW(), NOW()),
(88, 5, '392', 'JP', 'JPN', 0, 0, NOW(), NOW()),
(89, 4, '400', 'JO', 'JOR', 0, 0, NOW(), NOW()),
(90, 5, '398', 'KZ', 'KAZ', 0, 0, NOW(), NOW()),
(91, 4, '404', 'KE', 'KEN', 0, 0, NOW(), NOW()),
(92, 5, '417', 'KG', 'KGZ', 0, 0, NOW(), NOW()),
(93, 5, '296', 'KI', 'KIR', 0, 0, NOW(), NOW()),
(94, 4, '414', 'KW', 'KWT', 0, 0, NOW(), NOW()),
(95, 5, '418', 'LA', 'LAO', 0, 0, NOW(), NOW()),
(96, 4, '426', 'LS', 'LSO', 0, 0, NOW(), NOW()),
(97, 2, '428', 'LV', 'LVA', 0, 0, NOW(), NOW()),
(98, 4, '422', 'LB', 'LBN', 0, 0, NOW(), NOW()),
(99, 4, '430', 'LR', 'LBR', 0, 0, NOW(), NOW()),
(100, 4, '343', 'LY', 'LBY', 0, 0, NOW(), NOW()),
(101, 2, '438', 'LI', 'LIE', 0, 0, NOW(), NOW()),
(102, 2, '440', 'LT', 'LTU', 0, 0, NOW(), NOW()),
(103, 2, '442', 'LU', 'LUX', 0, 0, NOW(), NOW()),
(104, 3, '807', 'MK', 'MKD', 0, 0, NOW(), NOW()),
(105, 4, '450', 'MD', 'MDG', 0, 0, NOW(), NOW()),
(106, 5, '458', 'MY', 'MYS', 0, 0, NOW(), NOW()),
(107, 4, '454', 'MW', 'MWI', 0, 0, NOW(), NOW()),
(108, 5, '462', 'MV', 'MDV', 0, 0, NOW(), NOW()),
(109, 4, '466', 'ML', 'MLI', 0, 0, NOW(), NOW()),
(110, 2, '470', 'MT', 'MLT', 0, 0, NOW(), NOW()),
(111, 3, '504', 'MA', 'MAR', 0, 0, NOW(), NOW()),
(112, 5, '584', 'MH', 'MHL', 0, 0, NOW(), NOW()),
(113, 4, '480', 'MU', 'MUS', 0, 0, NOW(), NOW()),
(114, 4, '478', 'MR', 'MRT', 0, 0, NOW(), NOW()),
(115, 5, '484', 'MX', 'MEX', 0, 0, NOW(), NOW()),
(116, NULL, '583', 'FM', 'FSM', 0, 0, NOW(), NOW()),
(117, 3, '498', 'MD', 'MDA', 0, 0, NOW(), NOW()),
(118, 1, '492', 'MC', 'MCO', 0, 0, NOW(), NOW()),
(119, 5, '496', 'MN', 'MNG', 0, 0, NOW(), NOW()),
(120, 4, '508', 'MZ', 'MOZ', 0, 0, NOW(), NOW()),
(121, 4, '516', 'NA', 'NAM', 0, 0, NOW(), NOW()),
(122, 5, '520', 'NR', 'NRU', 0, 0, NOW(), NOW()),
(123, 5, '524', 'NP', 'NPL', 0, 0, NOW(), NOW()),
(124, 5, '558', 'NI', 'NIC', 0, 0, NOW(), NOW()),
(125, 4, '562', 'NE', 'NER', 0, 0, NOW(), NOW()),
(126, 4, '566', 'NG', 'NGA', 0, 0, NOW(), NOW()),
(127, NULL, '570', 'NU', 'NIU', 0, 0, NOW(), NOW()),
(128, 3, '578', 'NO', 'NOR', 0, 0, NOW(), NOW()),
(129, 5, '554', 'NZ', 'NZL', 0, 0, NOW(), NOW()),
(130, 4, '512', 'OM', 'OMN', 0, 0, NOW(), NOW()),
(131, 4, '800', 'UG', 'UGA', 0, 0, NOW(), NOW()),
(132, 5, '860', 'UZ', 'UZB', 0, 0, NOW(), NOW()),
(133, 5, '586', 'PK', 'PAK', 0, 0, NOW(), NOW()),
(134, 5, '591', 'PA', 'PAN', 0, 0, NOW(), NOW()),
(135, 5, '598', 'PG', 'PNG', 0, 0, NOW(), NOW()),
(136, 5, '600', 'PY', 'PRY', 0, 0, NOW(), NOW()),
(137, 2, '528', 'NL', 'NLD', 0, 0, NOW(), NOW()),
(138, 5, '604', 'PE', 'PER', 0, 0, NOW(), NOW()),
(139, 5, '608', 'PH', 'PHL', 0, 0, NOW(), NOW()),
(140, 2, '616', 'PL', 'POL', 0, 0, NOW(), NOW()),
(141, 2, '620', 'PT', 'PRT', 0, 0, NOW(), NOW()),
(142, 4, '634', 'QA', 'QAT', 0, 0, NOW(), NOW()),
(143, 4, '140', 'CF', 'CAF', 0, 0, NOW(), NOW()),
(144, 5, '214', 'DO', 'DOM', 0, 0, NOW(), NOW()),
(145, 2, '203', 'CZ', 'CZE', 0, 0, NOW(), NOW()),
(146, 2, '642', 'RO', 'ROU', 0, 0, NOW(), NOW()),
(147, 2, '826', 'GB', 'GBR', 0, 0, NOW(), NOW()),
(148, 3, '643', 'RU', 'RUS', 0, 0, NOW(), NOW()),
(149, 4, '646', 'RW', 'RWA', 0, 0, NOW(), NOW()),
(150, 5, '659', 'KN', 'KNA', 0, 0, NOW(), NOW()),
(151, 5, '662', 'LC', 'LCA', 0, 0, NOW(), NOW()),
(152, 2, '674', 'SM', 'SMR', 0, 0, NOW(), NOW()),
(153, 5, '670', 'VC', 'VCT', 0, 0, NOW(), NOW()),
(154, 5, '90', 'SB', 'SLB', 0, 0, NOW(), NOW()),
(155, NULL, '222', 'SV', 'SLV', 0, 0, NOW(), NOW()),
(156, 5, '882', 'WS', 'WSM', 0, 0, NOW(), NOW()),
(157, 4, '678', 'ST', 'STP', 0, 0, NOW(), NOW()),
(158, 4, '686', 'SN', 'SEN', 0, 0, NOW(), NOW()),
(159, 4, '690', 'SC', 'SYC', 0, 0, NOW(), NOW()),
(160, 4, '694', 'SL', 'SLE', 0, 0, NOW(), NOW()),
(161, 5, '702', 'SG', 'SGP', 0, 0, NOW(), NOW()),
(162, 2, '703', 'SK', 'SVK', 0, 0, NOW(), NOW()),
(163, 2, '705', 'SI', 'SVN', 0, 0, NOW(), NOW()),
(164, 4, '706', 'SO', 'SOM', 0, 0, NOW(), NOW()),
(165, 4, '729', 'SD', 'SDN', 0, 0, NOW(), NOW()),
(166, 5, '144', 'LK', 'LKA', 0, 0, NOW(), NOW()),
(167, 2, '752', 'SE', 'SWE', 0, 0, NOW(), NOW()),
(168, 2, '756', 'CH', 'CHE', 0, 0, NOW(), NOW()),
(169, 5, '740', 'SR', 'SUR', 0, 0, NOW(), NOW()),
(170, 4, '748', 'SZ', 'SWZ', 0, 0, NOW(), NOW()),
(171, 4, '760', 'SY', 'SYR', 0, 0, NOW(), NOW()),
(172, 5, '762', 'TJ', 'TJK', 0, 0, NOW(), NOW()),
(173, 5, '834', 'TZ', 'TZA', 0, 0, NOW(), NOW()),
(174, 4, '148', 'TD', 'TCD', 0, 0, NOW(), NOW()),
(175, 5, '764', 'TH', 'THA', 0, 0, NOW(), NOW()),
(176, 4, '768', 'TG', 'TGO', 0, 0, NOW(), NOW()),
(177, 5, '776', 'TO', 'TON', 0, 0, NOW(), NOW()),
(178, 5, '780', 'TT', 'TTO', 0, 0, NOW(), NOW()),
(179, 3, '788', 'TN', 'TUN', 0, 0, NOW(), NOW()),
(180, 5, '795', 'TM', 'TKM', 0, 0, NOW(), NOW()),
(181, 3, '792', 'TR', 'TUR', 0, 0, NOW(), NOW()),
(182, 5, '798', 'TV', 'TUV', 0, 0, NOW(), NOW()),
(183, 2, '804', 'UA', 'UKR', 0, 0, NOW(), NOW()),
(184, 5, '858', 'UY', 'URY', 0, 0, NOW(), NOW()),
(185, 2, '336', 'VA', 'VAT', 0, 0, NOW(), NOW()),
(186, 5, '548', 'VU', 'VUT', 0, 0, NOW(), NOW()),
(187, 5, '862', 'VE', 'VEN', 0, 0, NOW(), NOW()),
(188, 5, '704', 'VN', 'VNM', 0, 0, NOW(), NOW()),
(189, 4, '887', 'YE', 'YEM', 0, 0, NOW(), NOW()),
(191, 4, '180', 'CD', 'COD', 0, 0, NOW(), NOW()),
(192, 4, '894', 'ZM', 'ZMB', 0, 0, NOW(), NOW()),
(193, 4, '716', 'ZW', 'ZWE', 0, 0, NOW(), NOW()),
(196, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(197, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(198, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(199, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(200, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(201, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(202, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(203, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(204, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(205, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(206, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(207, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(208, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(209, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(210, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(211, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(212, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(213, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(214, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(215, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(216, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(217, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(218, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(219, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(220, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(221, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(222, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(223, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(224, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(225, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(226, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(227, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(228, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(229, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(230, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(231, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(232, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(233, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(234, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(235, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(236, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(237, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(238, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(239, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(240, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(241, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(242, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(243, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(244, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(245, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW()),
(246, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(247, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(248, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(249, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(250, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(251, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(252, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(253, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(254, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(255, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(256, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(257, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(258, 4, '124', 'CA', 'CAN', 0, 0, NOW(), NOW()),
(259, 6, '312', 'GP', 'GLP', 0, 0, NOW(), NOW()),
(260, 6, '254', 'GF', 'GUF', 0, 0, NOW(), NOW()),
(261, 6, '474', 'MQ', 'MTQ', 0, 0, NOW(), NOW()),
(262, 6, '175', 'YT', 'MYT', 0, 0, NOW(), NOW()),
(263, 6, '638', 'RE', 'REU', 0, 0, NOW(), NOW()),
(264, 6, '666', 'PM', 'SPM', 0, 0, NOW(), NOW()),
(265, 7, '540', 'NC', 'NCL', 0, 0, NOW(), NOW()),
(266, 7, '258', 'PF', 'PYF', 0, 0, NOW(), NOW()),
(267, 7, '876', 'WF', 'WLF', 0, 0, NOW(), NOW()),
(268, 4, '840', 'US', 'USA', 0, 0, NOW(), NOW());

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
