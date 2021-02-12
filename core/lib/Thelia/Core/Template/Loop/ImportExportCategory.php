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
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ExportCategoryQuery;
use Thelia\Model\ImportCategoryQuery;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * Class ImportExportCategory.
 *
 * @author Benjamin Perche <bperche@openstudio.fr>
 *
 * {@inheritdoc}
 *
 * @method int[]|null    getId()
 * @method string[]|null getRef()
 * @method string[]      getOrder()
 */
abstract class ImportExportCategory extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected $timestampable = true;

    /**
     * @return LoopResult
     *
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function parseResults(LoopResult $loopResult)
    {
        /** @var \Thelia\Model\ExportCategory|\Thelia\Model\ImportCategory $category */
        foreach ($loopResult->getResultDataCollection() as $category) {
            $loopResultRow = new LoopResultRow($category);

            $loopResultRow
                ->set('ID', $category->getId())
                ->set('REF', $category->getRef())
                ->set('TITLE', $category->getVirtualColumn('i18n_TITLE'))
                ->set('POSITION', $category->getPosition())
            ;

            $this->addOutputFields($loopResultRow, $category);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    /**
     * this method returns a Propel ModelCriteria.
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria()
    {
        /** @var ImportCategoryQuery|ExportCategoryQuery $query */
        $query = $this->getQueryModel();

        $this->configureI18nProcessing($query, ['TITLE']);

        if (null !== $ids = $this->getId()) {
            $query->filterById($ids, Criteria::IN);
        }

        if (null !== $refs = $this->getRef()) {
            $query->filterByRef($refs, Criteria::IN);
        }

        if (null !== $orders = $this->getOrder()) {
            foreach ($orders as $order) {
                switch ($order) {
                    case 'id':
                        $query->orderById();
                        break;
                    case 'id_reverse':
                        $query->orderById(Criteria::DESC);
                        break;
                    case 'ref':
                        $query->orderByRef();
                        break;
                    case 'ref_reverse':
                        $query->orderByRef(Criteria::DESC);
                        break;
                    case 'alpha':
                        $query->addAscendingOrderByColumn('i18n_TITLE');
                        break;
                    case 'alpha_reverse':
                        $query->addDescendingOrderByColumn('i18n_TITLE');
                        break;
                    case 'manual':
                        $query->orderByPosition();
                        break;
                    case 'manual_reverse':
                        $query->orderByPosition(Criteria::DESC);
                        break;
                }
            }
        }

        return $query;
    }

    /**
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createAnyListTypeArgument('ref'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['id', 'id_reverse', 'ref', 'ref_reverse', 'alpha', 'alpha_reverse', 'manual', 'manual_reverse'])
                ),
                'manual'
            )
        );
    }

    /**
     * @return ImportCategoryQuery|ExportCategoryQuery
     */
    abstract protected function getQueryModel();
}
