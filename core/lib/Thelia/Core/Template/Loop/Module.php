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

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

use Thelia\Model\ModuleQuery;

use Thelia\Module\BaseModule;
use Thelia\Type;

/**
 *
 * Module loop
 *
 *
 * Class Module
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class Module extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('profile'),
            new Argument(
                'code',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            new Argument(
                'module_type',
                new Type\TypeCollection(
                    new Type\EnumListType(array(
                        BaseModule::CLASSIC_MODULE_TYPE,
                        BaseModule::DELIVERY_MODULE_TYPE,
                        BaseModule::PAYMENT_MODULE_TYPE,
                    ))
                )
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', Type\BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = ModuleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $profile = $this->getProfile();

        if (null !== $profile) {
            $search->leftJoinProfileModule('profile_module')
                ->addJoinCondition('profile_module', 'profile_module.PROFILE_ID=?', $profile, null, \PDO::PARAM_INT)
                ->withColumn('profile_module.access', 'access');
        }

        $code = $this->getCode();

        if (null !== $code) {
            $search->filterByCode($code, Criteria::IN);
        }

        $moduleType = $this->getModule_type();

        if (null !== $moduleType) {
            $search->filterByType($moduleType, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();

        if ($active !== Type\BooleanOrBothType::ANY) {
            $search->filterByActivate($active ? 1 : 0, Criteria::EQUAL);
        }

        $search->orderByPosition();

        return $search;

    }

    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $module) {
            $loopResultRow = new LoopResultRow($module);
            $loopResultRow->set("ID", $module->getId())
                ->set("IS_TRANSLATED",$module->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE",$this->locale)
                ->set("TITLE",$module->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $module->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $module->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $module->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("CODE", $module->getCode())
                ->set("TYPE", $module->getType())
                ->set("ACTIVE", $module->getActivate())
                ->set("CLASS", $module->getFullNamespace())
                ->set("POSITION", $module->getPosition());

            if (null !== $this->getProfile()) {
                $accessValue = $module->getVirtualColumn('access');
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
