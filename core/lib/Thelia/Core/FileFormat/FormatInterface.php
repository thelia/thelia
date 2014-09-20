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
 * Interface FormatInterface
 * @package Thelia\Core\FileFormat
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * This interface defines what a formatter must have:
 *     - A name ( example: XML, JSON, yaml )
 *     - An extension ( example: xml, json, yml )
 *     - A mime type ( example: application/xml, application/json, ... )
 */
interface FormatInterface
{
    /**
     * @return string
     *
     * This method must return a string, the name of the format.
     *
     * example:
     * return "XML";
     */
    public function getName();

    /**
     * @return string
     *
     * This method must return a string, the extension of the file format, without the ".".
     * The string should be lowercase.
     *
     * example:
     * return "xml";
     */
    public function getExtension();

    /**
     * @return string
     *
     * This method must return a string, the mime type of the file format.
     *
     * example:
     * return "application/json";
     */
    public function getMimeType();
}
