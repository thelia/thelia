SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `atos_log`;
DROP TABLE IF EXISTS `atos_config`;
DROP TABLE IF EXISTS `atos_transaction`;

-- ---------------------------------------------------------------------
-- Mail templates for Atos Transaction
-- ---------------------------------------------------------------------
-- First, delete existing entries
SET @var := 0;
SELECT @var := `id` FROM `message` WHERE name="mail_atos";
DELETE FROM `message` WHERE `id`=@var;

-- Try if ON DELETE constraint isn't set
DELETE FROM `message_i18n` WHERE `id`=@var;

-- Then add new entries
SELECT @max := MAX(`id`) FROM `message`;
SET @max := @max+1;
-- insert message
INSERT INTO `message` (`id`, `name`, `secured`) VALUES
  (@max,
   'mail_atos',
   '0'
  );


-- and template fr_FR
INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
  (@max,
   'fr_FR',
   'payment confirmation_atos',
   'TICKET DE PAIEMENT CARTE BANCAIRE (Transaction N {$TRANS_ID})',
   '
\r\n   
################################### \r\n
{$METHOD_PAID} {$ETP} \r\n
{$MESSAGE_HAUT_TICKET_ATOS} \r\n
A{$MERCHANT} \r\n
CB - @auto {$autorisation} \r\n
{$LE} {$DATE_TRANS} {$A} {$TIME_TRANS} \r\n
{$STORE_NAME} \r\n
{$STORE_LINE1} \r\n
{$STORE_CP} \r\n
{$CB_CRYPTE} \r\n
{$CERTIFICAT} \r\n
{$FIN} --/--/-- \r\n
100 {$TRANS_ID} 01 c \r\n
{$MONT} : \r\n
{$MONTANT_TRANS_EUR} \r\n
{$INFO} : \r\n
{$MONTANT_TRANS_FRF} \r\n
{$MONTANT_TRANS_USD} \r\n
@DEBIT \r\n
{$MESSAGE_TICKET_CLIENT} \r\n
{$CONSERVE} \r\n
{$BYE} \r\n
###################################
Id : {$order_ref} / {$order_id}
###################################
 ',
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Paid Receipt</title>
<style type="text/css">
body,td,th {
	font-family: Verdana, Geneva, sans-serif;
	font-size: 12px;
	color: #000;
	}</style></head><body><table width="361" border="0" align="center"><tr><td>###################################</td></tr><tr><td>{$METHOD_PAID} {$ETP}</td></tr><tr><td>{$MESSAGE_HAUT_TICKET_ATOS}</td></tr><tr><td>A{$MERCHANT} </td></tr><tr><td>CB - @auto {$autorisation} </td></tr><tr><td>{$LE} {$DATE_TRANS} {$A} {$TIME_TRANS}</td> </tr><tr> <td>{$STORE_NAME}</td></tr><tr><td>{$STORE_LINE1}</td></tr><tr><td>{$STORE_CP}</td></tr><tr><td>{$CB_CRYPTE}</td></tr><tr><td>{$CERTIFICAT} </td></tr> <tr><td>{$FIN} --/--/--</td></tr><tr><td>100 {$TRANS_ID} 01 c</td> </tr><tr><td>{$MONT} :</td></tr><tr> <td bgcolor="#CCCCCC"><table width="80%" border="0" align="center"><tr><td align="center"><strong>{$MONTANT_TRANS_EUR}</strong></td></tr></table></td></tr> <tr> <td style="font-size:10px;">{$INFO} : </td>
  </tr><tr> <td bgcolor="#CCCCCC"><table width="80%" border="0" align="center"><tr><td style="font-size:10px;" align="center">{$MONTANT_TRANS_FRF}</td></tr> <tr><td style="font-size:10px;" align="center">{$MONTANT_TRANS_USD}</td>
      </tr></table></td></tr><tr><td><em>@DEBIT</em></td> </tr><tr><td>{$MESSAGE_TICKET_CLIENT}</td></tr><tr><td>{$CONSERVE}</td></tr><tr> <td>{$BYE}</td></tr><tr><td>###################################</td></tr> <tr>
    <td style=\'font-size:9px;\'>Id : {$order_ref} / {$order_id}</td></tr> <tr><td>###################################</td></tr></table></body></html>'
  );
