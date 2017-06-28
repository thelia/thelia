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

use Propel\Runtime\ActiveQuery\Criteria;
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
use Thelia\Core\HttpFoundation\Request as TheliaRequest;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\ConfigQuery;
use Thelia\Model\CustomerQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Rewriting\RewritingResolver;
use Thelia\Tools\URL;

/**
 * Class RewritingRouter
 * @package Thelia\Core\Routing
 * @author Manuel Raynaud <manu@raynaud.io>
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class RewritingRouter implements RouterInterface, RequestMatcherInterface
{
    /**
     * @var RequestContext The context
     */
    protected $context;

    /**
     * @var array options, don't use for now but mandatory
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
            $urlTool = URL::getInstance();

            $pathInfo = $request instanceof TheliaRequest ? $request->getRealPathInfo() : $request->getPathInfo();

            try {
                $rewrittenUrlData = $urlTool->resolve($pathInfo);
            } catch (UrlRewritingException $e) {
                switch ($e->getCode()) {
                    case UrlRewritingException::URL_NOT_FOUND:
                        throw new ResourceNotFoundException();
                        break;
                    default:
                        throw $e;
                }
            }

            // If we have a "lang" parameter, whe have to check if the found URL has the proper locale
            // If it's not the case, find the rewritten URL with the requested locale, and redirect to it.
            if (null ==! $requestedLocale = $request->get('lang')) {
                if (null !== $requestedLang = LangQuery::create()->findOneByLocale($requestedLocale)) {
                    if ($requestedLang->getLocale() != $rewrittenUrlData->locale) {
                        // Save one redirection if requested locale is disabled.
                        if (! $requestedLang->getActive()) {
                            $requestedLang = Lang::getDefaultLanguage();
                        }

                        $localizedUrl = $urlTool->retrieve(
                            $rewrittenUrlData->view,
                            $rewrittenUrlData->viewId,
                            $requestedLang->getLocale()
                        )->toString();

                        $this->redirect($urlTool->absoluteUrl($localizedUrl), 301);
                    }
                }
            }

            // If the rewritten URL locale is disabled, redirect to the URL in the default language
            if (null === $lang = LangQuery::create()
                    ->filterByActive(true)
                    ->filterByLocale($rewrittenUrlData->locale)
                    ->findOne()) {
                $lang = Lang::getDefaultLanguage();

                $localizedUrl = $urlTool->retrieve(
                    $rewrittenUrlData->view,
                    $rewrittenUrlData->viewId,
                    $lang->getLocale()
                )->toString();

                $this->redirect($urlTool->absoluteUrl($localizedUrl), 301);
            }

            /* is the URL redirected ? */
            if (null !== $rewrittenUrlData->redirectedToUrl) {
                $redirect = RewritingUrlQuery::create()
                    ->filterByView($rewrittenUrlData->view)
                    ->filterByViewId($rewrittenUrlData->viewId)
                    ->filterByViewLocale($rewrittenUrlData->locale)
                    ->filterByRedirected(null, Criteria::ISNULL)
                    ->findOne()
                ;

                $this->redirect($urlTool->absoluteUrl($redirect->getUrl()), 301);
            }

            /* define GET arguments in request */

            if (null !== $rewrittenUrlData->view) {
                $request->attributes->set('_view', $rewrittenUrlData->view);
                if (null !== $rewrittenUrlData->viewId) {
                    $request->query->set($rewrittenUrlData->view . '_id', $rewrittenUrlData->viewId);
                }
            }

            if (null !== $rewrittenUrlData->locale) {
                $this->manageLocale($rewrittenUrlData, $request);
            }


            foreach ($rewrittenUrlData->otherParameters as $parameter => $value) {
                $request->query->set($parameter, $value);
            }

            return array(
                '_controller' => 'Thelia\\Controller\\Front\\DefaultController::noAction',
                '_route' => 'rewrite',
                '_rewritten' => true,
            );
        }
        throw new ResourceNotFoundException();
    }

    protected function manageLocale(RewritingResolver $rewrittenUrlData, TheliaRequest $request)
    {
        $lang = LangQuery::create()
            ->findOneByLocale($rewrittenUrlData->locale);

        $langSession = $request->getSession()->getLang();

        if ($lang != $langSession) {
            if (ConfigQuery::isMultiDomainActivated()) {
                $this->redirect(
                    sprintf("%s/%s", $lang->getUrl(), $rewrittenUrlData->rewrittenUrl)
                );
            } else {
                $request->getSession()->setLang($lang);
            }
        }
    }

    protected function redirect($url, $status = 302)
    {
        throw new RedirectException($url, $status);
    }
}
