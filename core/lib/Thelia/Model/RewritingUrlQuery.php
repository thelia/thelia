<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Model;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Base\RewritingUrlQuery as BaseRewritingUrlQuery;
use Thelia\Model\Map\RewritingUrlTableMap;

/**
 * Skeleton subclass for performing query and update operations on the 'rewriting_url' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class RewritingUrlQuery extends BaseRewritingUrlQuery
{
    public function getResolverSearch($rewrittenUrl): ?RewritingArgument
    {
        $redirectedJoin = new Join();
        $redirectedJoin->addExplicitCondition(
            RewritingUrlTableMap::TABLE_NAME,
            'REDIRECTED',
            'ru',
            RewritingUrlTableMap::TABLE_NAME,
            'ID',
            'is_redirected',
        );
        $redirectedJoin->setJoinType(Criteria::LEFT_JOIN);

        return RewritingArgumentQuery::create()
            ->joinRewritingUrl('ru', Criteria::RIGHT_JOIN)
            ->addJoinObject($redirectedJoin)
            ->where('`ru`.URL = ?', $rewrittenUrl, \PDO::PARAM_STR)
            ->withColumn('`ru`.URL', 'ru_url')
            ->withColumn('`ru`.VIEW', 'ru_view')
            ->withColumn('`ru`.VIEW_LOCALE', 'ru_locale')
            ->withColumn('`ru`.VIEW_ID', 'ru_viewId')
            ->withColumn('`is_redirected`.URL', 'ru_redirected_to_url')
            ->findOne();
    }

    public function getViewUrlQuery($view, $viewLocale, $viewId): ?RewritingUrl
    {
        return self::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            ->where('ISNULL(`ra`.REWRITING_URL_ID)')
            ->filterByView($view)
            ->filterByViewLocale($this->retrieveLocale($viewLocale))
            ->filterByViewId($viewId)
            ->filterByRedirected(null)
            ->orderById(Criteria::DESC)
            ->findOne();
    }

    public function getSpecificUrlQuery($view, $viewLocale, $viewId, $viewOtherParameters): ?RewritingUrl
    {
        $urlQuery = self::create()
            ->joinRewritingArgument('ra', Criteria::LEFT_JOIN)
            ->withColumn('`ra`.REWRITING_URL_ID', 'ra_REWRITING_URL_ID')
            ->filterByView($view)
            ->filterByViewLocale($this->retrieveLocale($viewLocale))
            ->filterByViewId($viewId)
            ->filterByRedirected(null)
            ->orderById(Criteria::DESC);

        $otherParametersCount = \count($viewOtherParameters);

        if ($otherParametersCount > 0) {
            $parameterConditions = [];

            foreach ($viewOtherParameters as $parameter => $value) {
                $conditionName = 'other_parameter_condition_'.\count($parameterConditions);
                $urlQuery->condition('parameter_condition', '`ra`.PARAMETER= ?', $parameter, \PDO::PARAM_STR)
                    ->condition('value_condition', '`ra`.VALUE = ?', $value, \PDO::PARAM_STR)
                    ->combine(['parameter_condition', 'value_condition'], Criteria::LOGICAL_AND, $conditionName);
                $parameterConditions[] = $conditionName;
            }

            $urlQuery->where($parameterConditions, Criteria::LOGICAL_OR);

            $urlQuery->groupBy(RewritingUrlTableMap::COL_ID);

            $urlQuery->condition('count_condition_1', 'COUNT('.RewritingUrlTableMap::COL_ID.') = ?', $otherParametersCount, \PDO::PARAM_INT) // ensure we got all the asked parameters (provided by the query)
                ->condition('count_condition_2', 'COUNT('.RewritingUrlTableMap::COL_ID.') = (SELECT COUNT(*) FROM rewriting_argument WHERE rewriting_argument.REWRITING_URL_ID = ra_REWRITING_URL_ID)'); // ensure we don't miss any parameters (needed to match the rewritten url)

            $urlQuery->having(['count_condition_1', 'count_condition_2'], Criteria::LOGICAL_AND);
        } else {
            $urlQuery->where('ISNULL(`ra`.REWRITING_URL_ID)');
        }

        return $urlQuery->findOne();
    }

    protected function retrieveLocale($viewLocale)
    {
        if (2 === \strlen((string) $viewLocale) && null !== $lang = LangQuery::create()->findOneByCode($viewLocale)) {
            $viewLocale = $lang->getLocale();
        }

        return $viewLocale;
    }
}

// RewritingUrlQuery
