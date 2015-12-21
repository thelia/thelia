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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Model\LangQuery;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\TypeCollection;
use Thelia\Type;

/**
 * Language loop, to get a list of available languages
 *
 * - id is the language id
 * - exclude is a comma separated list of lang IDs that will be excluded from output
 * - default if 1, the loop return only default lang. If 0, return all but the default language
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method string[] getCode()
 * @method string[] getLocale()
 * @method int[] getExclude()
 * @method bool getDefaultOnly()
 * @method bool getExcludeDefault()
 * @method string[] getOrder()
 */
class Lang extends BaseLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyListTypeArgument('code'),
            Argument::createAnyListTypeArgument('locale'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createBooleanTypeArgument('default_only', false),
            Argument::createBooleanTypeArgument('exclude_default', false),
            new Argument(
                'order',
                new TypeCollection(
                    new Type\EnumListType(array('id', 'id_reverse', 'alpha', 'alpha_reverse', 'position', 'position_reverse'))
                ),
                'position'
            )
        );
    }

    public function buildModelCriteria()
    {
        $search = LangQuery::create();

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $code = $this->getCode()) {
            $search->filterByCode($code, Criteria::IN);
        }

        if (null !== $locale = $this->getLocale()) {
            $search->filterByLocale($locale, Criteria::IN);
        }

        if ($this->getDefaultOnly()) {
            $search->filterByByDefault(true);
        }

        if ($this->getExcludeDefault()) {
            $search->filterByByDefault(false);
        }

        if (null !== $exclude = $this->getExclude()) {
            $search->filterById($exclude, Criteria::NOT_IN);
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
                    $search->orderByTitle(Criteria::ASC);
                    break;
                case "alpha_reverse":
                    $search->orderByTitle(Criteria::DESC);
                    break;
                case "position":
                    $search->orderByPosition(Criteria::ASC);
                    break;
                case "position_reverse":
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\Lang $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set("ID", $result->getId())
                ->set("TITLE", $result->getTitle())
                ->set("CODE", $result->getCode())
                ->set("LOCALE", $result->getLocale())
                ->set("URL", $result->getUrl())
                ->set("IS_DEFAULT", $result->getByDefault())
                ->set("DATE_FORMAT", $result->getDateFormat())
                ->set("TIME_FORMAT", $result->getTimeFormat())
                ->set("DECIMAL_SEPARATOR", $result->getDecimalSeparator())
                ->set("THOUSANDS_SEPARATOR", $result->getThousandsSeparator())
                ->set("DECIMAL_COUNT", $result->getDecimals())
                ->set("POSITION", $result->getPosition())
            ;

            $this->addOutputFields($loopResultRow, $result);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
