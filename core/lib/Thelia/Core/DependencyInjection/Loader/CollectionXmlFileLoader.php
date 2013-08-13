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
namespace Thelia\Core\DependencyInjection\Loader;


class CollectionXmlFileLoader {

    protected $files = array();
    protected $cacheDir;
    protected $outputName;

    public function __construct($cacheDir, $outputName, array $files = array())
    {
        $this->cacheDir = $cacheDir;
        $this->outputName = $outputName;
        $this->files = $files;
    }

    public function addFile($file)
    {
        $this->files[] = $file;
    }

    public function process()
    {
        $pattern = '<import resource="%s" />';

        $outputPattern = <<<EOF
<?xml version="1.0" encoding="UTF-8" ?>

<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing http://symfony.com/schema/routing/routing-1.0.xsd">
        %s
</routes>
EOF;
        $imports = array();
        foreach ($this->files as $file) {
            $imports[] = sprintf($pattern, $file);
        }


        $output = sprintf($outputPattern, implode($imports));
        file_put_contents($this->cacheDir .'/'. $this->outputName, $output);

    }

}