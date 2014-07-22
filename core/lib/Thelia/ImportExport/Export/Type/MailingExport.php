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
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\Core\Translation\Translator;
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
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds
     */
    public function buildFormatterData(Lang $lang)
    {
        $translator = Translator::getInstance();

        $email = $translator->trans("email");
        $lastName = $translator->trans("last name");
        $firstName = $translator->trans("first name");


        $aliases = [
            NewsletterTableMap::EMAIL       => $email,
            CustomerTableMap::EMAIL         => $email,

            NewsletterTableMap::LASTNAME    => $lastName,
            CustomerTableMap::LASTNAME      => $lastName,

            NewsletterTableMap::FIRSTNAME   => $firstName,
            CustomerTableMap::FIRSTNAME     => $firstName,
        ];

        $data = new FormatterData($aliases);

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

        $both = $newsletter + $customers;

        return $data->setData($both);
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