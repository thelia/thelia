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
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\FeatureAv;
use Thelia\Model\FeatureAvQuery;
use Thelia\Model\FeatureProductQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 * FeatureAvailability loop
 *
 *
 * Class FeatureAvailability
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method int[] getFeature()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class FeatureAvailability extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('feature'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha-reverse', 'manual', 'manual_reverse'))
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
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha-reverse":
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case "manual":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var FeatureAv $featureAv */
        foreach ($loopResult->getResultDataCollection() as $featureAv) {
            $isFreeText = FeatureProductQuery::create()
                ->filterByFeatureId($featureAv->getFeatureId())
                ->filterByFeatureAvId($featureAv->getId())
                ->findOneByFreeTextValue(true);

            if ($isFreeText === null) {
                $loopResultRow = new LoopResultRow($featureAv);
                $loopResultRow->set("ID", $featureAv->getId())
                    ->set("IS_TRANSLATED", $featureAv->getVirtualColumn('IS_TRANSLATED'))
                    ->set("LOCALE", $this->locale)
                    ->set("FEATURE_ID", $featureAv->getFeatureId())
                    ->set("TITLE", $featureAv->getVirtualColumn('i18n_TITLE'))
                    ->set("CHAPO", $featureAv->getVirtualColumn('i18n_CHAPO'))
                    ->set("DESCRIPTION", $featureAv->getVirtualColumn('i18n_DESCRIPTION'))
                    ->set("POSTSCRIPTUM", $featureAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                    ->set("POSITION", $featureAv->getPosition());
                $this->addOutputFields($loopResultRow, $featureAv);

                $loopResult->addRow($loopResultRow);
            }
        }

        return $loopResult;
    }
}
