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
use Thelia\Core\FileFormat\Formatting\FormatterData;
use Thelia\ImportExport\AbstractHandler;

/**
 * Class ImportHandler
 * @package Thelia\ImportExport
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
abstract class ImportHandler extends AbstractHandler
{
    protected $importedRows = 0;

    public function getImportedRows()
    {
        return $this->importedRows;
    }

    /**
     * @param \Thelia\Core\FileFormat\Formatting\FormatterData
     * @return string|array error messages
     *
     * The method does the import routine from a FormatterData
     */
    abstract public function retrieveFromFormatterData(FormatterData $data);
} 