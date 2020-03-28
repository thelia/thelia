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
use Thelia\Model\Map\NewsletterTableMap;

/**
 * Class MailingExport
 * @author Jérôme Billiras <jbilliras@openstudio.fr>
 * @author Florian Bernard <fbernard@openstudio.fr>
 */
class MailingExport extends JsonFileAbstractExport
{
    const FILE_NAME = 'mailing';

    protected $orderAndAliases = [
        NewsletterTableMap::COL_ID => 'Identifier',
        NewsletterTableMap::COL_EMAIL => 'Email',
        NewsletterTableMap::COL_FIRSTNAME => 'FirstName',
        NewsletterTableMap::COL_LASTNAME => 'LastName'
    ];

    protected function getData()
    {
        $con = Propel::getConnection();
        $query = 'SELECT 
                        newsletter.id as "newsletter.id",
                        newsletter.email as "newsletter.email", 
                        newsletter.firstname as "newsletter.firstname", 
                        newsletter.lastname as "newsletter.lastname"
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
