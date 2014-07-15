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

namespace Thelia\ImportExport\Import;
use Propel\Runtime\Collection\ObjectCollection;
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Export\ExportType;
use Thelia\ImportExport\ImportHandler;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductStockImport
 * @package Thelia\ImportExport\Import
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductStockImport extends ImportHandler
{
    /**
     * @param \Thelia\Core\FileFormat\Formatting\FormatterData
     * @return string|array error messages
     *
     * The method does the import routine from a FormatterData
     */
    public function retrieveFromFormatterData(FormatterData $data)
    {
        $errors = [];
        $translator = Translator::getInstance();
        $collection = new ObjectCollection();

        while (false !== $row = $data->popRow()) {
            $obj = ProductSaleElementsQuery::create()->findOneByRef($row["ref"]);

            if ($obj === null) {
                $errors += [
                    $translator->trans(
                        "The product sale elements reference %ref doesn't exist",
                        [
                            "%ref" => $row["ref"]
                        ]
                    )
                ];
            } else {
                $collection->append($obj->setQuantity($row["stock"]));
            }
        }

        $collection->save();

        return $errors;
    }

    /**
     * @return string|array
     *
     * Define all the type of import/formatters that this can handle
     * return a string if it handle a single type ( specific exports ),
     * or an array if multiple.
     *
     * Thelia types are defined in \Thelia\ImportExport\Export\ExportType
     *
     * example:
     * return array(
     *     ExportType::EXPORT_TABLE,
     *     ExportType::EXPORT_UNBOUNDED,
     * );
     */
    public function getHandledType()
    {
        return array(
            ExportType::EXPORT_TABLE,
            ExportType::EXPORT_UNBOUNDED,
        );
    }

} 