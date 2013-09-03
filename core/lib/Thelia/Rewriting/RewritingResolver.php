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
use Thelia\Exception\RewritingUrlException;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\RewritingArgumentQuery;

/**
 * Class RewritingResolver
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to resolve rewritten URL as a query
 */
class RewritingResolver
{
    public $view;
    public $viewId;
    public $locale;
    public $otherParameters;

    public function __construct($url = null)
    {
        if($url !== null) {
            $this->load($url);
        }
    }

    public function load($rewrittenUrl)
    {
        $search = RewritingArgumentQuery::create()
            //->filterByUrl($rewrittenUrl)
            ->joinRewritingUrl('ru', Criteria::RIGHT_JOIN)
            ->where('`ru`.URL = ?', $rewrittenUrl, \PDO::PARAM_STR)
            ->withColumn('`ru`.URL', 'ru_url')
            ->withColumn('`ru`.VIEW', 'ru_view')
            ->withColumn('`ru`.VIEW_LOCALE', 'ru_locale')
            ->withColumn('`ru`.VIEW_ID', 'ru_viewId')
            ->find();

        if($search->count() == 0) {
            throw new UrlRewritingException('URL NOT FOUND', UrlRewritingException::URL_NOT_FOUND);
        }

        $otherParameters = array();
        foreach($search as $result) {
            $parameter = $result->getParameter();
            $value = $result->getValue();

            if(null !== $parameter) {
                $otherParameters[$parameter] = $value;
            }
        }

        $this->view = $search->getFirst()->getVirtualColumn('ru_view');
        $this->viewId = $search->getFirst()->getVirtualColumn('ru_viewId');
        $this->locale = $search->getFirst()->getVirtualColumn('ru_locale');
        $this->otherParameters = $otherParameters;
    }
}
