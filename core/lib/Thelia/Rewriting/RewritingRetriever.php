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

namespace Thelia\Rewriting;

use Thelia\Model\RewritingUrlQuery;
use Thelia\Tools\URL;

/**
 * Class RewritingRetriever
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to retrieve a rewritten URL from a query
 */
class RewritingRetriever
{
    protected $search = null;
    protected $rewritingUrlQuery = null;

    public $url;
    public $rewrittenUrl;

    public function __construct($view = null, $viewLocale = null, $viewId = null)
    {
        $this->rewritingUrlQuery = new RewritingUrlQuery();

        if ($view !== null && $viewLocale !== null) {
            $this->load($view, $viewLocale, $viewId);
        }
    }

    /**
     * @param      $view
     * @param      $viewLocale
     * @param null $viewId
     */
    public function loadViewUrl($view, $viewLocale = null, $viewId = null)
    {
        $this->search = $this->rewritingUrlQuery->getViewUrlQuery($view, $viewLocale, $viewId);

        $allParametersWithoutView = array();
        if (null !== $viewLocale) {
            $allParametersWithoutView['lang'] = $viewLocale;
        }
        if (null !== $viewId) {
            $allParametersWithoutView[$view . '_id'] = $viewId;
        }

        $this->rewrittenUrl = null;
        $this->url = URL::getInstance()->viewUrl($view, $allParametersWithoutView);
        if ($this->search !== null) {
            $this->rewrittenUrl = URL::getInstance()->absoluteUrl(
                $this->search->getUrl()
            );
        }
    }

    /**
     * @param       $view
     * @param       $viewLocale
     * @param null  $viewId
     * @param array $viewOtherParameters
     */
    public function loadSpecificUrl($view, $viewLocale, $viewId = null, $viewOtherParameters = array())
    {
        if (empty($viewOtherParameters)) {
            $this->loadViewUrl($view, $viewLocale, $viewId);

            return;
        }

        $this->search = $this->rewritingUrlQuery->getSpecificUrlQuery($view, $viewLocale, $viewId, $viewOtherParameters);

        $allParametersWithoutView = $viewOtherParameters;
        $allParametersWithoutView['lang'] = $viewLocale;
        if (null !== $viewId) {
            $allParametersWithoutView[$view . '_id'] = $viewId;
        }

        $this->rewrittenUrl = null;
        $this->url = URL::getInstance()->viewUrl($view, $allParametersWithoutView);
        if ($this->search !== null) {
            $this->rewrittenUrl = $this->search->getUrl();
        }
    }

    /**
     * @return mixed
     */
    public function toString()
    {
        return $this->rewrittenUrl === null ? $this->url : $this->rewrittenUrl;
    }
}
