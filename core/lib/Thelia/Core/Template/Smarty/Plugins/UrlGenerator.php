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

namespace Thelia\Core\Template\Smarty\Plugins;

use Thelia\Core\Template\Smarty\SmartyPluginDescriptor;
use Thelia\Core\Template\Smarty\AbstractSmartyPlugin;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;

class UrlGenerator extends AbstractSmartyPlugin
{
    protected $request;

    protected $tokenProvider;

    public function __construct(Request $request, TokenProvider $tokenProvider)
    {
        $this->request = $request;
        $this->tokenProvider = $tokenProvider;
    }

    /**
     * Process url generator function
     *
     * @param  array   $params
     * @param  unknown $smarty
     * @return string  no text is returned.
     */
    public function generateUrlFunction($params, &$smarty)
    {
        // the path to process
        $path  = $this->getParam($params, 'path', null);
        $file  = $this->getParam($params, 'file', null); // Do not invoke index.php in URL (get a static file in web space
        $noamp = $this->getParam($params, 'noamp', null); // Do not change & in &amp;

        if ($file !== null) {
            $path = $file;
            $mode = URL::PATH_TO_FILE;
        } elseif ($path !== null) {
            $mode = URL::WITH_INDEX_PAGE;
        } else {
            throw new \InvalidArgumentException(Translator::getInstance()->trans("Please specify either 'path' or 'file' parameter in {url} function."));
        }

        $target = $this->getParam($params, 'target', null);

        $url = URL::getInstance()->absoluteUrl(
                $path,
                $this->getArgsFromParam($params, array('noamp', 'path', 'file', 'target')),
                $mode
        );

        if ($noamp == null) $url = str_replace('&', '&amp;', $url);

        if ($target != null) $url .= '#'.$target;
        return $url;
     }

     /**
      * Process view url generator function
      *
      * @param  array $params
      * @param  unknown $smarty
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
      * @param  unknown $smarty
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

         return $this->$toMethod();
     }

     protected function generateViewUrlFunction($params, $forAdmin)
     {
         // the view name (without .html)
         $view = $this->getParam($params,'view');

          // the related action (optionale)
         $action = $this->getParam($params, 'action');

         $args = $this->getArgsFromParam($params, array('view', 'action', 'target'));

         if (! empty($action)) $args['action'] = $action;
         return $forAdmin ? URL::getInstance()->adminViewUrl($view, $args) : URL::getInstance()->viewUrl($view, $args);
     }

     /**
      * Get URL parameters array from parameters.
      *
      * @param array $params Smarty function params
      * @return array the parameters array (either emply, of valued)
      */
     private function getArgsFromParam($params, $exclude = array())
     {
         $pairs = array();

           foreach ($params as $name => $value) {

               if (in_array($name, $exclude)) continue;

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

        return $newUrl;
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
            $this->request->attributes->set('_previous_url', 'dont-save');
        } else {
            $this->request->attributes->set('_previous_url', $this->generateUrlFunction($params, $smarty));
        }
    }

    /**
     * Define the various smarty plugins handled by this class
     *
     * @return an array of smarty plugin descriptors
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
            throw new \InvalidArgumentException(sprintf("Incorrect value `%s` for parameter `to` in `navigate` substitution.", $to));
        }

        return $navigateToValues[$to];
    }

    protected function getCurrentUrl()
    {
        return $this->request->getUri();
    }

    protected function getPreviousUrl()
    {
        return URL::getInstance()->absoluteUrl($this->request->getSession()->getReturnToUrl());
    }

    protected function getIndexUrl()
    {
        return URL::getInstance()->getIndexPage();
    }
}
