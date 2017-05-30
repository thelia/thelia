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

namespace TheliaSmarty\Template\Plugins;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Thelia\Core\HttpFoundation\Session\Session;
use TheliaSmarty\Template\SmartyParser;
use TheliaSmarty\Template\SmartyPluginDescriptor;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\LangQuery;
use Thelia\Model\ConfigQuery;

class UrlGenerator extends AbstractSmartyPlugin
{
    /** @var RequestStack */
    protected $requestStack;

    /** @var TokenProvider */
    protected $tokenProvider;

    /** @var ContainerInterface */
    private $container;

    /**
     * @param RequestStack       $requestStack
     * @param TokenProvider      $tokenProvider
     * @param ContainerInterface $container         Needed to get all router.
     */
    public function __construct(RequestStack $requestStack, TokenProvider $tokenProvider, ContainerInterface $container)
    {
        $this->requestStack = $requestStack;
        $this->tokenProvider = $tokenProvider;
        $this->container = $container;
    }

    /**
     * Process url generator function
     *
     * @param  array   $params
     * @param  \Smarty $smarty
     * @return string  no text is returned.
     */
    public function generateUrlFunction($params, &$smarty)
    {
        // the path to process
        $current = $this->getParam($params, 'current', false);
        $path  = $this->getParam($params, 'path', null);
        // Do not invoke index.php in URL (get a static file in web space
        $file = $this->getParam($params, 'file', null);
        $routeId = $this->getParam($params, 'route_id', null);
        // select default router
        if ($this->getRequest()->fromAdmin()) {
            $defaultRouter = 'admin';
        } elseif ($this->getRequest()->fromFront()) {
            $defaultRouter = 'front';
        } else {
            $defaultRouter = null;
        }
        $routerId = $this->getParam($params, 'router', $defaultRouter);

        if ($current) {
            $path = $this->getRequest()->getPathInfo();
            unset($params["current"]); // Delete the current param, so it isn't included in the url

            // build the query variables
            $params = array_merge(
                $this->getRequest()->query->all(),
                $params
            );
        }

        if ($routeId !== null && $routerId !== null) {
            $routerId = 'router.' . $routerId;

            // test if the router exists
            if (!$this->container->has($routerId)) {
                throw new \InvalidArgumentException(
                    'The router "' . $routerId . '" not found.'
                );
            }
            // get url by router and id
            /** @var Router $router */
            $router = $this->container->get($routerId);

            $url = $router->generate(
                $routeId,
                $this->getArgsFromParam($params, ['route_id', 'router']),
                Router::ABSOLUTE_URL
            );
        } else {
            if ($file !== null) {
                $path = $file;
                $mode = URL::PATH_TO_FILE;
            } elseif ($path !== null) {
                $mode = URL::WITH_INDEX_PAGE;
            } else {
                throw new \InvalidArgumentException(
                    "Please specify either 'path', 'file' or router and route_id on parameters in {url} function."
                );
            }

            $excludeParams = $this->resolvePath($params, $path, $smarty);

            $url = URL::getInstance()->absoluteUrl(
                $path,
                $this->getArgsFromParam($params, array_merge(['noamp', 'path', 'file', 'target'], $excludeParams)),
                $mode
            );
            
            $request = $this->getRequest();
            $requestedLangCodeOrLocale = $params["lang"];
            $view = $request->attributes->get('_view', null);
            $viewId = $view === null ? null : $request->query->get($view . '_id', null);

            if (null !== $requestedLangCodeOrLocale) {
                if (strlen($requestedLangCodeOrLocale) > 2) {
                    $lang = LangQuery::create()->findOneByLocale($requestedLangCodeOrLocale);
                } else {
                    $lang = LangQuery::create()->findOneByCode($requestedLangCodeOrLocale);
                }

                if (ConfigQuery::isMultiDomainActivated()) {
                    $urlRewrite = RewritingUrlQuery::create()
                        ->filterByView($view)
                        ->filterByViewId($viewId)
                        ->filterByViewLocale($lang->getLocale())
                        ->findOneByRedirected(null)
                    ;
                    
                    $path = '';
                    if (null != $urlRewrite) {
                        $path = "/".$urlRewrite->getUrl();
                    }
                    $url = rtrim($lang->getUrl(), "/").$request->getBaseUrl().$path;
                }

            }
        }
        return $this->applyNoAmpAndTarget($params, $url);
    }

    /**
     *
     * find placeholders in the path and replace them by the given value
     *
     * @param $params
     * @param $path
     * @param $smarty
     * @return array the placeholders found
     */
    protected function resolvePath(&$params, &$path, $smarty)
    {
        $placeholder = [];

        foreach ($params as $key => $value) {
            if (false !== strpos($path, "%$key")) {
                $placeholder["%$key"] = SmartyParser::theliaEscape($value, $smarty);
                unset($params[$key]);
            }
        }

        $path = strtr($path, $placeholder);
        $keys = array_keys($placeholder);
        array_walk($keys, function(&$item, $key) {
            $item = str_replace('%', '', $item);
        });

        return $keys;
    }

     /**
      * Process view url generator function
      *
      * @param  array $params
      * @param  \Smarty $smarty
      * @return string no text is returned.
      */
    public function generateFrontViewUrlFunction($params, &$smarty)
    {
        return $this->generateViewUrlFunction($params, false);
    }

