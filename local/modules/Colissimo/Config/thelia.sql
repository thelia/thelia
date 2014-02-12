# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Mail templates for icirelais
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
(@max, 'fr_FR', 'mail livraison colissimo', 'Suivi colissimo commande : {$order_ref}', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\n{$LASTNAME} {$FIRSTNAME},\r\n{/loop}\r\nNous vous remercions de votre commande sur notre site {config key="store_name"}\r\nUn colis concernant votre commande {$order_ref} du {format_date date=$order_date} a quitté nos entrepôts pour être pris en charge par La Poste le {format_date date=$update_date}.\r\nSon numéro de suivi est le suivant : {$package}\r\nIl vous permet de suivre votre colis en ligne sur le site de La Poste : www.coliposte.net\r\nIl vous sera, par ailleurs, très utile si vous étiez absent au moment de la livraison de votre colis : en fournissant ce numéro de Colissimo Suivi, vous pourrez retirer votre colis dans le bureau de Poste le plus proche.\r\nATTENTION ! Si vous ne trouvez pas l''avis de passage normalement déposé dans votre boîte aux lettres au bout de 48 Heures jours ouvrables, n''hésitez pas à aller le réclamer à votre bureau de Poste, muni de votre numéro de Colissimo Suivi.\r\nNous restons à votre disposition pour toute information complémentaire.\r\nCordialement', '{loop type="customer" name="customer.order" current="false" id="$customer_id" backend_context="1"}\r\n{$LASTNAME} {$FIRSTNAME},\r\n{/loop}\r\nNous vous remercions de votre commande sur notre site {config key="store_name"}\r\nUn colis concernant votre commande {$order_ref} du {format_date date=$order_date} a quitté nos entrepôts pour être pris en charge par La Poste le {format_date date=$update_date}.\r\nSon numéro de suivi est le suivant : {$package}\r\nIl vous permet de suivre votre colis en ligne sur le site de La Poste : www.coliposte.net\r\nIl vous sera, par ailleurs, très utile si vous étiez absent au moment de la livraison de votre colis : en fournissant ce numéro de Colissimo Suivi, vous pourrez retirer votre colis dans le bureau de Poste le plus proche.\r\nATTENTION ! Si vous ne trouvez pas l''avis de passage normalement déposé dans votre boîte aux lettres au bout de 48 Heures jours ouvrables, n''hésitez pas à aller le réclamer à votre bureau de Poste, muni de votre numéro de Colissimo Suivi.\r\nNous restons à votre disposition pour toute information complémentaire.\r\nCordialement');


SET FOREIGN_KEY_CHECKS = 1;