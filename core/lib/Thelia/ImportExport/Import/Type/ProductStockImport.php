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

namespace Thelia\ImportExport\Import\Type;

use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\Core\FileFormat\FormatType;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ControllerTestBase
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

        while (null !== $row = $data->popRow()) {
            /**
             * Check for mandatory columns
             */
            $this->checkMandatoryColumns($row);

            $obj = ProductSaleElementsQuery::create()->findPk($row["id"]);

            if ($obj === null) {
                $errors[] = $this->translator->trans(
                    "The product sale element reference %id doesn't exist",
                    [
                        "%id" => $row["id"]
                    ]
                );
            } else {
                $obj->setQuantity($row["stock"]);

                if (isset($row["ean"]) && !empty($row["ean"])) {
                    $obj->setEanCode($row["ean"]);
                }

                $obj->save();
                $this->importedRows++;
            }
        }

        return $errors;
    }

    protected function getMandatoryColumns()
    {
        return ["id", "stock"];
    }

    /**
     * @return string|array
     *
     * Define all the type of import/formatters that this can handle
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
