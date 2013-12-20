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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
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
use Thelia\Type\TypeCollection;

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
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'code', 'code_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse', 'enabled', 'enabled_reverse'))
                ),
                'manual'
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

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "id":
                    $search->orderById(Criteria::ASC);
                    break;
                case "id_reverse":
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "code":
                    $search->orderByCode(Criteria::ASC);
                    break;
                case "code_reverse":
                    $search->orderByCode(Criteria::DESC);
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case "enabled":
                    $search->orderByActivate(Criteria::ASC);
                    break;
                case "enabled_reverse":
                    $search->orderByActivate(Criteria::DESC);
                    break;
             }
        }

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

            $hasConfigurationInterface = false;

            /* first test if module defines it's own config route */
            $routerId = "router." . $module->getBaseDir();
            if($this->container->has($routerId)) {
                try {
                    if($this->container->get($routerId)->match('/admin/module/' . $module->getCode())) {
                        $hasConfigurationInterface = true;
                    }
                } catch(ResourceNotFoundException $e) {
                    /* $hasConfigurationInterface stays false */
                }
            }

            /* if not ; test if it uses admin inclusion : module_configuration.html */
            if(false === $hasConfigurationInterface) {
                if(file_exists( sprintf("%s/AdminIncludes/%s.html", $module->getAbsoluteBaseDir(), "module_configuration"))) {
                    $hasConfigurationInterface = true;
                }
            }

            $loopResultRow->set("CONFIGURABLE", $hasConfigurationInterface ? 1 : 0);

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
