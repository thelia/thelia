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

namespace Thelia\Core\Template\Loop;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ModuleHookQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ModuleHook.
 *
 * @author Julien Chanséaume <jchanseaume@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getHook()
 * @method int         getModule()
 * @method int[]       getExclude()
 * @method bool|string getModuleActive()
 * @method bool|string getHookActive()
 * @method bool|string getActive()
 * @method string[]    getOrder()
 */
class ModuleHook extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = false;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('hook'),
            Argument::createIntTypeArgument('module'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['id', 'id_reverse', 'hook', 'hook_reverse', 'manual', 'manual_reverse', 'enabled', 'enabled_reverse'])
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('hook_active', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('module_active', BooleanOrBothType::ANY)
        );
    }

    public function buildModelCriteria()
    {
        $search = ModuleHookQuery::create();

        $this->configureI18nProcessing($search, []);

        $id = $this->getId();
        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $hook = $this->getHook();
        if (null !== $hook) {
            $search->filterByHookId($hook, Criteria::EQUAL);
        }

        $module = $this->getModule();
        if (null !== $module) {
            $search->filterByModuleId($module, Criteria::EQUAL);
        }

        $exclude = $this->getExclude();
        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();
        if ($active !== BooleanOrBothType::ANY) {
            $search->filterByActive($active, Criteria::EQUAL);
        }

        $hookActive = $this->getHookActive();
        if ($hookActive !== BooleanOrBothType::ANY) {
            $search->filterByHookActive($hookActive, Criteria::EQUAL);
        }

        $moduleActive = $this->getModuleActive();
        if ($moduleActive !== BooleanOrBothType::ANY) {
            $search->filterByModuleActive($moduleActive, Criteria::EQUAL);
        }

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case 'hook':
                    $search->orderByHookId(Criteria::ASC);
                    break;
                case 'hook_reverse':
                    $search->orderByHookId(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'enabled':
                    $search->orderByActive(Criteria::ASC);
                    break;
                case 'enabled_reverse':
                    $search->orderByActive(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\ModuleHook $moduleHook */
        foreach ($loopResult->getResultDataCollection() as $moduleHook) {
            if ($this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($moduleHook);

                $loopResultRow
                    ->set('ID', $moduleHook->getId())
                    ->set('HOOK_ID', $moduleHook->getHookId())
                    ->set('MODULE_ID', $moduleHook->getModuleId())
                    ->set('MODULE_TITLE', $moduleHook->getModule()->setLocale($this->locale)->getTitle())
                    ->set('MODULE_CODE', $moduleHook->getModule()->getCode())
                    ->set('CLASSNAME', $moduleHook->getClassname())
                    ->set('METHOD', $moduleHook->getMethod())
                    ->set('ACTIVE', $moduleHook->getActive())
                    ->set('HOOK_ACTIVE', $moduleHook->getHookActive())
                    ->set('MODULE_ACTIVE', $moduleHook->getModuleActive())
                    ->set('POSITION', $moduleHook->getPosition())
                    ->set('TEMPLATES', $moduleHook->getTemplates())
                ;

                $this->addOutputFields($loopResultRow, $moduleHook);
                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
