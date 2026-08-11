SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- France departments as states
--
-- Adds the 96 metropolitan departments and the 5 overseas departments and
-- regions (Guadeloupe, Martinique, Guyane, La Réunion, Mayotte) as states of
-- France, so delivery zones and tax rules can be scoped by department the
-- same way they already are for the US, Italy, Japan, Indonesia, Mexico,
-- Argentina and Canada.
--
-- Overseas collectivities that already exist as distinct countries
-- (Saint-Pierre-et-Miquelon, Saint-Barthélemy, Saint-Martin, Wallis-et-Futuna,
-- French Polynesia, New Caledonia, French Southern and Antarctic Lands) are
-- deliberately excluded.
--
-- Idempotent on (country_id, isocode): safe to replay, and does not touch
-- departments a project already entered by hand under a different isocode
-- (e.g. FR-75) - see setup/update/instruction/3.0.0-beta1.md.
-- ---------------------------------------------------------------------

SELECT @france_id := `id` FROM `country` WHERE `isoalpha3` = 'FRA' LIMIT 1;

-- 01 Ain
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '01', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '01');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Ain' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 02 Aisne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '02', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '02');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Aisne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 03 Allier
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '03', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '03');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Allier' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 04 Alpes-de-Haute-Provence
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '04', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '04');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Alpes-de-Haute-Provence' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 05 Hautes-Alpes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '05', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '05');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Hautes-Alpes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 06 Alpes-Maritimes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '06', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '06');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Alpes-Maritimes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 07 Ardèche
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '07', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '07');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Ardèche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 08 Ardennes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '08', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '08');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Ardennes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 09 Ariège
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '09', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '09');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Ariège' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 10 Aube
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '10', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '10');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Aube' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 11 Aude
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '11', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '11');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Aude' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 12 Aveyron
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '12', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '12');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Aveyron' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 13 Bouches-du-Rhône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '13', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '13');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Bouches-du-Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 14 Calvados
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '14', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '14');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Calvados' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 15 Cantal
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '15', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '15');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Cantal' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 16 Charente
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '16', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '16');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Charente' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 17 Charente-Maritime
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '17', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '17');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Charente-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 18 Cher
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '18', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '18');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 19 Corrèze
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '19', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '19');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Corrèze' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 2A Corse-du-Sud
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '2A', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '2A');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Corse-du-Sud' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 2B Haute-Corse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '2B', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '2B');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Corse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 21 Côte-d'Or
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '21', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '21');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Côte-d\'Or' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 22 Côtes-d'Armor
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '22', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '22');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Côtes-d\'Armor' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 23 Creuse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '23', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '23');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Creuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 24 Dordogne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '24', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '24');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Dordogne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 25 Doubs
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '25', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '25');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Doubs' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 26 Drôme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '26', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '26');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Drôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 27 Eure
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '27', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '27');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Eure' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 28 Eure-et-Loir
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '28', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '28');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Eure-et-Loir' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 29 Finistère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '29', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '29');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Finistère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 30 Gard
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '30', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '30');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Gard' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 31 Haute-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '31', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '31');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 32 Gers
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '32', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '32');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Gers' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 33 Gironde
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '33', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '33');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Gironde' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 34 Hérault
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '34', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '34');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Hérault' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 35 Ille-et-Vilaine
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '35', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '35');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Ille-et-Vilaine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 36 Indre
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '36', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '36');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Indre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 37 Indre-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '37', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '37');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Indre-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 38 Isère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '38', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '38');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Isère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 39 Jura
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '39', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '39');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Jura' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 40 Landes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '40', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '40');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Landes' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 41 Loir-et-Cher
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '41', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '41');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Loir-et-Cher' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 42 Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '42', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '42');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 43 Haute-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '43', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '43');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 44 Loire-Atlantique
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '44', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '44');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Loire-Atlantique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 45 Loiret
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '45', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '45');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Loiret' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 46 Lot
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '46', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '46');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Lot' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 47 Lot-et-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '47', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '47');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Lot-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 48 Lozère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '48', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '48');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Lozère' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 49 Maine-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '49', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '49');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Maine-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 50 Manche
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '50', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '50');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Manche' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 51 Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '51', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '51');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 52 Haute-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '52', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '52');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 53 Mayenne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '53', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '53');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Mayenne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 54 Meurthe-et-Moselle
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '54', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '54');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Meurthe-et-Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 55 Meuse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '55', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '55');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Meuse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 56 Morbihan
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '56', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '56');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Morbihan' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 57 Moselle
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '57', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '57');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Moselle' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 58 Nièvre
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '58', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '58');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Nièvre' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 59 Nord
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '59', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '59');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Nord' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 60 Oise
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '60', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '60');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 61 Orne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '61', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '61');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Orne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 62 Pas-de-Calais
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '62', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '62');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Pas-de-Calais' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 63 Puy-de-Dôme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '63', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '63');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Puy-de-Dôme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 64 Pyrénées-Atlantiques
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '64', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '64');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Pyrénées-Atlantiques' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 65 Hautes-Pyrénées
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '65', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '65');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Hautes-Pyrénées' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 66 Pyrénées-Orientales
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '66', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '66');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Pyrénées-Orientales' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 67 Bas-Rhin
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '67', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '67');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Bas-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 68 Haut-Rhin
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '68', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '68');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haut-Rhin' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 69 Rhône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '69', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '69');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Rhône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 70 Haute-Saône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '70', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '70');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Saône' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 71 Saône-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '71', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '71');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Saône-et-Loire' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 72 Sarthe
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '72', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '72');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Sarthe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 73 Savoie
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '73', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '73');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 74 Haute-Savoie
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '74', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '74');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Savoie' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 75 Paris
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '75', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '75');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Paris' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 76 Seine-Maritime
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '76', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '76');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Seine-Maritime' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 77 Seine-et-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '77', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '77');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Seine-et-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 78 Yvelines
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '78', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '78');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Yvelines' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 79 Deux-Sèvres
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '79', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '79');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Deux-Sèvres' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 80 Somme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '80', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '80');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Somme' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 81 Tarn
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '81', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '81');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Tarn' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 82 Tarn-et-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '82', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '82');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Tarn-et-Garonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 83 Var
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '83', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '83');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Var' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 84 Vaucluse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '84', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '84');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Vaucluse' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 85 Vendée
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '85', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '85');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Vendée' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 86 Vienne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '86', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '86');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 87 Haute-Vienne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '87', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '87');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Haute-Vienne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 88 Vosges
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '88', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '88');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Vosges' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 89 Yonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '89', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '89');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Yonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 90 Territoire de Belfort
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '90', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '90');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Territoire de Belfort' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 91 Essonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '91', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '91');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Essonne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 92 Hauts-de-Seine
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '92', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '92');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Hauts-de-Seine' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 93 Seine-Saint-Denis
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '93', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '93');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Seine-Saint-Denis' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 94 Val-de-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '94', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '94');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Val-de-Marne' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 95 Val-d'Oise
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '95', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '95');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Val-d\'Oise' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 971 Guadeloupe
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '971', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '971');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Guadeloupe' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 972 Martinique
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '972', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '972');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Martinique' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 973 Guyane
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '973', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '973');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Guyane' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 974 La Réunion
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '974', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '974');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'La Réunion' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

-- 976 Mayotte
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '976', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '976');

INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'cs_CZ', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'cs_CZ');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'de_DE', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'de_DE');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'en_US', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'en_US');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'es_ES', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'es_ES');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'fr_FR', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'fr_FR');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'it_IT', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'it_IT');
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, 'ru_RU', 'Mayotte' FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = 'ru_RU');

SET FOREIGN_KEY_CHECKS = 1;
