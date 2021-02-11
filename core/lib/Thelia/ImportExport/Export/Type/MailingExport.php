<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        'newsletter_lastname' => 'LastName',
        'newsletter_locale' => 'Locale'
    ];

    protected function getData()
    {
        $con = Propel::getConnection();
        $query = 'SELECT
                        newsletter.id as "newsletter_id",
                        newsletter.email as "newsletter_email",
                        newsletter.firstname as "newsletter_firstname",
                        newsletter.lastname as "newsletter_lastname",
                        newsletter.locale as "newsletter_locale"
                    FROM newsletter
                    WHERE newsletter.unsubscribed = 0'
        ;
        $stmt = $con->prepare($query);
        $stmt->execute();

        return $this->getDataJsonCache($stmt, 'mailing');
    }
}
