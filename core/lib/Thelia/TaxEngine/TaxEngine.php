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
namespace Thelia\TaxEngine;

/**
 * Class TaxEngine
 * @package Thelia\TaxEngine
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class TaxEngine
{
    static public function getInstance()
    {
        return new TaxEngine();
    }

    private function getTaxTypeDirectory()
    {
        return __DIR__ . "/TaxType";
    }

    public function getTaxTypeList()
    {
        $typeList = array();

        try {
            $directoryBrowser = new \DirectoryIterator($this->getTaxTypeDirectory($this->getTaxTypeDirectory()));
        } catch (\UnexpectedValueException $e) {
            return $typeList;
        }

        /* browse the directory */
        foreach ($directoryBrowser as $directoryContent) {
            /* is it a file ? */
            if (!$directoryContent->isFile()) {
                continue;
            }

            $fileName = $directoryContent->getFilename();
            $className = substr($fileName, 0, (1+strlen($directoryContent->getExtension())) * -1);

            if($className == "BaseTaxType") {
                continue;
            }

            $typeList[] = $className;
        }

        return $typeList;
    }
}
