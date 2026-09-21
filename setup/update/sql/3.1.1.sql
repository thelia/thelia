-- ---------------------------------------------------------------------
-- The two flag facets of a product listing: on sale, and new.
--
-- `choice_filter_other` is what makes a filter that hangs off no feature
-- and no attribute reachable from the back office: the category and the
-- template screens list its rows, and a merchant decides there whether
-- the facet shows, where it sits and how it is drawn. A filter with no
-- row still answers the API, but nobody can configure it, so the two
-- filters this release adds come with theirs.
--
-- The ids are fixed at 4 and 5. A shop that came from Thelia 2 already
-- owns 2 (brand) and 3 (category) from 3.0.0-alpha1, and a shop
-- installed fresh owns none; 4 and 5 are free on both, and no code ever
-- reads these rows by id — the filters are matched by `type`.
--
-- INSERT IGNORE is the idempotency: a run stopped halfway can be
-- replayed from the top without the primary key turning the second
-- attempt into a failure.
--
-- The i18n rows cover the eight locales a fresh install seeds (see
-- setup/insert.sql). A locale left without a row shows the merchant a
-- filter with no name at all on the screen where they configure it.
-- ---------------------------------------------------------------------

INSERT IGNORE INTO `choice_filter_other` (`id`, `type`, `visible`) VALUES
    (4, 'promo', 1),
    (5, 'new', 1);

INSERT IGNORE INTO `choice_filter_other_i18n` (`id`, `locale`, `title`, `description`) VALUES
    (4, 'cs_CZ', 'Akce', NULL),
    (5, 'cs_CZ', 'Novinka', NULL),
    (4, 'de_DE', 'Aktion', NULL),
    (5, 'de_DE', 'Neuheit', NULL),
    (4, 'en_US', 'Promotion', NULL),
    (5, 'en_US', 'Newness', NULL),
    (4, 'es_ES', 'Promoción', NULL),
    (5, 'es_ES', 'Novedad', NULL),
    (4, 'fr_FR', 'Promotion', NULL),
    (5, 'fr_FR', 'Nouveauté', NULL),
    (4, 'it_IT', 'Promozione', NULL),
    (5, 'it_IT', 'Novità', NULL),
    (4, 'nl_NL', 'Promotie', NULL),
    (5, 'nl_NL', 'Nieuw', NULL),
    (4, 'ru_RU', 'Акция', NULL),
    (5, 'ru_RU', 'Новинка', NULL);
