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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\CustomerTitle as CustomerTitleModel;
use Thelia\Model\CustomerTitleQuery;

/**
 * Title loop.
 *
 * Class Title
 *
 * @author Etienne Roudeix <eroudeix@openstudio.fr>
 *
 * @method int[] getId()
 */
class Title extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
        );
    }

    public function buildModelCriteria(): ModelCriteria
    {
        $search = CustomerTitleQuery::create();

        /* manage translations */
        $this->configureI18nProcessing($search, ['SHORT', 'LONG']);

        $id = $this->getId();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }

        $search->orderByPosition();

        return $search;
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var CustomerTitleModel $title */
        foreach ($loopResult->getResultDataCollection() as $title) {
            $loopResultRow = new LoopResultRow($title);
            $loopResultRow->set('ID', $title->getId())
                ->set('IS_TRANSLATED', $title->getVirtualColumn('IS_TRANSLATED'))
                ->set('LOCALE', $this->locale)
                ->set('DEFAULT', $title->getByDefault())
                ->set('SHORT', $title->getVirtualColumn('i18n_SHORT'))
                ->set('LONG', $title->getVirtualColumn('i18n_LONG'))
                ->set('POSITION', $title->getPosition());
            $this->addOutputFields($loopResultRow, $title);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
