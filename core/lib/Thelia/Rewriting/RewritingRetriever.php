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
use Thelia\Model\Base\RewritingUrlQuery;

/**
 * Class RewritingRetriever
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to retrieve a rewritten URL from a query
 */
class RewritingRetriever
{
    public function getViewUrl($view, $viewId, $viewLocale)
    {
        $url = RewritingUrlQuery::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            ->where('ISNULL(`ra`.REWRITING_URL_ID)')
            ->filterByView($view)
            ->filterByViewId($viewId)
            ->filterByViewLocale($viewLocale)
            ->filterByRedirected(null)
            ->orderByUpdatedAt(Criteria::DESC)
            ->findOne();

        return $url === null ? null : $url->getUrl();
    }

    /*public function getSpecificUrl($view, $viewId, $viewLocale, $viewOtherParameters = array())
    {

    }*/
}
