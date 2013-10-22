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
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Exception\RewritingUrlException;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\RewritingUrlQuery;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Class RewritingResolver
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to resolve rewritten URL as a query
 */
class RewritingResolver
{
    protected $search = null;
    protected $rewritingUrlQuery = null;

    public $view;
    public $viewId;
    public $locale;
    public $otherParameters;
    public $redirectedToUrl;

    public function __construct($url = null)
    {
        $this->rewritingUrlQuery = new RewritingUrlQuery();

        if($url !== null) {
            $this->load($url);
        }
    }

    public function load($rewrittenUrl)
    {
        $rewrittenUrl = ltrim($rewrittenUrl, '/');
        $rewrittenUrl = urldecode($rewrittenUrl);
        $this->search = $this->rewritingUrlQuery->getResolverSearch($rewrittenUrl);

        if($this->search->count() == 0) {
            throw new UrlRewritingException('URL NOT FOUND', UrlRewritingException::URL_NOT_FOUND);
        }

        $this->view = $this->search->getFirst()->getVirtualColumn('ru_view');
        $this->viewId = $this->search->getFirst()->getVirtualColumn('ru_viewId');
        $this->locale = $this->search->getFirst()->getVirtualColumn('ru_locale');
        $this->redirectedToUrl = $this->search->getFirst()->getVirtualColumn('ru_redirected_to_url');

        $this->otherParameters = $this->getOtherParameters();
    }

    protected function getOtherParameters()
    {
        if($this->search === null) {
            throw new UrlRewritingException('RESOLVER NULL SEARCH', UrlRewritingException::RESOLVER_NULL_SEARCH);
        }

        $otherParameters = array();
        foreach($this->search as $result) {
            $parameter = $result->getParameter();
            $value = $result->getValue();

            if(null !== $parameter) {
                $otherParameters[$parameter] = $value;
            }
        }

        return $otherParameters;
    }


}
