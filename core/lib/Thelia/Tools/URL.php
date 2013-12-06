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

use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RequestContext;

class URL
{
    protected $resolver = null;
    protected $retriever = null;

    protected $requestContext;

    const PATH_TO_FILE = true;
    const WITH_INDEX_PAGE = false;

    protected static $instance = null;

    public function __construct(ContainerInterface $container = null)
    {
        // Allow singleton style calls once intanciated.
        // For this to work, the URL service has to be instanciated very early. This is done manually
        // in TheliaHttpKernel, by calling $this->container->get('thelia.url.manager');
        self::$instance = $this;

        if ($container !== null)
            $this->requestContext = $container->get('router.admin')->getContext();

        $this->retriever = new RewritingRetriever();
        $this->resolver = new RewritingResolver();
    }

    /**
     * Return this class instance, only once instanciated.
     *
     * @throws \RuntimeException if the class has not been instanciated.
     * @return \Thelia\Tools\URL the instance.
     */
    public static function getInstance()
    {
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
        if ($host = $this->requestContext->getHost()) {

            $scheme = $this->requestContext->getScheme();

            $port = '';

            if ('http' === $scheme && 80 != $this->requestContext->getHttpPort()) {
                $port = ':'.$this->requestContext->getHttpPort();
            } elseif ('https' === $scheme && 443 != $this->requestContext->getHttpsPort()) {
                $port = ':'.$this->requestContext->getHttpsPort();
            }

            $schemeAuthority = "$scheme://$host"."$port";
        }

        return $schemeAuthority.$this->requestContext->getBaseUrl();
    }

    /**
     * @return string the index page, which is in fact the base URL.
     */
    public function getIndexPage()
    {
        // The index page is the base URL :)
        return $this->getBaseUrl();
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

            $base_url = $this->getBaseUrl();

            // TODO fix this ugly patch
            if (strpos($path, "index_dev.php")) {
                $path = str_replace('index_dev.php', '', $path);
            }

            // If only a path is requested, be sure to remove the script name (index.php or index_dev.php), if any.
            if ($path_only == self::PATH_TO_FILE) {

                // As the base_url always ends with '/', if we don't find / at the end, we have a script.
                if (substr($base_url, -3) == 'php') $base_url = dirname($base_url);
            }

            // Normalize the given path
            $base = rtrim($base_url, '/') . '/' . ltrim($path, '/');
        } else
            $base = $path;

        $queryString = '';

        if (! is_null($parameters)) {
            foreach ($parameters as $name => $value) {

                // Remove this parameter from base URL to prevent duplicate parameters
                $base = preg_replace('/([?&])'.$name.'=([^&])*(&|$)/', '$1', $base);

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
         if (ConfigQuery::isRewritingEnable()) {
             URL::getInstance()->absoluteUrl($this->retriever->loadViewUrl($view, $viewLocale, $viewId));
         } else {
             $allParametersWithoutView = array();
             $allParametersWithoutView['locale'] = $viewLocale;
             if (null !== $viewId) {
                 $allParametersWithoutView[$view . '_id'] = $viewId;
             }
             $this->retriever->rewrittenUrl = null;
             $this->retriever->url = URL::getInstance()->viewUrl($view, $allParametersWithoutView);
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
         if (ConfigQuery::isRewritingEnable()) {
             $view = $request->attributes->get('_view', null);
             $viewLocale = $request->query->get('locale', null);
             $viewId = $view === null ? null : $request->query->get($view . '_id', null);

             $allOtherParameters = $request->query->all();
             if ($view !== null) {
                 unset($allOtherParameters['view']);
                 if ($viewId !== null) {
                     unset($allOtherParameters[$view . '_id']);
                 }
             }
             if ($viewLocale !== null) {
                 unset($allOtherParameters['locale']);
             }

             $this->retriever->loadSpecificUrl($view, $viewLocale, $viewId, $allOtherParameters);
         } else {
             $allParametersWithoutView = $request->query->all();
             $view = $request->attributes->get('_view');
             if (isset($allOtherParameters['view'])) {
                 unset($allOtherParameters['view']);
             }
             $this->retriever->rewrittenUrl = null;
             $this->retriever->url = URL::getInstance()->viewUrl($view, $allParametersWithoutView);
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

     protected function sanitize($string, $force_lowercase = true, $alphabetic_only = false)
     {
         static $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                 "}", "\\", "|", ";", ":", "\"", "'", "&#8216;", "&#8217;", "&#8220;", "&#8221;", "&#8211;", "&#8212;",
                 "â€”", "â€“", ",", "<", ".", ">", "/", "?");

         $clean = trim(str_replace($strip, "", strip_tags($string)));

         $clean = preg_replace('/\s+/', "-", $clean);

         $clean = ($alphabetic_only) ? preg_replace("/[^a-zA-Z0-9]/", "", $clean) : $clean ;

         return ($force_lowercase) ?
             (function_exists('mb_strtolower')) ?
                 mb_strtolower($clean, 'UTF-8') :
             strtolower($clean) :
             $clean;
     }

    /**
      * Genenerate the file part of a rewritten URL from a given baseString, using a view, a view id and a locale
      *
      * @param $view
      * @param $viewId
      * @param $viewLocale
      * @param $baseString the string to be converted in a valid URL
      *
      * @return A valid file part URL.
      */
     public function generateRewritenUrl($view, $viewId, $viewLocale, $baseString)
     {
         // Borrowed from http://stackoverflow.com/questions/2668854/sanitizing-strings-to-make-them-url-and-filename-safe

         // Replace all weird characters with dashes
         $string = preg_replace('/[^\w\-~_\.]+/u', '-', $baseString);

         // Only allow one dash separator at a time (and make string lowercase)
         $cleanString = mb_strtolower(preg_replace('/--+/u', '-', $string), 'UTF-8');

         $urlFilePart = $cleanString . ".html";

         // TODO :
         // check if URL url already exists, and add a numeric suffix, or the like
         // insert the URL in the rewriting table
     }
}
