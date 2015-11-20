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
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\RewritingUrlQuery;

/**
 * Class FolderI18Import
 * @package Thelia\ImportExport\Import\Type
 */
class FolderI18Import extends ImportHandler
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
        $viewName = (new Folder())->getRewrittenUrlViewName();

        while (null !== $row = $data->popRow())
        {

            $this->checkMandatoryColumns($row);

            $obj = FolderQuery::create()->findPk($row["id"]);

            if ($obj === null)
            {
                $errorMessage = $translator->trans(
                    "The folder id %id doesn't exist",
                    [
                        "%id" => $row["id"]
                    ]
                );

                $errors[] = $errorMessage ;
            } else {

                $obj->setLocale($locale);

                if(isset($row["folder_title"]))
                    $obj->setTitle($row["folder_title"]);

                if(isset($row["folder_description"]))
                    $obj->setDescription($row["folder_description"]);

                if(isset($row["folder_chapo"]))
                    $obj->setChapo($row["folder_chapo"]);

                if(isset($row["folder_postscriptum"]))
                    $obj->setPostscriptum($row["folder_postscriptum"]);

                if(isset($row["page_title"]))
                    $obj->setMetaTitle($row["page_title"]);

                if(isset($row["meta_description"]))
                    $obj->setMetaDescription($row["meta_description"]);

                if(isset($row["meta_keywords"]))
                    $obj->setMetaKeywords($row["meta_keywords"]);

                $obj->save();

                $objFolderUrl = RewritingUrlQuery::create()
                    ->filterByView($viewName)
                    ->filterByViewId($obj->getId())
                    ->filterByViewLocale($locale)
                    ->filterByRedirected(NULL)
                    ->findOne();


                if(null !== $objFolderUrl)
                {
                    $isUrlExist = RewritingUrlQuery::create()
                        ->filterByUrl($row["url"])
                        ->findOne();
                    if ( (null !== $isUrlExist && $isUrlExist->getView() === $viewName &&  $isUrlExist->getViewId() == $obj->getId() ) || (null === $isUrlExist)){
                        $objFolderUrl->setUrl($row["url"])
                            ->save();
                    }
                    else{
                        $errorMessage = $translator->trans(
                            "The folder url \"%url\" already exist for folder id %id",
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
        //return ["id", "folder_title", "folder_description","folder_chapo","folder_postscriptum","page_title", "meta_description", "meta_keywords", "url"];
        return(["id"]);
    }
}
