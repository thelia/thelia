<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Thelia\Module;
use Thelia\Module\Exception\InvalidXmlDocumentException;

/**
 * Class ModuleDescriptorValidator
 * @package Thelia\Module
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class ModuleDescriptorValidator
{
    private $xsd_file;

    public function __construct()
    {
        $this->xsd_file = __DIR__ . '/schema/module/module.xsd';
    }

    public function validate($xml_file)
    {
        $dom = new \DOMDocument();

        if ($dom->load($xml_file)) {
            if ($dom->schemaValidate($this->xsd_file)) {
                return true;
            }
        }

        throw new InvalidXmlDocumentException(sprintf("%s file is not a valid file", $xml_file));
    }

    public function getDescriptor($xml_file)
    {
        $this->validate($xml_file);

        return @simplexml_load_file($xml_file);
    }
}
