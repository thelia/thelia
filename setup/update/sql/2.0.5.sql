SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `order` DROP FOREIGN KEY `fk_order_cart_id`;

UPDATE `config` SET `value`='2.0.5' WHERE `name`='thelia_version';
UPDATE `config` SET `value`='5' WHERE `name`='thelia_release_version';
UPDATE `config` SET `value`='0' WHERE `name`='thelia_minus_version';
UPDATE `config` SET `value`='' WHERE `name`='thelia_extra_version';

UPDATE `config` SET `name`='form_firewall_active' WHERE `name`='from_firewall_active';

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `resource`;

INSERT INTO resource (`id`, `code`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'admin.search', NOW(), NOW());

INSERT INTO resource_i18n (`id`, `locale`, `title`) VALUES
(@max_id + 1, 'ar_SA', 'بحث'),
(@max_id + 1, 'cs_CZ', 'Vyhledávání'),
(@max_id + 1, 'de_DE', 'Suchen'),
(@max_id + 1, 'el_GR', 'Αναζήτηση'),
(@max_id + 1, 'en_US', 'Search'),
(@max_id + 1, 'es_ES', 'Buscar'),
(@max_id + 1, 'fa_IR', 'جستجو'),
(@max_id + 1, 'fr_FR', 'Recherche'),
(@max_id + 1, 'hu_HU', 'Keresés'),
(@max_id + 1, 'id_ID', NULL),
(@max_id + 1, 'it_IT', 'Ricerca'),
(@max_id + 1, 'pl_PL', 'Szukaj'),
(@max_id + 1, 'pt_BR', 'Procurar'),
(@max_id + 1, 'pt_PT', NULL),
(@max_id + 1, 'ru_RU', 'Поиск'),
(@max_id + 1, 'sk_SK', 'Hľadať'),
(@max_id + 1, 'tr_TR', 'Arama'),
(@max_id + 1, 'uk_UA', NULL)
;

SET FOREIGN_KEY_CHECKS = 1;