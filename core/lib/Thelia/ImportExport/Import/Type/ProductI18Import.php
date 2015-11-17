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
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use Thelia\Model\RewritingUrlQuery;

/**
 * Class ProductPricesImport
 * @package Thelia\ImportExport\Import\Type
 */
class ProductI18Import extends ImportHandler
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

        $locale = $this->translator->getLocale();
        $viewName = (new Product())->getRewrittenUrlViewName();

        while (null !== $row = $data->popRow())
        {

            $this->checkMandatoryColumns($row);

            $obj = ProductQuery::create()->findPk($row["id"]);

            if ($obj === null)
            {
                $errorMessage = $translator->trans(
                    "The product id %id doesn't exist",
                    [
                        "%id" => $row["id"]
                    ]
                );

                $errors[] = $errorMessage ;
            } else {

                //maj du produit
                $obj->setLocale($locale)
                    ->setTitle($row["product_title"])
                    ->setDescription($row["product_description"])
                    ->setChapo($row["product_chapo"])
                    ->setPostscriptum($row["product_postscriptum"])
                    ->setMetaTitle($row["page_title"])
                    ->setMetaDescription($row["meta_description"])
                    ->setMetaKeywords($row["meta_keywords"])
                    ->save();

                //maj de l'url
                $objProductUrl = RewritingUrlQuery::create()
                    ->filterByView($viewName)
                    ->filterByViewId($obj->getId())
                    ->filterByViewLocale($locale)
                    ->filterByRedirected(NULL)
                    ->findOne();


                if(null !== $objProductUrl)
                {
                    $isUrlExist = RewritingUrlQuery::create()
                        ->filterByUrl($row["url"])
                        ->findOne();
                    if ( (null !== $isUrlExist && $isUrlExist->getView() === $viewName &&  $isUrlExist->getViewId() == $obj->getId() ) || (null === $isUrlExist)){
                        $objProductUrl->setUrl($row["url"])
                            ->save();
                    }
                    else{
                        $errorMessage = $translator->trans(
                            "The product url \"%url\" already exist for product id %id",
                            [
                                "%url" => $row["url"],
                                "%id" => $row["id"]
                            ]
                        );
                        $errors[] = $errorMessage ;
                    }
                }

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
        return ["id", "product_title", "product_description","product_chapo","product_postscriptum","page_title", "meta_description", "meta_keywords", "url"];
    }
}