     /**
      * Process administration view url generator function
      *
      * @param  array $params
      * @param  \Smarty $smarty
      * @return string no text is returned.
      */
    public function generateAdminViewUrlFunction($params, &$smarty)
    {
        return $this->generateViewUrlFunction($params, true);
    }


    public function navigateToUrlFunction($params, &$smarty)
    {
        $to = $this->getParam($params, 'to', null);

        $toMethod = $this->getNavigateToMethod($to);
     
        $url = URL::getInstance()->absoluteUrl(
            $this->$toMethod(),
            $this->getArgsFromParam($params, ['noamp', 'to', 'target']),
            URL::WITH_INDEX_PAGE
        );

        return $this->applyNoAmpAndTarget($params, $url);
    }

    protected function generateViewUrlFunction($params, $forAdmin)
    {
        // the view name (without .html)
        $view   = $this->getParam($params, 'view');

        $args = $this->getArgsFromParam($params, array('view', 'noamp', 'target'));

        $url = $forAdmin ? URL::getInstance()->adminViewUrl($view, $args) : URL::getInstance()->viewUrl($view, $args);

        return $this->applyNoAmpAndTarget($params, $url);
    }

     /**
      * Get URL parameters array from parameters.
      *
      * @param array $params Smarty function params
      * @param array $exclude Smarty function exclude params
      * @return array the parameters array (either emply, of valued)
      */
    private function getArgsFromParam($params, $exclude = array())
    {
        $pairs = array();

        foreach ($params as $name => $value) {
            if (in_array($name, $exclude)) {
                continue;
            }

            $pairs[$name] = $value;
        }

        return $pairs;
    }

    public function generateUrlWithToken($params, &$smarty)
    {
        /**
         * Compute the url
         */
        $url = $this->generateUrlFunction($params, $smarty);

        $urlTokenParam = $this->getParam($params, "url_param", "_token");

        /**
         * Add the token
         */
        $token = $this->tokenProvider->assignToken();

        $newUrl = URL::getInstance()->absoluteUrl(
            $url,
            [
                $urlTokenParam => $token
            ]
        );

        return $this->applyNoAmpAndTarget($params, $newUrl);
    }

    protected function applyNoAmpAndTarget($params, $url)
    {
        $noamp  = $this->getParam($params, 'noamp', null); // Do not change & in &amp;
        $target = $this->getParam($params, 'target', null);

        if (!$noamp) {
            $url = str_replace('&', '&amp;', $url);
        }

        if ($target != null) {
            $url .= '#'.$target;
        }

        return $url;
    }

    /**
     * Set the _previous_url request attribute, to define the previous URL, or
     * prevent saving the current URL as the previous one.
     *
     * @param array                     $params
     * @param \Smarty_Internal_Template $smarty
     */
    public function setPreviousUrlFunction($params, &$smarty)
    {
        $ignore_current = $this->getParam($params, 'ignore_current', false);

        if ($ignore_current !== false) {
            $this->getRequest()->attributes->set('_previous_url', 'dont-save');
        } else {
            $this->getRequest()->attributes->set('_previous_url', $this->generateUrlFunction($params, $smarty));
        }
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return array an array of smarty plugin descriptors
     */
    public function getPluginDescriptors()
    {
        return array(
            new SmartyPluginDescriptor('function', 'url', $this, 'generateUrlFunction'),
            new SmartyPluginDescriptor('function', 'token_url', $this, 'generateUrlWithToken'),
            new SmartyPluginDescriptor('function', 'viewurl', $this, 'generateFrontViewUrlFunction'),
            new SmartyPluginDescriptor('function', 'admin_viewurl', $this, 'generateAdminViewUrlFunction'),
            new SmartyPluginDescriptor('function', 'navigate', $this, 'navigateToUrlFunction'),
            new SmartyPluginDescriptor('function', 'set_previous_url', $this, 'setPreviousUrlFunction')
        );
    }

    /**
     * @return array sur le format "to_value" => "method_name"
     */
    protected function getNavigateToValues()
    {
        return array(
            "current"  => "getCurrentUrl",
            "previous" => "getPreviousUrl",
            "index"    => "getIndexUrl",
        );
    }

    protected function getNavigateToMethod($to)
    {
        if ($to === null) {
            throw new \InvalidArgumentException("Missing 'to' parameter in `navigate` substitution.");
        }

        $navigateToValues = $this->getNavigateToValues();

        if (!array_key_exists($to, $navigateToValues)) {
            throw new \InvalidArgumentException(
                sprintf("Incorrect value `%s` for parameter `to` in `navigate` substitution.", $to)
            );
        }

        return $navigateToValues[$to];
    }

    protected function getCurrentUrl()
    {
        return $this->getRequest()->getUri();
    }

    protected function getPreviousUrl()
    {
        return URL::getInstance()->absoluteUrl($this->getSession()->getReturnToUrl());
    }

    protected function getIndexUrl()
    {
        return URL::getInstance()->getIndexPage();
    }

    /**
     * @return Request
     */
    protected function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * @return Session
     */
    protected function getSession()
    {
        return $this->getRequest()->getSession();
    }
}
