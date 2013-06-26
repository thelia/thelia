<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*	    email : info@thelia.net                                                      */
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
namespace Thelia\Admin\Controller;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;

use Thelia\Form\BaseForm;
use Thelia\Model\ConfigQuery;

/**
 *
 * The defaut administration controller. Basically, display the login form if
 * user is not yet logged in, or back-office home page if the user is logged in.
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 */

class BaseAdminController extends ContainerAware
{

    /**
     * @param $templateName
     * @param array $args
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($templateName, $args = array())
    {
        $args = array_merge($args, array('lang' => 'fr'));

        $response = new Response();

        return $response->setContent($this->renderRaw($templateName, $args));
    }

    public function renderRaw($templateName, $args = array())
    {
        $args = array_merge($args, array('lang' => 'fr'));

        return $this->getParser()->render($templateName, $args);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }

    public function getParser()
    {
        $parser = $this->container->get("thelia.parser");

        // FIXME: should be read from config
        $parser->setTemplate('admin/default');

        return $parser;
    }

    public function getFormFactory()
    {
        return BaseForm::getFormFactory($this->getRequest(), ConfigQuery::read("form.secret.admin", md5(__DIR__)));
    }

    public function getFormBuilder()
    {
        return $this->getFormFactory()->createBuilder("form");
    }


}