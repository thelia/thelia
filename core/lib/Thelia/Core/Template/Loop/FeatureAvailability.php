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
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * FeatureAvailability loop.
 *
 * Class FeatureAvailability
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[]    getId()
 * @method int[]    getFeature()
 * @method int[]    getExclude()
 * @method string[] getOrder()
 */
class FeatureAvailability extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('feature'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['id', 'id_reverse', 'alpha', 'alpha-reverse', 'alpha_reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = FeatureAvQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $exclude = $this->getExclude();

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        $feature = $this->getFeature();

        if (null !== $feature) {
            $search->filterByFeatureId($feature, Criteria::IN);
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
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha_reverse':
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'manual':
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case 'manual_reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        // Search only non-freetext feature values.
        $search
            ->useFeatureProductQuery()
                ->filterByIsFreeText(false)
                ->_or()
                ->filterByIsFreeText(null) // does not belong to any product
            ->endUse()
        ;

        // Joining with FeatureProduct may result in multiple occurences of the same FeatureAv. Juste get one.
        $search->distinct();

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var FeatureAv $featureAv */
        foreach ($loopResult->getResultDataCollection() as $featureAv) {
            $loopResultRow = new LoopResultRow($featureAv);
            $loopResultRow->set('ID', $featureAv->getId())
                ->set('IS_TRANSLATED', $featureAv->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('FEATURE_ID', $featureAv->getFeatureId())
                ->set('TITLE', $featureAv->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $featureAv->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $featureAv->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $featureAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('POSITION', $featureAv->getPosition());
            $this->addOutputFields($loopResultRow, $featureAv);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
