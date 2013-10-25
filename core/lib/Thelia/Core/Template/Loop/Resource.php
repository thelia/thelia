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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\ResourceQuery;
use Thelia\Type;

/**
 *
 * Resource loop
 *
 *
 * Class Resource
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Resource extends BaseI18nLoop
{
    public $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('profile'),
            new Argument(
                'code',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            )
        );
    }

    /**
     * @param $pagination
     *
     * @return \Thelia\Core\Template\Element\LoopResult
     */
    public function exec(&$pagination)
    {
        $search = ResourceQuery::create();

        /* manage translations */
        $locale = $this->configureI18nProcessing($search);

        $profile = $this->getProfile();

        if (null !== $profile) {
            $search->leftJoinProfileResource('profile_resource')
                ->addJoinCondition('profile_resource', 'profile_resource.PROFILE_ID=?', $profile, null, \PDO::PARAM_INT)
                ->withColumn('profile_resource.access', 'access');
        }

        $code = $this->getCode();

        if (null !== $code) {
            $search->filterByCode($code, Criteria::IN);
        }

        $search->orderById(Criteria::ASC);

        /* perform search */
        $resources = $this->search($search, $pagination);

        $loopResult = new LoopResult($resources);

        foreach ($resources as $resource) {
            $loopResultRow = new LoopResultRow($loopResult, $resource, $this->versionable, $this->timestampable, $this->countable);
            $loopResultRow->set("ID", $resource->getId())
                ->set("IS_TRANSLATED",$resource->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$locale)
                ->set("CODE",$resource->getCode())
                ->set("TITLE",$resource->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $resource->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $resource->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $resource->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;

            if (null !== $profile) {
                $accessValue = $resource->getVirtualColumn('access');
                $manager = new AccessManager($accessValue);

                $loopResultRow->set("VIEWABLE", $manager->can(AccessManager::VIEW)? 1 : 0)
                    ->set("CREATABLE", $manager->can(AccessManager::CREATE) ? 1 : 0)
                    ->set("UPDATABLE", $manager->can(AccessManager::UPDATE)? 1 : 0)
                    ->set("DELETABLE", $manager->can(AccessManager::DELETE)? 1 : 0);
            }

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
