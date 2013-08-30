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
    public function getViewUrl($view, $viewId, $viewLocale)
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
            ->filterByViewId($viewId)
            ->filterByViewLocale($viewLocale)
            ->filterByRedirected(null)
            ->orderByUpdatedAt(Criteria::DESC)
            ->findOne();
    }

    public function getSpecificUrl($view = null, $viewId = null, $viewLocale = null, $viewOtherParameters = array())
    {
        $urlQuery = RewritingUrlQuery::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            ->withColumn('`ra`.PARAMETER', 'ra_parameter')
            ->withColumn('`ra`.VALUE', 'ra_value')
            ->filterByView($view)
            ->filterByViewId($viewId)
            ->filterByViewLocale($viewLocale)
            ->filterByRedirected(null)
            ->orderByUpdatedAt(Criteria::DESC);

        $otherParametersCount = count($viewOtherParameters);
        if($otherParametersCount > 0) {
            $parameterConditions = array();
            foreach($viewOtherParameters as $parameter => $value) {
                $conditionName = 'other_parameter_condition_' . count($parameterConditions);
                $urlQuery->condition('parameter_condition', '`ra_parameter`= ?', $parameter, \PDO::PARAM_STR)
                    ->condition('value_condition', '`ra_value` = ?', $value, \PDO::PARAM_STR)
                    ->combine(array('parameter_condition', 'value_condition'), Criteria::LOGICAL_AND, $conditionName);
                $parameterConditions[] = $conditionName;
            }

            $urlQuery->combine($parameterConditions, Criteria::LOGICAL_OR, 'parameter_full_condition');

            $urlQuery->groupBy(RewritingUrlTableMap::ID);

            $urlQuery->condition('count_condition', 'COUNT(' . RewritingUrlTableMap::ID . ') = ?', $otherParametersCount, \PDO::PARAM_INT)
                ->combine(array('count_condition', 'parameter_full_condition'), Criteria::LOGICAL_AND, 'full_having_condition');


            $urlQuery
                ->having(array('full_having_condition'))
                //->having('COUNT(' . RewritingUrlTableMap::ID . ') = ?', $otherParametersCount, \PDO::PARAM_INT)
            ;
        } else {
            $urlQuery->where('ISNULL(`ra`.REWRITING_URL_ID)');
        }

        $url = $urlQuery->findOne();

        return $url === null ? null : $url->getUrl();
    }
}
