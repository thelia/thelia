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

namespace Thelia\Module\Loader;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Util\XmlUtils;


/**
 * Class XmlFileLoader
 * @package Thelia\Module\Loader
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class XmlFileLoader extends FileLoader
{

    /**
     * Loads a resource.
     *
     * @param mixed $resource The resource
     * @param string $type     The resource type
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFile($path);
    }

    protected function parseFile($file)
    {
        $schema = str_replace('\\', '/',__DIR__.'/schema/module-1.0.xsd');

        $dom = XmlUtils::loadFile($file, $schema);

        return simplexml_import_dom($dom);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        // TODO: Implement supports() method.
    }
}