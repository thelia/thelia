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
use Thelia\Model\FeatureProductQuery;
use Thelia\Model\Map\FeatureAvTableMap;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 *
 * FeatureValue loop
 *
 *
 * Class FeatureValue
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class FeatureValue extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('feature', null, true),
            Argument::createIntTypeArgument('product'),
            Argument::createIntListTypeArgument('feature_availability'),
            Argument::createAnyListTypeArgument('free_text'),
            Argument::createBooleanTypeArgument('exclude_feature_availability', 0),
            Argument::createBooleanTypeArgument('exclude_free_text', 0),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            ),
            Argument::createBooleanTypeArgument('force_return', true)
        );
    }

    public function buildModelCriteria()
    {
        $search = FeatureProductQuery::create();

         // manage featureAv translations
        $this->configureI18nProcessing(
            $search,
            array('TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM'),
            FeatureAvTableMap::TABLE_NAME,
            'FEATURE_AV_ID',
            true
        );

        $search
            ->useFeatureAvQuery('feature_av')
                ->withColumn(FeatureAvTableMap::POSITION, 'feature_av_position')
            ->endUse();

        $feature = $this->getFeature();

        $search->filterByFeatureId($feature, Criteria::EQUAL);

        if (null !== $product = $this->getProduct()) {
            $search->filterByProductId($product, Criteria::EQUAL);
        }

        if (null !== $featureAvailability = $this->getFeature_availability()) {
            $search->filterByFeatureAvId($featureAvailability, Criteria::IN);
        }

        if (null !== $freeText = $this->getFreeText()) {
            $search->filterByFreeTextValue($featureAvailability, Criteria::IN);
        }

        if (true === $excludeFeatureAvailability = $this->getExclude_feature_availability()) {
            $search->filterByFeatureAvId(null, Criteria::ISNULL);
        }

        if (true === $excludeFreeText = $this->getExcludeFreeText()) {
            $search->filterByFreeTextValue(null, Criteria::ISNULL);
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case "alpha":
                    $search->addAscendingOrderByColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
                case "alpha_reverse":
                    $search->addDescendingOrderByColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_TITLE');
                    break;
                case "manual":
                    $search->orderBy('feature_av_position', Criteria::ASC);
                    break;
                case "manual_reverse":
                    $search->orderBy('feature_av_position', Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\FeatureProduct $featureValue */
        foreach ($loopResult->getResultDataCollection() as $featureValue) {
            $loopResultRow = new LoopResultRow($featureValue);

            $loopResultRow
                ->set("ID", $featureValue->getId())
                ->set("PRODUCT", $featureValue->getProductId())
                ->set("PRODUCT_ID", $featureValue->getProductId())
                ->set("FEATURE_AV_ID", $featureValue->getFeatureAvId())
                ->set("FREE_TEXT_VALUE", $featureValue->getFreeTextValue())
                ->set("IS_FREE_TEXT", is_null($featureValue->getFeatureAvId()) ? 1 : 0)
                ->set("IS_FEATURE_AV", is_null($featureValue->getFeatureAvId()) ? 0 : 1)
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_TITLE'))
                ->set("CHAPO", $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_CHAPO'))
                ->set("DESCRIPTION", $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $featureValue->getVirtualColumn(FeatureAvTableMap::TABLE_NAME . '_i18n_POSTSCRIPTUM'))
                ->set("POSITION", $featureValue->getPosition())
            ;
            $this->addOutputFields($loopResultRow, $featureValue);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
