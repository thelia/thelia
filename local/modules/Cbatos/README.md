MODULE DE PAIEMENT CBATOS By  KNJ
<contact@knjhair.fr>

Fr_fr (Français)

Fonctionnalité du module
------------------------

- Encaissement de paiement
- Gestion de débit à l'expedition / Débit différé
- Gestion sécuritaire Email/Ip/Num client
- Mode DEBUG Activable ou désactivable
- Retour automatique Prise en charge
- Retour manuel Prise en charge
- Le client connaitra dans le cadre d'un retour vers le site la raison exact pour laquel le paiement est refusé
- En cas d'acceptation le numéro de transaction apparaitra dans la commande 


SUMMARY
-------

Français :

0.  Pré-requis
1.  Installation
2.  Utilisation
3.  Intégration
4.  Variable Mail


Français
-----

### Pré-requis

- Finaliser un contrat de vente à distance auprès de votre banque et signer avec la solution ATOS
proposer par votre banque,
Votre banque vous enverra un certificat d'installation qui vous permettra d'installer ce module

### Installation

- Pour installer le module cbatos, téléchargez l'archive et décompressez la dans <dossier de thelia>/local/modules

### Utilisation

Pour utiliser le module cbatos, allez dans le back-office, onglet Modules, et activez le,
puis cliquez sur "Configurer" sur la ligne du module. puis renseigner :

- Votre numéro de marchand (généralement votre certificat et sous la forme 01010101010101.certif ) le chiffre est donc votre numéro de marchand.
- l'url de retour : http://www.votresite.com/cbatos/manuel.
- l'url de retour automatique : http://www.votresite.com/cbatos/answer.
- Si vous souhaitez ou pas différé le débit (immédiat = débit immédiat) 1,2,3,4,5 (jusqu'à 5 jours de débit pour des raison juridique vous ne pouvez pas dépasser les 5 jours).
- la devise d'encaissement par default EUROS.
- Si vous voulez ou pas que : l'adresse mail , le numéro de client , l'ip du client soit envoyée à atos.
- le plus important le pathbin (le chemin absolu du module) ex : /home/site/html/local/modules/CBatos. 
- Pour les dévellopeurs vous pouvez passer le mode DEBUG à oui afin de voir ce qui bloque en cas de soucis.

### Intégration

Ce module utilise deux pages:

    - atos.html :  page qui affiche les boutons de paiment.
    - result.html : page qui affiche le résultat de la transaction.
    
PARAMETRE DE TESTE :
- Numéro de marchand : 013044876511111
- Url retour : http://www.yourwebsite.com/cbatos/manuel (changer uniquement le nom de domaine )
- Url retour automatique  : http://www.yourwebsite/cbatos/answer (changer uniquement le nom de domaine)
- Paiement différé : Immediate charge
- Devise : EUR
- Envoyer adresse mail du client à atos  : OUI
- Envoyer adresse id du client à atos  : OUI
- Envoyer adresse ip du client à atos  : OUI
- Chemin absolue des fichiers requis  : /home/site/html/local/modules/Cbatos (indiquer le cheveux absolu jusqu'au répertoire modules/Cbatos ) 
- Mode DEBUG : OUI (NON c'est pour le mode production)
- 

### VARIABLE MAIL
- Le ticket de la transaction est systématiquement envoyé au client cela est une obligation faisant partie du CODE MONAITAIRE.

{$METHOD_PAID} = Modifiable dans la translation du mondule
{$ETP} = Technologie de passage utilisé
{$MESSAGE_HAUT_TICKET_ATOS} = Modifiable dans la translation du mondule
A{$MERCHANT} = Identifiant marchand 
CB - @auto {$autorisation} = Autorisation de la transaction
{$LE} {$DATE_TRANS} {$A} {$TIME_TRANS} = date et heure de la transaction
{$STORE_NAME} = Récupération des infos boutique (nom de société)
{$STORE_LINE1} = Récupération des infos boutique (adresse de société)
{$STORE_CP} = Récupération des infos boutique (Codepostal ville norme AFD)
{$CB_CRYPTE} = Numéro crypté de la carte utilisé
{$CERTIFICAT} = Certificat de transaction 
{$FIN} --/--/-- = Jamais communiqué (interdit)
100 {$TRANS_ID} 01 c = Identification Terminal Id rand
{$MONT} : = Modifiable dans la translation
{$MONTANT_TRANS_EUR} = Montant en euros de la transaction 
{$INFO} : = Modifiable dans la translation
{$MONTANT_TRANS_FRF} = Montant convertie en Francs
{$MONTANT_TRANS_USD} = Montant convertie en USD (Relation BCE)
{$MESSAGE_TICKET_CLIENT} = Modifiable dans la translation 
{$CONSERVE} = Modifiable dans la translation
{$BYE} = Modifiable dans la translation
{$order_ref} / {$order_id} (vous permettra d'identifier le ticket grace à la référence de la commande et l'id de la commande .

Par soucis de sécurité et de norme interbancaire nous vous invitons à ne pas modifier le template mail du ticket envoyé, vous pouvez changer les couleurs ... mais pas le contenu ni l'ordre .

###########################ENGLISH####################################

EN_en (English)

Functionality of the module
------------------------

- Receipt of payment
- deferred payment
- safe management Email/Ip/Num customer
- Activate debug mode or switched off
- Automatic Back Support
- Back Manual Support
- The client knows in the context of a return to the site the exact reason Laquel the transaction was refused
- In case of acceptance the transaction number will appear in the order


SUMMARY
-------

English  :

0.  Prerequisites
1.  Installation
2.  Use
3.  integration
4.  Test parameter


English
-----

### Prerequisites

- Finalize opening your merchant agreement with your bank and sign with ATOS solution
provide by your bank,
Your bank will send you a certificate of installation that you can install this module

### Installation

- To install this module, download and unzip the archive in <userfolder of thelia> / local / modules

### Utilisation

To use this module, go to the back office, Modules tab, and enable,
then click "Configure" on the line module. then enter:

- Your Merchant ID (usually your certificate and in the form 01010101010101.certif) number is your merchant number.
- the return URL: http://www.yoursite.com/cbatos/manuel.
- url automatic return : http://www.yoursite.com/cbatos/answer.
- If you wish to make a deferred debit 1,2,3,4,5 (immediate = immediate payment) (up to 5 days of discharge for legal reasons you can not exceed 5 days).
- currency retraction default EUROS.
- If you want to or not as: email address, customer number, the ip of the client is sent to atos.
- the most important pathbin (the absolute path of the module) ex : /home/site/html/local/modules/CBatos. 
- For developers as you can pass the DEBUG mode to yes to see what blocks in case of trouble.

### Intégration

This module uses two pages:

    - atos.html :  page that displays buttons of payment.
    - result.html : page that displays the result of the transaction.
    
### Test parameter


TEST PARAMETER :
- Id merchant : 013044876511111
- Url back : http://www.yourwebsite.com/cbatos/manuel (change only domain name)
- Url back automatic : http://www.yourwebsite/cbatos/answer (change only domain name)
- Differed payment : Immediate charge
- Currency : EUR
- Sent Mail atos : YES
- Sent IdCustomer atos : YES
- Sent Ipcustomer atos : YES
- Absolute path : /home/site/html/local/modules/Cbatos (not add more just change for acces /modules/CBatos ) 
- Mode DEBUG : YES (NO its for no display error)
