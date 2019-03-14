SET FOREIGN_KEY_CHECKS = 0;

-- Hooks

-- front hooks

SELECT @max_id := IFNULL(MAX(`id`),0) FROM `hook`;

INSERT INTO `hook` (`id`, `code`, `type`, `by_module`, `block`, `native`, `activate`, `position`, `created_at`, `updated_at`) VALUES
(@max_id + 1, 'account.additional', 1, 0, 1, 1, 1, 1, NOW(), NOW()),

(@max_id + 2, 'product.modification.form_top', 2, 0, 0, 1, 1, 1, NOW(), NOW()),
(@max_id + 3, 'product.modification.form_bottom', 2, 0, 0, 1, 1, 1, NOW(), NOW())
;

INSERT INTO  `hook_i18n` (`id`, `locale`, `title`, `description`, `chapo`) VALUES
(@max_id + 1, 'ar_SA', NULL, '', ''),
(@max_id + 2, 'ar_SA', NULL, '', ''),
(@max_id + 3, 'ar_SA', NULL, '', ''),
(@max_id + 1, 'cs_CZ', NULL, '', ''),
(@max_id + 2, 'cs_CZ', NULL, '', ''),
(@max_id + 3, 'cs_CZ', NULL, '', ''),
(@max_id + 1, 'de_DE', 'Kundenkonto - Erweitere Informationen', '', ''),
(@max_id + 2, 'de_DE', 'Produktseite - oben im Formular', '', ''),
(@max_id + 3, 'de_DE', 'Produktseite - unten an dem Formular', '', ''),
(@max_id + 1, 'el_GR', NULL, '', ''),
(@max_id + 2, 'el_GR', NULL, '', ''),
(@max_id + 3, 'el_GR', NULL, '', ''),
(@max_id + 1, 'en_US', 'Customer account - additional information', '', ''),
(@max_id + 2, 'en_US', 'Product page - On the top of the form', '', ''),
(@max_id + 3, 'en_US', 'Product page - On the bottom of the form', '', ''),
(@max_id + 1, 'es_ES', 'Cuenta de cliente - información adicional', '', ''),
(@max_id + 2, 'es_ES', 'Página de producto - en la parte superior del formulario', '', ''),
(@max_id + 3, 'es_ES', 'Página del producto - en la parte inferior del formulario', '', ''),
(@max_id + 1, 'fa_IR', NULL, '', ''),
(@max_id + 2, 'fa_IR', NULL, '', ''),
(@max_id + 3, 'fa_IR', NULL, '', ''),
(@max_id + 1, 'fr_FR', 'Compte client - informations additionnelles', '', ''),
(@max_id + 2, 'fr_FR', 'Page produit - En haut du formulaire', '', ''),
(@max_id + 3, 'fr_FR', 'Page produit - En bas du formulaire', '', ''),
(@max_id + 1, 'hu_HU', NULL, '', ''),
(@max_id + 2, 'hu_HU', NULL, '', ''),
(@max_id + 3, 'hu_HU', NULL, '', ''),
(@max_id + 1, 'id_ID', NULL, '', ''),
(@max_id + 2, 'id_ID', NULL, '', ''),
(@max_id + 3, 'id_ID', NULL, '', ''),
(@max_id + 1, 'it_IT', NULL, '', ''),
(@max_id + 2, 'it_IT', NULL, '', ''),
(@max_id + 3, 'it_IT', NULL, '', ''),
(@max_id + 1, 'pl_PL', 'Konto klienta - dodatkowe informacje', '', ''),
(@max_id + 2, 'pl_PL', NULL, '', ''),
(@max_id + 3, 'pl_PL', NULL, '', ''),
(@max_id + 1, 'pt_BR', NULL, '', ''),
(@max_id + 2, 'pt_BR', NULL, '', ''),
(@max_id + 3, 'pt_BR', NULL, '', ''),
(@max_id + 1, 'pt_PT', NULL, '', ''),
(@max_id + 2, 'pt_PT', NULL, '', ''),
(@max_id + 3, 'pt_PT', NULL, '', ''),
(@max_id + 1, 'ru_RU', 'Аккаунт клиента - дополнительная информация', '', ''),
(@max_id + 2, 'ru_RU', 'Страница товара - Вверху формы', '', ''),
(@max_id + 3, 'ru_RU', 'Страница товара - Внизу формы', '', ''),
(@max_id + 1, 'sk_SK', NULL, '', ''),
(@max_id + 2, 'sk_SK', NULL, '', ''),
(@max_id + 3, 'sk_SK', NULL, '', ''),
(@max_id + 1, 'tr_TR', 'Müşteri hesabı - ek bilgi', '', ''),
(@max_id + 2, 'tr_TR', 'Ürün sayfasında - formun altı', '', ''),
(@max_id + 3, 'tr_TR', 'Ürün sayfasında - formun altı', '', ''),
(@max_id + 1, 'uk_UA', NULL, '', ''),
(@max_id + 2, 'uk_UA', NULL, '', ''),
(@max_id + 3, 'uk_UA', NULL, '', '')
;

SET FOREIGN_KEY_CHECKS = 1;