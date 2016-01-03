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
namespace Thelia\ImportExport\Export;

/**
 * Interface DocumentsExportInterface
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
interface DocumentsExportInterface
{
    const DOCUMENTS_DIRECTORY = "documents";

    /**
     * @return array
     *
     * return an array with the paths to the documents to include in the archive
     */
    public function getDocumentsPaths();
}
