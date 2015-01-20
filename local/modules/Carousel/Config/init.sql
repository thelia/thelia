INSERT INTO `carousel` (`id`, `file`, `position`, `url`, `created_at`, `updated_at`) VALUES
(1, 'sample-carousel-image-1.png', NULL, NULL, NOW(), NOW()),
(2, 'sample-carousel-image-2.png', NULL, NULL, NOW(), NOW());


INSERT INTO `carousel_i18n` (`id`, `locale`, `alt`, `title`, `description`, `chapo`, `postscriptum`) VALUES
(1, 'en_US', 'Thelia e-commerce', 'A sample carousel image', NULL, NULL, NULL),
(1, 'fr_FR', 'Thelia e-commerce', 'Une image de démonstration', NULL, NULL, NULL),
(2, 'en_US', 'Based on Symfony 2', 'Another sample carousle image', NULL, NULL, NULL),
(2, 'fr_FR', 'Construit avec Symphony 2', 'Un autre image de démonstration', NULL, NULL, NULL);
