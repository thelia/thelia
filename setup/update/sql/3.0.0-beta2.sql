SET FOREIGN_KEY_CHECKS = 0;

-- add the ISO 3166-1 entries missing from the country table (#3528): Serbia, Montenegro,
-- Puerto Rico, South Sudan, Timor-Leste and the officially assigned dependent territories.
-- Nothing existing is renumbered or modified: the new countries take the ids following the
-- highest one already in use. Every statement below is guarded, so a second run is a no-op.
INSERT INTO `country` (`visible`, `isocode`, `isoalpha2`, `isoalpha3`, `by_default`, `shop_country`, `has_states`, `need_zip_code`, `zip_code_format`, `created_at`, `updated_at`)
SELECT `iso3166`.* FROM (
    SELECT 1 AS `visible`, '660' AS `isocode`, 'AI' AS `isoalpha2`, 'AIA' AS `isoalpha3`, 0 AS `by_default`, 0 AS `shop_country`, 0 AS `has_states`, 1 AS `need_zip_code`, '' AS `zip_code_format`, NOW() AS `created_at`, NOW() AS `updated_at`
    UNION ALL SELECT 1, '10', 'AQ', 'ATA', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '16', 'AS', 'ASM', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '533', 'AW', 'ABW', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '248', 'AX', 'ALA', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '60', 'BM', 'BMU', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '535', 'BQ', 'BES', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '74', 'BV', 'BVT', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '166', 'CC', 'CCK', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '531', 'CW', 'CUW', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '162', 'CX', 'CXR', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '732', 'EH', 'ESH', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '238', 'FK', 'FLK', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '234', 'FO', 'FRO', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '831', 'GG', 'GGY', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '292', 'GI', 'GIB', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '304', 'GL', 'GRL', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '239', 'GS', 'SGS', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '316', 'GU', 'GUM', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '334', 'HM', 'HMD', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '833', 'IM', 'IMN', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '86', 'IO', 'IOT', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '832', 'JE', 'JEY', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '136', 'KY', 'CYM', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '499', 'ME', 'MNE', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '446', 'MO', 'MAC', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '580', 'MP', 'MNP', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '500', 'MS', 'MSR', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '574', 'NF', 'NFK', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '612', 'PN', 'PCN', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '630', 'PR', 'PRI', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '275', 'PS', 'PSE', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '688', 'RS', 'SRB', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '654', 'SH', 'SHN', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '744', 'SJ', 'SJM', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '728', 'SS', 'SSD', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '534', 'SX', 'SXM', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '796', 'TC', 'TCA', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '772', 'TK', 'TKL', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '626', 'TL', 'TLS', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '581', 'UM', 'UMI', 0, 0, 0, 0, '', NOW(), NOW()
    UNION ALL SELECT 1, '92', 'VG', 'VGB', 0, 0, 0, 1, '', NOW(), NOW()
    UNION ALL SELECT 1, '850', 'VI', 'VIR', 0, 0, 0, 1, '', NOW(), NOW()
) AS `iso3166`
LEFT JOIN `country` AS `existing` ON `existing`.`isoalpha3` = `iso3166`.`isoalpha3`
WHERE `existing`.`id` IS NULL;

-- one translation row per shop language, for the countries added above
INSERT IGNORE INTO `country_i18n` (`id`, `locale`, `title`, `chapo`, `description`, `postscriptum`)
SELECT `country`.`id`, `lang`.`locale`, NULL, NULL, NULL, NULL
FROM `country`, `lang`
WHERE `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

-- fill the titles of the languages shipped with Thelia, leaving any existing one untouched
UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Anguilla'
    WHEN 'ATA' THEN 'Antarctica'
    WHEN 'ASM' THEN 'American Samoa'
    WHEN 'ABW' THEN 'Aruba'
    WHEN 'ALA' THEN 'Åland Islands'
    WHEN 'BMU' THEN 'Bermuda'
    WHEN 'BES' THEN 'Bonaire, Sint Eustatius and Saba'
    WHEN 'BVT' THEN 'Bouvet Island'
    WHEN 'CCK' THEN 'Cocos (Keeling) Islands'
    WHEN 'CUW' THEN 'Curaçao'
    WHEN 'CXR' THEN 'Christmas Island'
    WHEN 'ESH' THEN 'Western Sahara'
    WHEN 'FLK' THEN 'Falkland Islands (Malvinas)'
    WHEN 'FRO' THEN 'Faroe Islands'
    WHEN 'GGY' THEN 'Guernsey'
    WHEN 'GIB' THEN 'Gibraltar'
    WHEN 'GRL' THEN 'Greenland'
    WHEN 'SGS' THEN 'South Georgia and the South Sandwich Islands'
    WHEN 'GUM' THEN 'Guam'
    WHEN 'HMD' THEN 'Heard Island and McDonald Islands'
    WHEN 'IMN' THEN 'Isle of Man'
    WHEN 'IOT' THEN 'British Indian Ocean Territory'
    WHEN 'JEY' THEN 'Jersey'
    WHEN 'CYM' THEN 'Cayman Islands'
    WHEN 'MNE' THEN 'Montenegro'
    WHEN 'MAC' THEN 'Macao'
    WHEN 'MNP' THEN 'Northern Mariana Islands'
    WHEN 'MSR' THEN 'Montserrat'
    WHEN 'NFK' THEN 'Norfolk Island'
    WHEN 'PCN' THEN 'Pitcairn'
    WHEN 'PRI' THEN 'Puerto Rico'
    WHEN 'PSE' THEN 'State of Palestine'
    WHEN 'SRB' THEN 'Serbia'
    WHEN 'SHN' THEN 'Saint Helena, Ascension and Tristan da Cunha'
    WHEN 'SJM' THEN 'Svalbard and Jan Mayen'
    WHEN 'SSD' THEN 'South Sudan'
    WHEN 'SXM' THEN 'Sint Maarten (Dutch part)'
    WHEN 'TCA' THEN 'Turks and Caicos Islands'
    WHEN 'TKL' THEN 'Tokelau'
    WHEN 'TLS' THEN 'Timor-Leste'
    WHEN 'UMI' THEN 'United States Minor Outlying Islands'
    WHEN 'VGB' THEN 'Virgin Islands (British)'
    WHEN 'VIR' THEN 'Virgin Islands (U.S.)'
END
WHERE `country_i18n`.`locale` = 'en_US' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Anguilla'
    WHEN 'ATA' THEN 'Antarctique'
    WHEN 'ASM' THEN 'Samoa américaines'
    WHEN 'ABW' THEN 'Aruba'
    WHEN 'ALA' THEN 'Îles Åland'
    WHEN 'BMU' THEN 'Bermudes'
    WHEN 'BES' THEN 'Bonaire, Saint-Eustache et Saba'
    WHEN 'BVT' THEN 'Île Bouvet'
    WHEN 'CCK' THEN 'Îles Cocos (Keeling)'
    WHEN 'CUW' THEN 'Curaçao'
    WHEN 'CXR' THEN 'Île Christmas'
    WHEN 'ESH' THEN 'Sahara occidental'
    WHEN 'FLK' THEN 'Îles Malouines (Falkland)'
    WHEN 'FRO' THEN 'Îles Féroé'
    WHEN 'GGY' THEN 'Guernesey'
    WHEN 'GIB' THEN 'Gibraltar'
    WHEN 'GRL' THEN 'Groenland'
    WHEN 'SGS' THEN 'Géorgie du Sud-et-les Îles Sandwich du Sud'
    WHEN 'GUM' THEN 'Guam'
    WHEN 'HMD' THEN 'Île Heard-et-Îles MacDonald'
    WHEN 'IMN' THEN 'Île de Man'
    WHEN 'IOT' THEN 'Territoire britannique de l\'océan Indien'
    WHEN 'JEY' THEN 'Jersey'
    WHEN 'CYM' THEN 'Îles Caïmans'
    WHEN 'MNE' THEN 'Monténégro'
    WHEN 'MAC' THEN 'Macao'
    WHEN 'MNP' THEN 'Îles Mariannes du Nord'
    WHEN 'MSR' THEN 'Montserrat'
    WHEN 'NFK' THEN 'Île Norfolk'
    WHEN 'PCN' THEN 'Pitcairn'
    WHEN 'PRI' THEN 'Porto Rico'
    WHEN 'PSE' THEN 'État de Palestine'
    WHEN 'SRB' THEN 'Serbie'
    WHEN 'SHN' THEN 'Sainte-Hélène, Ascension et Tristan da Cunha'
    WHEN 'SJM' THEN 'Svalbard et Île Jan Mayen'
    WHEN 'SSD' THEN 'Soudan du Sud'
    WHEN 'SXM' THEN 'Saint-Martin (partie néerlandaise)'
    WHEN 'TCA' THEN 'Îles Turques-et-Caïques'
    WHEN 'TKL' THEN 'Tokelau'
    WHEN 'TLS' THEN 'Timor-Leste'
    WHEN 'UMI' THEN 'Îles mineures éloignées des États-Unis'
    WHEN 'VGB' THEN 'Îles Vierges britanniques'
    WHEN 'VIR' THEN 'Îles Vierges des États-Unis'
END
WHERE `country_i18n`.`locale` = 'fr_FR' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Anguilla'
    WHEN 'ATA' THEN 'Antarktis'
    WHEN 'ASM' THEN 'Amerikanisch-Samoa'
    WHEN 'ABW' THEN 'Aruba'
    WHEN 'ALA' THEN 'Ålandinseln'
    WHEN 'BMU' THEN 'Bermuda'
    WHEN 'BES' THEN 'Bonaire, Sint Eustatius und Saba'
    WHEN 'BVT' THEN 'Bouvetinsel'
    WHEN 'CCK' THEN 'Kokosinseln (Keelinginseln)'
    WHEN 'CUW' THEN 'Curaçao'
    WHEN 'CXR' THEN 'Weihnachtsinsel'
    WHEN 'ESH' THEN 'Westsahara'
    WHEN 'FLK' THEN 'Falklandinseln (Malwinen)'
    WHEN 'FRO' THEN 'Färöer'
    WHEN 'GGY' THEN 'Guernsey'
    WHEN 'GIB' THEN 'Gibraltar'
    WHEN 'GRL' THEN 'Grönland'
    WHEN 'SGS' THEN 'Südgeorgien und die Südlichen Sandwichinseln'
    WHEN 'GUM' THEN 'Guam'
    WHEN 'HMD' THEN 'Heard und McDonaldinseln'
    WHEN 'IMN' THEN 'Isle of Man'
    WHEN 'IOT' THEN 'Britisches Territorium im Indischen Ozean'
    WHEN 'JEY' THEN 'Jersey'
    WHEN 'CYM' THEN 'Kaimaninseln'
    WHEN 'MNE' THEN 'Montenegro'
    WHEN 'MAC' THEN 'Macau'
    WHEN 'MNP' THEN 'Nördliche Marianen'
    WHEN 'MSR' THEN 'Montserrat'
    WHEN 'NFK' THEN 'Norfolkinsel'
    WHEN 'PCN' THEN 'Pitcairninseln'
    WHEN 'PRI' THEN 'Puerto Rico'
    WHEN 'PSE' THEN 'Staat Palästina'
    WHEN 'SRB' THEN 'Serbien'
    WHEN 'SHN' THEN 'St. Helena, Ascension und Tristan da Cunha'
    WHEN 'SJM' THEN 'Spitzbergen und Jan Mayen'
    WHEN 'SSD' THEN 'Südsudan'
    WHEN 'SXM' THEN 'Sint Maarten (niederländischer Teil)'
    WHEN 'TCA' THEN 'Turks- und Caicosinseln'
    WHEN 'TKL' THEN 'Tokelau'
    WHEN 'TLS' THEN 'Timor-Leste'
    WHEN 'UMI' THEN 'Amerikanische Überseeinseln'
    WHEN 'VGB' THEN 'Britische Jungferninseln'
    WHEN 'VIR' THEN 'Amerikanische Jungferninseln'
END
WHERE `country_i18n`.`locale` = 'de_DE' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Anguila'
    WHEN 'ATA' THEN 'Antártida'
    WHEN 'ASM' THEN 'Samoa Americana'
    WHEN 'ABW' THEN 'Aruba'
    WHEN 'ALA' THEN 'Islas Åland'
    WHEN 'BMU' THEN 'Bermudas'
    WHEN 'BES' THEN 'Bonaire, San Eustaquio y Saba'
    WHEN 'BVT' THEN 'Isla Bouvet'
    WHEN 'CCK' THEN 'Islas Cocos (Keeling)'
    WHEN 'CUW' THEN 'Curazao'
    WHEN 'CXR' THEN 'Isla de Navidad'
    WHEN 'ESH' THEN 'Sáhara Occidental'
    WHEN 'FLK' THEN 'Islas Malvinas (Falkland)'
    WHEN 'FRO' THEN 'Islas Feroe'
    WHEN 'GGY' THEN 'Guernesey'
    WHEN 'GIB' THEN 'Gibraltar'
    WHEN 'GRL' THEN 'Groenlandia'
    WHEN 'SGS' THEN 'Islas Georgias del Sur y Sandwich del Sur'
    WHEN 'GUM' THEN 'Guam'
    WHEN 'HMD' THEN 'Islas Heard y McDonald'
    WHEN 'IMN' THEN 'Isla de Man'
    WHEN 'IOT' THEN 'Territorio Británico del Océano Índico'
    WHEN 'JEY' THEN 'Jersey'
    WHEN 'CYM' THEN 'Islas Caimán'
    WHEN 'MNE' THEN 'Montenegro'
    WHEN 'MAC' THEN 'Macao'
    WHEN 'MNP' THEN 'Islas Marianas del Norte'
    WHEN 'MSR' THEN 'Montserrat'
    WHEN 'NFK' THEN 'Isla Norfolk'
    WHEN 'PCN' THEN 'Pitcairn'
    WHEN 'PRI' THEN 'Puerto Rico'
    WHEN 'PSE' THEN 'Estado de Palestina'
    WHEN 'SRB' THEN 'Serbia'
    WHEN 'SHN' THEN 'Santa Elena, Ascensión y Tristán de Acuña'
    WHEN 'SJM' THEN 'Svalbard y Jan Mayen'
    WHEN 'SSD' THEN 'Sudán del Sur'
    WHEN 'SXM' THEN 'Sint Maarten (parte neerlandesa)'
    WHEN 'TCA' THEN 'Islas Turcas y Caicos'
    WHEN 'TKL' THEN 'Tokelau'
    WHEN 'TLS' THEN 'Timor-Leste'
    WHEN 'UMI' THEN 'Islas menores alejadas de los Estados Unidos'
    WHEN 'VGB' THEN 'Islas Vírgenes Británicas'
    WHEN 'VIR' THEN 'Islas Vírgenes de los Estados Unidos'
END
WHERE `country_i18n`.`locale` = 'es_ES' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Anguilla'
    WHEN 'ATA' THEN 'Antartide'
    WHEN 'ASM' THEN 'Samoa Americane'
    WHEN 'ABW' THEN 'Aruba'
    WHEN 'ALA' THEN 'Isole Åland'
    WHEN 'BMU' THEN 'Bermuda'
    WHEN 'BES' THEN 'Bonaire, Sint Eustatius e Saba'
    WHEN 'BVT' THEN 'Isola Bouvet'
    WHEN 'CCK' THEN 'Isole Cocos (Keeling)'
    WHEN 'CUW' THEN 'Curaçao'
    WHEN 'CXR' THEN 'Isola Christmas'
    WHEN 'ESH' THEN 'Sahara Occidentale'
    WHEN 'FLK' THEN 'Isole Falkland (Malvine)'
    WHEN 'FRO' THEN 'Isole Fær Øer'
    WHEN 'GGY' THEN 'Guernsey'
    WHEN 'GIB' THEN 'Gibilterra'
    WHEN 'GRL' THEN 'Groenlandia'
    WHEN 'SGS' THEN 'Georgia del Sud e Isole Sandwich Australi'
    WHEN 'GUM' THEN 'Guam'
    WHEN 'HMD' THEN 'Isole Heard e McDonald'
    WHEN 'IMN' THEN 'Isola di Man'
    WHEN 'IOT' THEN 'Territorio britannico dell\'Oceano Indiano'
    WHEN 'JEY' THEN 'Jersey'
    WHEN 'CYM' THEN 'Isole Cayman'
    WHEN 'MNE' THEN 'Montenegro'
    WHEN 'MAC' THEN 'Macao'
    WHEN 'MNP' THEN 'Isole Marianne Settentrionali'
    WHEN 'MSR' THEN 'Montserrat'
    WHEN 'NFK' THEN 'Isola Norfolk'
    WHEN 'PCN' THEN 'Pitcairn'
    WHEN 'PRI' THEN 'Puerto Rico'
    WHEN 'PSE' THEN 'Stato di Palestina'
    WHEN 'SRB' THEN 'Serbia'
    WHEN 'SHN' THEN 'Sant\'Elena, Ascensione e Tristan da Cunha'
    WHEN 'SJM' THEN 'Svalbard e Jan Mayen'
    WHEN 'SSD' THEN 'Sud Sudan'
    WHEN 'SXM' THEN 'Sint Maarten (parte olandese)'
    WHEN 'TCA' THEN 'Isole Turks e Caicos'
    WHEN 'TKL' THEN 'Tokelau'
    WHEN 'TLS' THEN 'Timor Est'
    WHEN 'UMI' THEN 'Isole minori esterne degli Stati Uniti'
    WHEN 'VGB' THEN 'Isole Vergini Britanniche'
    WHEN 'VIR' THEN 'Isole Vergini Americane'
END
WHERE `country_i18n`.`locale` = 'it_IT' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

UPDATE `country_i18n` INNER JOIN `country` ON `country`.`id` = `country_i18n`.`id`
SET `country_i18n`.`title` = CASE `country`.`isoalpha3`
    WHEN 'AIA' THEN 'Ангилья'
    WHEN 'ATA' THEN 'Антарктида'
    WHEN 'ASM' THEN 'Американское Самоа'
    WHEN 'ABW' THEN 'Аруба'
    WHEN 'ALA' THEN 'Аландские острова'
    WHEN 'BMU' THEN 'Бермудские острова'
    WHEN 'BES' THEN 'Бонэйр, Синт-Эстатиус и Саба'
    WHEN 'BVT' THEN 'Остров Буве'
    WHEN 'CCK' THEN 'Кокосовые острова'
    WHEN 'CUW' THEN 'Кюрасао'
    WHEN 'CXR' THEN 'Остров Рождества'
    WHEN 'ESH' THEN 'Западная Сахара'
    WHEN 'FLK' THEN 'Фолклендские острова (Мальвинские)'
    WHEN 'FRO' THEN 'Фарерские острова'
    WHEN 'GGY' THEN 'Гернси'
    WHEN 'GIB' THEN 'Гибралтар'
    WHEN 'GRL' THEN 'Гренландия'
    WHEN 'SGS' THEN 'Южная Георгия и Южные Сандвичевы острова'
    WHEN 'GUM' THEN 'Гуам'
    WHEN 'HMD' THEN 'Острова Херд и Макдональд'
    WHEN 'IMN' THEN 'Остров Мэн'
    WHEN 'IOT' THEN 'Британская территория в Индийском океане'
    WHEN 'JEY' THEN 'Джерси'
    WHEN 'CYM' THEN 'Острова Кайман'
    WHEN 'MNE' THEN 'Черногория'
    WHEN 'MAC' THEN 'Макао'
    WHEN 'MNP' THEN 'Северные Марианские острова'
    WHEN 'MSR' THEN 'Монтсеррат'
    WHEN 'NFK' THEN 'Остров Норфолк'
    WHEN 'PCN' THEN 'Питкэрн'
    WHEN 'PRI' THEN 'Пуэрто-Рико'
    WHEN 'PSE' THEN 'Государство Палестина'
    WHEN 'SRB' THEN 'Сербия'
    WHEN 'SHN' THEN 'Острова Святой Елены, Вознесения и Тристан-да-Кунья'
    WHEN 'SJM' THEN 'Шпицберген и Ян-Майен'
    WHEN 'SSD' THEN 'Южный Судан'
    WHEN 'SXM' THEN 'Синт-Мартен (нидерландская часть)'
    WHEN 'TCA' THEN 'Острова Тёркс и Кайкос'
    WHEN 'TKL' THEN 'Токелау'
    WHEN 'TLS' THEN 'Тимор-Лесте'
    WHEN 'UMI' THEN 'Внешние малые острова США'
    WHEN 'VGB' THEN 'Британские Виргинские острова'
    WHEN 'VIR' THEN 'Американские Виргинские острова'
END
WHERE `country_i18n`.`locale` = 'ru_RU' AND `country_i18n`.`title` IS NULL AND `country`.`isoalpha3` IN ('AIA', 'ATA', 'ASM', 'ABW', 'ALA', 'BMU', 'BES', 'BVT', 'CCK', 'CUW', 'CXR', 'ESH', 'FLK', 'FRO', 'GGY', 'GIB', 'GRL', 'SGS', 'GUM', 'HMD', 'IMN', 'IOT', 'JEY', 'CYM', 'MNE', 'MAC', 'MNP', 'MSR', 'NFK', 'PCN', 'PRI', 'PSE', 'SRB', 'SHN', 'SJM', 'SSD', 'SXM', 'TCA', 'TKL', 'TLS', 'UMI', 'VGB', 'VIR');

-- ---------------------------------------------------------------------
-- Columns added to the schema after the 3.0.0-beta1 tag
--
-- order_status.equivalent_code (#3533), order.customer_discount_rate (#3532)
-- and currency.isocode_numeric (#3549) reached setup/thelia.sql without an
-- update script, so only fresh installs had them. The DDL below matches
-- setup/thelia.sql exactly, column position included, so an updated shop and
-- a fresh install end up with the same structure.
--
-- Each ALTER is guarded on information_schema, so a shop that already has the
-- column - anyone who installed from a main snapshot - is left untouched
-- instead of failing on a duplicate column.
-- ---------------------------------------------------------------------

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_status' AND `COLUMN_NAME` = 'equivalent_code');
SET @statement := IF(@add_column, 'ALTER TABLE `order_status` ADD `equivalent_code` VARCHAR(45) AFTER `code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order' AND `COLUMN_NAME` = 'customer_discount_rate');
SET @statement := IF(@add_column, 'ALTER TABLE `order` ADD `customer_discount_rate` DECIMAL(16,6) DEFAULT 0.000000 COMMENT \'the customer discount rate, as a percentage, already included in the order products prices\' AFTER `discount`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- `order` is versionable: the version table mirrors its columns
SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'order_version' AND `COLUMN_NAME` = 'customer_discount_rate');
SET @statement := IF(@add_column, 'ALTER TABLE `order_version` ADD `customer_discount_rate` DECIMAL(16,6) DEFAULT 0.000000 COMMENT \'the customer discount rate, as a percentage, already included in the order products prices\' AFTER `discount`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

SET @add_column := (SELECT COUNT(*) = 0 FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'currency' AND `COLUMN_NAME` = 'isocode_numeric');
SET @statement := IF(@add_column, 'ALTER TABLE `currency` ADD `isocode_numeric` VARCHAR(3) COMMENT \'the ISO 4217 numeric currency code\' AFTER `code`', 'DO 0');
PREPARE add_column_statement FROM @statement;
EXECUTE add_column_statement;
DEALLOCATE PREPARE add_column_statement;

-- ISO 4217 numeric codes for the currencies seeded by setup/insert.sql, matched
-- on the alpha-3 code. Currencies added by the shop, and any numeric code
-- already filled in, are left alone.
UPDATE `currency` SET `isocode_numeric` = CASE `code`
    WHEN 'EUR' THEN '978'
    WHEN 'USD' THEN '840'
    WHEN 'GBP' THEN '826'
    WHEN 'CHF' THEN '756'
    WHEN 'MXN' THEN '484'
    WHEN 'PLN' THEN '985'
    WHEN 'CNY' THEN '156'
    WHEN 'NOK' THEN '578'
    WHEN 'MDL' THEN '498'
    WHEN 'PYG' THEN '600'
    WHEN 'ARS' THEN '032'
    WHEN 'BYR' THEN '974'
    WHEN 'FJD' THEN '242'
    WHEN 'RSD' THEN '941'
    WHEN 'SEK' THEN '752'
    WHEN 'HRK' THEN '191'
    WHEN 'DKK' THEN '208'
    WHEN 'NGN' THEN '566'
    WHEN 'HKD' THEN '344'
    WHEN 'CAD' THEN '124'
    WHEN 'SAR' THEN '682'
    WHEN 'CZK' THEN '203'
    WHEN 'CRC' THEN '188'
    WHEN 'AZN' THEN '944'
    WHEN 'IDR' THEN '360'
    WHEN 'PKR' THEN '586'
    WHEN 'BRL' THEN '986'
    WHEN 'VND' THEN '704'
    WHEN 'PHP' THEN '608'
    WHEN 'GTQ' THEN '320'
    WHEN 'TRY' THEN '949'
    WHEN 'JPY' THEN '392'
    WHEN 'RUB' THEN '643'
    WHEN 'PEN' THEN '604'
    WHEN 'EGP' THEN '818'
    WHEN 'GEL' THEN '981'
    WHEN 'BOB' THEN '068'
    WHEN 'AED' THEN '784'
    WHEN 'THB' THEN '764'
    WHEN 'ILS' THEN '376'
    WHEN 'MYR' THEN '458'
    WHEN 'VEF' THEN '937'
    WHEN 'HUF' THEN '348'
    WHEN 'KES' THEN '404'
    WHEN 'UAH' THEN '980'
    WHEN 'TND' THEN '788'
    WHEN 'BGN' THEN '975'
    WHEN 'INR' THEN '356'
END
WHERE (`isocode_numeric` IS NULL OR `isocode_numeric` = '')
  AND `code` IN ('EUR', 'USD', 'GBP', 'CHF', 'MXN', 'PLN', 'CNY', 'NOK', 'MDL', 'PYG', 'ARS', 'BYR', 'FJD', 'RSD', 'SEK', 'HRK', 'DKK', 'NGN', 'HKD', 'CAD', 'SAR', 'CZK', 'CRC', 'AZN', 'IDR', 'PKR', 'BRL', 'VND', 'PHP', 'GTQ', 'TRY', 'JPY', 'RUB', 'PEN', 'EGP', 'GEL', 'BOB', 'AED', 'THB', 'ILS', 'MYR', 'VEF', 'HUF', 'KES', 'UAH', 'TND', 'BGN', 'INR');

-- ---------------------------------------------------------------------
-- Two wrong ISO 3166-1 codes in the country seed (#3559)
--
-- Belize was seeded with the alpha-2 code 'BL', which belongs to
-- Saint-Barthelemy and is also seeded: two rows claimed the same code.
-- Libya was seeded with the numeric code '343' instead of '434'.
--
-- Matched on the alpha-3 code, and only on rows still carrying the wrong
-- value, so a shop that already corrected them by hand is left untouched.
-- ---------------------------------------------------------------------

UPDATE `country` SET `isoalpha2` = 'BZ' WHERE `isoalpha3` = 'BLZ' AND `isoalpha2` = 'BL';
UPDATE `country` SET `isocode` = '434' WHERE `isoalpha3` = 'LBY' AND `isocode` = '343';

SET FOREIGN_KEY_CHECKS = 1;
