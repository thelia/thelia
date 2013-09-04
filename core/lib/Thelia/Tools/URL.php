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

namespace Thelia\Tools;

use Thelia\Model\ConfigQuery;
use Thelia\Rewriting\RewritingResolver;
use Thelia\Rewriting\RewritingRetriever;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

class URL
{
    protected $resolver = null;
    protected $retriever = null;

    protected $container;
    protected $environment;

    const PATH_TO_FILE = true;
    const WITH_INDEX_PAGE = false;

    private static $instance = null;

    public function __construct(ContainerInterface $container, $environment)
    {
        // Allow singleton style calls once intanciated.
        // For this to work, the URL service has to be instanciated very early. This is done manually
        // in TheliaHttpKernel, by calling $this->container->get('thelia.url.manager');
        self::$instance = $this;

        $this->container = $container;
        $this->environment = $environment;

        $this->retriever = new RewritingRetriever();
        $this->resolver = new RewritingResolver();
    }

    /**
     * Return this class instance, only once instanciated.
     *
     * @throws \RuntimeException if the class has not been instanciated.
     * @return \Thelia\Tools\URL the instance.
     */
    public static function getInstance() {
        if (self::$instance == null) throw new \RuntimeException("URL instance is not initialized.");

        return self::$instance;
    }

    /**
     * Return the base URL, either the base_url defined in Config, or the URL
     * of the current language, if 'one_domain_foreach_lang' is enabled.
     *
     * @return string the base URL, with a trailing '/'
     */
    public function getBaseUrl()
    {
        $lang = $this->container->get('request')->getSession()->getLang();

        // Check if we have a specific URL for each lang.
        $one_domain_foreach_lang = ConfigQuery::read("one_domain_foreach_lang", false);

        if ($one_domain_foreach_lang == true) {
            // If it's the case, get the current lang URL
            $base_url = $lang->getUrl();

            $err_msg_part = 'base_url';
        }
        else {
            // Get the base URL
            $base_url = ConfigQuery::read('base_url', null);

            $err_msg_part = sprintf('base_url for lang %s', $lang->getCode());
        }

        // Be sure that base-url starts with http, give up if it's not the case.
        if (substr($base_url, 0, 4) != 'http') {
            throw new \InvalidArgumentException(
                    sprintf("The %s configuration parameter shoud contains the URL of your shop, starting with http or https.", $err_msg_part));
        }

        // Normalize the base_url
        return rtrim($base_url, '/').'/';
    }

    /**
     * @return string the index page, which is basically the base_url in prod environment.
     */
    public function getIndexPage()
    {
        // Get the base URL
        $base_url = $this->getBaseUrl();

        // For dev environment, add the proper page.
        if ($this->environment == 'dev') {
            $base_url .= "index_dev.php";
        }

        return $base_url;
    }

    /**
     * Returns the Absolute URL for a given path relative to web root. By default,
     * the script name (index_dev.php) is added to the URL in dev_environment, use
     * $path_only = true to get a path without the index script.
     *
     * @param string  $path       the relative path
     * @param array   $parameters An array of parameters
     * @param boolean $path_only  if true (PATH_TO_FILE), getIndexPage() will  not be added
     *
     * @return string The generated URL
     */
    public function absoluteUrl($path, array $parameters = null, $path_only = self::WITH_INDEX_PAGE)
    {
         // Already absolute ?
        if (substr($path, 0, 4) != 'http') {

            /**
             * @etienne : can't be done here for it's already done in ::viewUrl / ::adminViewUrl
             * @franck : should be done, as absoluteUrl() is sometimes called directly (see UrlGenerator::generateUrlFunction())
             */
            $root = $path_only == self::PATH_TO_FILE ? $this->getBaseUrl() : $this->getIndexPage();

            // Normalize root path
            $base = rtrim($root, '/') . '/' . ltrim($path, '/');
        } else
            $base = $path;

        $queryString = '';

        if (! is_null($parameters)) {
            foreach ($parameters as $name => $value) {
                $queryString .= sprintf("%s=%s&", urlencode($name), urlencode($value));
            }
        }

        $sepChar = strstr($base, '?') === false ? '?' : '&';

        if ('' !== $queryString = rtrim($queryString, "&")) $queryString = $sepChar . $queryString;

        return $base . $queryString;
    }

    /**
     * Returns the Absolute URL to a administration view
     *
     * @param string $viewName   the view name (e.g. login for login.html)
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated URL
     */
    public function adminViewUrl($viewName, array $parameters = array())
    {
        $path = sprintf("%s/admin/%s", $this->getIndexPage(), $viewName);

        return $this->absoluteUrl($path, $parameters);
    }

    /**
     * Returns the Absolute URL to a view
     *
     * @param string $viewName   the view name (e.g. login for login.html)
     * @param mixed  $parameters An array of parameters
     *
     * @return string The generated URL
     */
     public function viewUrl($viewName, array $parameters = array())
     {
         $path = sprintf("?view=%s", $viewName);

         return $this->absoluteUrl($path, $parameters);
     }
     /**
      * Retrieve a rewritten URL from a view, a view id and a locale
      *
      * @param $view
      * @param $viewId
      * @param $viewLocale
      *
      * @return RewritingRetriever You can access $url and $rewrittenUrl properties
      */
     public function retrieve($view, $viewId, $viewLocale)
     {
         if(ConfigQuery::isRewritingEnable()) {
             $this->retriever->loadViewUrl($view, $viewLocale, $viewId);
         }

         return $this->retriever;
     }

     /**
      * Retrieve a rewritten URL from the current GET parameters
      *
      * @param Request $request
      *
      * @return RewritingRetriever You can access $url and $rewrittenUrl properties or use toString method
      */
     public function retrieveCurrent(Request $request)
     {
         if(ConfigQuery::isRewritingEnable()) {
             $view = $request->query->get('view', null);
             $viewLocale = $request->query->get('locale', null);
             $viewId = $view === null ? null : $request->query->get($view . '_id', null);

             $allOtherParameters = $request->query->all();
             if($view !== null) {
                 unset($allOtherParameters['view']);
             }
             if($viewLocale !== null) {
                 unset($allOtherParameters['locale']);
             }
             if($viewId !== null) {
                 unset($allOtherParameters[$view . '_id']);
             }

             $this->retriever->loadSpecificUrl($view, $viewLocale, $viewId, $allOtherParameters);
         }

         return $this->retriever;
     }

     /**
      * Retrieve a rewritten URL from the current GET parameters or use toString method
      *
      * @param $url
      *
      * @return RewritingResolver
      */
     public function resolve($url)
     {
         $this->resolver->load($url);
         return $this->resolver;
     }
}