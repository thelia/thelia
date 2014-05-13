<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;

use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;

//use Thelia\Module\BaseModule;
use Thelia\Model\ModuleHookQuery;
use Thelia\Type;
use Thelia\Type\TypeCollection;

/**
 *
 * Class ModuleHook
 * @package Thelia\Controller\Admin
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 */
class ModuleHook extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = false;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            new Argument(
                'hook',
                new Type\TypeCollection(
                    new Type\AlphaNumStringListType()
                )
            ),
            Argument::createIntTypeArgument('module'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'code', 'code_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse', 'enabled', 'enabled_reverse'))
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', Type\BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('module_active', Type\BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = ModuleHookQuery::create();

        $id = $this->getId();
        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $hook = $this->getHook();
        if (null !== $hook) {
            $search->filterByEvent($hook, Criteria::IN);
        }

        $exclude = $this->getExclude();
        if (!is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();
        if ($active !== Type\BooleanOrBothType::ANY) {
            $search->filterByActive($active, Criteria::EQUAL);
        }

        $moduleActive = $this->getModule_active();
        if ($moduleActive !== Type\BooleanOrBothType::ANY) {
            $search->filterByModuleActive($moduleActive, Criteria::EQUAL);
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
        /** @var \Thelia\Model\ModuleHook $moduleHook */
        foreach ($loopResult->getResultDataCollection() as $moduleHook) {

            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($moduleHook);

                $loopResultRow
                    ->set("ID"           , $moduleHook->getId())
                    //->set("IS_TRANSLATED", $moduleHook->getVirtualColumn('IS_TRANSLATED'))
                    ->set("LOCALE"       , $this->locale)
                    ->set("MODULE_ID"    , $moduleHook->getModuleId())
                    ->set("MODULE_TITLE" , $moduleHook->getModule()->getTitle())
                    ->set("HOOK"         , $moduleHook->getEvent())
                    ->set("CLASSNAME"    , $moduleHook->getClassname())
                    ->set("ACTIVE"       , $moduleHook->getActive())
                    ->set("MODULE_ACTIVE", $moduleHook->getModuleActive())
                    ->set("POSITION"     , $moduleHook->getPosition())
                ;

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;

    }
}
