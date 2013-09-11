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

namespace Thelia\Controller\Install;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\BaseController;

/**
 * Class BaseInstallController
 * @package Thelia\Controller\Install
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */
class BaseInstallController extends BaseController
{
    /**
     * @return a ParserInterface instance parser
     */
    protected function getParser()
    {
        $parser = $this->container->get("thelia.parser");

        // Define the template thant shoud be used
        $parser->setTemplate("install");

        return $parser;
    }

    public function render($templateName, $args = array())
    {
        return new Response($this->renderRaw($templateName, $args));
    }

    public function renderRaw($templateName, $args = array())
    {
        $data = $this->getParser()->render($templateName, $args);

        return $data;
    }
}
