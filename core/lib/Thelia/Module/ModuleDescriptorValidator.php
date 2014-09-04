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

use ErrorException;
use Symfony\Component\Finder\Finder;
use Thelia\Module\Exception\InvalidXmlDocumentException;

/**
 * Class ModuleDescriptorValidator
 * @package Thelia\Module
 * @author Manuel Raynaud <manu@thelia.net>
 */
class ModuleDescriptorValidator
{

    public function __construct()
    {
        $this->xsdFinder = new Finder();
        $this->xsdFinder
            ->name('*.xsd')
            ->in(__DIR__ . '/schema/module/');
    }

    public function validate($xml_file)
    {
        $dom = new \DOMDocument();

        if ($dom->load($xml_file)) {
            // todo: detect the right version of xsd for the module
            foreach ($this->xsdFinder as $xsdFile){
                try{
                    if ($dom->schemaValidate($xsdFile->getRealPath())) {
                        return true;
                    }
                } catch (ErrorException $ex) {}
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
