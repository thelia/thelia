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
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Class RewritingRetriever
 * @package Thelia\Rewriting
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * This class provides methods to retrieve a rewritten URL from a query
 */
class RewritingRetriever
{
    public function getViewUrl($view, $viewLocale, $viewId)
    {
        $url = $this->getViewUrlQuery($view, $viewId, $viewLocale);

        return $url === null ? null : $url->getUrl();
    }

    protected function getViewUrlQuery($view, $viewId, $viewLocale)
    {
        return RewritingUrlQuery::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            ->where('ISNULL(`ra`.REWRITING_URL_ID)')
            ->filterByView($view)
            ->filterByViewLocale($viewLocale)
            ->filterByViewId($viewId)
            ->filterByRedirected(null)
            ->orderByUpdatedAt(Criteria::DESC)
            ->findOne();
    }

    public function getSpecificUrl($view, $viewLocale, $viewId = null, $viewOtherParameters = array())
    {
        $urlQuery = RewritingUrlQuery::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            //->withColumn('`ra`.PARAMETER', 'ra_parameter')
            //->withColumn('`ra`.VALUE', 'ra_value')
            ->withColumn('`ra`.REWRITING_URL_ID', 'ra_REWRITING_URL_ID')
            ->filterByView($view)
            ->filterByViewLocale($viewLocale)
            ->filterByViewId($viewId)
            ->filterByRedirected(null)
            ->orderByUpdatedAt(Criteria::DESC);

        $otherParametersCount = count($viewOtherParameters);
        if($otherParametersCount > 0) {
            $parameterConditions = array();

            foreach($viewOtherParameters as $parameter => $value) {
                $conditionName = 'other_parameter_condition_' . count($parameterConditions);
                $urlQuery->condition('parameter_condition', '`ra`.PARAMETER= ?', $parameter, \PDO::PARAM_STR)
                    ->condition('value_condition', '`ra`.VALUE = ?', $value, \PDO::PARAM_STR)
                    ->combine(array('parameter_condition', 'value_condition'), Criteria::LOGICAL_AND, $conditionName);
                $parameterConditions[] = $conditionName;
            }

            $urlQuery->where($parameterConditions, Criteria::LOGICAL_OR);

            $urlQuery->groupBy(RewritingUrlTableMap::ID);

            $urlQuery->condition('count_condition_1', 'COUNT(' . RewritingUrlTableMap::ID . ') = ?', $otherParametersCount, \PDO::PARAM_INT) // ensure we got all the asked parameters (provided by the query)
                ->condition('count_condition_2', 'COUNT(' . RewritingUrlTableMap::ID . ') = (SELECT COUNT(*) FROM rewriting_argument WHERE rewriting_argument.REWRITING_URL_ID = ra_REWRITING_URL_ID)'); // ensure we don't miss any parameters (needed to match the rewritten url)

            $urlQuery->having(array('count_condition_1', 'count_condition_2'), Criteria::LOGICAL_AND);
        } else {
            $urlQuery->where('ISNULL(`ra`.REWRITING_URL_ID)');
        }

        $url = $urlQuery->findOne();

        return $url === null ? null : $url->getUrl();
    }
}
