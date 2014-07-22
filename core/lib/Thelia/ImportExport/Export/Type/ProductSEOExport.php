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
use Thelia\Model\Lang;

/**
 * Class ProductSEOExport
 * @package Thelia\ImportExport\Export\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductSEOExport extends ExportHandler
{
    /**
     * @return string|array
     *
     * Define all the type of formatters that this can handle
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

    /**
     * @param \Thelia\Model\Lang $lang
     * @return \Thelia\Core\FileFormat\Formatting\FormatterData
     *
     * The method builds the FormatterData for the formatter
     */
    public function buildFormatterData(Lang $lang)
    {

    }

} 