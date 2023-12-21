<?php

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
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ModuleQuery;
use Thelia\Type\EnumType;
use Thelia\Type\TypeCollection;

/**
 * @author Manuel Raynaud <manu@raynaud.io>
 *
 * @method int      getId()
 * @method int[]    getExclude()
 * @method string[] getExcludeCode()
 * @method string   getCode()
 * @method string[] getOrder()
 */
abstract class BaseSpecificModule extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createAnyListTypeArgument('exclude_code'),
            Argument::createAnyTypeArgument('code'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumType(
                        [
                            'id',
                            'id_reverse',
                            'alpha',
                            'alpha_reverse',
                            'manual',
                            'manual_reverse',
                        ]
                    )
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = ModuleQuery::create();

        $search->filterByActivate(1);

        if (null !== $id = $this->getId()) {
            $search->filterById($id);
        }

        if (null !== $exclude = $this->getExclude()) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (null !== $excludeCode = $this->getExcludeCode()) {
            $search->filterByCode($excludeCode, Criteria::NOT_IN);
        }

        if (null !== $code = $this->getCode()) {
            $search->filterByCode($code);
        }

        $this->configureI18nProcessing($search);

        $search->filterByType($this->getModuleType(), Criteria::EQUAL);

        $order = $this->getOrder();

        switch ($order) {
            case 'id':
                $search->orderById(Criteria::ASC);
                break;
            case 'id_reverse':
                $search->orderById(Criteria::DESC);
                break;
            case 'alpha':
                $search->addAscendingOrderByColumn('i18n_TITLE');
                break;
            case 'alpha_reverse':
                $search->addDescendingOrderByColumn('i18n_TITLE');
                break;
            case 'manual_reverse':
                $search->orderByPosition(Criteria::DESC);
                break;
            case 'manual':
            default:
                $search->orderByPosition(Criteria::ASC);
                break;
        }

        return $search;
    }

    abstract protected function getModuleType();
}
