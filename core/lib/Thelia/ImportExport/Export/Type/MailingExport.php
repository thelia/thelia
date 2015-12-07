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
use Thelia\ImportExport\Export\AbstractExport;
use Thelia\ImportExport\Export\ExportHandler;
use Thelia\Model\Lang;
use Thelia\Model\Map\NewsletterTableMap;
use Thelia\Model\NewsletterQuery;

/**
 * Class MailingExport
 * @package Thelia\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class MailingExport extends AbstractExport
{
    protected function getData()
    {
        return new NewsletterQuery;
    }

    public function getFileName()
    {
        return 'mailing';
    }

    public function beforeSerialize(&$data)
    {
//        var_dump($data);
        $data['CreatedAt'] = $data['CreatedAt']->format('c');
        $data['UpdatedAt'] = $data['UpdatedAt']->format('c');
    }

    public function afterSerialize(&$data)
    {
//        var_dump($data);
//        exit;
    }

    protected function getAliases()
    {
        $email = "email";
        $lastName = "last_name";
        $firstName = "first_name";

        return [
            NewsletterTableMap::EMAIL       => $email,
            NewsletterTableMap::LASTNAME    => $lastName,
            NewsletterTableMap::FIRSTNAME   => $firstName,
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
