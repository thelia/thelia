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

namespace Thelia\Core\FileFormat;

/**
 * Class FormatType
 * @package Thelia\Core\FileFormat
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class FormatType
{
    /**
     * This type is for unbounded formats, in general serialization formats
     * example: XML, json, yaml
     */
    const UNBOUNDED  = "export.unbounded";

    /**
     * This type is for tabled format ( matrix ), most used by spreadsheet application.
     * example:  CSV, ODS, XLS
     */
    const TABLE      = "export.table";
}
