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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\ModuleHookQuery;
use Thelia\Model\ModuleQuery;
use Thelia\Module\BaseModule;
use Thelia\Type\AlphaNumStringListType;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Module loop.
 *
 * Class Module
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]       getId()
 * @method int         getProfile()
 * @method int[]       getArea()
 * @method string[]    getCode()
 * @method string[]    getModuleType()
 * @method string[]    getModuleCategory()
 * @method int[]       getExclude()
 * @method bool|string getActive()
 * @method string[]    getOrder()
 * @method bool|string getMandatory()
 * @method bool|string getHidden()
 */
class Module extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    public function __construct(
        protected RouterInterface $router,
    ) {
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntTypeArgument('profile'),
            Argument::createIntListTypeArgument('area'),
            new Argument(
                'code',
                new TypeCollection(
                    new AlphaNumStringListType()
                )
            ),
            new Argument(
                'module_type',
                new TypeCollection(
                    new EnumListType([
                        BaseModule::CLASSIC_MODULE_TYPE,
                        BaseModule::DELIVERY_MODULE_TYPE,
                        BaseModule::PAYMENT_MODULE_TYPE,
                    ])
                )
            ),
            new Argument(
                'module_category',
                new TypeCollection(
                    new EnumListType(explode(',', BaseModule::MODULE_CATEGORIES))
                )
            ),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType([
                        'id',
                        'id_reverse',
                        'code',
                        'code_reverse',
                        'title',
                        'title_reverse',
                        'type',
                        'type_reverse',
                        'manual',
                        'manual_reverse',
                        'enabled',
                        'enabled_reverse',
                    ])
                ),
                'manual'
            ),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanOrBothTypeArgument('active', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('hidden', BooleanOrBothType::ANY),
            Argument::createBooleanOrBothTypeArgument('mandatory', BooleanOrBothType::ANY)
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

        $area = $this->getArea();

        if (null !== $area) {
            $search
                ->useAreaDeliveryModuleQuery()
                ->filterByAreaId($area, Criteria::IN)
                ->endUse();
        }

        $code = $this->getCode();

        if (null !== $code) {
            $search->filterByCode($code, Criteria::IN);
        }

        $moduleType = $this->getModuleType();

        if (null !== $moduleType) {
            $search->filterByType($moduleType, Criteria::IN);
        }

        $moduleCategory = $this->getModuleCategory();

        if (null !== $moduleCategory) {
            $search->filterByCategory($moduleCategory, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $active = $this->getActive();

        if ($active !== BooleanOrBothType::ANY) {
            $search->filterByActivate($active ? 1 : 0, Criteria::EQUAL);
        }

        $hidden = $this->getHidden();

        if ($hidden !== BooleanOrBothType::ANY) {
            $search->filterByHidden($hidden ? 1 : 0, Criteria::EQUAL);
        }

        $mandatory = $this->getMandatory();

        if ($mandatory !== BooleanOrBothType::ANY) {
            $search->filterByMandatory($mandatory ? 1 : 0, Criteria::EQUAL);
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
                case 'title':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'title_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'code':
                    $search->orderByCode(Criteria::ASC);
                    break;
                case 'code_reverse':
                    $search->orderByCode(Criteria::DESC);
                    break;
                case 'type':
                    $search->orderByType(Criteria::ASC);
                    break;
                case 'type_reverse':
                    $search->orderByType(Criteria::DESC);
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
                case 'enabled':
                    $search->orderByActivate(Criteria::ASC);
                    break;
                case 'enabled_reverse':
                    $search->orderByActivate(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \Thelia\Model\Module $module */
        foreach ($loopResult->getResultDataCollection() as $module) {
            try {
                new \ReflectionClass($module->getFullNamespace());

                $exists = true;
            } catch (\ReflectionException) {
                $exists = false;
            }

            if ($exists || $this->getBackendContext()) {
                $loopResultRow = new LoopResultRow($module);

                $loopResultRow
                    ->set('ID', $module->getId())
                    ->set('IS_TRANSLATED', $module->getVirtualColumn('IS_TRANSLATED'))
                    ->set('LOCALE', $this->locale)
                    ->set('TITLE', $module->getVirtualColumn('i18n_TITLE'))
                    ->set('CHAPO', $module->getVirtualColumn('i18n_CHAPO'))
                    ->set('DESCRIPTION', $module->getVirtualColumn('i18n_DESCRIPTION'))
                    ->set('POSTSCRIPTUM', $module->getVirtualColumn('i18n_POSTSCRIPTUM'))
                    ->set('CODE', $module->getCode())
                    ->set('TYPE', $module->getType())
                    ->set('CATEGORY', $module->getCategory())
                    ->set('ACTIVE', $module->getActivate())
                    ->set('VERSION', $module->getVersion())
                    ->set('CLASS', $module->getFullNamespace())
                    ->set('POSITION', $module->getPosition())
                    ->set('MANDATORY', $module->getMandatory())
                    ->set('HIDDEN', $module->getHidden())
                    ->set('EXISTS', $exists);

                $loopResultRow->set('CONFIGURABLE', $this->moduleHasConfigurationInterface($module));

                // Does module have hook(s)
                $hookable = false;
                $moduleHookCount = ModuleHookQuery::create()
                    ->filterByModuleId($module->getId())
                    ->count()
                ;
                $hookable = ($moduleHookCount > 0);

                $loopResultRow->set('HOOKABLE', $hookable ? 1 : 0);

                if (null !== $this->getProfile()) {
                    $accessValue = $module->getVirtualColumn('access');
                    $manager = new AccessManager($accessValue);

                    $loopResultRow->set('VIEWABLE', $manager->can(AccessManager::VIEW) ? 1 : 0)
                        ->set('CREATABLE', $manager->can(AccessManager::CREATE) ? 1 : 0)
                        ->set('UPDATABLE', $manager->can(AccessManager::UPDATE) ? 1 : 0)
                        ->set('DELETABLE', $manager->can(AccessManager::DELETE) ? 1 : 0);
                }

                $this->addOutputFields($loopResultRow, $module);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }

    private function moduleHasConfigurationInterface(\Thelia\Model\Module $module)
    {
        if (!$module->getActivate()) {
            return false;
        }

        // test if a hook
        $hookConfiguration = ModuleHookQuery::create()
            ->filterByModuleId($module->getId())
            ->filterByActive(true)
            ->useHookQuery()
            ->filterByCode('module.configuration')
            ->filterByType(TemplateDefinition::BACK_OFFICE)
            ->endUse()
            ->findOne();

        if (null !== $hookConfiguration) {
            return true;
        }

        $routerId = 'router.'.$module->getBaseDir();
        if ($this->container->has($routerId)) {
            try {
                if ($this->container->get($routerId)->match('/admin/module/'.$module->getCode())) {
                    return true;
                }
            } catch (ResourceNotFoundException) {
                /* Keep searching */
            }
        }

        try {
            if ($this->router->match('/admin/module/'.$module->getCode())) {
                return true;
            }
        } catch (\Exception) {
            /* Keep searching */
        }

        try {
            if ($this->router->match('/admin/module/'.$module->getCode())) {
                return true;
            }
        } catch (\Exception) {
            /* Keep searching */
        }

        // Make a quick and dirty test on the module's config.xml file
        $configContent = @file_get_contents($module->getAbsoluteConfigPath().DS.'config.xml');
        if ($configContent && preg_match('/event\s*=\s*[\'"]module.configuration[\'"]/', $configContent) === 1) {
            return true;
        }

        $routing = @file_get_contents($module->getAbsoluteConfigPath().DS.'routing.xml');
        if ($routing && preg_match('@[\'"]/?admin/module/'.$module->getCode().'[\'"]@', $routing)) {
            return true;
        }

        /* if not ; test if it uses admin inclusion : module_configuration.html */
        return file_exists($module->getAbsoluteAdminIncludesPath().DS.'module_configuration.html');
    }
}
