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
 * Class ExportType
 * @package Thelia\ImportExport\Export
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class ExportType 
{
    /**
     * This type is for unbounded formats, in general serialization formats
     * example: XML, json, yaml
     */
    const EXPORT_UNBOUNDED  = "export.unbounded";

    /**
     * This type is for tabled format ( matrix ), most used by spreadsheet application.
     * example:  CSV, ODS, XLS
     */
    const EXPORT_TABLE      = "export.table";
} 