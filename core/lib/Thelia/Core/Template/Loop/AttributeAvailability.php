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

use Thelia\Model\AttributeAvQuery;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 * AttributeAvailability loop
 *
 *
 * Class AttributeAvailability
 * @package Thelia\Core\Template\Loop
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 */
class AttributeAvailability extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('attribute'),
            Argument::createIntListTypeArgument('exclude'),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse'))
                ),
                'manual'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = AttributeAvQuery::create();

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

        $attribute = $this->getAttribute();

        if (null !== $attribute) {
            $search->filterByAttributeId($attribute, Criteria::IN);
        }

        $orders  = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById(Criteria::ASC);
                    break;
                case 'id_reverse':
                    $search->orderById(Criteria::DESC);
                    break;
                case "alpha":
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case "alpha_reverse":
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
        foreach ($loopResult->getResultDataCollection() as $attributeAv) {
            $loopResultRow = new LoopResultRow($attributeAv);
            $loopResultRow
                ->set("ID"           , $attributeAv->getId())
                ->set("ATTRIBUTE_ID" , $attributeAv->getAttributeId())
                ->set("IS_TRANSLATED", $attributeAv->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE"       , $this->locale)
                ->set("TITLE"        , $attributeAv->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO"        , $attributeAv->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION"  , $attributeAv->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM" , $attributeAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION"     , $attributeAv->getPosition())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;

    }
}
