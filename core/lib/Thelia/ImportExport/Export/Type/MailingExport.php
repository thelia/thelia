<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\ImportExport\Export\Type;

use PDO;
use Propel\Runtime\Propel;
use Thelia\ImportExport\Export\JsonFileAbstractExport;

/**
 * Class MailingExport
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class MailingExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'mailing';

    protected $orderAndAliases = [
        'newsletter_id' => 'Identifier',
        'newsletter_email' => 'Email',
        'newsletter_firstname' => 'FirstName',
        'newsletter_lastname' => 'LastName'
    ];

    protected function getData()
    {
        $con = Propel::getConnection();
        $query = 'SELECT 
                        newsletter.id as "newsletter_id",
                        newsletter.email as "newsletter_email", 
                        newsletter.firstname as "newsletter_firstname", 
                        newsletter.lastname as "newsletter_lastname"
                    FROM newsletter
                    WHERE newsletter.unsubscribed = 0'
        ;
        $stmt = $con->prepare($query);
        $stmt->execute();

        $filename = THELIA_CACHE_DIR . '/export/' . 'mailing.json';

        if (file_exists($filename)) {
            unlink($filename);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            file_put_contents($filename, json_encode($row) . "\r\n", FILE_APPEND);
        }

        return $filename;
    }
}
