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
namespace Thelia\Rewriting;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\Map\RewritingUrlTableMap;
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

        if($view !== null && $viewLocale !== null) {
            $this->load($view, $viewLocale, $viewId);
        }
    }

    /**
     * @param      $view
     * @param      $viewLocale
     * @param null $viewId
     */
    public function loadViewUrl($view, $viewLocale, $viewId = null)
    {
        $this->search = $this->rewritingUrlQuery->getViewUrlQuery($view, $viewLocale, $viewId);

        $allParametersWithoutView = array();
        $allParametersWithoutView['locale'] = $viewLocale;
        if(null !== $viewId) {
            $allParametersWithoutView[$view . '_id'] = $viewId;
        }

        $this->url = URL::viewUrl($view, $allParametersWithoutView);
        if($this->search !== null) {
            $this->rewrittenUrl = $this->search->getUrl();
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
        if(empty($viewOtherParameters)) {
            $this->loadViewUrl($view, $viewLocale, $viewId);
            return;
        }

        $this->search = $this->rewritingUrlQuery->getSpecificUrlQuery($view, $viewLocale, $viewId, $viewOtherParameters);

        $allParametersWithoutView = $viewOtherParameters;
        $allParametersWithoutView['locale'] = $viewLocale;
        if(null !== $viewId) {
            $allParametersWithoutView[$view . '_id'] = $viewId;
        }

        $this->url = URL::viewUrl($view, $allParametersWithoutView);
        if($this->search !== null) {
            $this->rewrittenUrl = $this->search->getUrl();
        }
    }
}
