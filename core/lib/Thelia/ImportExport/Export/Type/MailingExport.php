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

use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\Map\CustomerTableMap;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\Model\NewsletterQuery;

/**
 * Class MailingExport
 * @package Thelia\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class MailingExport extends ExportHandler
{
    /**
     * @param  Lang                                            $lang
     * @return array|\Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildDataSet(Lang $lang)
    {
        $newsletter = NewsletterQuery::create()
            ->select([
                NewsletterTableMap::EMAIL,
                NewsletterTableMap::LASTNAME,
                NewsletterTableMap::FIRSTNAME,
            ])
            ->find()
            ->toArray()
        ;

        $customers = CustomerQuery::create()
            ->select([
                CustomerTableMap::EMAIL,
                CustomerTableMap::LASTNAME,
                CustomerTableMap::FIRSTNAME,
            ])
            ->find()
            ->toArray()
        ;

        return $customers + $newsletter;
    }

    protected function getAliases()
    {
        $email = "email";
        $lastName = "last_name";
        $firstName = "first_name";

        return [
            NewsletterTableMap::EMAIL       => $email,
            CustomerTableMap::EMAIL         => $email,

            NewsletterTableMap::LASTNAME    => $lastName,
            CustomerTableMap::LASTNAME      => $lastName,

            NewsletterTableMap::FIRSTNAME   => $firstName,
            CustomerTableMap::FIRSTNAME     => $firstName,
        ];
    }

    /**
     * @return string|array
     *
     * Define all the type of export/formatters that this can handle
     * return a string if it handle a single type ( specific exports ),
     * or an array if multiple.
     *
     * Thelia types are defined in \Thelia\Core\FileFormat\FormatType
     *
     * example:
     * return array(
     *     FormatType::TABLE,
     *     FormatType::UNBOUNDED,
     * );
     */
    public function getHandledTypes()
    {
        return array(
            FormatType::TABLE,
            FormatType::UNBOUNDED,
        );
    }
}
