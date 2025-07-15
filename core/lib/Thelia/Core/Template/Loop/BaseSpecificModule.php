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
use Propel\Runtime\ActiveQuery\ModelCriteria;
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

    protected function getArgDefinitions(): ArgumentCollection
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
                        ],
                    ),
                ),
                'manual',
            ),
        );
    }

    public function buildModelCriteria(): ModelCriteria
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

        match ($order) {
            'id' => $search->orderById(Criteria::ASC),
            'id_reverse' => $search->orderById(Criteria::DESC),
            'alpha' => $search->addAscendingOrderByColumn('i18n_TITLE'),
            'alpha_reverse' => $search->addDescendingOrderByColumn('i18n_TITLE'),
            'manual_reverse' => $search->orderByPosition(Criteria::DESC),
            default => $search->orderByPosition(Criteria::ASC),
        };

        return $search;
    }

    abstract protected function getModuleType();
}
