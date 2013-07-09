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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Security\Exception\AuthenticationTokenNotFoundException;

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

	const TEMPLATE_404 = "404.html";

	public function notFoundAction()
	{
		return new Response($this->renderRaw(self::TEMPLATE_404), 404);
	}

    /**
     * Render the givent template, and returns the result as an Http Response.
     *
     * @param $templateName the complete template name, with extension
     * @param array $args the template arguments
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($templateName, $args = array())
    {
        $response = new Response();

        return $response->setContent($this->renderRaw($templateName, $args));
    }

    /**
     * Render the givent template, and returns the result as a string.
     *
     * @param $templateName the complete template name, with extension
     * @param array $args the template arguments
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function renderRaw($templateName, $args = array())
    {
        $args = array_merge($args, array('lang' => 'fr')); // FIXME

        try {
        	$data = $this->getParser()->render($templateName, $args);
        }
        catch (AuthenticationTokenNotFoundException $ex) {

			// No auth token -> perform login
        	return new RedirectResponse($this->generateUrl('admin/login'));
        }

        return $data;
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     */
    public function getRequest()
    {
        return $this->container->get('request');
    }

    /**
     *
     * @return a ParserInterface instance parser, as configured.
     */
    public function getParser()
    {
        $parser = $this->container->get("thelia.parser");

        // FIXME: should be read from config
        $parser->setTemplate('admin/default');

        return $parser;
    }

    protected function getFormFactory()
    {
        return BaseForm::getFormFactory($this->getRequest(), ConfigQuery::read("form.secret.admin", md5(__DIR__)));
    }

    protected function getFormBuilder()
    {
        return $this->getFormFactory()->createBuilder("form");
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string         $route         The name of the route
     * @param mixed          $parameters    An array of parameters
     * @param Boolean|string $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The generated URL
     *
     * @see UrlGeneratorInterface
     */
    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
    	return "thelia2/$route"; //FIXME

    	//return $this->container->get('router')->generate($route, $parameters, $referenceType);
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    public function forward($controller, array $path = array(), array $query = array())
    {
    	$path['_controller'] = $controller;
    	$subRequest = $this->container->get('request')->duplicate($query, null, $path);

    	return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param string  $url    The URL to redirect to
     * @param integer $status The status code to use for the Response
     *
     * @return RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
    	return new RedirectResponse($url, $status);
    }
}