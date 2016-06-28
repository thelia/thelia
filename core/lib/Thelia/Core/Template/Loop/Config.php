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
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\TypeCollection;
use Thelia\Type\EnumListType;
use Thelia\Model\Config as ConfigModel;

/**
 * Config loop, to access configuration variables
 *
 * - id is the config id
 * - name is the config name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of config IDs that will be excluded from output
 *
 * @package Thelia\Core\Template\Loop
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * {@inheritdoc}
 * @method int[] getId()
 * @method string getVariable()
 * @method bool|string getHidden()
 * @method bool|string getSecured()
 * @method int[] getExclude()
 * @method string[] getOrder()
 */
class Config extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude'),
            Argument::createAnyTypeArgument('variable'),
            Argument::createBooleanOrBothTypeArgument('hidden'),
            Argument::createBooleanOrBothTypeArgument('secured'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(
                        array(
                            'id', 'id_reverse',
                            'name', 'name_reverse',
                            'title', 'title_reverse',
                            'value', 'value_reverse',
                        )
                    )
                ),
                'name'
            )
        );
    }

    public function buildModelCriteria()
    {
        $id      = $this->getId();
        $name    = $this->getVariable();
        $secured = $this->getSecured();
        $exclude = $this->getExclude();

        $search = ConfigQuery::create();

        $this->configureI18nProcessing($search);

        if (! is_null($id)) {
            $search->filterById($id);
        }

        if (! is_null($name)) {
            $search->filterByName($name);
        }

        if (! is_null($exclude)) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if ($this->getHidden() != BooleanOrBothType::ANY) {
            $search->filterByHidden($this->getHidden() ? 1 : 0);
        }

        if (! is_null($secured) && $secured != BooleanOrBothType::ANY) {
            $search->filterBySecured($secured ? 1 : 0);
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

                case 'name':
                     $search->orderByName(Criteria::ASC);
                    break;
                case 'name_reverse':
                     $search->orderByName(Criteria::DESC);
                    break;

                case 'title':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'title_reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;

                case 'value':
                    $search->orderByValue(Criteria::ASC);
                    break;
                case 'value_reverse':
                    $search->orderByValue(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }

    public function parseResults(LoopResult $loopResult)
    {
        /** @var ConfigModel $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set("ID", $result->getId())
                ->set("NAME", $result->getName())
                ->set("VALUE", $result->getValue())
                ->set("IS_TRANSLATED", $result->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $result->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $result->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $result->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("HIDDEN", $result->getHidden())
                ->set("SECURED", $result->getSecured())
            ;

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
