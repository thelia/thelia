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