-- and en_US
INSERT INTO `message_i18n` (`id`, `locale`, `title`, `subject`, `text_message`, `html_message`) VALUES
  (@max,
   'en_US',
   'payment confirmation_atos',
   'Your payment receipt credit card (Trans Id: {$TRANS_ID} )',
   '
\r\n   
################################### \r\n
{$METHOD_PAID} {$ETP} \r\n
{$MESSAGE_HAUT_TICKET_ATOS} \r\n
A{$MERCHANT} \r\n
CB - @auto {$autorisation} \r\n
{$LE} {$DATE_TRANS} {$A} {$TIME_TRANS} \r\n
{$STORE_NAME} \r\n
{$STORE_LINE1} \r\n
{$STORE_CP} \r\n
{$CB_CRYPTE} \r\n
{$CERTIFICAT} \r\n
{$FIN} --/--/-- \r\n
100 {$TRANS_ID} 01 c \r\n
{$MONT} : \r\n
{$MONTANT_TRANS_EUR} \r\n
{$INFO} : \r\n
{$MONTANT_TRANS_FRF} \r\n
{$MONTANT_TRANS_USD} \r\n
@DEBIT \r\n
{$MESSAGE_TICKET_CLIENT} \r\n
{$CONSERVE} \r\n
{$BYE} \r\n
###################################
Id : {$order_ref} / {$order_id}
###################################
 ',
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Paid Receipt</title>
<style type="text/css">
body,td,th {
	font-family: Verdana, Geneva, sans-serif;
	font-size: 12px;
	color: #000;
	}</style></head><body><table width="361" border="0" align="center"><tr><td>###################################</td></tr><tr><td>{$METHOD_PAID} {$ETP}</td></tr><tr><td>{$MESSAGE_HAUT_TICKET_ATOS}</td></tr><tr><td>A{$MERCHANT} </td></tr><tr><td>CB - @auto {$autorisation} </td></tr><tr><td>{$LE} {$DATE_TRANS} {$A} {$TIME_TRANS}</td> </tr><tr> <td>{$STORE_NAME}</td></tr><tr><td>{$STORE_LINE1}</td></tr><tr><td>{$STORE_CP}</td></tr><tr><td>{$CB_CRYPTE}</td></tr><tr><td>{$CERTIFICAT} </td></tr> <tr><td>{$FIN} --/--/--</td></tr><tr><td>100 {$TRANS_ID} 01 c</td> </tr><tr><td>{$MONT} :</td></tr><tr> <td bgcolor="#CCCCCC"><table width="80%" border="0" align="center"><tr><td align="center"><strong>{$MONTANT_TRANS_EUR}</strong></td></tr></table></td></tr> <tr> <td style="font-size:10px;">{$INFO} : </td>
  </tr><tr> <td bgcolor="#CCCCCC"><table width="80%" border="0" align="center"><tr><td style="font-size:10px;" align="center">{$MONTANT_TRANS_FRF}</td></tr> <tr><td style="font-size:10px;" align="center">{$MONTANT_TRANS_USD}</td>
      </tr></table></td></tr><tr><td><em>@DEBIT</em></td> </tr><tr><td>{$MESSAGE_TICKET_CLIENT}</td></tr><tr><td>{$CONSERVE}</td></tr><tr> <td>{$BYE}</td></tr><tr><td>###################################</td></tr> <tr>
    <td style=\'font-size:9px;\'>Id : {$order_ref} / {$order_id}</td></tr> <tr><td>###################################</td></tr></table></body></html>'
  );

SET FOREIGN_KEY_CHECKS = 1;

