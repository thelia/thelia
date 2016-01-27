# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Mail templates for colissimo
-- ---------------------------------------------------------------------

-- First, delete existing entries
SET @var := 0;
SELECT @var := `id` FROM `message` WHERE name="mail_colissimo";
DELETE FROM `message` WHERE `id`=@var;
-- Try if ON DELETE constraint isn't set
DELETE FROM `message_i18n` WHERE `id`=@var;

-- Then add new entries
SELECT @max := MAX(`id`) FROM `message`;
SET @max := @max+1;
-- insert message
INSERT INTO `message` (`id`, `name`, `secured`) VALUES
(@max,
'mail_colissimo',
'0'
);

-- and template fr_FR
INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
(@max, 'en_US', 'Colissimo shipping message', 'Your order {$order_ref} has been shipped', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\nDear {$FIRSTNAME} {$LASTNAME},\r\n{/loop}\r\nThank you for your order on our online store {config key="store_name"}.\r\nYour order {$order_ref} dated {format_date date=$order_date} has been shipped on {format_date date=$update_date}.\r\nThe tracking number for this delivery is {$package}. Please check the La Poste website for tracking your parcel: www.coliposte.net.\r\nYou can use this tracking number to get your parcel in your local La Poste office. If don''t get an advice in your mailbox after two working days, claim your parcel at your local La Poste office, using this tracking number.\r\nFeel free to contact us for any forther information\r\nBest Regards.', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\n<p>Dear {$FIRSTNAME} {$LASTNAME},</p>\r\n{/loop}\r\n<p>Thank you for your order on our online store {config key="store_name"}.</p>\r\n<p>Your order {$order_ref} dated {format_date date=$order_date} has been shipped on {format_date date=$update_date}.\r\nThe tracking number for this delivery is {$package}. Please check the La Poste website for tracking your parcel: <a href="www.coliposte.net">www.coliposte.net</a>.</p>\r\n<p>You can use this tracking number to get your parcel in your local La Poste office. If don''t get an advice in your mailbox after two working days, claim your parcel at your local La Poste office, using this tracking number.</p>\r\n<p>Feel free to contact us for any forther information</p>\r\n<p>Best Regards.</p>'),
(@max, 'fr_FR', 'Message d''expédition de colissimo', 'Suivi colissimo commande : {$order_ref}', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\n{$LASTNAME} {$FIRSTNAME},\r\n{/loop}\r\nNous vous remercions de votre commande sur notre site {config key="store_name"}\r\nUn colis concernant votre commande {$order_ref} du {format_date date=$order_date} a quitté nos entrepôts pour être pris en charge par La Poste le {format_date date=$update_date}.\r\nSon numéro de suivi est le suivant : {$package}\r\nIl vous permet de suivre votre colis en ligne sur le site de La Poste : www.coliposte.net\r\nIl vous sera, par ailleurs, très utile si vous étiez absent au moment de la livraison de votre colis : en fournissant ce numéro de Colissimo Suivi, vous pourrez retirer votre colis dans le bureau de Poste le plus proche.\r\nATTENTION ! Si vous ne trouvez pas l''avis de passage normalement déposé dans votre boîte aux lettres au bout de 48 Heures jours ouvrables, n''hésitez pas à aller le réclamer à votre bureau de Poste, muni de votre numéro de Colissimo Suivi.\r\nNous restons à votre disposition pour toute information complémentaire.\r\nCordialement', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\n{$LASTNAME} {$FIRSTNAME},\r\n{/loop}\r\nNous vous remercions de votre commande sur notre site {config key="store_name"}\r\nUn colis concernant votre commande {$order_ref} du {format_date date=$order_date} a quitté nos entrepôts pour être pris en charge par La Poste le {format_date date=$update_date}.\r\nSon numéro de suivi est le suivant : {$package}\r\nIl vous permet de suivre votre colis en ligne sur le site de La Poste : www.coliposte.net\r\nIl vous sera, par ailleurs, très utile si vous étiez absent au moment de la livraison de votre colis : en fournissant ce numéro de Colissimo Suivi, vous pourrez retirer votre colis dans le bureau de Poste le plus proche.\r\nATTENTION ! Si vous ne trouvez pas l''avis de passage normalement déposé dans votre boîte aux lettres au bout de 48 Heures jours ouvrables, n''hésitez pas à aller le réclamer à votre bureau de Poste, muni de votre numéro de Colissimo Suivi.\r\nNous restons à votre disposition pour toute information complémentaire.\r\nCordialement');

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;