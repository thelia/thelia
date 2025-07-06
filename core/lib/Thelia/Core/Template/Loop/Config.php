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
use Thelia\Model\Config as ConfigModel;
use Thelia\Model\ConfigQuery;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Config loop, to access configuration variables.
 *
 * - id is the config id
 * - name is the config name
 * - hidden filters by hidden status (yes, no, both)
 * - secured filters by secured status (yes, no, both)
 * - exclude is a comma separated list of config IDs that will be excluded from output
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 *
 * @method int[]       getId()
 * @method string      getVariable()
 * @method bool|string getHidden()
 * @method bool|string getSecured()
 * @method int[]       getExclude()
 * @method string[]    getOrder()
 */
class Config extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
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
                        [
                            'id', 'id_reverse',
                            'name', 'name_reverse',
                            'title', 'title_reverse',
                            'value', 'value_reverse',
                        ],
                    ),
                ),
                'name',
            ),
        );
    }

    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $id = $this->getId();
        $name = $this->getVariable();
        $secured = $this->getSecured();
        $exclude = $this->getExclude();

        $search = ConfigQuery::create();

        $this->configureI18nProcessing($search);

        if (null !== $id) {
            $search->filterById($id);
        }

        if (null !== $name) {
            $search->filterByName($name);
        }

        if (null !== $exclude) {
            $search->filterById($exclude, Criteria::NOT_IN);
        }

        if (BooleanOrBothType::ANY !== $this->getHidden()) {
            $search->filterByHidden($this->getHidden() ? 1 : 0);
        }

        if (null !== $secured && BooleanOrBothType::ANY !== $secured) {
            $search->filterBySecured($secured ? 1 : 0);
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

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var ConfigModel $result */
        foreach ($loopResult->getResultDataCollection() as $result) {
            $loopResultRow = new LoopResultRow($result);

            $loopResultRow
                ->set('ID', $result->getId())
                ->set('NAME', $result->getName())
                ->set('VALUE', $result->getValue())
                ->set('IS_OVERRIDDEN_IN_ENV', $result->isOverriddenInEnv())
                ->set('IS_TRANSLATED', $result->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('TITLE', $result->getVirtualColumn('i18n_TITLE'))
                ->set('CHAPO', $result->getVirtualColumn('i18n_CHAPO'))
                ->set('DESCRIPTION', $result->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('POSTSCRIPTUM', $result->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('HIDDEN', $result->getHidden())
                ->set('SECURED', $result->isOverriddenInEnv() ? 1 : $result->getSecured());

            $this->addOutputFields($loopResultRow, $result);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
