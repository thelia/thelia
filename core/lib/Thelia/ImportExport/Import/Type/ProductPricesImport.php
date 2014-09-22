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
use Thelia\Core\Translation\Translator;
use Thelia\ImportExport\Import\ImportHandler;
use Thelia\Model\Currency;
use Thelia\Model\CurrencyQuery;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductPriceQuery;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Class ProductPricesImport
 * @package Thelia\ImportExport\Import\Type
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ProductPricesImport extends ImportHandler
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
     * @param \Thelia\Core\FileFormat\Formatting\FormatterData
     * @return string|array error messages
     *
     * The method does the import routine from a FormatterData
     */
    public function retrieveFromFormatterData(FormatterData $data)
    {
        $errors = [];
        $translator = Translator::getInstance();

        while (null !== $row = $data->popRow()) {

            $this->checkMandatoryColumns($row);

            $obj = ProductSaleElementsQuery::create()->findOneByRef($row["ref"]);

            if ($obj === null) {
                $errorMessage = $translator->trans(
                    "The product sale element reference %ref doesn't exist",
                    [
                        "%ref" => $row["ref"]
                    ]
                );

                $errors[] = $errorMessage ;
            } else {

                $currency = null;

                if (isset($row["currency"])) {
                    $currency = CurrencyQuery::create()->findOneByCode($row["currency"]);
                }

                if ($currency === null) {
                    $currency = Currency::getDefaultCurrency();
                }

                $price = ProductPriceQuery::create()
                    ->filterByProductSaleElementsId($obj->getId())
                    ->findOneByCurrencyId($currency->getId())
                ;

                if ($price === null) {
                    $price = new ProductPrice();

                    $price
                        ->setProductSaleElements($obj)
                        ->setCurrency($currency)
                    ;
                }

                $price->setPrice($row["price"]);

                if (isset($row["promo_price"])) {
                    $price->setPromoPrice($row["promo_price"]);
                }

                if (isset($row["promo"])) {
                    $price
                        ->getProductSaleElements()
                        ->setPromo((int) $row["promo"])
                        ->save()
                    ;
                }

                $price->save();
                $this->importedRows++;
            }
        }

        return $errors;
    }

    /**
     * @return array The mandatory columns to have for import
     */
    protected function getMandatoryColumns()
    {
        return ["ref", "price"];
    }
}
