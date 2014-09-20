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

namespace Thelia\Core\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\Redirect;
use Thelia\Tools\URL;

/**
 * Class RewritingRouter
 * @package Thelia\Core\Routing
 * @author Manuel Raynaud <mraynaud@openstudio.fr>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class RewritingRouter implements RouterInterface, RequestMatcherInterface
{
    /**
     * @var RequestContext The context
     */
    protected $context;

    /**
     * @var options, don't use for now but mandatory
     */
    protected $options;

    /**
     * Sets the request context.
     *
     * @param RequestContext $context The context
     *
     * @api
     */
    public function setContext(RequestContext $context)
    {
        // TODO: Implement setContext() method.
        $this->context = $context;
    }

    /**
     * Gets the request context.
     *
     * @return RequestContext The context
     *
     * @api
     */
    public function getContext()
    {
        // TODO: Implement getContext() method.
        return $this->context;
    }

    public function setOption($key, $value)
    {
        //NOTHING TO DO FOR NOW
    }

    /**
     * Gets the RouteCollection instance associated with this Router.
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function getRouteCollection()
    {
        return new RouteCollection();
    }

    /**
     * Generates a URL or path for a specific route based on the given parameters.
     *
     * Parameters that reference placeholders in the route pattern will substitute them in the
     * path or host. Extra params are added as query string to the URL.
     *
     * When the passed reference type cannot be generated for the route because it requires a different
     * host or scheme than the current one, the method will return a more comprehensive reference
     * that includes the required params. For example, when you call this method with $referenceType = ABSOLUTE_PATH
     * but the route requires the https scheme whereas the current scheme is http, it will instead return an
     * ABSOLUTE_URL with the https scheme and the current host. This makes sure the generated URL matches
     * the route in any case.
     *
     * If there is no route with the given name, the generator must throw the RouteNotFoundException.
     *
     * @param string         $name          The name of the route
     * @param mixed          $parameters    An array of parameters
     * @param Boolean|string $referenceType The type of reference to be generated (one of the constants)
     *
     * @return string The generated URL
     *
     * @throws RouteNotFoundException              If the named route doesn't exist
     * @throws MissingMandatoryParametersException When some parameters are missing that are mandatory for the route
     * @throws InvalidParameterException           When a parameter value for a placeholder is not correct because
     *                                             it does not match the requirement
     *
     * @api
     */
    public function generate($name, $parameters = array(), $referenceType = self::ABSOLUTE_PATH)
    {
        throw new RouteNotFoundException();
    }

    /**
     * Tries to match a URL path with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param string $pathinfo The path info to be parsed (raw format, i.e. not urldecoded)
     *
     * @return array An array of parameters
     *
     * @throws ResourceNotFoundException If the resource could not be found
     * @throws MethodNotAllowedException If the resource was found but the request method is not allowed
     *
     * @api
     */
    public function match($pathinfo)
    {
        throw new ResourceNotFoundException("impossible to find route with this method, please use matchRequest method");
    }

    /**
     * Tries to match a request with a set of routes.
     *
     * If the matcher can not find information, it must throw one of the exceptions documented
     * below.
     *
     * @param Request $request The request to match
     *
     * @throws \Exception|\Thelia\Exception\UrlRewritingException
     * @throws \Symfony\Component\Routing\Exception\ResourceNotFoundException
     * @return array                                                          An array of parameters
     *
     */
    public function matchRequest(Request $request)
    {
        if (ConfigQuery::isRewritingEnable()) {
            try {
                $rewrittenUrlData = URL::getInstance()->resolve($request->getPathInfo());
            } catch (UrlRewritingException $e) {
                switch ($e->getCode()) {
                    case UrlRewritingException::URL_NOT_FOUND :
                        throw new ResourceNotFoundException();
                        break;
                    default:
                        throw $e;
                }
            }

            /* is the URL redirected ? */

            if (null !== $rewrittenUrlData->redirectedToUrl) {
                $this->redirect($rewrittenUrlData->redirectedToUrl, 301);
            }

            /* define GET arguments in request */

            if (null !== $rewrittenUrlData->view) {
                $request->attributes->set('_view', $rewrittenUrlData->view);
                if (null !== $rewrittenUrlData->viewId) {
                    $request->query->set($rewrittenUrlData->view . '_id', $rewrittenUrlData->viewId);
                }
            }
            if (null !== $rewrittenUrlData->locale) {
                $request->query->set('locale', $rewrittenUrlData->locale);
            }

            foreach ($rewrittenUrlData->otherParameters as $parameter => $value) {
                $request->query->set($parameter, $value);
            }

            return array (
                '_controller' => 'Thelia\\Controller\\Front\\DefaultController::noAction',
                '_route' => 'rewrite',
                '_rewritten' => true,
            );
        }
        throw new ResourceNotFoundException();
    }

    protected function redirect($url, $status = 302)
    {
        Redirect::exec($url, $status);
    }
}
