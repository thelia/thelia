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

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Ain' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '01'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 02 Aisne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '02', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '02');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Aisne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '02'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 03 Allier
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '03', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '03');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Allier' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '03'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 04 Alpes-de-Haute-Provence
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '04', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '04');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Alpes-de-Haute-Provence' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '04'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 05 Hautes-Alpes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '05', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '05');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Hautes-Alpes' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '05'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 06 Alpes-Maritimes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '06', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '06');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Alpes-Maritimes' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '06'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 07 Ardèche
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '07', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '07');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Ardèche' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '07'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 08 Ardennes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '08', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '08');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Ardennes' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '08'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 09 Ariège
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '09', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '09');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Ariège' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '09'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 10 Aube
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '10', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '10');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Aube' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '10'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 11 Aude
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '11', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '11');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Aude' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '11'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 12 Aveyron
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '12', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '12');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Aveyron' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '12'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 13 Bouches-du-Rhône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '13', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '13');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Bouches-du-Rhône' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '13'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 14 Calvados
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '14', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '14');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Calvados' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '14'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 15 Cantal
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '15', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '15');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Cantal' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '15'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 16 Charente
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '16', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '16');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Charente' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '16'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 17 Charente-Maritime
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '17', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '17');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Charente-Maritime' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '17'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 18 Cher
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '18', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '18');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Cher' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '18'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 19 Corrèze
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '19', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '19');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Corrèze' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '19'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 2A Corse-du-Sud
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '2A', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '2A');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Corse-du-Sud' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2A'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 2B Haute-Corse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '2B', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '2B');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Corse' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '2B'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 21 Côte-d'Or
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '21', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '21');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l="Côte-d'Or" locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '21'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 22 Côtes-d'Armor
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '22', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '22');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l="Côtes-d'Armor" locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '22'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 23 Creuse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '23', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '23');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Creuse' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '23'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 24 Dordogne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '24', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '24');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Dordogne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '24'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 25 Doubs
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '25', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '25');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Doubs' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '25'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 26 Drôme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '26', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '26');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Drôme' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '26'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 27 Eure
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '27', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '27');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Eure' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '27'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 28 Eure-et-Loir
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '28', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '28');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Eure-et-Loir' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '28'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 29 Finistère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '29', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '29');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Finistère' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '29'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 30 Gard
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '30', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '30');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Gard' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '30'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 31 Haute-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '31', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '31');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Garonne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '31'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 32 Gers
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '32', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '32');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Gers' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '32'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 33 Gironde
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '33', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '33');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Gironde' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '33'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 34 Hérault
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '34', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '34');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Hérault' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '34'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 35 Ille-et-Vilaine
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '35', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '35');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Ille-et-Vilaine' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '35'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 36 Indre
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '36', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '36');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Indre' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '36'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 37 Indre-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '37', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '37');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Indre-et-Loire' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '37'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 38 Isère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '38', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '38');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Isère' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '38'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 39 Jura
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '39', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '39');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Jura' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '39'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 40 Landes
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '40', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '40');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Landes' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '40'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 41 Loir-et-Cher
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '41', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '41');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Loir-et-Cher' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '41'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 42 Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '42', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '42');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Loire' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '42'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 43 Haute-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '43', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '43');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Loire' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '43'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 44 Loire-Atlantique
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '44', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '44');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Loire-Atlantique' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '44'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 45 Loiret
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '45', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '45');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Loiret' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '45'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 46 Lot
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '46', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '46');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Lot' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '46'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 47 Lot-et-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '47', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '47');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Lot-et-Garonne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '47'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 48 Lozère
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '48', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '48');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Lozère' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '48'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 49 Maine-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '49', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '49');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Maine-et-Loire' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '49'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 50 Manche
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '50', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '50');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Manche' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '50'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 51 Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '51', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '51');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Marne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '51'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 52 Haute-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '52', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '52');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Marne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '52'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 53 Mayenne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '53', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '53');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Mayenne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '53'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 54 Meurthe-et-Moselle
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '54', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '54');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Meurthe-et-Moselle' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '54'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 55 Meuse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '55', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '55');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Meuse' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '55'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 56 Morbihan
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '56', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '56');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Morbihan' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '56'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 57 Moselle
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '57', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '57');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Moselle' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '57'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 58 Nièvre
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '58', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '58');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Nièvre' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '58'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 59 Nord
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '59', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '59');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Nord' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '59'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 60 Oise
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '60', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '60');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Oise' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '60'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 61 Orne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '61', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '61');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Orne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '61'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 62 Pas-de-Calais
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '62', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '62');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Pas-de-Calais' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '62'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 63 Puy-de-Dôme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '63', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '63');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Puy-de-Dôme' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '63'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 64 Pyrénées-Atlantiques
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '64', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '64');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Pyrénées-Atlantiques' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '64'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 65 Hautes-Pyrénées
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '65', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '65');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Hautes-Pyrénées' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '65'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 66 Pyrénées-Orientales
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '66', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '66');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Pyrénées-Orientales' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '66'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 67 Bas-Rhin
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '67', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '67');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Bas-Rhin' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '67'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 68 Haut-Rhin
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '68', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '68');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haut-Rhin' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '68'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 69 Rhône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '69', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '69');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Rhône' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '69'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 70 Haute-Saône
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '70', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '70');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Saône' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '70'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 71 Saône-et-Loire
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '71', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '71');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Saône-et-Loire' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '71'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 72 Sarthe
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '72', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '72');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Sarthe' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '72'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 73 Savoie
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '73', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '73');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Savoie' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '73'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 74 Haute-Savoie
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '74', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '74');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Savoie' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '74'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 75 Paris
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '75', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '75');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Paris' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '75'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 76 Seine-Maritime
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '76', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '76');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Seine-Maritime' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '76'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 77 Seine-et-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '77', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '77');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Seine-et-Marne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '77'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 78 Yvelines
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '78', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '78');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Yvelines' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '78'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 79 Deux-Sèvres
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '79', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '79');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Deux-Sèvres' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '79'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 80 Somme
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '80', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '80');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Somme' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '80'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 81 Tarn
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '81', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '81');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Tarn' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '81'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 82 Tarn-et-Garonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '82', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '82');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Tarn-et-Garonne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '82'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 83 Var
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '83', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '83');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Var' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '83'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 84 Vaucluse
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '84', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '84');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Vaucluse' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '84'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 85 Vendée
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '85', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '85');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Vendée' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '85'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 86 Vienne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '86', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '86');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Vienne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '86'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 87 Haute-Vienne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '87', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '87');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Haute-Vienne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '87'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 88 Vosges
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '88', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '88');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Vosges' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '88'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 89 Yonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '89', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '89');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Yonne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '89'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 90 Territoire de Belfort
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '90', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '90');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Territoire de Belfort' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '90'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 91 Essonne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '91', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '91');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Essonne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '91'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 92 Hauts-de-Seine
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '92', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '92');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Hauts-de-Seine' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '92'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 93 Seine-Saint-Denis
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '93', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '93');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Seine-Saint-Denis' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '93'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 94 Val-de-Marne
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '94', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '94');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Val-de-Marne' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '94'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 95 Val-d'Oise
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '95', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '95');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l="Val-d'Oise" locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '95'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 971 Guadeloupe
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '971', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '971');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Guadeloupe' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '971'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 972 Martinique
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '972', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '972');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Martinique' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '972'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 973 Guyane
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '973', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '973');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Guyane' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '973'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 974 La Réunion
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '974', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '974');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='La Réunion' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '974'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

-- 976 Mayotte
INSERT INTO `state` (`visible`, `isocode`, `country_id`, `created_at`, `updated_at`)
SELECT 1, '976', @france_id, NOW(), NOW() FROM DUAL
WHERE @france_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `state` s WHERE s.`country_id` = @france_id AND s.`isocode` = '976');

{foreach $locales as $locale}
INSERT INTO `state_i18n` (`id`, `locale`, `title`)
SELECT s.`id`, '{$locale}', {intl l='Mayotte' locale=$locale use_default=1} FROM `state` s
WHERE s.`country_id` = @france_id AND s.`isocode` = '976'
  AND NOT EXISTS (SELECT 1 FROM `state_i18n` i WHERE i.`id` = s.`id` AND i.`locale` = '{$locale}');
{/foreach}

SET FOREIGN_KEY_CHECKS = 1;
